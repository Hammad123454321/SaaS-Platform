"""Factory for creating vendor clients based on module type and credentials."""
from typing import Optional

from sqlmodel import Session, select

from app.models import ModuleCode, VendorCredential
from app.models.taskify_config import TenantTaskifyConfig, TaskifyUserMapping
from app.services.vendor_clients.base import BaseVendorClient
from app.services.vendor_clients.taskify_client import TaskifyClient


async def create_vendor_client(
    module_code: ModuleCode,
    tenant_id: int,
    session: Session,
    user_id: Optional[int] = None,
) -> Optional[BaseVendorClient]:
    """
    Create a vendor client instance for a given module and tenant.

    Args:
        module_code: The module to create a client for
        tenant_id: Tenant ID to get credentials for
        session: Database session
        user_id: Optional user ID to get user-specific token (for Taskify)

    Returns:
        Vendor client instance or None if credentials not found or invalid
    """
    # Factory pattern: create client based on module type
    if module_code == ModuleCode.TASKS:
        # Use TenantTaskifyConfig for Taskify (new approach)
        taskify_config = session.exec(
            select(TenantTaskifyConfig).where(
                TenantTaskifyConfig.tenant_id == tenant_id,
                TenantTaskifyConfig.is_active == True,  # noqa: E712
            )
        ).first()

        if not taskify_config:
            # Fallback to VendorCredential for backward compatibility
            stmt = select(VendorCredential).where(
                VendorCredential.tenant_id == tenant_id,
                VendorCredential.vendor == module_code.value,
            )
            creds = session.exec(stmt).first()
            
            if not creds:
                return None
            
            credentials = creds.credentials
            base_url = credentials.get("base_url", "http://taskify:8001")
            api_token = credentials.get("api_token")
            workspace_id = credentials.get("workspace_id")
        else:
            base_url = taskify_config.base_url
            workspace_id = taskify_config.workspace_id
            
            # Get user-specific token if user_id provided
            if user_id:
                user_mapping = session.exec(
                    select(TaskifyUserMapping).where(
                        TaskifyUserMapping.user_id == user_id,
                        TaskifyUserMapping.tenant_id == tenant_id,
                        TaskifyUserMapping.is_active == True,  # noqa: E712
                    )
                ).first()
                
                if user_mapping:
                    # In production, fetch the actual Sanctum token for this user
                    # For now, use the master API token
                    api_token = taskify_config.api_token
                else:
                    api_token = taskify_config.api_token
            else:
                api_token = taskify_config.api_token

        if not api_token:
            return None

        return TaskifyClient(
            base_url=base_url,
            api_token=api_token,
            workspace_id=workspace_id,
        )

    # Add other modules here (CRM, HRM, POS, Booking, Landing)
    # elif module_code == ModuleCode.CRM:
    #     return CrmClient(...)
    # elif module_code == ModuleCode.HRM:
    #     return HrmClient(...)

    return None




