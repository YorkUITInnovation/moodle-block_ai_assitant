from __future__ import annotations
import base64
import json
from fastapi.testclient import TestClient
from app.main import app

client = TestClient(app)

ISSUER = "https://canvas.example.edu"
CLIENT_ID = "10000000000001"
TARGET_LINK_URI = "http://localhost:8000/launch-destination"


def b64url(data: bytes) -> str:
    return base64.urlsafe_b64encode(data).rstrip(b"=").decode()


def build_dummy_id_token(sub: str, state: str) -> str:
    header = {"alg": "RS256", "typ": "JWT"}
    claims = {
        "iss": ISSUER,
        "sub": sub,
        "name": "Alice Example",
        "email": "alice@example.edu",
        "given_name": "Alice",
        "family_name": "Example",
        "https://purl.imsglobal.org/spec/lti/claim/roles": [
            "http://purl.imsglobal.org/vocab/lis/v2/membership#Learner"
        ],
        "https://purl.imsglobal.org/spec/lti/claim/context": {
            "id": "course-XYZ",
            "title": "Sample Course"
        },
        "https://purl.imsglobal.org/spec/lti/claim/deployment_id": "deploy-1234",
        "https://purl.imsglobal.org/spec/lti/claim/message_type": "LtiResourceLinkRequest",
        "https://purl.imsglobal.org/spec/lti/claim/version": "1.3.0",
        "https://purl.imsglobal.org/spec/lti/claim/target_link_uri": TARGET_LINK_URI,
    }
    header_b64 = b64url(json.dumps(header).encode())
    claims_b64 = b64url(json.dumps(claims).encode())
    sig_b64 = b64url(b"dummy-signature")
    return f"{header_b64}.{claims_b64}.{sig_b64}"


def test_health():
    r = client.get("/healthz")
    assert r.status_code == 200
    assert r.json()["status"] == "ok"


def test_oidc_login_redirect_and_state_header():
    r = client.get(
        "/lti/oidc/login",
        params={
            "iss": ISSUER,
            "client_id": CLIENT_ID,
            "login_hint": "user-login-hint",
            "target_link_uri": TARGET_LINK_URI,
        },
        follow_redirects=False,
    )
    assert r.status_code in (302, 307)
    assert "x-debug-state" in r.headers
    location = r.headers["location"]
    assert "response_type=id_token" in location


def test_jwks():
    r = client.get("/.well-known/jwks.json")
    assert r.status_code == 200
    data = r.json()
    assert "keys" in data and isinstance(data["keys"], list) and data["keys"]


def test_lti_launch_flow_with_dummy_token():
    login_resp = client.get(
        "/lti/oidc/login",
        params={
            "iss": ISSUER,
            "client_id": CLIENT_ID,
            "login_hint": "user-login-hint2",
            "target_link_uri": TARGET_LINK_URI,
        },
        follow_redirects=False,
    )
    state = login_resp.headers["x-debug-state"]
    id_token = build_dummy_id_token("user-123", state)
    launch_resp = client.post(
        "/lti/launch",
        data={"id_token": id_token, "state": state},
        headers={"Content-Type": "application/x-www-form-urlencoded"},
    )
    assert launch_resp.status_code == 200
    text = launch_resp.text
    assert "LTI Launch Received" in text
    assert "Alice Example" in text
