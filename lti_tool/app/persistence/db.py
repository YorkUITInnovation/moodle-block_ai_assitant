from __future__ import annotations
import os
from pathlib import Path
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker, declarative_base, scoped_session
from app.config import get_settings

Base = declarative_base()
_engine = None
_SessionFactory = None


def get_engine():
    global _engine
    settings = get_settings()
    if _engine is None:
        db_url = settings.db_url or 'sqlite:///./lti_scaffold.db'
        connect_args = {}
        if db_url.startswith('sqlite'):  # allow SQLite quick start
            connect_args = {"check_same_thread": False}
        _engine = create_engine(db_url, echo=False, future=True, connect_args=connect_args)
    return _engine


def init_db():
    settings = get_settings()
    engine = get_engine()
    if settings.use_migrations:
        # If using migrations and auto_migrate enabled, run Alembic upgrade head.
        if settings.auto_migrate:
            try:
                from alembic.config import Config
                from alembic import command
                root = Path(__file__).resolve().parents[2]  # lti_tool directory
                cfg = Config(str(root / 'alembic.ini'))
                # Override URL at runtime (alembic.ini uses placeholder)
                cfg.set_main_option('sqlalchemy.url', settings.db_url or 'sqlite:///./lti_scaffold.db')
                command.upgrade(cfg, 'head')
            except Exception as e:  # noqa: BLE001
                # Fallback: if migrations fail, last resort create_all to keep tests functional.
                print(f"[init_db] Alembic migration failed: {e}; falling back to metadata.create_all")
                Base.metadata.create_all(engine)
        # If not auto_migrate, assume external migration management; do nothing.
    else:
        Base.metadata.create_all(engine)


def get_session():
    global _SessionFactory
    if _SessionFactory is None:
        engine = get_engine()
        _SessionFactory = scoped_session(sessionmaker(bind=engine, expire_on_commit=False, autoflush=False))
    return _SessionFactory()


def shutdown_db():
    global _engine, _SessionFactory
    if _SessionFactory:
        _SessionFactory.remove()
    _SessionFactory = None
    _engine = None
