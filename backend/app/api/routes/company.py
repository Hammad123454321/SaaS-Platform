from fastapi import APIRouter, Depends, HTTPException, status
from sqlmodel import Session, select, func
from app.api.deps import get_current_user
from app.models import User, Tenant, ModuleEntitlement, Subscription

router = APIRouter(prefix="/company", tags=["company"])


@router.get("/stats")
async def get_company_stats(
    current_user: User = Depends(get_current_user),
) -> dict:
    """Get company-specific statistics."""
    tenant = session.get(Tenant, current_user.tenant_id)
    if not tenant:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Tenant not found.")
    
    # Count users in tenant
    total_users = session.exec(
        select(func.count(User.id)).where(
            User.tenant_id == current_user.tenant_id,
            User.is_active == True  # noqa: E712
        )
    ).one()
    
    # Count enabled modules
    enabled_modules = session.exec(
        select(func.count(ModuleEntitlement.id)).where(
            ModuleEntitlement.tenant_id == current_user.tenant_id,
            ModuleEntitlement.enabled == True  # noqa: E712
        )
    ).one()
    
    # Get subscription status
    subscription = session.exec(
        select(Subscription).where(Subscription.tenant_id == current_user.tenant_id)
    ).first()
    
    subscription_status = subscription.status if subscription else "inactive"
    
    return {
        "company_name": tenant.name,
        "total_users": total_users or 0,
        "enabled_modules": enabled_modules or 0,
        "subscription_status": subscription_status,
    }

