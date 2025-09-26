from __future__ import annotations
from typing import Any, Dict, Optional
from app.lti.schemas import LTILaunchData, LTIUser, LTIContext
from app.security.jwt_tools import extract_core_lti_claims

USER_CLAIM = "https://purl.imsglobal.org/spec/lti/claim/lis"  # not always present
CONTEXT_CLAIM = "https://purl.imsglobal.org/spec/lti/claim/context"
ROLES_CLAIM = "https://purl.imsglobal.org/spec/lti/claim/roles"
TARGET_LINK_URI_CLAIM = "https://purl.imsglobal.org/spec/lti/claim/target_link_uri"


def build_launch_data(claims: Dict[str, Any]) -> LTILaunchData:
    message_type, version, deployment_id = extract_core_lti_claims(claims)

    issuer = claims.get("iss")
    target_link_uri: Optional[str] = claims.get(TARGET_LINK_URI_CLAIM)

    # Basic user info per OIDC base claims
    user = LTIUser(
        sub=claims.get("sub"),
        name=claims.get("name"),
        email=claims.get("email"),
        given_name=claims.get("given_name"),
        family_name=claims.get("family_name"),
        roles=claims.get(ROLES_CLAIM, []),
    )

    ctx_block = claims.get(CONTEXT_CLAIM)
    context = None
    if isinstance(ctx_block, dict):
        ctx_id = ctx_block.get("id") or ctx_block.get("context_id")
        if ctx_id:
            context = LTIContext(id=ctx_id, title=ctx_block.get("title"))

    return LTILaunchData(
        issuer=issuer,
        deployment_id=deployment_id,
        target_link_uri=target_link_uri,
        message_type=message_type,
        version=version,
        user=user,
        context=context,
        raw_claims=claims,
    )

