from __future__ import annotations
import base64
import json
import os
import time
from fastapi.testclient import TestClient
from jose import jwt
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.hazmat.primitives import serialization
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
        "sub": "user-abc",
        "name": "Test User",
        "email": "test@example.edu",
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


def reset_settings_env(**env_updates):
    # Preserve existing
    old = {k: os.environ.get(k) for k in env_updates}
    try:
        for k, v in env_updates.items():
            if v is None and k in os.environ:
                del os.environ[k]
            elif v is not None:
                os.environ[k] = str(v)
        # Clear cached settings
        get_settings.cache_clear()  # type: ignore[attr-defined]
        yield
    finally:
        for k, v in old.items():
            if v is None:
                if k in os.environ:
                    del os.environ[k]
            else:
                os.environ[k] = v
        get_settings.cache_clear()  # type: ignore[attr-defined]


def test_invalid_state_rejected():
    token = build_dummy_token()
    r = client.post("/lti/launch", data={"id_token": token, "state": "bogus"})
    assert r.status_code == 400
    assert "Invalid or expired state" in r.text


def test_state_reuse_rejected():
    login = client.get(
        "/lti/oidc/login",
        params={
            "iss": ISSUER,
            "client_id": CLIENT_ID,
            "login_hint": "hint",
            "target_link_uri": TARGET_LINK_URI,
        },
        follow_redirects=False,
    )
    state = login.headers["x-debug-state"]
    token = build_dummy_token()
    first = client.post("/lti/launch", data={"id_token": token, "state": state})
    assert first.status_code == 200
    second = client.post("/lti/launch", data={"id_token": token, "state": state})
    assert second.status_code == 400
    assert "Invalid or expired state" in second.text


def test_aud_mismatch():
    login = client.get(
        "/lti/oidc/login",
        params={
            "iss": ISSUER,
            "client_id": CLIENT_ID,
            "login_hint": "hint2",
            "target_link_uri": TARGET_LINK_URI,
        },
        follow_redirects=False,
    )
    state = login.headers["x-debug-state"]
    token = build_dummy_token({"aud": "WRONG"})
    r = client.post("/lti/launch", data={"id_token": token, "state": state})
    assert r.status_code == 400
    assert "aud claim mismatch" in r.text or "aud claim does not" in r.text


def test_missing_required_claim():
    login = client.get(
        "/lti/oidc/login",
        params={
            "iss": ISSUER,
            "client_id": CLIENT_ID,
            "login_hint": "hint3",
            "target_link_uri": TARGET_LINK_URI,
        },
        follow_redirects=False,
    )
    state = login.headers["x-debug-state"]
    # Remove sub
    token = build_dummy_token({"sub": None})
    # Rebuild token manually with sub removed
    header_b64, claims_b64, sig = token.split('.')
    claims = json.loads(base64.urlsafe_b64decode(claims_b64 + '==').decode())
    if 'sub' in claims:
        del claims['sub']
    new_claims_b64 = b64url(json.dumps(claims).encode())
    token = f"{header_b64}.{new_claims_b64}.{sig}"
    r = client.post("/lti/launch", data={"id_token": token, "state": state})
    assert r.status_code == 400
    assert "Missing required claims" in r.text


def test_strict_mode_expired_token():
    # Enable strict validation
    for _ in reset_settings_env(LTI_STRICT="1"):
        login = client.get(
            "/lti/oidc/login",
            params={
                "iss": ISSUER,
                "client_id": CLIENT_ID,
                "login_hint": "hint4",
                "target_link_uri": TARGET_LINK_URI,
            },
            follow_redirects=False,
        )
        state = login.headers["x-debug-state"]
        nonce = login.headers["x-debug-nonce"]
        past = int(time.time()) - 60
        claims_overrides = {
            "aud": CLIENT_ID,
            "exp": past,
            "iat": past,
            "nonce": nonce,
        }
        token = build_dummy_token(claims_overrides)
        r = client.post("/lti/launch", data={"id_token": token, "state": state})
        assert r.status_code == 400
        assert "Token expired" in r.text
        break  # only run once inside context manager


def test_signature_verification_and_tamper():
    # Real signature path: disable demo mode
    for _ in reset_settings_env(LTI_DEMO_MODE="0"):
        # Generate RSA key pair
        private_key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
        public_numbers = private_key.public_key().public_numbers()
        def int_to_b64u(n: int) -> str:
            length = (n.bit_length() + 7) // 8
            return base64.urlsafe_b64encode(n.to_bytes(length, 'big')).decode().rstrip('=')
        jwk_dict = {
            'kty': 'RSA',
            'n': int_to_b64u(public_numbers.n),
            'e': int_to_b64u(public_numbers.e),
            'alg': 'RS256',
            'use': 'sig',
            'kid': 'testkey1',
        }
        # Monkeypatch JWKS fetch
        from app.security import jwks_cache
        original_get = jwks_cache.get_jwks
        jwks_cache.get_jwks = lambda tenant: [jwk_dict]  # type: ignore
        try:
            login = client.get(
                "/lti/oidc/login",
                params={
                    "iss": ISSUER,
                    "client_id": CLIENT_ID,
                    "login_hint": "hint5",
                    "target_link_uri": TARGET_LINK_URI,
                },
                follow_redirects=False,
            )
            state = login.headers["x-debug-state"]
            now = int(time.time())
            claims = {
                "iss": ISSUER,
                "sub": "user-signed",
                "aud": CLIENT_ID,
                "exp": now + 300,
                "iat": now,
                "https://purl.imsglobal.org/spec/lti/claim/deployment_id": "deploy-1234",
                "https://purl.imsglobal.org/spec/lti/claim/message_type": "LtiResourceLinkRequest",
                "https://purl.imsglobal.org/spec/lti/claim/version": "1.3.0",
                "https://purl.imsglobal.org/spec/lti/claim/target_link_uri": TARGET_LINK_URI,
            }
            private_pem = private_key.private_bytes(
                encoding=serialization.Encoding.PEM,
                format=serialization.PrivateFormat.PKCS8,
                encryption_algorithm=serialization.NoEncryption(),
            )
            token = jwt.encode(claims, private_pem, algorithm='RS256', headers={'kid': 'testkey1'})
            # Successful launch
            ok = client.post("/lti/launch", data={"id_token": token, "state": state})
            assert ok.status_code == 200
            # Reuse state should fail before signature check
            reuse = client.post("/lti/launch", data={"id_token": token, "state": state})
            assert reuse.status_code == 400
            # New login for tamper test
            login2 = client.get(
                "/lti/oidc/login",
                params={
                    "iss": ISSUER,
                    "client_id": CLIENT_ID,
                    "login_hint": "hint6",
                    "target_link_uri": TARGET_LINK_URI,
                },
                follow_redirects=False,
            )
            state2 = login2.headers["x-debug-state"]
            # Tamper signature
            header_b64, payload_b64, sig_b64 = token.split('.')
            tampered = f"{header_b64}.{payload_b64}.invalidsig"
            bad = client.post("/lti/launch", data={"id_token": tampered, "state": state2})
            assert bad.status_code == 401
            assert "verification failed" in bad.text.lower()
        finally:
            jwks_cache.get_jwks = original_get  # type: ignore
        break

