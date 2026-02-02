from collections import namedtuple

from app.models import Permission, Role, RolePermission
from app.models.role import PermissionCode

# Lightweight role definition container
RoleDef = namedtuple("RoleDef", ["name", "permissions"])

# Define default role → permission mapping. Super admin gets all permissions.
DEFAULT_ROLES: list[RoleDef] = [
    RoleDef(
        "super_admin",
        permissions=list(PermissionCode),
    ),
    RoleDef(
        "company_admin",
        permissions=[
            PermissionCode.MANAGE_ENTITLEMENTS,
            PermissionCode.MANAGE_USERS,
            PermissionCode.MANAGE_VENDOR_CREDENTIALS,
            PermissionCode.VIEW_BILLING,
            PermissionCode.ACCESS_MODULES,  # Required to access module features like Tasks
            PermissionCode.POS_ACCESS,
            PermissionCode.POS_PROCESS_SALES,
            PermissionCode.POS_MANAGE_REGISTERS,
            PermissionCode.POS_MANAGE_REFUNDS,
            PermissionCode.POS_MANAGE_INVENTORY,
            PermissionCode.POS_MANAGE_CATALOG,
            PermissionCode.POS_VIEW_ANALYTICS,
        ],
    ),
    RoleDef(
        "staff",
        permissions=[
            PermissionCode.ACCESS_MODULES,
            PermissionCode.POS_ACCESS,
            PermissionCode.POS_PROCESS_SALES,
            PermissionCode.POS_MANAGE_REGISTERS,
        ],
    ),
]


async def seed_permissions() -> None:
    """Ensure the global permission catalog exists."""
    existing_permissions = await Permission.find_all().to_list()
    existing_codes = {p.code for p in existing_permissions}
    
    for code in PermissionCode:
        if code.value not in existing_codes:
            permission = Permission(code=code.value, description=code.description)
            await permission.insert()
            print(f"Created permission: {code.value}")


async def ensure_roles_for_tenant(tenant_id: str) -> dict[str, Role]:
    """Create default roles for a tenant if they do not exist."""
    roles_by_name: dict[str, Role] = {}

    existing_roles = await Role.find(Role.tenant_id == tenant_id).to_list()
    roles_by_name.update({r.name: r for r in existing_roles})

    for role_def in DEFAULT_ROLES:
        role = roles_by_name.get(role_def.name)
        if not role:
            role = Role(
                tenant_id=tenant_id,
                name=role_def.name,
                permission_codes=[perm.value for perm in role_def.permissions]
            )
            await role.insert()
            roles_by_name[role_def.name] = role
            print(f"Created role: {role_def.name} for tenant {tenant_id}")

        # Update permissions if needed
        current_permission_codes = set(role.permission_codes)
        expected_permission_codes = {perm.value for perm in role_def.permissions}
        
        if current_permission_codes != expected_permission_codes:
            role.permission_codes = list(expected_permission_codes)
            await role.save()
            print(f"Updated permissions for role: {role_def.name}")

    return roles_by_name
