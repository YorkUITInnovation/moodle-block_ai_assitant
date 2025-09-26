from __future__ import annotations
import json
import time
from typing import List, Dict, Any
from sqlalchemy import select, desc
from app.persistence.db import get_session
from app.persistence.models import LTILaunch

def save_launch(*, issuer: str, client_id: str, user_sub: str, deployment_id: str, claims: Dict[str, Any]) -> int:
    sess = get_session()
    rec = LTILaunch(
        issuer=issuer,
        client_id=client_id,
        user_sub=user_sub,
        deployment_id=deployment_id,
        raw_claims=json.dumps(claims, separators=(',', ':')),
        created_at=int(time.time()),
    )
    sess.add(rec)
    sess.commit()
    return rec.id

def list_recent_launches(limit: int = 20) -> List[Dict[str, Any]]:
    sess = get_session()
    stmt = select(LTILaunch).order_by(desc(LTILaunch.id)).limit(limit)
    rows = sess.execute(stmt).scalars().all()
    out: List[Dict[str, Any]] = []
    for r in rows:
        out.append({
            'id': r.id,
            'issuer': r.issuer,
            'client_id': r.client_id,
            'user_sub': r.user_sub,
            'deployment_id': r.deployment_id,
            'created_at': r.created_at,
        })
    return out

