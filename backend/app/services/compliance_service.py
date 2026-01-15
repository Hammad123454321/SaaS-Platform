"""Compliance Service - Stage 4

Privacy/CASL wording, Financial setup, HR policies, and employee acknowledgements.
"""
from datetime import datetime
from typing import List, Optional
from sqlmodel import Session, select

from app.models.compliance import (
    FinancialSetup,
    PayrollType,
    PaySchedule,
    WSIBClass,
    HRPolicy,
    HRPolicyType,
    PolicyAcknowledgement,
    PrivacyWording,
)
from app.models.user import User
from app.models.tenant import Tenant


# Hardcoded privacy and CASL wording (version 1.0)
PRIVACY_POLICY_WORDING = """
Privacy Policy

We are committed to protecting your personal information in accordance with PIPEDA (Personal Information Protection and Electronic Documents Act).

[Your privacy policy content here]
"""

CASL_WORDING = """
By providing your email address, you consent to receive commercial electronic messages from us in accordance with Canada's Anti-Spam Legislation (CASL).

You can unsubscribe at any time by clicking the unsubscribe link in our emails.
"""


def get_privacy_wording(version: str = "1.0") -> str:
    """Get hardcoded privacy policy wording."""
    return PRIVACY_POLICY_WORDING


def get_casl_wording(version: str = "1.0") -> str:
    """Get hardcoded CASL wording."""
    return CASL_WORDING


def confirm_privacy_wording(
    session: Session,
    tenant_id: int,
    wording_type: str,
    confirmed_by_user_id: int
) -> PrivacyWording:
    """Confirm privacy or CASL wording."""
    # Check if already confirmed
    existing = session.exec(
        select(PrivacyWording).where(
            PrivacyWording.tenant_id == tenant_id,
            PrivacyWording.wording_type == wording_type
        )
    ).first()
    
    if existing:
        existing.confirmed_at = datetime.utcnow()
        existing.confirmed_by_user_id = confirmed_by_user_id
        session.add(existing)
        session.commit()
        session.refresh(existing)
        return existing
    
    # Get wording content
    content = get_privacy_wording() if wording_type == "privacy_policy" else get_casl_wording()
    
    wording = PrivacyWording(
        tenant_id=tenant_id,
        wording_type=wording_type,
        version="1.0",
        content=content,
        confirmed_at=datetime.utcnow(),
        confirmed_by_user_id=confirmed_by_user_id
    )
    session.add(wording)
    session.commit()
    session.refresh(wording)
    return wording


def create_or_update_financial_setup(
    session: Session,
    tenant_id: int,
    payroll_type: Optional[PayrollType],
    pay_schedule: Optional[PaySchedule],
    wsib_class: Optional[WSIBClass]
) -> FinancialSetup:
    """Create or update financial setup."""
    existing = session.exec(
        select(FinancialSetup).where(FinancialSetup.tenant_id == tenant_id)
    ).first()
    
    if existing:
        existing.payroll_type = payroll_type
        existing.pay_schedule = pay_schedule
        existing.wsib_class = wsib_class
        existing.is_confirmed = False  # Reset confirmation when updated
        session.add(existing)
        session.commit()
        session.refresh(existing)
        return existing
    
    financial_setup = FinancialSetup(
        tenant_id=tenant_id,
        payroll_type=payroll_type,
        pay_schedule=pay_schedule,
        wsib_class=wsib_class
    )
    session.add(financial_setup)
    session.commit()
    session.refresh(financial_setup)
    return financial_setup


def confirm_financial_setup(
    session: Session,
    tenant_id: int,
    confirmed_by_user_id: int
) -> FinancialSetup:
    """Confirm financial setup values."""
    financial_setup = session.exec(
        select(FinancialSetup).where(FinancialSetup.tenant_id == tenant_id)
    ).first()
    
    if not financial_setup:
        raise ValueError("Financial setup not found")
    
    financial_setup.is_confirmed = True
    financial_setup.confirmed_at = datetime.utcnow()
    financial_setup.confirmed_by_user_id = confirmed_by_user_id
    session.add(financial_setup)
    session.commit()
    session.refresh(financial_setup)
    return financial_setup


def seed_hr_policies(session: Session) -> List[HRPolicy]:
    """Seed predefined HR policies for all tenants."""
    policies_data = [
        {
            "policy_type": HRPolicyType.HEALTH_SAFETY,
            "title": "Health & Safety Policy",
            "content": "All employees must follow health and safety protocols. Report any incidents immediately.",
            "is_required": True
        },
        {
            "policy_type": HRPolicyType.HARASSMENT,
            "title": "Harassment Policy",
            "content": "We are committed to a harassment-free workplace. All forms of harassment are prohibited.",
            "is_required": True
        },
        {
            "policy_type": HRPolicyType.TRAINING,
            "title": "Training Requirements",
            "content": "Employees must complete required training modules as assigned.",
            "is_required": True
        }
    ]
    
    policies = []
    for policy_data in policies_data:
        existing = session.exec(
            select(HRPolicy).where(HRPolicy.policy_type == policy_data["policy_type"])
        ).first()
        
        if existing:
            policies.append(existing)
            continue
        
        policy = HRPolicy(**policy_data)
        session.add(policy)
        policies.append(policy)
    
    session.commit()
    for policy in policies:
        session.refresh(policy)
    
    return policies


def get_required_hr_policies(session: Session) -> List[HRPolicy]:
    """Get all required HR policies."""
    policies = session.exec(
        select(HRPolicy).where(HRPolicy.is_required == True)
    ).all()
    return list(policies)


def acknowledge_hr_policies(
    session: Session,
    user_id: int,
    policy_ids: List[int],
    ip_address: Optional[str] = None,
    user_agent: Optional[str] = None
) -> List[PolicyAcknowledgement]:
    """Record employee acknowledgement of HR policies."""
    acknowledgements = []
    
    for policy_id in policy_ids:
        # Check if already acknowledged
        existing = session.exec(
            select(PolicyAcknowledgement).where(
                PolicyAcknowledgement.user_id == user_id,
                PolicyAcknowledgement.policy_id == policy_id
            )
        ).first()
        
        if existing:
            acknowledgements.append(existing)
            continue
        
        acknowledgement = PolicyAcknowledgement(
            user_id=user_id,
            policy_id=policy_id,
            ip_address=ip_address,
            user_agent=user_agent
        )
        session.add(acknowledgement)
        acknowledgements.append(acknowledgement)
    
    session.commit()
    for ack in acknowledgements:
        session.refresh(ack)
    
    return acknowledgements


def has_user_acknowledged_all_required_policies(session: Session, user_id: int) -> bool:
    """Check if user has acknowledged all required HR policies."""
    required_policies = get_required_hr_policies(session)
    if not required_policies:
        return True  # No required policies
    
    required_policy_ids = {p.id for p in required_policies}
    
    acknowledged = session.exec(
        select(PolicyAcknowledgement).where(
            PolicyAcknowledgement.user_id == user_id,
            PolicyAcknowledgement.policy_id.in_(required_policy_ids)
        )
    ).all()
    
    acknowledged_ids = {a.policy_id for a in acknowledged}
    return required_policy_ids.issubset(acknowledged_ids)

