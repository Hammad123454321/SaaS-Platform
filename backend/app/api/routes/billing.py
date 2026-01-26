from datetime import datetime
from typing import Any, Optional

import stripe
from fastapi import APIRouter, Depends, Header, HTTPException, Request, status
from sqlmodel import Session, select

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


def _resolve_tenant_id(
    session: Session, customer_id: Optional[str], payload_object: dict[str, Any]
) -> Optional[int]:
    if payload_object.get("metadata", {}).get("tenant_id"):
        try:
            return int(payload_object["metadata"]["tenant_id"])
        except ValueError:
            return None
    if customer_id:
        stmt = select(Subscription).where(Subscription.stripe_customer_id == customer_id)
        subscription = session.exec(stmt).first()
        if subscription:
            return subscription.tenant_id
    return None


def _apply_plan_entitlements(
    session: Session, tenant_id: int, modules: list[ModuleCode], seats: int | None, ai: bool | None
) -> None:
    for module in modules:
        stmt = select(ModuleEntitlement).where(
            ModuleEntitlement.tenant_id == tenant_id,
            ModuleEntitlement.module_code == module,
        )
        ent = session.exec(stmt).first()
        if not ent:
            ent = ModuleEntitlement(tenant_id=tenant_id, module_code=module)
            session.add(ent)
        ent.enabled = True
        if seats is not None:
            ent.seats = seats
        if ai is not None:
            ent.ai_access = ai
        session.add(ent)


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

    # Idempotency guard
    if session.exec(select(WebhookEvent).where(WebhookEvent.event_id == event_id)).first():
        return {"status": "ok"}

    obj = event.get("data", {}).get("object", {}) or {}
    customer_id = obj.get("customer")
    tenant_id = _resolve_tenant_id(session, customer_id, obj)
    if tenant_id is None:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Tenant not resolved.")

    subscription = session.exec(
        select(Subscription).where(Subscription.tenant_id == tenant_id)
    ).first()
    if not subscription:
        subscription = Subscription(tenant_id=tenant_id)
        session.add(subscription)

    # Map plan → modules (placeholder logic)
    modules: list[ModuleCode] = []
    seats: int | None = None
    ai_access: bool | None = None
    price_id = None
    if obj.get("items") and isinstance(obj["items"], dict):
        price_id = obj["items"].get("data", [{}])[0].get("price", {}).get("id")
    if obj.get("metadata", {}).get("modules"):
        modules = [ModuleCode(m.strip()) for m in obj["metadata"]["modules"].split(",") if m.strip()]
    elif price_id:
        # simple placeholder mapping based on price id prefix
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

    # Update subscription record
    subscription.stripe_customer_id = customer_id or subscription.stripe_customer_id
    subscription.stripe_subscription_id = obj.get("subscription") or subscription.stripe_subscription_id
    subscription.status = event.get("type", "unknown")
    if obj.get("current_period_end"):
        subscription.current_period_end = datetime.fromtimestamp(obj["current_period_end"])
    if obj.get("plan", {}).get("nickname"):
        subscription.plan_name = obj["plan"]["nickname"]
    session.add(subscription)

    if modules:
        _apply_plan_entitlements(session, tenant_id, modules, seats, ai_access)

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
        raw=event.to_dict() if hasattr(event, "to_dict") else event,  # type: ignore[arg-type]
    )
    session.add(history)
    session.add(WebhookEvent(event_id=event_id))

    session.commit()
    return {"status": "ok"}


@router.get("/history", response_model=list[BillingHistoryRead])
def list_billing_history(
    current_user: User = Depends(require_permission(PermissionCode.VIEW_BILLING)),
) -> list[BillingHistoryRead]:
    stmt = (
        select(BillingHistory)
        .where(BillingHistory.tenant_id == current_user.tenant_id)
        .order_by(BillingHistory.created_at.desc())
        .limit(50)
    )
    history = session.exec(stmt).all()
    log_audit(
        session,
        tenant_id=current_user.tenant_id,
        actor_user_id=current_user.id,  # type: ignore[arg-type]
        action="billing.history.view",
        target=str(current_user.tenant_id),
    )
    return [BillingHistoryRead.model_validate(h) for h in history]

