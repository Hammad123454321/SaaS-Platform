"""API routes for Stages 4 and 5."""
from datetime import datetime
from fastapi import APIRouter, Depends, HTTPException, status, Request
from sqlmodel import Session, select
from typing import List

from app.db import get_session
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
def get_privacy_wording_endpoint(
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
def confirm_privacy_wording_endpoint(
    payload: PrivacyWordingConfirm,
    request: Request,
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> dict:
    """Confirm privacy policy or CASL wording."""
    # Only owner can confirm
    if not is_user_owner(session, current_user.id, current_user.tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can confirm privacy wording."
        )
    
    wording = confirm_privacy_wording(
        session=session,
        tenant_id=current_user.tenant_id,
        wording_type=payload.wording_type,
        confirmed_by_user_id=current_user.id
    )
    
    return {
        "status": "success",
        "message": f"{payload.wording_type} confirmed successfully.",
        "confirmed_at": wording.confirmed_at
    }


# ========== Stage 4: Financial Setup ==========

@router.post("/financial-setup", response_model=FinancialSetupResponse, status_code=status.HTTP_201_CREATED)
def create_financial_setup(
    payload: FinancialSetupCreate,
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> FinancialSetupResponse:
    """Create or update financial setup."""
    # Only owner can configure financial setup
    if not is_user_owner(session, current_user.id, current_user.tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can configure financial setup."
        )
    
    financial_setup = create_or_update_financial_setup(
        session=session,
        tenant_id=current_user.tenant_id,
        payroll_type=payload.payroll_type,
        pay_schedule=payload.pay_schedule,
        wsib_class=payload.wsib_class
    )
    
    return FinancialSetupResponse(**financial_setup.model_dump())


@router.get("/financial-setup", response_model=FinancialSetupResponse)
def get_financial_setup(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> FinancialSetupResponse:
    """Get financial setup for tenant."""
    financial_setup = session.exec(
        select(FinancialSetup).where(FinancialSetup.tenant_id == current_user.tenant_id)
    ).first()
    
    if not financial_setup:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Financial setup not found."
        )
    
    return FinancialSetupResponse(**financial_setup.model_dump())


@router.post("/financial-setup/confirm", response_model=FinancialSetupResponse)
def confirm_financial_setup_endpoint(
    payload: FinancialSetupConfirm,
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> FinancialSetupResponse:
    """Confirm financial setup values."""
    if not payload.confirm:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="You must confirm the financial setup values."
        )
    
    # Only owner can confirm
    if not is_user_owner(session, current_user.id, current_user.tenant_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the owner can confirm financial setup."
        )
    
    financial_setup = confirm_financial_setup(
        session=session,
        tenant_id=current_user.tenant_id,
        confirmed_by_user_id=current_user.id
    )
    
    return FinancialSetupResponse(**financial_setup.model_dump())


# ========== Stage 4: HR Policies ==========

@router.post("/hr-policies/seed", response_model=List[HRPolicyResponse])
def seed_hr_policies_endpoint(
    session: Session = Depends(get_session),
) -> List[HRPolicyResponse]:
    """Seed predefined HR policies (idempotent)."""
    policies = seed_hr_policies(session)
    return [HRPolicyResponse(**p.model_dump()) for p in policies]


@router.get("/hr-policies", response_model=List[HRPolicyResponse])
def get_hr_policies(
    session: Session = Depends(get_session),
) -> List[HRPolicyResponse]:
    """Get all required HR policies."""
    policies = get_required_hr_policies(session)
    return [HRPolicyResponse(**p.model_dump()) for p in policies]


@router.post("/hr-policies/acknowledge")
def acknowledge_hr_policies_endpoint(
    payload: PolicyAcknowledgementRequest,
    request: Request,
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> dict:
    """Acknowledge HR policies."""
    ip_address = request.client.host if request.client else None
    user_agent = request.headers.get("user-agent")
    
    acknowledgements = acknowledge_hr_policies(
        session=session,
        user_id=current_user.id,
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
def get_acknowledgement_status(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> dict:
    """Check if user has acknowledged all required HR policies."""
    has_acknowledged = has_user_acknowledged_all_required_policies(session, current_user.id)
    
    return {
        "has_acknowledged_all": has_acknowledged,
        "user_id": current_user.id
    }

