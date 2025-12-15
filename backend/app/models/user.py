from datetime import datetime
from typing import Optional

from sqlmodel import SQLModel, Field, Relationship

from app.models.role import Role, UserRole


class User(SQLModel, table=True):
    __tablename__ = "users"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    email: str = Field(index=True)
    hashed_password: str
    is_active: bool = Field(default=True)
    is_super_admin: bool = Field(default=False)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    tenant: "Tenant" = Relationship(back_populates="users")  # noqa: F821
    roles: list[Role] = Relationship(
        back_populates="users", link_model=UserRole  # type: ignore[arg-type]
    )

