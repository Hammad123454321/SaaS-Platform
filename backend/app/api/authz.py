from collections.abc import Callable

from fastapi import Depends, HTTPException, status

from app.api.deps import get_current_user
from app.models import Permission, RolePermission, UserRole, User
from app.models.role import PermissionCode
from app.config import is_development


def require_permission(permission: PermissionCode) -> Callable:
    """Reusable RBAC dependency for routes (Mongo/Beanie)."""

    async def _checker(
        current_user: User = Depends(get_current_user),
    ) -> User:
        if current_user.is_super_admin:
            return current_user

        # Development mode override: bypass billing permission checks
        if is_development() and permission == PermissionCode.VIEW_BILLING:
            return current_user

        # Look up role permissions via Beanie relationships
        user_roles = await UserRole.find(UserRole.user_id == str(current_user.id)).to_list()
        role_ids = [ur.role_id for ur in user_roles]
        if not role_ids:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN, detail="Forbidden."
            )

        role_permissions = await RolePermission.find(
            RolePermission.role_id.in_(role_ids)
        ).to_list()
        permission_ids = {rp.permission_id for rp in role_permissions}

        if not permission_ids:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN, detail="Forbidden."
            )

        permission_docs = await Permission.find(
            Permission.id.in_(list(permission_ids))
        ).to_list()
        codes = {p.code for p in permission_docs}

        if permission.value in codes:
            return current_user

        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Forbidden.")

    return _checker





