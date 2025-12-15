from datetime import datetime, timedelta
from typing import Optional

from sqlmodel import SQLModel, Field
from sqlalchemy import Column, JSON


class PasswordResetToken(SQLModel, table=True):
    __tablename__ = "password_reset_tokens"

    id: Optional[int] = Field(default=None, primary_key=True)
    user_id: int = Field(foreign_key="users.id", index=True)
    token_hash: str = Field(index=True)
    expires_at: datetime = Field(default_factory=lambda: datetime.utcnow() + timedelta(hours=2))
    used_at: Optional[datetime] = Field(default=None)
    created_at: datetime = Field(default_factory=datetime.utcnow)


class ImpersonationAudit(SQLModel, table=True):
    __tablename__ = "impersonation_audit"

    id: Optional[int] = Field(default=None, primary_key=True)
    actor_user_id: int = Field(index=True)
    target_user_id: int = Field(index=True)
    reason: str = Field(default="")
    created_at: datetime = Field(default_factory=datetime.utcnow)


class AuditLog(SQLModel, table=True):
    __tablename__ = "audit_log"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(index=True)
    actor_user_id: int = Field(index=True)
    action: str = Field(index=True)
    target: str = Field(default="")
    details: dict = Field(default_factory=dict, sa_column=Column(JSON))
    created_at: datetime = Field(default_factory=datetime.utcnow)


