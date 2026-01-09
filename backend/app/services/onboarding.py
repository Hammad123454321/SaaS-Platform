"""
Onboarding service for handling module subscriptions and provisioning.

Flow:
1. User selects modules during onboarding
2. Stripe checkout session created
3. After successful payment (webhook), provision modules
4. For Tasks module: Create Taskify workspace and admin user
"""
import logging
from datetime import datetime
from typing import Optional, List, Dict, Any

from sqlmodel import Session, select

from app.models import (
    User,
    Tenant,
    ModuleCode,
    ModuleEntitlement,
    Subscription,
    TenantTaskifyConfig,
    TaskifyUserMapping,
)
from app.services.vendor_clients.factory import create_vendor_client
from app.services.vendor_clients.taskify_client import TaskifyClient

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
    session: Session,
    tenant_id: int,
    user_id: int,
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
    tenant = session.get(Tenant, tenant_id)
    if not tenant:
        raise ValueError("Tenant not found")
    
    # Get or create subscription
    subscription = session.exec(
        select(Subscription).where(Subscription.tenant_id == tenant_id)
    ).first()
    
    if not subscription:
        subscription = Subscription(
            tenant_id=tenant_id,
            status="pending",
            amount=total_amount,
            interval=billing_interval,
            plan_name=", ".join([m["name"] for m in valid_modules]),
            modules={m["code"].value: True for m in valid_modules},
        )
        session.add(subscription)
        session.commit()
        session.refresh(subscription)
    else:
        # Update existing subscription
        subscription.amount = total_amount
        subscription.interval = billing_interval
        subscription.plan_name = ", ".join([m["name"] for m in valid_modules])
        subscription.modules = {m["code"].value: True for m in valid_modules}
        subscription.updated_at = datetime.utcnow()
        session.add(subscription)
        session.commit()
    
    # In production, create actual Stripe checkout session here
    # For now, return a mock response that can be used for testing
    # 
    # Example Stripe integration:
    # import stripe
    # stripe.api_key = settings.stripe_secret_key
    # checkout_session = stripe.checkout.Session.create(
    #     customer_email=user.email,
    #     line_items=[...],
    #     mode='subscription',
    #     success_url=f"{settings.frontend_base_url}/onboarding/success?session_id={{CHECKOUT_SESSION_ID}}",
    #     cancel_url=f"{settings.frontend_base_url}/onboarding/cancel",
    # )
    
    return {
        "subscription_id": subscription.id,
        "checkout_url": f"/onboarding/payment?subscription_id={subscription.id}",
        "total_amount": total_amount / 100,
        "currency": "usd",
        "modules": [{"code": m["code"].value, "name": m["name"]} for m in valid_modules],
        "billing_interval": billing_interval,
        # In production: "stripe_session_id": checkout_session.id
    }


async def complete_subscription(
    session: Session,
    subscription_id: int,
    stripe_data: Optional[Dict[str, Any]] = None,
) -> Dict[str, Any]:
    """
    Complete subscription after successful payment.
    
    This is called either:
    1. By Stripe webhook after payment
    2. Manually for testing/development
    
    Provisions all selected modules including Taskify workspace.
    """
    subscription = session.get(Subscription, subscription_id)
    if not subscription:
        raise ValueError("Subscription not found")
    
    # Update subscription status
    subscription.status = "active"
    subscription.current_period_start = datetime.utcnow()
    if subscription.interval == "month":
        from datetime import timedelta
        subscription.current_period_end = datetime.utcnow() + timedelta(days=30)
    else:
        from datetime import timedelta
        subscription.current_period_end = datetime.utcnow() + timedelta(days=365)
    
    if stripe_data:
        subscription.stripe_customer_id = stripe_data.get("customer_id")
        subscription.stripe_subscription_id = stripe_data.get("subscription_id")
        subscription.stripe_price_id = stripe_data.get("price_id")
    
    subscription.updated_at = datetime.utcnow()
    session.add(subscription)
    
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
        entitlement = session.exec(
            select(ModuleEntitlement).where(
                ModuleEntitlement.tenant_id == subscription.tenant_id,
                ModuleEntitlement.module_code == module_code,
            )
        ).first()
        
        if entitlement:
            entitlement.enabled = True
            entitlement.seats = 10  # Default seats
            entitlement.updated_at = datetime.utcnow()
            session.add(entitlement)
        
        # Provision module-specific resources
        if module_code == ModuleCode.TASKS:
            await provision_taskify_workspace(session, subscription.tenant_id)
        
        provisioned_modules.append(module_code_str)
    
    session.commit()
    
    return {
        "subscription_id": subscription.id,
        "status": "active",
        "provisioned_modules": provisioned_modules,
    }


async def provision_taskify_workspace(session: Session, tenant_id: int) -> TenantTaskifyConfig:
    """
    Provision a new Taskify workspace for a tenant.
    
    This automatically:
    1. Creates a user in Taskify using the tenant owner's email
    2. Authenticates to get a Sanctum token
    3. Stores the configuration in our database
    
    The user is created via Taskify's signup endpoint, then authenticated to get token.
    """
    # Check if already provisioned
    existing = session.exec(
        select(TenantTaskifyConfig).where(TenantTaskifyConfig.tenant_id == tenant_id)
    ).first()
    
    if existing and existing.is_active:
        logger.info(f"Taskify workspace already exists for tenant {tenant_id}")
        return existing
    
    # Get tenant owner (first user, typically Business Owner)
    tenant = session.get(Tenant, tenant_id)
    owner = session.exec(
        select(User).where(User.tenant_id == tenant_id).order_by(User.id)
    ).first()
    
    if not owner:
        raise ValueError(f"No owner found for tenant {tenant_id}")
    
    from app.config import settings
    
    taskify_base_url = getattr(settings, "taskify_base_url", "http://taskify:8001")
    taskify_master_token = getattr(settings, "taskify_master_token", None)
    
    # Generate workspace details
    workspace_id = tenant_id  # Use tenant_id as workspace_id for simplicity
    workspace_name = f"{tenant.name} Workspace"
    
    # Generate a secure password for the Taskify user
    import secrets
    import string
    password_chars = string.ascii_letters + string.digits + "!@#$%^&*"
    taskify_password = ''.join(secrets.choice(password_chars) for _ in range(16))
    
    # Extract name from email (User model doesn't have first_name/last_name)
    email_local = owner.email.split("@")[0]
    name_parts = email_local.split(".", 1)
    first_name = name_parts[0].capitalize() if name_parts else "Admin"
    last_name = name_parts[1].capitalize() if len(name_parts) > 1 else "User"
    
    api_token = None
    taskify_user_id = None
    
    try:
        # Step 1: Create user in Taskify using signup endpoint
        import httpx
        async with httpx.AsyncClient(
            base_url=taskify_base_url,
            timeout=30.0,
            headers={
                "Accept": "application/json",
                "Content-Type": "application/json",
            },
        ) as client:
            # Create user via signup endpoint
            signup_payload = {
                "type": "member",  # Create as team member
                "first_name": first_name,
                "last_name": last_name,
                "email": owner.email,
                "password": taskify_password,
                "password_confirmation": taskify_password,
                "isApi": True,  # Skip email verification
            }
            
            try:
                signup_response = await client.post("/api/users/signup", json=signup_payload)
                
                # Log the full response for debugging
                logger.debug(f"Taskify signup response status: {signup_response.status_code}")
                logger.debug(f"Taskify signup response body: {signup_response.text}")
                
                signup_response.raise_for_status()
                signup_data = signup_response.json()
                
                if signup_data.get("error"):
                    error_msg = signup_data.get("message", "Unknown error")
                    error_details = signup_data.get("errors", {})
                    full_error = f"{error_msg}"
                    if error_details:
                        full_error += f" | Errors: {error_details}"
                    raise ValueError(f"Taskify signup failed: {full_error}")
                
                # Extract user ID from response
                user_data = signup_data.get("data", {})
                taskify_user_id = user_data.get("id")
                
                logger.info(f"Created Taskify user {taskify_user_id} for tenant {tenant_id}")
                
            except httpx.HTTPStatusError as e:
                # Get detailed error information
                error_text = e.response.text
                error_data = {}
                try:
                    error_data = e.response.json()
                except:
                    pass
                
                # Log full error details
                logger.error(f"Taskify signup HTTP error {e.response.status_code}: {error_text}")
                logger.error(f"Error data: {error_data}")
                
                # Check if user already exists
                error_str = str(error_data)
                if "already been taken" in error_str or "already exists" in error_str.lower():
                    logger.info(f"User {owner.email} already exists in Taskify, will authenticate instead")
                    # User exists, we'll authenticate to get token
                elif e.response.status_code == 500:
                    # Server error - might be due to missing primary workspace
                    error_msg = error_data.get("message", "Server Error")
                    
                    # Try using authenticated endpoint if master token is available
                    if taskify_master_token:
                        logger.info("Signup failed, trying authenticated user creation endpoint instead...")
                        try:
                            # Use authenticated endpoint to create user
                            create_payload = {
                                "first_name": first_name,
                                "last_name": last_name,
                                "email": owner.email,
                                "password": taskify_password,
                                "password_confirmation": taskify_password,
                                "role": 1,  # Default role ID (adjust if needed)
                                "status": 1,  # Active
                                "require_ev": 0,  # Skip email verification
                                "isApi": True,
                            }
                            
                            async with httpx.AsyncClient(
                                base_url=taskify_base_url,
                                timeout=30.0,
                                headers={
                                    "Authorization": f"Bearer {taskify_master_token}",
                                    "Accept": "application/json",
                                    "Content-Type": "application/json",
                                    "workspace_id": str(workspace_id),
                                },
                            ) as auth_client:
                                create_response = await auth_client.post("/api/users/store", json=create_payload)
                                
                                logger.debug(f"Authenticated create response: {create_response.status_code} - {create_response.text}")
                                create_response.raise_for_status()
                                create_data = create_response.json()
                                
                                if create_data.get("error"):
                                    raise ValueError(f"Taskify user creation failed: {create_data.get('message', 'Unknown error')}")
                                
                                user_data = create_data.get("data", {})
                                taskify_user_id = create_data.get("id") or user_data.get("id")
                                logger.info(f"Created Taskify user {taskify_user_id} via authenticated endpoint")
                            
                        except Exception as create_err:
                            logger.error(f"Authenticated user creation also failed: {create_err}")
                            import traceback
                            logger.error(traceback.format_exc())
                            raise ValueError(
                                f"Taskify server error: {error_msg}. "
                                f"Both signup and authenticated user creation failed. "
                                f"Please ensure Taskify has a primary workspace configured and the master token is valid. "
                                f"Error: {str(create_err)}"
                            )
                    else:
                        raise ValueError(
                            f"Taskify server error: {error_msg}. "
                            f"This might be because no primary workspace is set up in Taskify. "
                            f"Please ensure Taskify has a primary workspace configured, or provide a master token in settings."
                        )
                else:
                    # Other errors
                    error_msg = error_data.get("message", error_text)
                    error_details = error_data.get("errors", {})
                    full_error = f"HTTP {e.response.status_code}: {error_msg}"
                    if error_details:
                        full_error += f" | Validation errors: {error_details}"
                    raise ValueError(f"Failed to create user in Taskify: {full_error}")
            
            # Step 2: Authenticate to get Sanctum token
            auth_payload = {
                "email": owner.email,
                "password": taskify_password,
                "isApi": True,
            }
            
            auth_response = await client.post("/api/users/login", json=auth_payload)
            auth_response.raise_for_status()
            auth_data = auth_response.json()
            
            if auth_data.get("error"):
                raise ValueError(f"Taskify authentication failed: {auth_data.get('message', 'Unknown error')}")
            
            # Extract Sanctum token
            token_data = auth_data.get("data", {})
            api_token = auth_data.get("token") or token_data.get("token")
            
            if not api_token:
                raise ValueError("No token received from Taskify authentication")
            
            logger.info(f"Successfully authenticated and obtained Sanctum token for tenant {tenant_id}")
            
            # If we didn't get user_id from signup, try to get it from auth response
            if not taskify_user_id:
                taskify_user_id = token_data.get("user_id")
    
    except Exception as e:
        logger.error(f"Failed to provision Taskify workspace for tenant {tenant_id}: {e}")
        import traceback
        logger.error(traceback.format_exc())
        raise ValueError(f"Taskify provisioning failed: {str(e)}")
    
    # Step 3: Store configuration
    config = TenantTaskifyConfig(
        tenant_id=tenant_id,
        workspace_id=workspace_id,
        workspace_name=workspace_name,
        api_token=api_token,
        base_url=taskify_base_url,
        taskify_admin_email=owner.email,
        taskify_admin_user_id=taskify_user_id,
        is_active=True,
        provisioned_at=datetime.utcnow(),
    )
    
    session.add(config)
    session.commit()
    session.refresh(config)
    
    # Step 4: Create user mapping for tenant owner
    if taskify_user_id:
        await create_taskify_user_mapping(
            session=session,
            user=owner,
            taskify_config=config,
            role="admin",
            taskify_user_id=taskify_user_id,
        )
    
    logger.info(f"Successfully provisioned Taskify workspace {workspace_id} for tenant {tenant_id}")
    
    return config


async def create_taskify_user_mapping(
    session: Session,
    user: User,
    taskify_config: TenantTaskifyConfig,
    role: str = "member",
    taskify_user_id: Optional[int] = None,
) -> TaskifyUserMapping:
    """
    Create a Taskify user mapping for a SaaS platform user.
    
    Called when:
    1. Business Owner subscribes to Tasks (admin role)
    2. Staff is created by Business Owner (member role)
    
    Args:
        session: Database session
        user: SaaS platform user
        taskify_config: Tenant Taskify configuration
        role: Role in Taskify (admin, member, etc.)
        taskify_user_id: Taskify user ID (if already created)
    """
    # Check if mapping already exists
    existing = session.exec(
        select(TaskifyUserMapping).where(
            TaskifyUserMapping.user_id == user.id,
            TaskifyUserMapping.tenant_id == user.tenant_id,
        )
    ).first()
    
    if existing:
        return existing
    
    # Use provided taskify_user_id or get from config
    if not taskify_user_id:
        taskify_user_id = taskify_config.taskify_admin_user_id
    
    mapping = TaskifyUserMapping(
        user_id=user.id,
        tenant_id=user.tenant_id,
        taskify_user_id=taskify_user_id or user.id,  # Fallback to user.id if not provided
        taskify_email=user.email,
        taskify_workspace_id=taskify_config.workspace_id,
        taskify_role=role,
        is_active=True,
    )
    
    session.add(mapping)
    session.commit()
    session.refresh(mapping)
    
    logger.info(f"Created Taskify user mapping for user {user.id} in workspace {taskify_config.workspace_id}")
    
    return mapping


async def provision_staff_to_taskify(session: Session, user: User, password: str) -> Optional[TaskifyUserMapping]:
    """
    Provision a newly created staff member to Taskify.
    
    Called automatically when Business Owner creates staff.
    """
    # Check if tenant has Tasks module enabled
    entitlement = session.exec(
        select(ModuleEntitlement).where(
            ModuleEntitlement.tenant_id == user.tenant_id,
            ModuleEntitlement.module_code == ModuleCode.TASKS,
            ModuleEntitlement.enabled == True,  # noqa: E712
        )
    ).first()
    
    if not entitlement:
        logger.debug(f"Tasks module not enabled for tenant {user.tenant_id}, skipping Taskify provisioning")
        return None
    
    # Get Taskify config for tenant
    taskify_config = session.exec(
        select(TenantTaskifyConfig).where(
            TenantTaskifyConfig.tenant_id == user.tenant_id,
            TenantTaskifyConfig.is_active == True,  # noqa: E712
        )
    ).first()
    
    if not taskify_config:
        logger.warning(f"No Taskify config found for tenant {user.tenant_id}")
        return None
    
    # Create user in Taskify
    try:
        client = TaskifyClient(
            base_url=taskify_config.base_url,
            api_token=taskify_config.api_token,
            workspace_id=taskify_config.workspace_id,
        )
        
        # Extract name from email or use defaults
        email_parts = user.email.split("@")[0].split(".", 1)
        first_name = email_parts[0].capitalize() if email_parts else "User"
        last_name = email_parts[1].capitalize() if len(email_parts) > 1 else "Staff"
        
        # Create user in Taskify
        taskify_user = await client.create_user(
            first_name=first_name,
            last_name=last_name,
            email=user.email,
            password=password,
            is_team_member=True,
        )
        
        await client.close()
        
        # Create mapping
        mapping = TaskifyUserMapping(
            user_id=user.id,
            tenant_id=user.tenant_id,
            taskify_user_id=taskify_user.get("id", user.id),
            taskify_email=user.email,
            taskify_workspace_id=taskify_config.workspace_id,
            taskify_role="member",
            is_active=True,
        )
        
        session.add(mapping)
        session.commit()
        session.refresh(mapping)
        
        logger.info(f"Staff user {user.id} provisioned to Taskify workspace {taskify_config.workspace_id}")
        return mapping
        
    except Exception as e:
        logger.error(f"Failed to provision staff to Taskify: {e}")
        return None

