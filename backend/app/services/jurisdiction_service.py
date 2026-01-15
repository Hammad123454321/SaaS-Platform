"""Service for jurisdiction mapping and compliance rule activation."""
from typing import List
from sqlmodel import Session, select

from app.models.tenant import Tenant
from app.models.onboarding import (
    BusinessProfile,
    ProvinceCode,
    ComplianceRuleCode,
    TenantComplianceRule,
)


def get_compliance_rules_for_jurisdiction(province: ProvinceCode, country: str = "Canada") -> List[ComplianceRuleCode]:
    """
    Map jurisdiction to applicable compliance rules.
    
    Returns list of ComplianceRuleCode that should be activated.
    """
    rules = []
    
    # Always include PIPEDA and CASL for Canadian businesses
    if country == "Canada":
        rules.append(ComplianceRuleCode.PIPEDA)
        rules.append(ComplianceRuleCode.CASL)
    
    # Province-specific rules
    if province == ProvinceCode.ONTARIO:
        rules.append(ComplianceRuleCode.PAWS)  # Provincial Animal Welfare Services
        rules.append(ComplianceRuleCode.WSIB)  # Workplace Safety and Insurance Board
    
    # Add more province-specific mappings as needed
    # if province == ProvinceCode.ALBERTA:
    #     rules.append(ComplianceRuleCode.ALBERTA_SPECIFIC_RULE)
    
    return rules


def activate_compliance_rules_for_business_profile(
    session: Session,
    business_profile: BusinessProfile
) -> List[TenantComplianceRule]:
    """
    Activate compliance rules based on business profile jurisdiction.
    
    Returns list of activated TenantComplianceRule records.
    """
    # Get applicable rules
    rule_codes = get_compliance_rules_for_jurisdiction(
        province=business_profile.province,
        country=business_profile.country
    )
    
    activated_rules = []
    
    for rule_code in rule_codes:
        # Check if rule already exists for this tenant
        existing = session.exec(
            select(TenantComplianceRule).where(
                TenantComplianceRule.tenant_id == business_profile.tenant_id,
                TenantComplianceRule.rule_code == rule_code
            )
        ).first()
        
        if existing:
            continue  # Already activated
        
        # Create new compliance rule
        compliance_rule = TenantComplianceRule(
            tenant_id=business_profile.tenant_id,
            business_profile_id=business_profile.id,
            rule_code=rule_code,
            activated_by_jurisdiction=True
        )
        session.add(compliance_rule)
        activated_rules.append(compliance_rule)
    
    session.commit()
    
    # Refresh all rules
    for rule in activated_rules:
        session.refresh(rule)
    
    return activated_rules


def get_activated_rules_for_tenant(session: Session, tenant_id: int) -> List[TenantComplianceRule]:
    """Get all activated compliance rules for a tenant."""
    rules = session.exec(
        select(TenantComplianceRule).where(
            TenantComplianceRule.tenant_id == tenant_id
        )
    ).all()
    return list(rules)

