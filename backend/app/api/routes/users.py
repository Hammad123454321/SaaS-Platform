from fastapi import APIRouter, Depends, HTTPException, status
from beanie import PydanticObjectId

from app.api.deps import get_current_user
from app.models import User, Tenant, UserRole, Role
from app.api.authz import require_permission
from app.models.role import PermissionCode
from app.services.owner_service import is_user_owner
from app.core.security import hash_password
from app.api.routes.auth import _validate_password_strength
from app.schemas.user import UserRead, UserCreate, UserUpdate
from typing import List

router = APIRouter(prefix="/users", tags=["users"])


@router.get("", response_model=List[UserRead])
async def list_users(
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_USERS)),
    skip: int = 0,
    limit: int = 100,
) -> List[UserRead]:
    """List users in the current tenant."""
    tenant_id = str(current_user.tenant_id)
    users = await User.find(
        User.tenant_id == tenant_id,
        User.is_active == True
    ).skip(skip).limit(limit).to_list()
    
    result = []
    for user in users:
        user_roles = await UserRole.find(UserRole.user_id == str(user.id)).to_list()
        role_names = []
        for ur in user_roles:
            role = await Role.get(ur.role_id)
            if role:
                role_names.append(role.name)
        result.append(UserRead.model_validate({**user.model_dump(), "roles": role_names}))
    
    return result


@router.post("", response_model=UserRead, status_code=status.HTTP_201_CREATED)
async def create_user(
    payload: UserCreate,
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_USERS)),
) -> UserRead:
    """Create a new user in the current tenant."""
    tenant_id = str(current_user.tenant_id)
    
    existing = await User.find_one(
        User.email == payload.email.lower(),
        User.tenant_id == tenant_id
    )
    
    if existing:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="User with this email already exists in your organization.",
        )
    
    _validate_password_strength(payload.password)
    
    user = User(
        tenant_id=tenant_id,
        email=payload.email.lower(),
        hashed_password=hash_password(payload.password),
        is_super_admin=False,
        is_active=True,
    )
    await user.insert()
    
    role_names = []
    if payload.role_names:
        roles = await Role.find(
            Role.tenant_id == tenant_id,
            {"name": {"$in": payload.role_names}}
        ).to_list()
        for role in roles:
            user_role = UserRole(user_id=str(user.id), role_id=str(role.id))
            await user_role.insert()
            role_names.append(role.name)
    
    try:
        from app.services.module_onboarding import provision_user_to_modules
        await provision_user_to_modules(user, payload.password)
    except Exception as e:
        import logging
        logger = logging.getLogger(__name__)
        logger.warning(f"Failed to provision user {user.email} to modules: {e}")
    
    return UserRead.model_validate({**user.model_dump(), "roles": role_names})


@router.get("/{user_id}", response_model=UserRead)
async def get_user(
    user_id: str,
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_USERS)),
) -> UserRead:
    """Get a specific user."""
    tenant_id = str(current_user.tenant_id)
    
    try:
        user = await User.get(PydanticObjectId(user_id))
    except Exception:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found.")
    
    if not user or user.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found.")
    
    user_roles = await UserRole.find(UserRole.user_id == str(user.id)).to_list()
    role_names = []
    for ur in user_roles:
        role = await Role.get(ur.role_id)
        if role:
            role_names.append(role.name)
    
    return UserRead.model_validate({**user.model_dump(), "roles": role_names})


@router.patch("/{user_id}", response_model=UserRead)
async def update_user(
    user_id: str,
    payload: UserUpdate,
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_USERS)),
) -> UserRead:
    """Update a user."""
    tenant_id = str(current_user.tenant_id)
    
    try:
        user = await User.get(PydanticObjectId(user_id))
    except Exception:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found.")
    
    if not user or user.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found.")
    
    if payload.email and payload.email.lower() != user.email:
        existing = await User.find_one(
            User.email == payload.email.lower(),
            User.tenant_id == tenant_id,
            User.id != user.id
        )
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
    
    if payload.role_names is not None:
        await UserRole.find(UserRole.user_id == str(user.id)).delete()
        
        roles = await Role.find(
            Role.tenant_id == tenant_id,
            {"name": {"$in": payload.role_names}}
        ).to_list()
        for role in roles:
            user_role = UserRole(user_id=str(user.id), role_id=str(role.id))
            await user_role.insert()
    
    await user.save()
    
    user_roles = await UserRole.find(UserRole.user_id == str(user.id)).to_list()
    role_names = []
    for ur in user_roles:
        role = await Role.get(ur.role_id)
        if role:
            role_names.append(role.name)
    
    return UserRead.model_validate({**user.model_dump(), "roles": role_names})


@router.delete("/{user_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_user(
    user_id: str,
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_USERS)),
) -> None:
    """Delete (deactivate) a user."""
    tenant_id = str(current_user.tenant_id)
    
    try:
        user = await User.get(PydanticObjectId(user_id))
    except Exception:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found.")
    
    if not user or user.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found.")
    
    if str(user.id) == str(current_user.id):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot delete your own account.",
        )
    
    if await is_user_owner(str(user.id), tenant_id):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot delete the owner account. The owner role is locked.",
        )
    
    user.is_active = False
    await user.save()
