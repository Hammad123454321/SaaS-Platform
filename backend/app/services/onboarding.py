"""
Onboarding service for handling module subscriptions and provisioning.

Flow:
1. User selects modules during onboarding
2. Stripe checkout session created
3. After successful payment (webhook), provision modules
4. For Tasks module: Initialize default statuses and priorities
"""
import logging
from datetime import datetime, timedelta
from typing import Optional, List, Dict, Any

from app.models import (
    User,
    Tenant,
    ModuleCode,
    ModuleEntitlement,
    Subscription,
    TaskStatus,
    TaskPriority,
)
from app.models.tasks import TaskStatusCategory

logger = logging.getLogger(__name__)


# Module pricing (in cents) - should come from config/database in production
MODULE_PRICING = {
    ModuleCode.TASKS: {"monthly": 2999, "yearly": 29990, "name": "Task Management"},
    ModuleCode.CRM: {"monthly": 4999, "yearly": 49990, "name": "CRM"},
    ModuleCode.HRM: {"monthly": 3999, "yearly": 39990, "name": "HR Management"},
    ModuleCode.POS: {"monthly": 5999, "yearly": 59990, "name": "Point of Sale"},
    ModuleCode.BOOKING: {"monthly": 2499, "yearly": 24990, "name": "Booking System"},
    ModuleCode.LANDING: {"monthly": 999, "yearly": 9990, "name": "Landing Pages"},
    ModuleCode.AI: {"monthly": 1999, "yearly": 19990, "name": "AI Assistant"},
}


async def get_available_modules() -> List[Dict[str, Any]]:
    """Get list of available modules with pricing."""
    modules = []
    for code, pricing in MODULE_PRICING.items():
        modules.append({
            "code": code.value,
            "name": pricing["name"],
            "monthly_price": pricing["monthly"] / 100,  # Convert to dollars
            "yearly_price": pricing["yearly"] / 100,
            "description": f"{pricing['name']} module for your business",
        })
    return modules


async def create_checkout_session(
    tenant_id: str,
    user_id: str,
    selected_modules: List[str],
    billing_interval: str = "month",  # month or year
) -> Dict[str, Any]:
    """
    Create a Stripe checkout session for selected modules.
    
    Returns checkout URL for frontend redirect.
    """
    # Validate modules
    valid_modules = []
    total_amount = 0
    
    for module_code in selected_modules:
        try:
            code = ModuleCode(module_code)
            if code in MODULE_PRICING:
                pricing = MODULE_PRICING[code]
                price_key = "monthly" if billing_interval == "month" else "yearly"
                total_amount += pricing[price_key]
                valid_modules.append({
                    "code": code,
                    "name": pricing["name"],
                    "amount": pricing[price_key],
                })
        except ValueError:
            logger.warning(f"Invalid module code: {module_code}")
            continue
    
    if not valid_modules:
        raise ValueError("No valid modules selected")
    
    # Get tenant
    tenant = await Tenant.get(tenant_id)
    if not tenant:
        raise ValueError("Tenant not found")
    
    # Get or create subscription
    subscription = await Subscription.find_one(Subscription.tenant_id == tenant_id)
    
    if not subscription:
        subscription = Subscription(
            tenant_id=tenant_id,
            status="pending",
            amount=total_amount,
            interval=billing_interval,
            plan_name=", ".join([m["name"] for m in valid_modules]),
            modules={m["code"].value: True for m in valid_modules},
        )
        await subscription.insert()
    else:
        # Update existing subscription
        subscription.amount = total_amount
        subscription.interval = billing_interval
        subscription.plan_name = ", ".join([m["name"] for m in valid_modules])
        subscription.modules = {m["code"].value: True for m in valid_modules}
        subscription.updated_at = datetime.utcnow()
        await subscription.save()
    
    # In production, create actual Stripe checkout session here
    return {
        "subscription_id": str(subscription.id),
        "checkout_url": f"/onboarding/payment?subscription_id={subscription.id}",
        "total_amount": total_amount / 100,
        "currency": "usd",
        "modules": [{"code": m["code"].value, "name": m["name"]} for m in valid_modules],
        "billing_interval": billing_interval,
    }


async def complete_subscription(
    subscription_id: str,
    stripe_data: Optional[Dict[str, Any]] = None,
) -> Dict[str, Any]:
    """
    Complete subscription after successful payment.
    
    This is called either:
    1. By Stripe webhook after payment
    2. Manually for testing/development
    
    Provisions all selected modules.
    """
    subscription = await Subscription.get(subscription_id)
    if not subscription:
        raise ValueError("Subscription not found")
    
    # Update subscription status
    subscription.status = "active"
    subscription.current_period_start = datetime.utcnow()
    if subscription.interval == "month":
        subscription.current_period_end = datetime.utcnow() + timedelta(days=30)
    else:
        subscription.current_period_end = datetime.utcnow() + timedelta(days=365)
    
    if stripe_data:
        subscription.stripe_customer_id = stripe_data.get("customer_id")
        subscription.stripe_subscription_id = stripe_data.get("subscription_id")
        subscription.stripe_price_id = stripe_data.get("price_id")
    
    subscription.updated_at = datetime.utcnow()
    await subscription.save()
    
    # Enable modules
    modules = subscription.modules or {}
    provisioned_modules = []
    
    for module_code_str, enabled in modules.items():
        if not enabled:
            continue
            
        try:
            module_code = ModuleCode(module_code_str)
        except ValueError:
            continue
        
        # Enable module entitlement
        entitlement = await ModuleEntitlement.find_one(
            ModuleEntitlement.tenant_id == subscription.tenant_id,
            ModuleEntitlement.module_code == module_code,
        )
        
        if entitlement:
            entitlement.enabled = True
            entitlement.seats = 10  # Default seats
            entitlement.updated_at = datetime.utcnow()
            await entitlement.save()
        
        # Provision module-specific resources
        if module_code == ModuleCode.TASKS:
            await initialize_tasks_module(subscription.tenant_id)
        
        provisioned_modules.append(module_code_str)
    
    return {
        "subscription_id": str(subscription.id),
        "status": "active",
        "provisioned_modules": provisioned_modules,
    }


async def initialize_tasks_module(tenant_id: str) -> None:
    """
    Initialize the Tasks module for a tenant.
    
    Creates default task statuses and priorities.
    """
    # Check if already initialized
    existing_status = await TaskStatus.find_one(TaskStatus.tenant_id == tenant_id)
    
    if existing_status:
        logger.info(f"Tasks module already initialized for tenant {tenant_id}")
        return
    
    # Create default statuses
    default_statuses = [
        {"name": "To Do", "color": "#6b7280", "category": TaskStatusCategory.TODO, "is_default": True, "display_order": 1},
        {"name": "In Progress", "color": "#3b82f6", "category": TaskStatusCategory.IN_PROGRESS, "is_default": False, "display_order": 2},
        {"name": "Done", "color": "#10b981", "category": TaskStatusCategory.DONE, "is_default": False, "display_order": 3},
        {"name": "Cancelled", "color": "#ef4444", "category": TaskStatusCategory.CANCELLED, "is_default": False, "display_order": 4},
    ]
    
    for status_data in default_statuses:
        status = TaskStatus(
            tenant_id=tenant_id,
            **status_data
        )
        await status.insert()
    
    # Create default priorities
    default_priorities = [
        {"name": "Low", "color": "#6b7280", "level": 0, "is_default": True, "display_order": 1},
        {"name": "Medium", "color": "#f59e0b", "level": 1, "is_default": False, "display_order": 2},
        {"name": "High", "color": "#ef4444", "level": 2, "is_default": False, "display_order": 3},
        {"name": "Urgent", "color": "#dc2626", "level": 3, "is_default": False, "display_order": 4},
    ]
    
    for priority_data in default_priorities:
        priority = TaskPriority(
            tenant_id=tenant_id,
            **priority_data
        )
        await priority.insert()
    
    logger.info(f"Initialized Tasks module for tenant {tenant_id}")
