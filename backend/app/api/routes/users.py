from fastapi import APIRouter, Depends, HTTPException, status
from sqlmodel import Session, select
from app.api.deps import get_current_user
from app.db import get_session
from app.models import User, Tenant, UserRole, Role
from app.api.authz import require_permission
from app.models.role import PermissionCode
from app.core.security import hash_password
from app.api.routes.auth import _validate_password_strength
from app.schemas.user import UserRead, UserCreate, UserUpdate
from typing import List

router = APIRouter(prefix="/users", tags=["users"])


@router.get("", response_model=List[UserRead])
async def list_users(
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_USERS)),
    session: Session = Depends(get_session),
    skip: int = 0,
    limit: int = 100,
) -> List[UserRead]:
    """List users in the current tenant."""
    users = session.exec(
        select(User)
        .where(User.tenant_id == current_user.tenant_id, User.is_active == True)  # noqa: E712
        .offset(skip)
        .limit(limit)
    ).all()
    
    result = []
    for user in users:
        roles = [r.name for r in user.roles]
        result.append(UserRead.model_validate({**user.model_dump(), "roles": roles}))
    
    return result


@router.post("", response_model=UserRead, status_code=status.HTTP_201_CREATED)
async def create_user(
    payload: UserCreate,
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_USERS)),
    session: Session = Depends(get_session),
) -> UserRead:
    """Create a new user in the current tenant."""
    # Check if email already exists in tenant
    existing = session.exec(
        select(User).where(
            User.email == payload.email.lower(),
            User.tenant_id == current_user.tenant_id
        )
    ).first()
    
    if existing:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="User with this email already exists in your organization.",
        )
    
    _validate_password_strength(payload.password)
    
    user = User(
        tenant_id=current_user.tenant_id,
        email=payload.email.lower(),
        hashed_password=hash_password(payload.password),
        is_super_admin=False,
        is_active=True,
    )
    session.add(user)
    session.flush()
    
    # Assign roles if provided
    if payload.role_names:
        roles = session.exec(
            select(Role).where(
                Role.tenant_id == current_user.tenant_id,
                Role.name.in_(payload.role_names)
            )
        ).all()
        for role in roles:
            session.add(UserRole(user_id=user.id, role_id=role.id))  # type: ignore[arg-type]
    
    session.commit()
    session.refresh(user)
    
    # Automatically provision user to enabled modules (especially Taskify)
    try:
        from app.services.module_onboarding import provision_user_to_modules
        # Provision user to all enabled modules
        await provision_user_to_modules(session, user, payload.password)
    except Exception as e:
        # Log but don't fail user creation if provisioning fails
        import logging
        logger = logging.getLogger(__name__)
        logger.warning(f"Failed to provision user {user.email} to modules: {e}")
    
    roles = [r.name for r in user.roles]
    return UserRead.model_validate({**user.model_dump(), "roles": roles})


@router.get("/{user_id}", response_model=UserRead)
async def get_user(
    user_id: int,
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_USERS)),
    session: Session = Depends(get_session),
) -> UserRead:
    """Get a specific user."""
    user = session.get(User, user_id)
    if not user or user.tenant_id != current_user.tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found.")
    
    roles = [r.name for r in user.roles]
    return UserRead.model_validate({**user.model_dump(), "roles": roles})


@router.patch("/{user_id}", response_model=UserRead)
async def update_user(
    user_id: int,
    payload: UserUpdate,
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_USERS)),
    session: Session = Depends(get_session),
) -> UserRead:
    """Update a user."""
    user = session.get(User, user_id)
    if not user or user.tenant_id != current_user.tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found.")
    
    if payload.email and payload.email.lower() != user.email:
        existing = session.exec(
            select(User).where(
                User.email == payload.email.lower(),
                User.tenant_id == current_user.tenant_id,
                User.id != user_id
            )
        ).first()
        if existing:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Email already in use.",
            )
        user.email = payload.email.lower()
    
    if payload.password:
        _validate_password_strength(payload.password)
        user.hashed_password = hash_password(payload.password)
    
    if payload.is_active is not None:
        user.is_active = payload.is_active
    
    # Update roles if provided
    if payload.role_names is not None:
        # Remove existing roles
        session.exec(
            UserRole.__table__.delete().where(UserRole.user_id == user_id)
        )
        # Add new roles
        roles = session.exec(
            select(Role).where(
                Role.tenant_id == current_user.tenant_id,
                Role.name.in_(payload.role_names)
            )
        ).all()
        for role in roles:
            session.add(UserRole(user_id=user.id, role_id=role.id))  # type: ignore[arg-type]
    
    session.add(user)
    session.commit()
    session.refresh(user)
    
    roles = [r.name for r in user.roles]
    return UserRead.model_validate({**user.model_dump(), "roles": roles})


@router.delete("/{user_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_user(
    user_id: int,
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_USERS)),
    session: Session = Depends(get_session),
) -> None:
    """Delete (deactivate) a user."""
    user = session.get(User, user_id)
    if not user or user.tenant_id != current_user.tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found.")
    
    if user.id == current_user.id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot delete your own account.",
        )
    
    user.is_active = False
    session.add(user)
    session.commit()

