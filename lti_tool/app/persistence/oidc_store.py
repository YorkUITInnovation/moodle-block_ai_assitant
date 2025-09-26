from __future__ import annotations
import secrets
import time
from sqlalchemy import select, update, delete
from app.persistence.db import get_session
from app.persistence.models import OIDCState, OIDCNonce

class PersistentOIDCStateNonceManager:
    """State/Nonce manager backed by DB for horizontal scalability."""
    def __init__(self, state_ttl: int, nonce_ttl: int) -> None:
        self.state_ttl = state_ttl
        self.nonce_ttl = nonce_ttl

    def _issue(self, model_cls, ttl: int) -> str:
        token = secrets.token_urlsafe(32)
        expires = int(time.time()) + ttl
        sess = get_session()
        record = model_cls(value=token, expires_at=expires)
        sess.add(record)
        sess.commit()
        return token

    def issue_state(self) -> str:
        return self._issue(OIDCState, self.state_ttl)

    def issue_nonce(self) -> str:
        return self._issue(OIDCNonce, self.nonce_ttl)

    def _consume(self, model_cls, value: str) -> bool:
        now = int(time.time())
        sess = get_session()
        # purge expired in lightweight fashion
        sess.execute(delete(model_cls).where(model_cls.expires_at < now))
        sess.commit()
        stmt = select(model_cls).where(model_cls.value == value)
        obj = sess.execute(stmt).scalars().first()
        if not obj:
            return False
        if obj.expires_at < now or obj.consumed:
            return False
        obj.consumed = True
        obj.consumed_at = now
        sess.commit()
        return True

    def validate_state(self, state: str) -> bool:
        return self._consume(OIDCState, state)

    def validate_nonce(self, nonce: str | None) -> bool:
        if not nonce:
            return False
        return self._consume(OIDCNonce, nonce)

