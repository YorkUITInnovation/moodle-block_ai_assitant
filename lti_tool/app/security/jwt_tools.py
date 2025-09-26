from __future__ import annotations
from typing import Any, Dict, Tuple
from jose import jwt
from jose.exceptions import JWKError, JWTError
from app.tenants import Tenant
from app.security import jwks_cache  # switched to module import for monkeypatching
import json, base64, os
from cryptography.hazmat.primitives.asymmetric import rsa, padding as asym_padding
from cryptography.hazmat.primitives import serialization, hashes

ALGORITHMS = ["RS256"]

class JWTValidationError(Exception):
    pass

def _demo_mode() -> bool:
    return os.getenv("LTI_DEMO_MODE", "1") == "1"

def _b64url_decode(segment: str) -> bytes:
    padding = '=' * (-len(segment) % 4)
    return base64.urlsafe_b64decode(segment + padding)

def peek_unverified_claims(id_token: str) -> Dict[str, Any]:
    try:
        parts = id_token.split('.')
        if len(parts) != 3:
            raise JWTValidationError('Invalid JWT structure')
        payload = json.loads(_b64url_decode(parts[1]).decode())
        return payload
    except Exception as e:  # noqa: BLE001
        raise JWTValidationError(f'Cannot parse JWT: {e}') from e

def decode_id_token(id_token: str, *, verify: bool = True) -> Dict[str, Any]:
    demo = _demo_mode()
    options = {
        "verify_signature": verify and not demo,
        "verify_aud": False,
        "verify_iat": False,
        "verify_exp": False,
    }
    try:
        decoded = jwt.decode(id_token, key="dummy" if demo else "", algorithms=ALGORITHMS, options=options)
        return decoded
    except Exception as e:  # noqa: BLE001
        raise JWTValidationError(str(e)) from e

def _manual_rs256_verify(id_token: str, jwk: Dict[str, Any]) -> Dict[str, Any]:
    parts = id_token.split('.')
    if len(parts) != 3:
        raise JWTValidationError('Invalid JWT structure')
    header_raw, payload_raw, sig_raw = parts
    try:
        header_bytes = _b64url_decode(header_raw)
        payload_bytes = _b64url_decode(payload_raw)
        header = json.loads(header_bytes.decode())
        if header.get('alg') != 'RS256':
            raise JWTValidationError('Unsupported alg')
        sig = _b64url_decode(sig_raw)
        signing_input = (header_raw + '.' + payload_raw).encode()
        if jwk.get('kty') != 'RSA':
            raise JWTValidationError('Unsupported kty')
        n_b = _b64url_decode(jwk['n'])
        e_b = _b64url_decode(jwk['e'])
        n = int.from_bytes(n_b, 'big')
        e = int.from_bytes(e_b, 'big')
        pub_key = rsa.RSAPublicNumbers(e, n).public_key()
        try:
            pub_key.verify(sig, signing_input, asym_padding.PKCS1v15(), hashes.SHA256())
        except Exception as ve:  # noqa: BLE001
            raise JWTValidationError(f'Signature mismatch: {ve}') from ve
        claims = json.loads(payload_bytes.decode())
        return claims
    except JWTValidationError:
        raise
    except Exception as e:  # noqa: BLE001
        # Embed diagnostic info lengths for troubleshooting
        raise JWTValidationError(
            f'Manual verify failed: {e}; diag=' \
            f'header_len={len(header_raw)} payload_len={len(payload_raw)} sig_len={len(sig_raw)}'
        ) from e

def _jwk_to_pem(jwk: Dict[str, Any]) -> bytes | None:
    try:
        if jwk.get('kty') != 'RSA' or 'n' not in jwk or 'e' not in jwk:
            return None
        n = int.from_bytes(_b64url_decode(jwk['n']), 'big')
        e = int.from_bytes(_b64url_decode(jwk['e']), 'big')
        pub_numbers = rsa.RSAPublicNumbers(e, n)
        pub_key = pub_numbers.public_key()
        return pub_key.public_bytes(
            encoding=serialization.Encoding.PEM,
            format=serialization.PublicFormat.SubjectPublicKeyInfo,
        )
    except Exception:  # noqa: BLE001
        return None

def decode_with_tenant(id_token: str, tenant: Tenant, *, demo_mode: bool) -> Dict[str, Any]:
    if demo_mode:
        return decode_id_token(id_token, verify=False)
    try:
        keys = jwks_cache.get_jwks(tenant)
    except jwks_cache.JWKSFetchError as e:  # type: ignore[attr-defined]
        raise JWTValidationError(str(e)) from e

    kid = None
    try:
        header_part = id_token.split('.')[0]
        header = json.loads(_b64url_decode(header_part).decode())
        kid = header.get('kid')
    except Exception:  # noqa: BLE001
        pass

    candidate_keys = [k for k in keys if not kid or k.get('kid') == kid] or keys

    last_err: Exception | None = None
    for key in candidate_keys:
        material = key
        if isinstance(key, dict):
            pem = _jwk_to_pem(key)
            if pem:
                material = pem
        try:
            return jwt.decode(
                id_token,
                material,
                algorithms=ALGORITHMS,
                options={"verify_aud": False},
            )
        except Exception as e:  # noqa: BLE001
            last_err = e
            if isinstance(key, dict):
                try:
                    return _manual_rs256_verify(id_token, key)
                except Exception as e2:  # noqa: BLE001
                    last_err = e2
                    continue
            continue
    raise JWTValidationError(f"Signature verification failed: {last_err}")

def extract_core_lti_claims(claims: Dict[str, Any]) -> Tuple[str, str, str]:
    message_type = claims.get("https://purl.imsglobal.org/spec/lti/claim/message_type")
    version = claims.get("https://purl.imsglobal.org/spec/lti/claim/version")
    deployment_id = claims.get("https://purl.imsglobal.org/spec/lti/claim/deployment_id")
    if not all([message_type, version, deployment_id]):
        raise JWTValidationError("Missing core LTI claims")
    return message_type, version, deployment_id
