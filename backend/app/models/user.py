from datetime import datetime
from typing import Optional, TYPE_CHECKING

from sqlmodel import SQLModel, Field, Relationship

from app.models.role import Role, UserRole

if TYPE_CHECKING:
    from app.models.tenant import Tenant


class User(SQLModel, table=True):
    __tablename__ = "users"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    email: str = Field(index=True)
    hashed_password: str
    is_active: bool = Field(default=True)
    is_super_admin: bool = Field(default=False)
    email_verified: bool = Field(default=False)  # Stage 0: Hard gate - must be True to login
    is_owner: bool = Field(default=False)  # Stage 2: Owner role flag
    password_change_required: bool = Field(default=False)  # Require password change on first login
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    tenant: Optional["Tenant"] = Relationship(back_populates="users")
    roles: list[Role] = Relationship(
        back_populates="users", link_model=UserRole
    )
    # Task Management relationships
    # Note: The assigned_tasks relationship is managed from Task side only
    # because of circular import limitations. Access via Task.assignees

