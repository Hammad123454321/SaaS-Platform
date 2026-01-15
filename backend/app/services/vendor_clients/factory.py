"""Factory for creating vendor clients based on module type and credentials."""
from typing import Optional

from sqlmodel import Session, select

from app.models import ModuleCode, VendorCredential
from app.services.vendor_clients.base import BaseVendorClient


async def create_vendor_client(
    module_code: ModuleCode,
    tenant_id: int,
    session: Session,
    user_id: Optional[int] = None,
) -> Optional[BaseVendorClient]:
    """
    Create a vendor client instance for a given module and tenant.

    Note: TASKS module has been replaced with native implementation
    and uses dedicated routes. This factory is for other modules.

    Args:
        module_code: The module to create a client for
        tenant_id: Tenant ID to get credentials for
        session: Database session
        user_id: Optional user ID (not used for now)

    Returns:
        Vendor client instance or None if credentials not found or invalid
    """
    # Tasks module is now native, skip it
    if module_code == ModuleCode.TASKS:
        return None

    # For other modules, check VendorCredential
    stmt = select(VendorCredential).where(
        VendorCredential.tenant_id == tenant_id,
        VendorCredential.vendor == module_code.value,
    )
    creds = session.exec(stmt).first()
    
    if not creds:
        return None
    
    # Add other modules here (CRM, HRM, POS, Booking, Landing)
    # elif module_code == ModuleCode.CRM:
    #     return CrmClient(...)
    # elif module_code == ModuleCode.HRM:
    #     return HrmClient(...)

    return None




