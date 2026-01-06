from datetime import datetime
from enum import StrEnum
from typing import Optional, TYPE_CHECKING

from sqlmodel import SQLModel, Field, Relationship

if TYPE_CHECKING:
    from app.models.user import User


class RolePermission(SQLModel, table=True):
    __tablename__ = "role_permissions"

    role_id: int = Field(foreign_key="roles.id", primary_key=True)
    permission_id: int = Field(foreign_key="permissions.id", primary_key=True)


class UserRole(SQLModel, table=True):
    __tablename__ = "user_roles"

    user_id: int = Field(foreign_key="users.id", primary_key=True)
    role_id: int = Field(foreign_key="roles.id", primary_key=True)


class PermissionCode(StrEnum):
    MANAGE_ENTITLEMENTS = "manage_entitlements"
    MANAGE_USERS = "manage_users"
    MANAGE_VENDOR_CREDENTIALS = "manage_vendor_credentials"
    VIEW_BILLING = "view_billing"
    ACCESS_MODULES = "access_modules"
    IMPERSONATE_USER = "impersonate_user"
    VIEW_PLATFORM_STATS = "view_platform_stats"

    @property
    def description(self) -> str:
        descriptions = {
            self.MANAGE_ENTITLEMENTS: "Enable/disable modules and seats",
            self.MANAGE_USERS: "Invite or manage users for a tenant",
            self.MANAGE_VENDOR_CREDENTIALS: "Manage vendor credential storage",
            self.VIEW_BILLING: "View billing history and subscription",
            self.ACCESS_MODULES: "Access module data and actions",
            self.IMPERSONATE_USER: "Impersonate tenant users (audited)",
            self.VIEW_PLATFORM_STATS: "View platform-wide statistics (Super Admin only)",
        }
        return descriptions[self]


class Role(SQLModel, table=True):
    __tablename__ = "roles"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    name: str = Field(index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    users: list["User"] = Relationship(
        back_populates="roles", link_model=UserRole  # type: ignore[arg-type]
    )
    permissions: list["Permission"] = Relationship(
        back_populates="roles", link_model=RolePermission  # type: ignore[arg-type]
    )


class Permission(SQLModel, table=True):
    __tablename__ = "permissions"

    id: Optional[int] = Field(default=None, primary_key=True)
    code: str = Field(index=True, unique=True)
    description: str = Field(default="")

    roles: list["Role"] = Relationship(
        back_populates="permissions", link_model=RolePermission  # type: ignore[arg-type]
    )

