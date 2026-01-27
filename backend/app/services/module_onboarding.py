"""Service for onboarding tenants to modules (Taskify, CRM, etc.)."""
import logging
import secrets
from typing import Dict, Any, Optional, List

from app.models import VendorCredential, Tenant, ModuleCode, ModuleEntitlement, User
from app.services.vendor_clients.factory import create_vendor_client

logger = logging.getLogger(__name__)


async def onboard_tenant_to_taskify(
    tenant_id: str,
    taskify_base_url: str,
    api_token: str,
    workspace_id: Optional[str] = None,
) -> VendorCredential:
    """
    Onboard a tenant to Taskify module.
    """
    # Check if credential already exists
    existing = await VendorCredential.find_one(
        VendorCredential.tenant_id == tenant_id,
        VendorCredential.vendor == ModuleCode.TASKS.value,
    )
    
    if existing:
        # Update existing
        existing.credentials = {
            "base_url": taskify_base_url,
            "api_token": api_token,
            "workspace_id": workspace_id,
        }
        await existing.save()
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
    await credential.insert()
    
    # Ensure entitlement exists and is enabled
    entitlement = await ModuleEntitlement.find_one(
        ModuleEntitlement.tenant_id == tenant_id,
        ModuleEntitlement.module_code == ModuleCode.TASKS,
    )
    
    if not entitlement:
        entitlement = ModuleEntitlement(
            tenant_id=tenant_id,
            module_code=ModuleCode.TASKS,
            enabled=True,
        )
        await entitlement.insert()
    
    return credential


async def verify_taskify_connection(tenant_id: str) -> Dict[str, Any]:
    """
    Verify Taskify connection for a tenant.
    """
    try:
        client = await create_vendor_client(ModuleCode.TASKS, tenant_id)
        if not client:
            return {"status": "error", "message": "No credentials found"}
        
        health = await client.health()
        await client.close()
        return health
    except Exception as e:
        return {"status": "error", "message": str(e)}


async def provision_user_to_modules(
    user: User,
    password: str,
) -> Dict[str, Any]:
    """
    Provision a user to all enabled modules for their tenant.
    """
    results = {}
    
    # Get all enabled modules for this tenant
    enabled_modules = await ModuleEntitlement.find(
        ModuleEntitlement.tenant_id == user.tenant_id,
        ModuleEntitlement.enabled == True,
    ).to_list()
    
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
            client = await create_vendor_client(module_code, user.tenant_id)
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
                        "status": 1,
                        "require_ev": 0,
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
    tenant_id: str,
    module_code: ModuleCode,
) -> Dict[str, Any]:
    """
    Sync all existing users from a tenant to a newly enabled module.
    """
    # Get all users for this tenant
    users = await User.find(User.tenant_id == tenant_id, User.is_active == True).to_list()
    
    if not users:
        return {"synced": 0, "errors": []}
    
    synced = 0
    errors = []
    
    # Try to provision each user (without password - they'll need to reset)
    for user in users:
        try:
            client = await create_vendor_client(module_code, tenant_id)
            if not client:
                errors.append(f"No client for {user.email}")
                continue
            
            # Extract name from email
            email_parts = user.email.split("@")[0].split(".", 1)
            first_name = email_parts[0].capitalize() if email_parts else "User"
            last_name = email_parts[1].capitalize() if len(email_parts) > 1 else ""
            
            if module_code == ModuleCode.TASKS and hasattr(client, "create_user"):
                # Create user in Taskify with a temporary password
                temp_password = secrets.token_urlsafe(16)
                user_data = {
                    "first_name": first_name,
                    "last_name": last_name,
                    "email": user.email,
                    "password": temp_password,
                    "status": 1,
                    "require_ev": 0,
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
        "message": f"Synced {synced}/{len(users)} users." if synced < len(users) else f"All {synced} users synced successfully.",
    }
