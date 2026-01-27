"""Compliance Service - Stage 4 (Mongo/Beanie)

Privacy/CASL wording, Financial setup, HR policies, and employee acknowledgements.
"""
from datetime import datetime
from typing import List, Optional

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


async def confirm_privacy_wording(
    tenant_id: str,
    wording_type: str,
    confirmed_by_user_id: str,
) -> PrivacyWording:
    """Confirm privacy or CASL wording (Mongo/Beanie)."""
    # Check if already confirmed
    existing = await PrivacyWording.find_one(
        PrivacyWording.tenant_id == tenant_id,
        PrivacyWording.wording_type == wording_type,
    )

    if existing:
        existing.confirmed_at = datetime.utcnow()
        existing.confirmed_by_user_id = confirmed_by_user_id
        await existing.save()
        return existing

    # Get wording content
    content = (
        get_privacy_wording()
        if wording_type == "privacy_policy"
        else get_casl_wording()
    )

    wording = PrivacyWording(
        tenant_id=tenant_id,
        wording_type=wording_type,
        version="1.0",
        content=content,
        confirmed_at=datetime.utcnow(),
        confirmed_by_user_id=confirmed_by_user_id,
    )
    await wording.insert()
    return wording


async def create_or_update_financial_setup(
    tenant_id: str,
    payroll_type: Optional[PayrollType],
    pay_schedule: Optional[PaySchedule],
    wsib_class: Optional[WSIBClass],
) -> FinancialSetup:
    """Create or update financial setup (Mongo/Beanie)."""
    existing = await FinancialSetup.find_one(FinancialSetup.tenant_id == tenant_id)

    if existing:
        existing.payroll_type = payroll_type
        existing.pay_schedule = pay_schedule
        existing.wsib_class = wsib_class
        existing.is_confirmed = False  # Reset confirmation when updated
        await existing.save()
        return existing

    financial_setup = FinancialSetup(
        tenant_id=tenant_id,
        payroll_type=payroll_type,
        pay_schedule=pay_schedule,
        wsib_class=wsib_class,
    )
    await financial_setup.insert()
    return financial_setup


async def confirm_financial_setup(
    tenant_id: str,
    confirmed_by_user_id: str,
) -> FinancialSetup:
    """Confirm financial setup values (Mongo/Beanie)."""
    financial_setup = await FinancialSetup.find_one(
        FinancialSetup.tenant_id == tenant_id
    )

    if not financial_setup:
        raise ValueError("Financial setup not found")

    financial_setup.is_confirmed = True
    financial_setup.confirmed_at = datetime.utcnow()
    financial_setup.confirmed_by_user_id = confirmed_by_user_id
    await financial_setup.save()
    return financial_setup


async def seed_hr_policies() -> List[HRPolicy]:
    """Seed predefined HR policies for all tenants (Mongo/Beanie)."""
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
    
    policies: List[HRPolicy] = []
    for policy_data in policies_data:
        existing = await HRPolicy.find_one(
            HRPolicy.policy_type == policy_data["policy_type"]
        )

        if existing:
            policies.append(existing)
            continue

        policy = HRPolicy(**policy_data)
        await policy.insert()
        policies.append(policy)

    return policies


async def get_required_hr_policies() -> List[HRPolicy]:
    """Get all required HR policies (Mongo/Beanie)."""
    policies = await HRPolicy.find(HRPolicy.is_required == True).to_list()  # noqa: E712
    return list(policies)


async def acknowledge_hr_policies(
    user_id: str,
    policy_ids: List[str],
    ip_address: Optional[str] = None,
    user_agent: Optional[str] = None,
) -> List[PolicyAcknowledgement]:
    """Record employee acknowledgement of HR policies (Mongo/Beanie)."""
    acknowledgements: List[PolicyAcknowledgement] = []

    for policy_id in policy_ids:
        # Check if already acknowledged
        existing = await PolicyAcknowledgement.find_one(
            PolicyAcknowledgement.user_id == user_id,
            PolicyAcknowledgement.policy_id == policy_id,
        )

        if existing:
            acknowledgements.append(existing)
            continue

        acknowledgement = PolicyAcknowledgement(
            user_id=user_id,
            policy_id=policy_id,
            ip_address=ip_address,
            user_agent=user_agent,
        )
        await acknowledgement.insert()
        acknowledgements.append(acknowledgement)

    return acknowledgements


async def has_user_acknowledged_all_required_policies(user_id: str) -> bool:
    """Check if user has acknowledged all required HR policies (Mongo/Beanie)."""
    required_policies = await get_required_hr_policies()
    if not required_policies:
        return True  # No required policies

    required_policy_ids = {str(p.id) for p in required_policies}

    acknowledged = await PolicyAcknowledgement.find(
        PolicyAcknowledgement.user_id == user_id,
        PolicyAcknowledgement.policy_id.in_(list(required_policy_ids)),
    ).to_list()

    acknowledged_ids = {a.policy_id for a in acknowledged}
    return required_policy_ids.issubset(acknowledged_ids)

