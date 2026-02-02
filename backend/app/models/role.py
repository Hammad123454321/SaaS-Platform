from datetime import datetime
from enum import StrEnum
from typing import Optional, List

from beanie import Document
from pydantic import Field


class PermissionCode(StrEnum):
    MANAGE_ENTITLEMENTS = "manage_entitlements"
    MANAGE_USERS = "manage_users"
    MANAGE_VENDOR_CREDENTIALS = "manage_vendor_credentials"
    VIEW_BILLING = "view_billing"
    ACCESS_MODULES = "access_modules"
    IMPERSONATE_USER = "impersonate_user"
    VIEW_PLATFORM_STATS = "view_platform_stats"
    POS_ACCESS = "pos_access"
    POS_PROCESS_SALES = "pos_process_sales"
    POS_MANAGE_REGISTERS = "pos_manage_registers"
    POS_MANAGE_REFUNDS = "pos_manage_refunds"
    POS_MANAGE_INVENTORY = "pos_manage_inventory"
    POS_MANAGE_CATALOG = "pos_manage_catalog"
    POS_VIEW_ANALYTICS = "pos_view_analytics"

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
            self.POS_ACCESS: "Access the POS module",
            self.POS_PROCESS_SALES: "Create and finalize POS sales",
            self.POS_MANAGE_REGISTERS: "Open/close POS registers and cash movements",
            self.POS_MANAGE_REFUNDS: "Process POS refunds and returns",
            self.POS_MANAGE_INVENTORY: "Adjust inventory and view stock",
            self.POS_MANAGE_CATALOG: "Manage POS catalog items and pricing",
            self.POS_VIEW_ANALYTICS: "View POS analytics and reports",
        }
        return descriptions[self]


class Role(Document):
    """Role model for MongoDB."""
    
    tenant_id: Optional[str] = Field(default=None, index=True)
    name: str = Field(..., index=True)
    permission_codes: List[str] = Field(default_factory=list)  # Store permission codes
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "roles"
        indexes = [
            "tenant_id",
            "name",
        ]


class Permission(Document):
    """Permission catalog model for MongoDB."""
    
    code: str = Field(..., index=True, unique=True)
    description: str = Field(default="")

    class Settings:
        name = "permissions"
        indexes = [
            "code",
        ]


class RolePermission(Document):
    """Many-to-many relationship between roles and permissions."""
    
    role_id: str = Field(..., index=True)
    permission_id: str = Field(..., index=True)

    class Settings:
        name = "role_permissions"
        indexes = [
            "role_id",
            "permission_id",
            ("role_id", "permission_id"),  # Compound index
        ]


class UserRole(Document):
    """Many-to-many relationship between users and roles."""
    
    user_id: str = Field(..., index=True)
    role_id: str = Field(..., index=True)

    class Settings:
        name = "user_roles"
        indexes = [
            "user_id",
            "role_id",
            ("user_id", "role_id"),  # Compound index
        ]
