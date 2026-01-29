"""Service to check if onboarding is completed."""
from typing import Optional

from app.config import is_development
from app.models import User, ModuleEntitlement
from app.models.onboarding import BusinessProfile, OwnerConfirmation
from app.models.compliance import FinancialSetup


async def is_onboarding_complete(user: User) -> tuple[bool, Optional[str]]:
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
    business_profile = await BusinessProfile.find_one(
        BusinessProfile.tenant_id == tenant_id
    )
    if not business_profile:
        return False, "business_profile"
    
    # Stage 2: Owner must be confirmed
    owner_confirmation = await OwnerConfirmation.find_one(
        OwnerConfirmation.tenant_id == tenant_id
    )
    if not owner_confirmation or not owner_confirmation.confirmed_at:
        return False, "owner_confirmation"
    
    # Stage 3: At least one module must be enabled (REQUIRED even in dev)
    enabled_modules = await ModuleEntitlement.find(
        ModuleEntitlement.tenant_id == tenant_id,
        ModuleEntitlement.enabled == True
    ).to_list()
    if not enabled_modules or len(enabled_modules) == 0:
        return False, "module_selection"
    
    # Stage 4: Financial setup must be confirmed (LAST STEP - REQUIRED)
    financial_setup = await FinancialSetup.find_one(
        FinancialSetup.tenant_id == tenant_id
    )
    if not financial_setup:
        return False, "compliance"
    if not financial_setup.is_confirmed:
        return False, "compliance"
    
    # Stage 5: Onboarding tasks are optional for completion
    
    return True, None
