from __future__ import annotations
from fastapi import FastAPI, Request, Depends, Form, HTTPException, Header
from fastapi.responses import RedirectResponse, JSONResponse, HTMLResponse
from fastapi.templating import Jinja2Templates
from urllib.parse import urlencode
from app.config import get_settings, Settings
# Load settings early so we can conditionally enable docs in development.
_initial_settings = get_settings()
from app.tenants import registry, Tenant
from app.security.nonce_state_store import OIDCStateNonceManager as MemoryOIDCStateNonceManager
from app.security import jwk as jwk_mod
from app.security.jwt_tools import JWTValidationError, decode_with_tenant, peek_unverified_claims
from app.lti.launch import build_launch_data
from app.lti.validation import validate_required_claims, LTIClaimError
from app.persistence.db import init_db
from app.persistence.oidc_store import PersistentOIDCStateNonceManager
from app.persistence.launch_repo import save_launch, list_recent_launches
from fastapi.openapi.utils import get_openapi
from fastapi.security import APIKeyHeader
import os, logging, time

# Re-create app with docs only in non-production.
if _initial_settings.environment.lower() == 'production':
    app = FastAPI(title="LTI AI Assistant Scaffold", version="0.1.0", docs_url=None, redoc_url=None, openapi_url=None)
else:
    app = FastAPI(title="LTI AI Assistant Scaffold", version="0.1.0")
    # Define API key security for docs
    api_key_header = APIKeyHeader(name="X-API-Key", auto_error=False)
    def custom_openapi():
        if app.openapi_schema:
            return app.openapi_schema
        openapi_schema = get_openapi(
            title=app.title,
            version=app.version,
            routes=app.routes,
            description="LTI AI Assistant API (Development mode). Provide X-API-Key if set.",
        )
        openapi_schema.setdefault('components', {}).setdefault('securitySchemes', {})['ApiKeyAuth'] = {
            'type': 'apiKey',
            'in': 'header',
            'name': 'X-API-Key'
        }
        # Apply security requirement globally if an API key is configured
        if get_settings().api_key:
            for path_item in openapi_schema.get('paths', {}).values():
                for operation in path_item.values():
                    if isinstance(operation, dict):
                        operation.setdefault('security', []).append({'ApiKeyAuth': []})
        app.openapi_schema = openapi_schema
        return app.openapi_schema
    app.openapi = custom_openapi  # type: ignore

templates = Jinja2Templates(directory=os.path.join(os.path.dirname(__file__), 'templates'))

_memory_manager: MemoryOIDCStateNonceManager | None = None
_persistent_manager: PersistentOIDCStateNonceManager | None = None

# ------------------------- API KEY MIDDLEWARE -----------------------------
@app.middleware("http")
async def api_key_enforcement(request: Request, call_next):
    settings = get_settings()
    required_key = settings.api_key
    env = settings.environment.lower()
    enforce = False
    if env == 'production':
        if not required_key:
            return JSONResponse({'detail': 'Server misconfigured: API_KEY not set in production'}, status_code=503)
        enforce = True
    else:
        if required_key:
            enforce = True
    if enforce:
        supplied = request.headers.get('X-API-Key') or request.query_params.get('api_key')
        if supplied != required_key:
            return JSONResponse({'detail': 'Invalid or missing API key'}, status_code=401)
    return await call_next(request)
# --------------------------------------------------------------------------

def get_oidc_manager(settings: Settings = Depends(get_settings)):
    global _memory_manager, _persistent_manager
    if settings.persist_enabled:
        if _persistent_manager is None:
            init_db()
            _persistent_manager = PersistentOIDCStateNonceManager(settings.state_ttl_seconds, settings.nonce_ttl_seconds)
        return _persistent_manager
    if _memory_manager is None:
        _memory_manager = MemoryOIDCStateNonceManager(settings.state_ttl_seconds, settings.nonce_ttl_seconds)
    return _memory_manager

logger = logging.getLogger("lti")
logging.basicConfig(level=logging.INFO)

@app.get('/healthz')
async def health() -> dict:
    return {"status": "ok"}

@app.get('/.well-known/jwks.json')
async def jwks():
    return jwk_mod.jwks_document()

@app.get('/lti/oidc/login')
async def oidc_login(
    iss: str,
    client_id: str,
    login_hint: str,
    target_link_uri: str,
    lti_message_hint: str | None = None,
    settings: Settings = Depends(get_settings),
    manager = Depends(get_oidc_manager),
):
    tenant = registry.get(iss, client_id)
    if not tenant:
        raise HTTPException(status_code=400, detail="Unknown issuer/client_id")
    state = manager.issue_state()
    nonce = manager.issue_nonce()

    redirect_params = {
        'scope': 'openid',
        'response_type': 'id_token',
        'response_mode': 'form_post',
        'prompt': 'none',
        'client_id': tenant.client_id,
        'redirect_uri': f"{settings.tool_host}/lti/launch",
        'login_hint': login_hint,
        'state': state,
        'nonce': nonce,
    }
    if lti_message_hint:
        redirect_params['lti_message_hint'] = lti_message_hint
    redirect_params['target_link_uri'] = target_link_uri

    url = tenant.authorization_endpoint + '?' + urlencode(redirect_params)
    response = RedirectResponse(url=url, status_code=307)
    response.headers['x-debug-state'] = state
    response.headers['x-debug-nonce'] = nonce
    return response

@app.post('/lti/launch')
async def lti_launch(
    request: Request,
    id_token: str = Form(...),
    state: str = Form(...),
    settings: Settings = Depends(get_settings),
    manager = Depends(get_oidc_manager),
):
    start_time = time.time()
    if not manager.validate_state(state):
        raise HTTPException(status_code=400, detail="Invalid or expired state")

    try:
        unverified = peek_unverified_claims(id_token)
    except JWTValidationError as e:
        raise HTTPException(status_code=400, detail=f"JWT parse failure: {e}") from e

    iss = unverified.get('iss')
    aud_claim = unverified.get('aud')
    tenant: Tenant | None = None
    if iss and aud_claim:
        if isinstance(aud_claim, list):
            for cid in aud_claim:
                tenant = registry.get(iss, cid)
                if tenant:
                    break
        else:
            tenant = registry.get(iss, aud_claim)
    if not tenant and iss:
        if os.getenv("LTI_STRICT", "0") == "1":
            raise HTTPException(status_code=400, detail="Cannot resolve tenant (strict mode requires aud)")
        tenant = registry.find_single_by_issuer(iss)
    if not tenant:
        raise HTTPException(status_code=400, detail="Unknown tenant for launch")

    demo_mode = os.getenv("LTI_DEMO_MODE", "1") == "1"
    strict_mode = os.getenv("LTI_STRICT", "0") == "1"
    logger.info({'event':'lti_launch_predecode','demo_mode':demo_mode,'strict_mode':strict_mode,'iss':iss})

    try:
        claims = decode_with_tenant(id_token, tenant, demo_mode=demo_mode)
    except JWTValidationError as e:
        logger.error({'event':'lti_launch_decode_error','error':str(e)})
        raise HTTPException(status_code=401, detail=f"JWT verification failed: {e}") from e

    try:
        validate_required_claims(claims, tenant, settings, strict_override=strict_mode)
    except LTIClaimError as e:
        logger.error({'event':'lti_launch_claim_error','error':str(e)})
        raise HTTPException(status_code=400, detail=str(e)) from e

    nonce_claim = claims.get('nonce')
    if nonce_claim:
        if not manager.validate_nonce(nonce_claim):
            raise HTTPException(status_code=400, detail="Invalid or expired nonce")
    elif strict_mode:
        raise HTTPException(status_code=400, detail="Missing nonce in strict mode")

    try:
        launch_data = build_launch_data(claims)
    except Exception as e:  # noqa: BLE001
        logger.error({'event':'lti_launch_build_error','error':str(e)})
        raise HTTPException(status_code=400, detail=f"Launch parsing failed: {e}") from e

    launch_id = None
    if settings.persist_enabled:
        try:
            launch_id = save_launch(
                issuer=tenant.issuer,
                client_id=tenant.client_id,
                user_sub=claims.get('sub','unknown'),
                deployment_id=tenant.deployment_id,
                claims=claims,
            )
        except Exception as e:  # noqa: BLE001
            logger.error({'event':'lti_launch_persist_error','error':str(e)})

    duration_ms = int((time.time() - start_time) * 1000)
    logger.info({
        'event': 'lti_launch',
        'status': 'success',
        'issuer': iss,
        'client_id': tenant.client_id,
        'deployment_id': tenant.deployment_id,
        'strict': strict_mode,
        'demo_mode': demo_mode,
        'duration_ms': duration_ms,
        'launch_id': launch_id,
        'persist': settings.persist_enabled,
    })

    response = templates.TemplateResponse(
        request,
        'launch.html',
        {
            'launch': launch_data,
            'demo_mode': demo_mode,
            'launch_id': launch_id,
            'persist_enabled': settings.persist_enabled,
        }
    )
    response.headers['x-debug-strict'] = '1' if strict_mode else '0'
    response.headers['x-debug-demo'] = '1' if demo_mode else '0'
    if launch_id is not None:
        response.headers['x-launch-id'] = str(launch_id)
    return response

@app.get('/lti/launches')
async def recent_launches(limit: int = 20, settings: Settings = Depends(get_settings)):
    if not settings.persist_enabled:
        raise HTTPException(status_code=400, detail="Persistence disabled")
    return {'launches': list_recent_launches(limit=limit)}

@app.get('/')
async def root():
    return JSONResponse({"message": "LTI AI Assistant Scaffold", "env": _initial_settings.environment, "endpoints": ["/healthz", "/lti/oidc/login", "/lti/launch", "/.well-known/jwks.json", "/lti/launches", *([] if _initial_settings.environment.lower() == 'production' else ['/docs'])]})
