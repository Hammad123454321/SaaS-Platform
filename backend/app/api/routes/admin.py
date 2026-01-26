from fastapi import APIRouter, Depends, HTTPException, status
from sqlmodel import Session, select, func
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
    
    total_tenants = session.exec(select(func.count(Tenant.id))).one()
    total_users = session.exec(select(func.count(User.id)).where(User.is_active == True)).one()  # noqa: E712
    
    # Count active subscriptions
    active_subscriptions = session.exec(
        select(func.count(Subscription.id)).where(Subscription.status == "active")
    ).one()
    
    # Calculate total revenue (sum of all subscription amounts)
    total_revenue_result = session.exec(
        select(func.sum(Subscription.amount)).where(Subscription.status == "active")
    ).one()
    total_revenue = float(total_revenue_result) if total_revenue_result else 0.0
    
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
    
    tenants = session.exec(select(Tenant).offset(skip).limit(limit)).all()
    return {"tenants": [{"id": t.id, "name": t.name, "created_at": t.created_at} for t in tenants]}


@router.get("/users")
async def list_all_users(
    current_user: User = Depends(get_current_user),
    skip: int = 0,
    limit: int = 100,
) -> dict:
    """List all users across all tenants (Super Admin only)."""
    if not current_user.is_super_admin:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Super Admin access required.")
    
    users = session.exec(select(User).offset(skip).limit(limit)).all()
    return {
        "users": [
            {
                "id": u.id,
                "email": u.email,
                "tenant_id": u.tenant_id,
                "is_super_admin": u.is_super_admin,
                "is_active": u.is_active,
            }
            for u in users
        ]
    }

