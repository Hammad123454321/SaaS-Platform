"""Factory for creating vendor clients based on module type and credentials."""
from typing import Optional

from sqlmodel import Session, select

from app.models import ModuleCode, VendorCredential
from app.services.vendor_clients.base import BaseVendorClient
from app.services.vendor_clients.taskify_client import TaskifyClient


async def create_vendor_client(
    module_code: ModuleCode,
    tenant_id: int,
    session: Session,
) -> Optional[BaseVendorClient]:
    """
    Create a vendor client instance for a given module and tenant.

    Args:
        module_code: The module to create a client for
        tenant_id: Tenant ID to get credentials for
        session: Database session

    Returns:
        Vendor client instance or None if credentials not found
    """
    # Fetch vendor credentials from database
    stmt = select(VendorCredential).where(
        VendorCredential.tenant_id == tenant_id,
        VendorCredential.vendor == module_code.value,
    )
    creds = session.exec(stmt).first()

    if not creds:
        return None

    credentials = creds.credentials

    # Factory pattern: create client based on module type
    if module_code == ModuleCode.TASKS:
        base_url = credentials.get("base_url", "http://taskify:8001")
        api_token = credentials.get("api_token")
        workspace_id = credentials.get("workspace_id")  # Maps FastAPI tenant_id -> Taskify workspace_id

        if not api_token:
            raise ValueError(f"Missing api_token for {module_code.value}")

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



