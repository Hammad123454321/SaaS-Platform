from datetime import datetime
from typing import Any, Optional

import stripe
from fastapi import APIRouter, Depends, Header, HTTPException, Request, status

from app.api.deps import get_current_user
from app.models.role import PermissionCode
from app.api.authz import require_permission
from app.config import settings
from app.models import (
    BillingHistory,
    Subscription,
    WebhookEvent,
    ModuleEntitlement,
    ModuleCode,
    User,
)
from app.schemas import BillingHistoryRead
from app.services.audit import log_audit

router = APIRouter(prefix="/billing", tags=["billing"])

stripe.api_key = settings.stripe_secret_key


async def _resolve_tenant_id(
    customer_id: Optional[str], payload_object: dict[str, Any]
) -> Optional[str]:
    if payload_object.get("metadata", {}).get("tenant_id"):
        return payload_object["metadata"]["tenant_id"]
    if customer_id:
        subscription = await Subscription.find_one(Subscription.stripe_customer_id == customer_id)
        if subscription:
            return subscription.tenant_id
    return None


async def _apply_plan_entitlements(
    tenant_id: str, modules: list[ModuleCode], seats: int | None, ai: bool | None
) -> None:
    for module in modules:
        ent = await ModuleEntitlement.find_one(
            ModuleEntitlement.tenant_id == tenant_id,
            ModuleEntitlement.module_code == module,
        )
        if not ent:
            ent = ModuleEntitlement(tenant_id=tenant_id, module_code=module)
        ent.enabled = True
        if seats is not None:
            ent.seats = seats
        if ai is not None:
            ent.ai_access = ai
        await ent.save()


@router.post("/webhook")
async def stripe_webhook(
    request: Request,
    stripe_signature: str | None = Header(default=None, convert_underscores=False, alias="Stripe-Signature"),
) -> dict[str, str]:
    payload = await request.body()
    if not stripe_signature:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Missing signature.")
    try:
        event = stripe.Webhook.construct_event(payload, stripe_signature, settings.stripe_webhook_secret)
    except stripe.error.SignatureVerificationError as exc:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Invalid signature.") from exc

    event_id = event.get("id")
    if not event_id:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Missing event id.")

    existing_event = await WebhookEvent.find_one(WebhookEvent.event_id == event_id)
    if existing_event:
        return {"status": "ok"}

    obj = event.get("data", {}).get("object", {}) or {}
    customer_id = obj.get("customer")
    tenant_id = await _resolve_tenant_id(customer_id, obj)
    if tenant_id is None:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Tenant not resolved.")

    subscription = await Subscription.find_one(Subscription.tenant_id == tenant_id)
    if not subscription:
        subscription = Subscription(tenant_id=tenant_id)

    modules: list[ModuleCode] = []
    seats: int | None = None
    ai_access: bool | None = None
    price_id = None
    if obj.get("items") and isinstance(obj["items"], dict):
        price_id = obj["items"].get("data", [{}])[0].get("price", {}).get("id")
    if obj.get("metadata", {}).get("modules"):
        modules = [ModuleCode(m.strip()) for m in obj["metadata"]["modules"].split(",") if m.strip()]
    elif price_id:
        if "crm" in price_id:
            modules.append(ModuleCode.CRM)
        if "hrm" in price_id:
            modules.append(ModuleCode.HRM)
        if "pos" in price_id:
            modules.append(ModuleCode.POS)
        if "tasks" in price_id:
            modules.append(ModuleCode.TASKS)
        if "booking" in price_id:
            modules.append(ModuleCode.BOOKING)
        if "landing" in price_id:
            modules.append(ModuleCode.LANDING)
        if "ai" in price_id:
            modules.append(ModuleCode.AI)

    if obj.get("metadata", {}).get("seats"):
        try:
            seats = int(obj["metadata"]["seats"])
        except ValueError:
            seats = None
    if obj.get("metadata", {}).get("ai_access"):
        ai_access = obj["metadata"]["ai_access"] in ("1", "true", "True", True)

    subscription.stripe_customer_id = customer_id or subscription.stripe_customer_id
    subscription.stripe_subscription_id = obj.get("subscription") or subscription.stripe_subscription_id
    subscription.status = event.get("type", "unknown")
    if obj.get("current_period_end"):
        subscription.current_period_end = datetime.fromtimestamp(obj["current_period_end"])
    if obj.get("plan", {}).get("nickname"):
        subscription.plan_name = obj["plan"]["nickname"]
    await subscription.save()

    if modules:
        await _apply_plan_entitlements(tenant_id, modules, seats, ai_access)

    amount = None
    currency = None
    if obj.get("amount_paid"):
        amount = obj["amount_paid"]
    elif obj.get("amount_due"):
        amount = obj["amount_due"]
    currency = obj.get("currency")

    history = BillingHistory(
        tenant_id=tenant_id,
        event_type=event.get("type", "unknown"),
        amount=amount,
        currency=currency,
        raw=event.to_dict() if hasattr(event, "to_dict") else event,
    )
    await history.insert()
    
    webhook_event = WebhookEvent(event_id=event_id)
    await webhook_event.insert()

    return {"status": "ok"}


@router.get("/history", response_model=list[BillingHistoryRead])
async def list_billing_history(
    current_user: User = Depends(require_permission(PermissionCode.VIEW_BILLING)),
) -> list[BillingHistoryRead]:
    tenant_id = str(current_user.tenant_id)
    history = await BillingHistory.find(
        BillingHistory.tenant_id == tenant_id
    ).sort(-BillingHistory.created_at).limit(50).to_list()
    
    await log_audit(
        tenant_id=tenant_id,
        actor_user_id=str(current_user.id),
        action="billing.history.view",
        target=tenant_id,
    )
    return [BillingHistoryRead.model_validate(h) for h in history]
