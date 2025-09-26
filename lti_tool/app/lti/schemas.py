from __future__ import annotations
from pydantic import BaseModel, Field
from typing import List, Optional, Any, Dict

class LTIUser(BaseModel):
    sub: str
    name: Optional[str] = None
    email: Optional[str] = None
    given_name: Optional[str] = None
    family_name: Optional[str] = None
    roles: List[str] = Field(default_factory=list)

class LTIContext(BaseModel):
    id: str
    title: Optional[str] = None

class LTILaunchData(BaseModel):
    issuer: str
    deployment_id: str
    target_link_uri: Optional[str]
    message_type: str
    version: str
    user: LTIUser
    context: Optional[LTIContext]
    raw_claims: Dict[str, Any]

