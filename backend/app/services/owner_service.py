"""Service for Owner role management and confirmation."""
from typing import Optional
from sqlmodel import Session, select

from app.models.user import User
from app.models.onboarding import OwnerConfirmation
from app.models.role import Role, UserRole


def confirm_owner(
    session: Session,
    user_id: int,
    tenant_id: int,
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
    user = session.get(User, user_id)
    if not user or user.tenant_id != tenant_id:
        raise ValueError("User not found or tenant mismatch")
    
    # Check if owner already exists for this tenant
    existing_owner = session.exec(
        select(User).where(
            User.tenant_id == tenant_id,
            User.is_owner == True
        )
    ).first()
    
    if existing_owner and existing_owner.id != user_id:
        raise ValueError("An owner already exists for this tenant")
    
    # Mark user as owner
    user.is_owner = True
    
    # Ensure user has company_admin role
    roles = session.exec(
        select(Role).where(
            Role.tenant_id == tenant_id,
            Role.name == "company_admin"
        )
    ).all()
    
    company_admin_role = roles[0] if roles else None
    
    if not company_admin_role:
        # Create company_admin role if it doesn't exist
        company_admin_role = Role(
            tenant_id=tenant_id,
            name="company_admin"
        )
        session.add(company_admin_role)
        session.flush()
    
    # Assign role if not already assigned
    existing_assignment = session.exec(
        select(UserRole).where(
            UserRole.user_id == user_id,
            UserRole.role_id == company_admin_role.id
        )
    ).first()
    
    if not existing_assignment:
        session.add(UserRole(user_id=user_id, role_id=company_admin_role.id))
    
    # Create confirmation record
    confirmation = OwnerConfirmation(
        user_id=user_id,
        tenant_id=tenant_id,
        responsibility_disclaimer_accepted=True,
        ip_address=ip_address,
        user_agent=user_agent
    )
    session.add(confirmation)
    session.commit()
    session.refresh(confirmation)
    session.refresh(user)
    
    return confirmation


def get_owner_for_tenant(session: Session, tenant_id: int) -> Optional[User]:
    """Get the owner user for a tenant."""
    owner = session.exec(
        select(User).where(
            User.tenant_id == tenant_id,
            User.is_owner == True
        )
    ).first()
    return owner


def is_user_owner(session: Session, user_id: int, tenant_id: int) -> bool:
    """Check if a user is the owner of a tenant."""
    user = session.get(User, user_id)
    return user is not None and user.tenant_id == tenant_id and user.is_owner == True

