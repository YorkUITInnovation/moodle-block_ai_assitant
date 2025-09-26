# LTI AI Assistant (Scaffold)

Minimal standalone FastAPI scaffolding for an LTI 1.3 Tool extracted from the Moodle-only plugin. This is an initial codebase focusing on:

- Health check endpoint
- In-memory tenant registration (single demo tenant)
- OIDC Login Initiation endpoint (`/lti/oidc/login`)
- LTI Launch endpoint (`/lti/launch`)
- Ephemeral RSA keypair + JWKS exposure (`/.well-known/jwks.json`)
- Basic state + nonce validation (TTL in-memory)

> NOTE: This is a learning / bootstrap scaffold. Not production-ready.

## Directory Layout
```
app/
  main.py                # FastAPI application instance
  config.py              # Settings & constants
  tenants.py             # In-memory tenant registry + loader pattern
  security/
    nonce_state_store.py # TTL store for OIDC state + nonce
    jwk.py               # Ephemeral RSA key + JWKS exposure
    jwt_tools.py         # JWT validation helpers (platform id_token)
  lti/
    launch.py            # LTI launch handling utilities
    schemas.py           # Pydantic models for parsed claims
  templates/
    launch.html          # Simple launch response page

.tenants/
  sample_canvas.json     # Example tenant registration metadata
```

## Quick Start

### 1. Create & Activate Virtual Environment
```bash
python3 -m venv .venv
source .venv/bin/activate
```

### 2. Install (Dev Extras)
IMPORTANT for zsh users: quote the extras spec so the shell does not glob the square brackets.
```bash
pip install -e '.[dev]'
```

### 3. Run Server
```bash
uvicorn app.main:app --reload --port 8000
```

### 4. Endpoints
- Health: `GET /healthz` -> `{ "status": "ok" }`
- OIDC Login Initiation: `GET /lti/oidc/login?iss=...&client_id=...&login_hint=...&target_link_uri=...`
- LTI Launch: `POST /lti/launch` (form_post with `id_token` + `state`)
- Tool JWKS: `GET /.well-known/jwks.json`

### 5. Simulating a Launch (Manual)
A real launch requires an LMS platform to:
1. Redirect user browser to Tool's `/lti/oidc/login` with required params.
2. Tool builds redirect and sends user to Platform authorization endpoint.
3. Platform `form_post`s an `id_token` (JWT) to Tool's `/lti/launch`.

For local experimentation you can craft a JWT signed with a *mock* platform key and temporarily disable signature validation (not recommended outside testing). See `jwt_tools.py` `VERIFY_SIGNATURE` flag.

### 6. Running Tests
```bash
pytest
```

## Extending Next
1. Persist tenants in a database + admin UI.
2. Add Deep Linking endpoint.
3. Implement NRPS & AGS service calls.
4. Integrate real AI chat endpoints with user/context scoping.
5. Replace in-memory stores with Redis / Postgres.

## Security TODOs
- Replace ephemeral RSA key with long-lived managed keypair (rotate via cron / KMS).
- Enforce stricter claim validation (audience array, deployment_id, message_type, version).
- Add CSRF protection for non-LTI form endpoints.
- Implement robust logging & audit trails.

## License
MIT
