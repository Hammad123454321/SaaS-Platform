from collections.abc import Callable

from fastapi import Depends, HTTPException, status
from sqlmodel import Session, select

from app.api.deps import get_current_user
from app.db import get_session
from app.models import Permission, RolePermission, UserRole, User
from app.models.role import PermissionCode
from app.config import is_development


def require_permission(permission: PermissionCode) -> Callable:
    """Reusable RBAC dependency for routes.
    
    Development mode override: Bypasses billing permission checks when ENVIRONMENT=development.
    """

    def _checker(
        current_user: User = Depends(get_current_user), session: Session = Depends(get_session)
    ) -> User:
        if current_user.is_super_admin:
            return current_user

        # Development mode override: bypass billing permission checks
        if is_development() and permission == PermissionCode.VIEW_BILLING:
            return current_user

        stmt = (
            select(RolePermission)
            .join(UserRole, RolePermission.role_id == UserRole.role_id)
            .join(Permission, RolePermission.permission_id == Permission.id)
            .where(UserRole.user_id == current_user.id, Permission.code == permission.value)
        )
        if session.exec(stmt).first():
            return current_user
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Forbidden.")

    return _checker





