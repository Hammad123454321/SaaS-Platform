from fastapi import APIRouter, Depends, HTTPException, status

from app.api.deps import get_current_user
from app.models import User, Tenant, ModuleEntitlement, Subscription

router = APIRouter(prefix="/company", tags=["company"])


@router.get("/stats")
async def get_company_stats(
    current_user: User = Depends(get_current_user),
) -> dict:
    """Get company-specific statistics."""
    tenant_id = str(current_user.tenant_id)
    tenant = await Tenant.get(current_user.tenant_id)
    if not tenant:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Tenant not found.")
    
    total_users = await User.find(
        User.tenant_id == tenant_id,
        User.is_active == True
    ).count()
    
    enabled_modules = await ModuleEntitlement.find(
        ModuleEntitlement.tenant_id == tenant_id,
        ModuleEntitlement.enabled == True
    ).count()
    
    subscription = await Subscription.find_one(
        Subscription.tenant_id == tenant_id
    )
    
    subscription_status = subscription.status if subscription else "inactive"
    
    return {
        "company_name": tenant.name,
        "total_users": total_users or 0,
        "enabled_modules": enabled_modules or 0,
        "subscription_status": subscription_status,
    }
