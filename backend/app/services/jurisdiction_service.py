"""Service for jurisdiction mapping and compliance rule activation."""
from typing import List

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
        rules.append(ComplianceRuleCode.PAWS)
        rules.append(ComplianceRuleCode.WSIB)
    
    return rules


async def activate_compliance_rules_for_business_profile(
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
        existing = await TenantComplianceRule.find_one(
            TenantComplianceRule.tenant_id == business_profile.tenant_id,
            TenantComplianceRule.rule_code == rule_code
        )
        
        if existing:
            continue  # Already activated
        
        # Create new compliance rule
        compliance_rule = TenantComplianceRule(
            tenant_id=business_profile.tenant_id,
            business_profile_id=str(business_profile.id),
            rule_code=rule_code,
            activated_by_jurisdiction=True
        )
        await compliance_rule.insert()
        activated_rules.append(compliance_rule)
    
    return activated_rules


async def get_activated_rules_for_tenant(tenant_id: str) -> List[TenantComplianceRule]:
    """Get all activated compliance rules for a tenant."""
    rules = await TenantComplianceRule.find(
        TenantComplianceRule.tenant_id == tenant_id
    ).to_list()
    return rules
