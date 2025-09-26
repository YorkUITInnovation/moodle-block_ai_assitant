from __future__ import annotations
import os
import pathlib
from fastapi.testclient import TestClient
from app.main import app
from app.config import get_settings

client = TestClient(app)
ISSUER = "https://canvas.example.edu"
CLIENT_ID = "10000000000001"
TARGET_LINK_URI = "http://localhost:8000/launch-destination"

def build_dummy_token(sub: str, target_link_uri: str):
    # Minimal dummy token (demo mode) – signature not verified.
    import base64, json
    def b64u(d: bytes) -> str:
        return base64.urlsafe_b64encode(d).decode().rstrip('=')
    header = {"alg": "RS256", "typ": "JWT"}
    claims = {
        "iss": ISSUER,
        "sub": sub,
        "https://purl.imsglobal.org/spec/lti/claim/deployment_id": "deploy-1234",
        "https://purl.imsglobal.org/spec/lti/claim/message_type": "LtiResourceLinkRequest",
        "https://purl.imsglobal.org/spec/lti/claim/version": "1.3.0",
        "https://purl.imsglobal.org/spec/lti/claim/target_link_uri": target_link_uri,
    }
    return f"{b64u(json.dumps(header).encode())}.{b64u(json.dumps(claims).encode())}.{b64u(b'demo')}"


def reset_env(**env):
    old = {k: os.environ.get(k) for k in env}
    try:
        for k,v in env.items():
            if v is None and k in os.environ:
                del os.environ[k]
            else:
                os.environ[k] = str(v)
        get_settings.cache_clear()  # type: ignore[attr-defined]
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
                os.environ[k] = v
        get_settings.cache_clear()  # type: ignore[attr-defined]
        import app.main as main_mod
        main_mod._persistent_manager = None  # type: ignore
        main_mod._memory_manager = None  # type: ignore


def test_alembic_auto_migration_path():
    db_path = pathlib.Path('test_migrations.db')
    if db_path.exists():
        db_path.unlink()
    for _ in reset_env(
        LTI_PERSIST="1",
        LTI_DB_URL="sqlite:///./test_migrations.db",
        LTI_USE_MIGRATIONS="1",
        LTI_AUTO_MIGRATE="1",
    ):
        # Trigger OIDC login to cause init_db (with migrations) via dependency.
        login = client.get(
            "/lti/oidc/login",
            params={
                "iss": ISSUER,
                "client_id": CLIENT_ID,
                "login_hint": "mig-hint",
                "target_link_uri": TARGET_LINK_URI,
            },
            follow_redirects=False,
        )
        assert login.status_code in (302,307)
        state = login.headers['x-debug-state']
        token = build_dummy_token("mig-user", TARGET_LINK_URI)
        launch = client.post(
            "/lti/launch",
            data={"id_token": token, "state": state},
            headers={"Content-Type": "application/x-www-form-urlencoded"},
        )
        assert launch.status_code == 200
        assert launch.headers.get('x-launch-id') is not None
        # Validate listing works
        listing = client.get('/lti/launches')
        assert listing.status_code == 200
        data = listing.json()
        assert 'launches' in data and len(data['launches']) >= 1
        break

