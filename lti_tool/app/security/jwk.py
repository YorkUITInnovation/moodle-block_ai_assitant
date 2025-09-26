from __future__ import annotations
import time
from typing import Any, Dict
import secrets
from dataclasses import dataclass
from app.config import get_settings
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.hazmat.primitives import serialization
import base64

# Helper: base64url without padding
def b64u(data: bytes) -> str:
    return base64.urlsafe_b64encode(data).decode().rstrip('=')

# Convert int to base64url per JWK requirements
def int_to_b64u(n: int) -> str:
    length = (n.bit_length() + 7) // 8
    return b64u(n.to_bytes(length, 'big'))

@dataclass
class EphemeralKey:
    kid: str
    private_key: rsa.RSAPrivateKey
    public_jwk: Dict[str, Any]
    created: float

_key: EphemeralKey | None = None

def _generate_key() -> EphemeralKey:
    kid = secrets.token_hex(8)
    private_key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
    numbers = private_key.public_key().public_numbers()
    public_jwk = {
        'kty': 'RSA',
        'e': int_to_b64u(numbers.e),
        'n': int_to_b64u(numbers.n),
        'alg': 'RS256',
        'use': 'sig',
        'kid': kid,
    }
    return EphemeralKey(kid=kid, private_key=private_key, public_jwk=public_jwk, created=time.time())

def get_current_key() -> EphemeralKey:
    global _key
    settings = get_settings()
    rotate = False
    if _key is None:
        rotate = True
    else:
        if time.time() - _key.created > settings.jwk_key_ttl_seconds:
            rotate = True
    if rotate:
        _key = _generate_key()
    return _key

def jwks_document() -> Dict[str, Any]:
    key = get_current_key()
    return { 'keys': [ key.public_jwk ] }
