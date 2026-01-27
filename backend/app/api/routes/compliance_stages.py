"""API routes for Stages 4 and 5."""
from datetime import datetime
from fastapi import APIRouter, Depends, HTTPException, status, Request
from typing import List

from app.api.deps import get_current_user
from app.models import User
from app.models.compliance import (
    FinancialSetup,
    HRPolicy,
    PolicyAcknowledgement,
    PrivacyWording,
)
from app.schemas.compliance_stages import (
    PrivacyWordingConfirm,
    FinancialSetupCreate,
    FinancialSetupResponse,
    FinancialSetupConfirm,
    HRPolicyResponse,
    PolicyAcknowledgementRequest,
)
from app.services.compliance_service import (
    get_privacy_wording,
    get_casl_wording,
    confirm_privacy_wording,
    create_or_update_financial_setup,
    confirm_financial_setup,
    seed_hr_policies,
    get_required_hr_policies,
    acknowledge_hr_policies,
    has_user_acknowledged_all_required_policies,
)
from app.services.owner_service import is_user_owner

router = APIRouter(prefix="/compliance", tags=["compliance"])


# ========== Stage 4: Privacy & CASL Wording ==========

@router.get("/privacy-wording")
async def get_privacy_wording_endpoint(
    wording_type: str = "privacy_policy",
    current_user: User = Depends(get_current_user),
) -> dict:
    """Get privacy policy or CASL wording (hardcoded)."""
    if wording_type == "privacy_policy":
        content = get_privacy_wording()
    elif wording_type == "casl":
        content = get_casl_wording()
    else:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Invalid wording_type. Must be 'privacy_policy' or 'casl'"
        )
    
    return {
        "wording_type": wording_type,
        "version": "1.0",
        "content": content
    }


@router.post("/privacy-wording/confirm")
async def confirm_privacy_wording_endpoint(
    payload: PrivacyWordingConfirm,
    request: Request,
    current_user: User = Depends(get_current_user),
) -> dict:
    """Confirm privacy policy or CASL wording."""
    tenant_id = str(current_user.tenant_id)
    
    if not await is_user_owner(str(current_user.id), tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can confirm privacy wording."
        )
    
    wording = await confirm_privacy_wording(
        tenant_id=tenant_id,
        wording_type=payload.wording_type,
        confirmed_by_user_id=str(current_user.id)
    )
    
    return {
        "status": "success",
        "message": f"{payload.wording_type} confirmed successfully.",
        "confirmed_at": wording.confirmed_at
    }


# ========== Stage 4: Financial Setup ==========

@router.post("/financial-setup", response_model=FinancialSetupResponse, status_code=status.HTTP_201_CREATED)
async def create_financial_setup(
    payload: FinancialSetupCreate,
    current_user: User = Depends(get_current_user),
) -> FinancialSetupResponse:
    """Create or update financial setup."""
    tenant_id = str(current_user.tenant_id)
    
    if not await is_user_owner(str(current_user.id), tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can configure financial setup."
        )
    
    financial_setup = await create_or_update_financial_setup(
        tenant_id=tenant_id,
        payroll_type=payload.payroll_type,
        pay_schedule=payload.pay_schedule,
        wsib_class=payload.wsib_class
    )
    
    return FinancialSetupResponse(**financial_setup.model_dump())


@router.get("/financial-setup", response_model=FinancialSetupResponse)
async def get_financial_setup(
    current_user: User = Depends(get_current_user),
) -> FinancialSetupResponse:
    """Get financial setup for tenant."""
    tenant_id = str(current_user.tenant_id)
    
    financial_setup = await FinancialSetup.find_one(
        FinancialSetup.tenant_id == tenant_id
    )
    
    if not financial_setup:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Financial setup not found."
        )
    
    return FinancialSetupResponse(**financial_setup.model_dump())


@router.post("/financial-setup/confirm", response_model=FinancialSetupResponse)
async def confirm_financial_setup_endpoint(
    payload: FinancialSetupConfirm,
    current_user: User = Depends(get_current_user),
) -> FinancialSetupResponse:
    """Confirm financial setup values."""
    if not payload.confirm:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="You must confirm the financial setup values."
        )
    
    tenant_id = str(current_user.tenant_id)
    
    if not await is_user_owner(str(current_user.id), tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can confirm financial setup."
        )
    
    financial_setup = await confirm_financial_setup(
        tenant_id=tenant_id,
        confirmed_by_user_id=str(current_user.id)
    )
    
    return FinancialSetupResponse(**financial_setup.model_dump())


# ========== Stage 4: HR Policies ==========

@router.post("/hr-policies/seed", response_model=List[HRPolicyResponse])
async def seed_hr_policies_endpoint() -> List[HRPolicyResponse]:
    """Seed predefined HR policies (idempotent)."""
    policies = await seed_hr_policies()
    return [HRPolicyResponse(**p.model_dump()) for p in policies]


@router.get("/hr-policies", response_model=List[HRPolicyResponse])
async def get_hr_policies() -> List[HRPolicyResponse]:
    """Get all required HR policies."""
    policies = await get_required_hr_policies()
    return [HRPolicyResponse(**p.model_dump()) for p in policies]


@router.post("/hr-policies/acknowledge")
async def acknowledge_hr_policies_endpoint(
    payload: PolicyAcknowledgementRequest,
    request: Request,
    current_user: User = Depends(get_current_user),
) -> dict:
    """Acknowledge HR policies."""
    ip_address = request.client.host if request.client else None
    user_agent = request.headers.get("user-agent")
    
    acknowledgements = await acknowledge_hr_policies(
        user_id=str(current_user.id),
        policy_ids=payload.policy_ids,
        ip_address=ip_address,
        user_agent=user_agent
    )
    
    return {
        "status": "success",
        "message": f"Acknowledged {len(acknowledgements)} policies.",
        "acknowledged_count": len(acknowledgements)
    }


@router.get("/hr-policies/acknowledgement-status")
async def get_acknowledgement_status(
    current_user: User = Depends(get_current_user),
) -> dict:
    """Check if user has acknowledged all required HR policies."""
    has_acknowledged = await has_user_acknowledged_all_required_policies(str(current_user.id))
    
    return {
        "has_acknowledged_all": has_acknowledged,
        "user_id": str(current_user.id)
    }
