"""Service for role templates (Manager, Staff, Accountant)."""
from sqlmodel import Session, select

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


def seed_role_templates(session: Session, tenant_id: int) -> list[Role]:
    """
    Seed default role templates for a tenant.
    
    Creates Manager, Staff, and Accountant roles if they don't exist.
    These can be edited/deleted later by the owner.
    """
    templates = []
    
    for template_code in RoleTemplateCode:
        # Check if role already exists
        existing = session.exec(
            select(Role).where(
                Role.tenant_id == tenant_id,
                Role.name == template_code.value
            )
        ).first()
        
        if existing:
            templates.append(existing)
            continue
        
        # Create new role
        role = Role(
            tenant_id=tenant_id,
            name=template_code.value
        )
        session.add(role)
        templates.append(role)
    
    session.commit()
    
    # Refresh all roles
    for role in templates:
        session.refresh(role)
    
    return templates


def get_role_templates(session: Session, tenant_id: int) -> list[Role]:
    """Get all role templates for a tenant."""
    template_names = [code.value for code in RoleTemplateCode]
    roles = session.exec(
        select(Role).where(
            Role.tenant_id == tenant_id,
            Role.name.in_(template_names)
        )
    ).all()
    return list(roles)

