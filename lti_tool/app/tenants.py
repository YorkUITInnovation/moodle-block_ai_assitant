from __future__ import annotations
import json
import threading
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, Optional, List

TENANTS_DIR = Path(__file__).resolve().parent.parent / ".tenants"
TENANTS_DIR.mkdir(exist_ok=True)

@dataclass
class Tenant:
    issuer: str
    client_id: str
    deployment_id: str
    authorization_endpoint: str
    jwks_uri: str
    token_endpoint: str
    name: str | None = None

    @property
    def key(self) -> str:
        return f"{self.issuer}::{self.client_id}"  # composite key

class TenantRegistry:
    def __init__(self) -> None:
        self._lock = threading.RLock()
        self._tenants: Dict[str, Tenant] = {}
        self._loaded = False

    def load(self) -> None:
        with self._lock:
            if self._loaded:
                return
            for f in TENANTS_DIR.glob("*.json"):
                try:
                    data = json.loads(f.read_text())
                    tenant = Tenant(**data)
                    self._tenants[tenant.key] = tenant
                except Exception as e:  # noqa: BLE001 - dev scaffold
                    print(f"Failed loading tenant file {f}: {e}")
            self._loaded = True

    def get(self, issuer: str, client_id: str) -> Optional[Tenant]:
        if not self._loaded:
            self.load()
        return self._tenants.get(f"{issuer}::{client_id}")

    def find_single_by_issuer(self, issuer: str) -> Optional[Tenant]:
        """Return the tenant if exactly one tenant exists for this issuer (non-strict fallback)."""
        if not self._loaded:
            self.load()
        matches: List[Tenant] = [t for t in self._tenants.values() if t.issuer == issuer]
        if len(matches) == 1:
            return matches[0]
        return None

registry = TenantRegistry()
