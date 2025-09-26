from __future__ import annotations
import os
from pydantic import BaseModel

class Settings(BaseModel):
    tool_host: str
    demo_mode: bool
    strict_validation: bool
    state_ttl_seconds: int
    nonce_ttl_seconds: int
    jwk_key_ttl_seconds: int
    leeway_seconds: int
    iat_max_age_seconds: int
    # Persistence
    persist_enabled: bool
    db_url: str | None
    use_migrations: bool
    auto_migrate: bool
    # Docs security (deprecated now that docs disabled)
    docs_api_key: str | None
    # Global API key + environment
    api_key: str | None
    environment: str  # 'development' or 'production'

def get_settings() -> Settings:  # type: ignore
    return Settings(
        tool_host=os.getenv("TOOL_HOST", "http://localhost:8000"),
        demo_mode=os.getenv("LTI_DEMO_MODE", "1") == "1",
        strict_validation=os.getenv("LTI_STRICT", "0") == "1",
        state_ttl_seconds=int(os.getenv("STATE_TTL_SECONDS", "300")),
        nonce_ttl_seconds=int(os.getenv("NONCE_TTL_SECONDS", "300")),
        jwk_key_ttl_seconds=int(os.getenv("JWK_TTL_SECONDS", "3600")),
        leeway_seconds=int(os.getenv("JWT_LEEWAY_SECONDS", "30")),
        iat_max_age_seconds=int(os.getenv("JWT_IAT_MAX_AGE_SECONDS", "300")),
        persist_enabled=os.getenv("LTI_PERSIST", "0") == "1",
        db_url=os.getenv("LTI_DB_URL"),
        use_migrations=os.getenv("LTI_USE_MIGRATIONS", "0") == "1",
        auto_migrate=os.getenv("LTI_AUTO_MIGRATE", "0") == "1",
        docs_api_key=os.getenv("DOCS_API_KEY"),
        api_key=os.getenv("API_KEY"),
        environment=os.getenv("APP_ENV", "development"),
    )

def _noop():
    pass
setattr(get_settings, 'cache_clear', _noop)  # type: ignore
