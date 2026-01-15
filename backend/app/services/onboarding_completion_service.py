"""Service to check if onboarding is completed."""
from sqlmodel import Session, select
from typing import Optional

from app.config import is_development
from app.models import User, ModuleEntitlement
from app.models.onboarding import BusinessProfile, OwnerConfirmation


def is_onboarding_complete(session: Session, user: User) -> tuple[bool, Optional[str]]:
    """
    Check if user's onboarding is complete.
    
    Returns:
        (is_complete: bool, missing_stage: Optional[str])
    
    Requirements (in dev mode, skip email verification and billing):
    - Stage 0: Email verified (SKIP in dev)
    - Stage 1: Business profile exists
    - Stage 2: Owner confirmed
    - Stage 3: At least one module selected (REQUIRED even in dev)
    - Stage 4: Compliance (skip billing in dev, but other parts may be needed)
    - Stage 5: Onboarding tasks (optional for completion)
    """
    tenant_id = user.tenant_id
    
    # Stage 0: Email verification (skip in dev mode)
    if not is_development():
        if not user.email_verified:
            return False, "email_verification"
    
    # Stage 1: Business profile must exist
    business_profile = session.exec(
        select(BusinessProfile).where(BusinessProfile.tenant_id == tenant_id)
    ).first()
    if not business_profile:
        return False, "business_profile"
    
    # Stage 2: Owner must be confirmed
    owner_confirmation = session.exec(
        select(OwnerConfirmation).where(OwnerConfirmation.tenant_id == tenant_id)
    ).first()
    if not owner_confirmation or not owner_confirmation.confirmed:
        return False, "owner_confirmation"
    
    # Stage 3: At least one module must be enabled (REQUIRED even in dev)
    enabled_modules = session.exec(
        select(ModuleEntitlement).where(
            ModuleEntitlement.tenant_id == tenant_id,
            ModuleEntitlement.enabled == True  # noqa: E712
        )
    ).all()
    if not enabled_modules or len(enabled_modules) == 0:
        return False, "module_selection"
    
    # Stage 4: Compliance (skip billing in dev, but check other compliance)
    # For now, we'll consider compliance optional if business profile exists
    # You can add more specific compliance checks here if needed
    
    # Stage 5: Onboarding tasks are optional for completion
    
    return True, None




