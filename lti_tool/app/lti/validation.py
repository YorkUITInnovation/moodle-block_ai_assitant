from __future__ import annotations
import time
from typing import Any, Dict, List
from app.config import Settings
from app.tenants import Tenant
from app.security.jwt_tools import JWTValidationError

class LTIClaimError(JWTValidationError):
    pass

def validate_required_claims(claims: Dict[str, Any], tenant: Tenant, settings: Settings, *, strict_override: bool | None = None) -> None:
    strict = settings.strict_validation if strict_override is None else strict_override
    # Always required
    always_required = [
        'iss', 'sub',
        'https://purl.imsglobal.org/spec/lti/claim/message_type',
        'https://purl.imsglobal.org/spec/lti/claim/version',
        'https://purl.imsglobal.org/spec/lti/claim/deployment_id',
        'https://purl.imsglobal.org/spec/lti/claim/target_link_uri',
    ]
    missing: List[str] = [c for c in always_required if c not in claims]
    if missing:
        raise LTIClaimError(f"Missing required claims: {', '.join(missing)}")

    if strict:
        strict_required = ['aud', 'exp', 'iat']
        strict_missing = [c for c in strict_required if c not in claims]
        if strict_missing:
            raise LTIClaimError(f"Missing strict claims: {', '.join(strict_missing)}")

    aud = claims.get('aud')
    if aud is not None:
        if isinstance(aud, list):
            if tenant.client_id not in aud:
                raise LTIClaimError('aud claim does not contain registered client_id')
        else:
            if aud != tenant.client_id:
                raise LTIClaimError('aud claim mismatch with registered client_id')

    if claims.get('https://purl.imsglobal.org/spec/lti/claim/deployment_id') != tenant.deployment_id:
        raise LTIClaimError('deployment_id does not match registered tenant')

    if strict:
        now = int(time.time())
        leeway = settings.leeway_seconds
        exp = claims.get('exp')
        iat = claims.get('iat')
        if exp is not None and exp + leeway < now:
            raise LTIClaimError('Token expired')
        if iat is not None:
            if iat - leeway > now:
                raise LTIClaimError('iat in the future')
            if now - iat > settings.iat_max_age_seconds + leeway:
                raise LTIClaimError('iat too old')
        if 'nonce' not in claims:
            raise LTIClaimError('nonce required in strict mode')
