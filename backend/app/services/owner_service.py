"""Service for Owner role management and confirmation."""
from typing import Optional

from app.models.user import User
from app.models.onboarding import OwnerConfirmation
from app.models.role import Role, UserRole


async def confirm_owner(
    user_id: str,
    tenant_id: str,
    ip_address: Optional[str] = None,
    user_agent: Optional[str] = None
) -> OwnerConfirmation:
    """
    Confirm a user as Owner and lock the role.
    
    Rules:
    - Sets is_owner=True on user
    - Creates OwnerConfirmation record
    - Ensures user has company_admin role (or creates it)
    - Owner cannot be deleted (enforced in user deletion endpoints)
    """
    user = await User.get(user_id)
    if not user or user.tenant_id != tenant_id:
        raise ValueError("User not found or tenant mismatch")
    
    # Check if owner already exists for this tenant
    existing_owner = await User.find_one(
        User.tenant_id == tenant_id,
        User.is_owner == True
    )
    
    if existing_owner and str(existing_owner.id) != user_id:
        raise ValueError("An owner already exists for this tenant")
    
    # Mark user as owner
    user.is_owner = True
    await user.save()
    
    # Ensure user has company_admin role
    company_admin_role = await Role.find_one(
        Role.tenant_id == tenant_id,
        Role.name == "company_admin"
    )
    
    if not company_admin_role:
        # Create company_admin role if it doesn't exist
        company_admin_role = Role(
            tenant_id=tenant_id,
            name="company_admin",
            permission_codes=[]
        )
        await company_admin_role.insert()
    
    # Assign role if not already assigned
    existing_assignment = await UserRole.find_one(
        UserRole.user_id == user_id,
        UserRole.role_id == str(company_admin_role.id)
    )
    
    if not existing_assignment:
        user_role = UserRole(user_id=user_id, role_id=str(company_admin_role.id))
        await user_role.insert()
    
    # Create confirmation record
    confirmation = OwnerConfirmation(
        user_id=user_id,
        tenant_id=tenant_id,
        responsibility_disclaimer_accepted=True,
        ip_address=ip_address,
        user_agent=user_agent
    )
    await confirmation.insert()
    
    return confirmation


async def get_owner_for_tenant(tenant_id: str) -> Optional[User]:
    """Get the owner user for a tenant."""
    return await User.find_one(
        User.tenant_id == tenant_id,
        User.is_owner == True
    )


async def is_user_owner(user_id: str, tenant_id: str) -> bool:
    """Check if a user is the owner of a tenant."""
    user = await User.get(user_id)
    return user is not None and user.tenant_id == tenant_id and user.is_owner == True
