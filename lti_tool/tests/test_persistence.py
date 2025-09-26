from __future__ import annotations
import os
import base64
import json
import pathlib
from fastapi.testclient import TestClient
from app.main import app
from app.config import get_settings

client = TestClient(app)

ISSUER = "https://canvas.example.edu"
CLIENT_ID = "10000000000001"
TARGET_LINK_URI = "http://localhost:8000/launch-destination"

def b64url(data: bytes) -> str:
    return base64.urlsafe_b64encode(data).rstrip(b"=").decode()

def build_dummy_token(claim_overrides: dict | None = None) -> str:
    header = {"alg": "RS256", "typ": "JWT"}
    base_claims = {
        "iss": ISSUER,
        "sub": "persist-user",
        "name": "Persist User",
        "email": "persist@example.edu",
        "https://purl.imsglobal.org/spec/lti/claim/deployment_id": "deploy-1234",
        "https://purl.imsglobal.org/spec/lti/claim/message_type": "LtiResourceLinkRequest",
        "https://purl.imsglobal.org/spec/lti/claim/version": "1.3.0",
        "https://purl.imsglobal.org/spec/lti/claim/target_link_uri": TARGET_LINK_URI,
    }
    if claim_overrides:
        base_claims.update(claim_overrides)
    header_b64 = b64url(json.dumps(header).encode())
    claims_b64 = b64url(json.dumps(base_claims).encode())
    sig_b64 = b64url(b"dummy-signature")
    return f"{header_b64}.{claims_b64}.{sig_b64}"

def reset_env(**env):
    old = {k: os.environ.get(k) for k in env}
    try:
        for k,v in env.items():
            if v is None and k in os.environ:
                del os.environ[k]
            else:
                os.environ[k] = str(v)
        get_settings.cache_clear()  # type: ignore[attr-defined]
        # reset managers
        import app.main as main_mod
        main_mod._persistent_manager = None  # type: ignore
        main_mod._memory_manager = None  # type: ignore
        yield
    finally:
        for k,v in old.items():
            if v is None:
                if k in os.environ:
                    del os.environ[k]
            else:
                os.environ[k]=v
        get_settings.cache_clear()  # type: ignore[attr-defined]
        import app.main as main_mod
        main_mod._persistent_manager = None  # type: ignore
        main_mod._memory_manager = None  # type: ignore

def test_persistence_launch_and_list():
    db_path = pathlib.Path('test_persist.db')
    if db_path.exists():
        db_path.unlink()
    for _ in reset_env(LTI_PERSIST="1", LTI_DB_URL="sqlite:///./test_persist.db"):
        login = client.get(
            "/lti/oidc/login",
            params={
                "iss": ISSUER,
                "client_id": CLIENT_ID,
                "login_hint": "persist-hint",
                "target_link_uri": TARGET_LINK_URI,
            },
            follow_redirects=False,
        )
        state = login.headers['x-debug-state']
        token = build_dummy_token()
        launch = client.post(
            "/lti/launch",
            data={"id_token": token, "state": state},
            headers={"Content-Type": "application/x-www-form-urlencoded"},
        )
        assert launch.status_code == 200
        assert launch.headers.get('x-launch-id') is not None
        list_resp = client.get("/lti/launches")
        assert list_resp.status_code == 200
        data = list_resp.json()
        assert 'launches' in data
        assert any(l['id'] == int(launch.headers['x-launch-id']) for l in data['launches'])
        break

def test_launches_endpoint_disabled_without_persist():
    for _ in reset_env(LTI_PERSIST="0"):
        r = client.get('/lti/launches')
        assert r.status_code == 400
        assert 'Persistence disabled' in r.text
        break

