from __future__ import annotations
import time
from sqlalchemy import Column, Integer, String, DateTime, Text, Boolean, Index
from sqlalchemy.orm import Mapped
from app.persistence.db import Base

class OIDCState(Base):
    __tablename__ = 'lti_oidc_states'
    id: Mapped[int] = Column(Integer, primary_key=True, autoincrement=True)
    value: Mapped[str] = Column(String(255), unique=True, index=True, nullable=False)
    expires_at: Mapped[int] = Column(Integer, index=True, nullable=False)
    consumed: Mapped[bool] = Column(Boolean, default=False, nullable=False)
    consumed_at: Mapped[int | None] = Column(Integer, nullable=True)

class OIDCNonce(Base):
    __tablename__ = 'lti_oidc_nonces'
    id: Mapped[int] = Column(Integer, primary_key=True, autoincrement=True)
    value: Mapped[str] = Column(String(255), unique=True, index=True, nullable=False)
    expires_at: Mapped[int] = Column(Integer, index=True, nullable=False)
    consumed: Mapped[bool] = Column(Boolean, default=False, nullable=False)
    consumed_at: Mapped[int | None] = Column(Integer, nullable=True)

class LTILaunch(Base):
    __tablename__ = 'lti_launches'
    id: Mapped[int] = Column(Integer, primary_key=True, autoincrement=True)
    issuer: Mapped[str] = Column(String(255), index=True, nullable=False)
    client_id: Mapped[str] = Column(String(255), index=True, nullable=False)
    user_sub: Mapped[str] = Column(String(255), index=True, nullable=False)
    deployment_id: Mapped[str] = Column(String(255), index=True, nullable=False)
    created_at: Mapped[int] = Column(Integer, index=True, default=lambda: int(time.time()))
    raw_claims: Mapped[str] = Column(Text, nullable=False)

Index('idx_launch_issuer_client', LTILaunch.issuer, LTILaunch.client_id)

