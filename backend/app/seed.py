from collections import namedtuple

from sqlmodel import Session, select

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
        ],
    ),
    RoleDef(
        "staff",
        permissions=[
            PermissionCode.ACCESS_MODULES,
        ],
    ),
]


def seed_permissions(session: Session) -> None:
    """Ensure the global permission catalog exists."""
    existing_codes = {p.code for p in session.exec(select(Permission)).all()}
    for code in PermissionCode:
        if code.value not in existing_codes:
            session.add(Permission(code=code.value, description=code.description))
    session.commit()


def ensure_roles_for_tenant(session: Session, tenant_id: int) -> dict[str, Role]:
    """Create default roles for a tenant if they do not exist."""
    roles_by_name: dict[str, Role] = {}

    existing_roles = session.exec(
        select(Role).where(Role.tenant_id == tenant_id)
    ).all()
    roles_by_name.update({r.name: r for r in existing_roles})

    for role_def in DEFAULT_ROLES:
        role = roles_by_name.get(role_def.name)
        if not role:
            role = Role(tenant_id=tenant_id, name=role_def.name)
            session.add(role)
            session.flush()
            roles_by_name[role_def.name] = role

        # Attach permissions
        permission_ids = {
            rp.permission_id
            for rp in session.exec(
                select(RolePermission).where(RolePermission.role_id == role.id)
            ).all()
        }
        for perm_code in role_def.permissions:
            perm = session.exec(
                select(Permission).where(Permission.code == perm_code.value)
            ).first()
            if perm and perm.id not in permission_ids:
                session.add(RolePermission(role_id=role.id, permission_id=perm.id))

    session.commit()
    return roles_by_name


