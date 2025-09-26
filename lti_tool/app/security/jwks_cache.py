from __future__ import annotations
import time
from typing import Dict, Any, List, Tuple
import httpx
from app.tenants import Tenant

_CACHE: Dict[str, Tuple[float, List[Dict[str, Any]]]] = {}
_TTL_SECONDS = 600

class JWKSFetchError(Exception):
    pass

def get_jwks(tenant: Tenant) -> List[Dict[str, Any]]:
    key = tenant.key
    now = time.time()
    cached = _CACHE.get(key)
    if cached and now - cached[0] < _TTL_SECONDS:
        return cached[1]
    try:
        resp = httpx.get(tenant.jwks_uri, timeout=5.0)
        resp.raise_for_status()
        data = resp.json()
        keys = data.get('keys', [])
        if not isinstance(keys, list) or not keys:
            raise JWKSFetchError('JWKS document missing keys')
        _CACHE[key] = (now, keys)
        return keys
    except Exception as e:  # noqa: BLE001
        raise JWKSFetchError(f"Failed fetching JWKS: {e}") from e

