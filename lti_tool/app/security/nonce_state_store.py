from __future__ import annotations
import secrets
import time
from typing import Dict, Optional

class TTLStore:
    def __init__(self, ttl_seconds: int) -> None:
        self.ttl = ttl_seconds
        self._store: Dict[str, float] = {}

    def _purge(self) -> None:
        now = time.time()
        expired = [k for k, v in self._store.items() if v < now]
        for k in expired:
            del self._store[k]

    def issue(self) -> str:
        self._purge()
        token = secrets.token_urlsafe(32)
        self._store[token] = time.time() + self.ttl
        return token

    def consume(self, token: str) -> bool:
        self._purge()
        exp = self._store.get(token)
        if exp and exp >= time.time():
            del self._store[token]
            return True
        return False

class OIDCStateNonceManager:
    def __init__(self, state_ttl: int, nonce_ttl: int) -> None:
        self.state_store = TTLStore(state_ttl)
        self.nonce_store = TTLStore(nonce_ttl)

    def issue_state(self) -> str:
        return self.state_store.issue()

    def validate_state(self, state: str) -> bool:
        return self.state_store.consume(state)

    def issue_nonce(self) -> str:
        return self.nonce_store.issue()

    def validate_nonce(self, nonce: Optional[str]) -> bool:
        if not nonce:
            return False
        return self.nonce_store.consume(nonce)

