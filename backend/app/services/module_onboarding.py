"""Service for onboarding tenants to modules (Taskify, CRM, etc.)."""
import logging
from typing import Dict, Any, Optional, List
from sqlmodel import Session, select

from app.models import VendorCredential, Tenant, ModuleCode, ModuleEntitlement, User
from app.services.vendor_clients.factory import create_vendor_client

logger = logging.getLogger(__name__)


async def onboard_tenant_to_taskify(
    session: Session,
    tenant_id: int,
    taskify_base_url: str,
    api_token: str,
    workspace_id: Optional[int] = None,
) -> VendorCredential:
    """
    Onboard a tenant to Taskify module.
    
    This creates:
    1. VendorCredential record with Taskify API credentials
    2. ModuleEntitlement record (enabled)
    
    Args:
        session: Database session
        tenant_id: Tenant ID
        taskify_base_url: Base URL of Taskify instance (e.g., "http://taskify:8001")
        api_token: Laravel Sanctum API token
        workspace_id: Optional workspace ID (if not provided, will be created in Taskify)
    
    Returns:
        Created VendorCredential
    """
    # Check if credential already exists
    stmt = select(VendorCredential).where(
        VendorCredential.tenant_id == tenant_id,
        VendorCredential.vendor == ModuleCode.TASKS.value,
    )
    existing = session.exec(stmt).first()
    
    if existing:
        # Update existing
        existing.credentials = {
            "base_url": taskify_base_url,
            "api_token": api_token,
            "workspace_id": workspace_id,
        }
        session.add(existing)
        session.commit()
        session.refresh(existing)
        return existing
    
    # Create new credential
    credential = VendorCredential(
        tenant_id=tenant_id,
        vendor=ModuleCode.TASKS.value,
        credentials={
            "base_url": taskify_base_url,
            "api_token": api_token,
            "workspace_id": workspace_id,
        },
    )
    session.add(credential)
    
    # Ensure entitlement exists and is enabled
    stmt = select(ModuleEntitlement).where(
        ModuleEntitlement.tenant_id == tenant_id,
        ModuleEntitlement.module_code == ModuleCode.TASKS,
    )
    entitlement = session.exec(stmt).first()
    
    if not entitlement:
        entitlement = ModuleEntitlement(
            tenant_id=tenant_id,
            module_code=ModuleCode.TASKS,
            enabled=True,
        )
        session.add(entitlement)
    
    session.commit()
    session.refresh(credential)
    return credential


async def verify_taskify_connection(
    session: Session,
    tenant_id: int,
) -> Dict[str, Any]:
    """
    Verify Taskify connection for a tenant.
    
    Returns:
        Dict with status and error message if any
    """
    try:
        client = await create_vendor_client(ModuleCode.TASKS, tenant_id, session)
        if not client:
            return {"status": "error", "message": "No credentials found"}
        
        health = await client.health()
        await client.close()
        return health
    except Exception as e:
        return {"status": "error", "message": str(e)}


async def provision_user_to_modules(
    session: Session,
    user: User,
    password: str,
) -> Dict[str, Any]:
    """
    Provision a user to all enabled modules for their tenant.
    
    This creates the user in each enabled module (Taskify, CRM, etc.)
    so they can access those services.
    
    Args:
        session: Database session
        user: User to provision
        password: User's plain text password (for module accounts)
    
    Returns:
        Dict with provisioning results per module
    """
    results = {}
    
    # Get all enabled modules for this tenant
    stmt = select(ModuleEntitlement).where(
        ModuleEntitlement.tenant_id == user.tenant_id,
        ModuleEntitlement.enabled == True,  # noqa: E712
    )
    enabled_modules = session.exec(stmt).all()
    
    if not enabled_modules:
        logger.info(f"No enabled modules for tenant {user.tenant_id}, skipping user provisioning")
        return {"provisioned": [], "errors": []}
    
    # Extract email parts for first_name/last_name
    email_parts = user.email.split("@")[0].split(".", 1)
    first_name = email_parts[0].capitalize() if email_parts else "User"
    last_name = email_parts[1].capitalize() if len(email_parts) > 1 else ""
    
    for entitlement in enabled_modules:
        module_code = entitlement.module_code
        try:
            client = await create_vendor_client(module_code, user.tenant_id, session)
            if not client:
                logger.warning(f"No client available for {module_code.value}, skipping")
                continue
            
            # Provision user based on module type
            if module_code == ModuleCode.TASKS:
                if hasattr(client, "create_user"):
                    user_data = {
                        "first_name": first_name,
                        "last_name": last_name,
                        "email": user.email,
                        "password": password,
                        "status": 1,  # Active
                        "require_ev": 0,  # Skip email verification (already verified in SaaS platform)
                    }
                    result = await client.create_user(user_data)
                    results[module_code.value] = {"status": "success", "data": result}
                    logger.info(f"Provisioned user {user.email} to {module_code.value}")
                else:
                    logger.warning(f"create_user not implemented for {module_code.value}")
            
            await client.close()
            
        except Exception as e:
            logger.error(f"Failed to provision user to {module_code.value}: {e}")
            results[module_code.value] = {"status": "error", "message": str(e)}
    
    return results


async def sync_all_users_to_module(
    session: Session,
    tenant_id: int,
    module_code: ModuleCode,
) -> Dict[str, Any]:
    """
    Sync all existing users from a tenant to a newly enabled module.
    
    This is called when a module is first enabled to create accounts
    for all existing users.
    
    Args:
        session: Database session
        tenant_id: Tenant ID
        module_code: Module to sync users to
    
    Returns:
        Dict with sync results
    """
    # Get all users for this tenant
    stmt = select(User).where(User.tenant_id == tenant_id, User.is_active == True)  # noqa: E712
    users = session.exec(stmt).all()
    
    if not users:
        return {"synced": 0, "errors": []}
    
    synced = 0
    errors = []
    
    # Try to provision each user (without password - they'll need to reset)
    for user in users:
        try:
            client = await create_vendor_client(module_code, tenant_id, session)
            if not client:
                errors.append(f"No client for {user.email}")
                continue
            
            # Extract name from email
            email_parts = user.email.split("@")[0].split(".", 1)
            first_name = email_parts[0].capitalize() if email_parts else "User"
            last_name = email_parts[1].capitalize() if len(email_parts) > 1 else ""
            
            if module_code == ModuleCode.TASKS and hasattr(client, "create_user"):
                # Create user in Taskify with a temporary password
                # User will need to reset password via Taskify's forgot password
                import secrets
                temp_password = secrets.token_urlsafe(16)
                user_data = {
                    "first_name": first_name,
                    "last_name": last_name,
                    "email": user.email,
                    "password": temp_password,
                    "status": 1,
                    "require_ev": 0,  # Skip email verification
                }
                await client.create_user(user_data)
                synced += 1
                logger.info(f"Synced user {user.email} to {module_code.value}")
            
            await client.close()
        except Exception as e:
            error_msg = f"Failed to sync {user.email}: {str(e)}"
            errors.append(error_msg)
            logger.error(error_msg)
    
    return {
        "synced": synced,
        "total_users": len(users),
        "errors": errors,
        "message": f"Synced {synced}/{len(users)} users. Users may need to reset passwords in {module_code.value}." if synced < len(users) else f"All {synced} users synced successfully.",
    }



