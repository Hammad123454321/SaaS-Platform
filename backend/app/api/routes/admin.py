from fastapi import APIRouter, Depends, HTTPException, status

from app.api.deps import get_current_user
from app.models import User, Tenant, ModuleEntitlement, Subscription
from app.api.authz import require_permission
from app.models.role import PermissionCode

router = APIRouter(prefix="/admin", tags=["admin"])


@router.get("/stats")
async def get_admin_stats(
    current_user: User = Depends(get_current_user),
) -> dict:
    """Get platform-wide statistics (Super Admin only)."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super Admin access required.")
    
    total_tenants = await Tenant.find().count()
    total_users = await User.find(User.is_active == True).count()
    
    active_subscriptions = await Subscription.find(Subscription.status == "active").count()
    
    active_subs = await Subscription.find(Subscription.status == "active").to_list()
    from decimal import Decimal
    total_revenue = sum(Decimal(str(s.amount)) for s in active_subs if s.amount)
    total_revenue = float(total_revenue)  # Convert back for JSON serialization
    
    return {
        "total_tenants": total_tenants or 0,
        "total_users": total_users or 0,
        "active_subscriptions": active_subscriptions or 0,
        "total_revenue": total_revenue,
    }


@router.get("/tenants")
async def list_tenants(
    current_user: User = Depends(get_current_user),
    skip: int = 0,
    limit: int = 100,
) -> dict:
    """List all tenants (Super Admin only)."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super Admin access required.")
    
    tenants = await Tenant.find().skip(skip).limit(limit).to_list()
    return {"tenants": [{"id": str(t.id), "name": t.name, "created_at": t.created_at} for t in tenants]}


@router.get("/users")
async def list_all_users(
    current_user: User = Depends(get_current_user),
    skip: int = 0,
    limit: int = 100,
) -> dict:
    """List all users across all tenants (Super Admin only)."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super Admin access required.")
    
    users = await User.find().skip(skip).limit(limit).to_list()
    return {
        "users": [
            {
                "id": str(u.id),
                "email": u.email,
                "tenant_id": u.tenant_id,
                "is_super_admin": u.is_super_admin,
                "is_active": u.is_active,
            }
            for u in users
        ]
    }
