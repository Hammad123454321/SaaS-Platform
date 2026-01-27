"""Service for role templates (Manager, Staff, Accountant)."""
from typing import List

from app.models.role import Role
from app.models.onboarding import RoleTemplateCode


# Default permissions for each role template
ROLE_TEMPLATE_PERMISSIONS = {
    RoleTemplateCode.MANAGER: [
        "manage_users",
        "view_billing",
        "access_modules",
    ],
    RoleTemplateCode.STAFF: [
        "access_modules",
    ],
    RoleTemplateCode.ACCOUNTANT: [
        "view_billing",
        "access_modules",
    ],
}


async def seed_role_templates(tenant_id: str) -> List[Role]:
    """
    Seed default role templates for a tenant.
    
    Creates Manager, Staff, and Accountant roles if they don't exist.
    These can be edited/deleted later by the owner.
    """
    templates = []
    
    for template_code in RoleTemplateCode:
        # Check if role already exists
        existing = await Role.find_one(
            Role.tenant_id == tenant_id,
            Role.name == template_code.value
        )
        
        if existing:
            templates.append(existing)
            continue
        
        # Create new role
        role = Role(
            tenant_id=tenant_id,
            name=template_code.value,
            permission_codes=ROLE_TEMPLATE_PERMISSIONS.get(template_code, [])
        )
        await role.insert()
        templates.append(role)
    
    return templates


async def get_role_templates(tenant_id: str) -> List[Role]:
    """Get all role templates for a tenant."""
    template_names = [code.value for code in RoleTemplateCode]
    roles = await Role.find(
        Role.tenant_id == tenant_id,
        {"name": {"$in": template_names}}
    ).to_list()
    return roles
