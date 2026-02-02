from __future__ import annotations

from datetime import datetime, date, timedelta
from typing import List, Optional

from fastapi import HTTPException, status

from app.models.pos import (
    SubscriptionPlan,
    CustomerSubscription,
    SubscriptionInvoice,
    SubscriptionStatus,
    Sale,
    SaleItem,
    Payment,
    Receipt,
    SaleStatus,
    PaymentMethod,
    SalesChannel,
)


def _add_interval(start: date, interval: str, count: int) -> date:
    if interval == "year":
        return date(start.year + count, start.month, start.day)
    if interval == "week":
        return start + timedelta(weeks=count)
    # default month
    month = start.month - 1 + count
    year = start.year + month // 12
    month = month % 12 + 1
    day = min(start.day, 28)
    return date(year, month, day)


async def create_subscription_plan(tenant_id: str, payload) -> SubscriptionPlan:
    plan = SubscriptionPlan(
        tenant_id=tenant_id,
        name=payload.name,
        description=payload.description,
        price_cents=payload.price_cents,
        interval=payload.interval,
        interval_count=payload.interval_count,
        is_active=payload.is_active,
    )
    await plan.insert()
    return plan


async def list_subscription_plans(tenant_id: str) -> List[SubscriptionPlan]:
    return await SubscriptionPlan.find(SubscriptionPlan.tenant_id == tenant_id).sort(SubscriptionPlan.name).to_list()


async def create_customer_subscription(tenant_id: str, payload) -> CustomerSubscription:
    plan = await SubscriptionPlan.get(payload.plan_id)
    if not plan or plan.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Subscription plan not found")

    start_date = payload.start_date or date.today()
    next_billing_date = payload.next_billing_date or _add_interval(start_date, plan.interval, plan.interval_count)

    subscription = CustomerSubscription(
        tenant_id=tenant_id,
        customer_id=payload.customer_id,
        plan_id=payload.plan_id,
        status=payload.status or SubscriptionStatus.ACTIVE,
        start_date=start_date,
        next_billing_date=next_billing_date,
    )
    await subscription.insert()

    invoice = SubscriptionInvoice(
        tenant_id=tenant_id,
        subscription_id=str(subscription.id),
        amount_cents=plan.price_cents,
        due_date=next_billing_date,
        status="due",
    )
    await invoice.insert()
    return subscription


async def list_customer_subscriptions(tenant_id: str) -> List[CustomerSubscription]:
    return await CustomerSubscription.find(CustomerSubscription.tenant_id == tenant_id).to_list()


async def list_subscription_invoices(tenant_id: str) -> List[SubscriptionInvoice]:
    return await SubscriptionInvoice.find(SubscriptionInvoice.tenant_id == tenant_id).sort(-SubscriptionInvoice.due_date).to_list()


async def pay_subscription_invoice(
    tenant_id: str,
    invoice_id: str,
    user_id: str,
    payload,
) -> SubscriptionInvoice:
    invoice = await SubscriptionInvoice.get(invoice_id)
    if not invoice or invoice.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Invoice not found")
    if invoice.status == "paid":
        return invoice

    subscription = await CustomerSubscription.get(invoice.subscription_id)
    if not subscription or subscription.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Subscription not found")
    plan = await SubscriptionPlan.get(subscription.plan_id)
    if not plan or plan.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Subscription plan not found")

    sale = Sale(
        tenant_id=tenant_id,
        cashier_id=user_id,
        customer_id=subscription.customer_id,
        channel=SalesChannel.ONLINE,
        status=SaleStatus.COMPLETED,
        subtotal_cents=plan.price_cents,
        discount_cents=0,
        tax_cents=0,
        total_cents=plan.price_cents,
        paid_cents=plan.price_cents,
        items_count=1,
        completed_at=datetime.utcnow(),
        created_at=datetime.utcnow(),
        updated_at=datetime.utcnow(),
    )
    await sale.insert()

    sale_item = SaleItem(
        tenant_id=tenant_id,
        sale_id=str(sale.id),
        product_name=plan.name,
        quantity=1,
        unit_price_cents=plan.price_cents,
        discount_cents=0,
        tax_cents=0,
        line_total_cents=plan.price_cents,
        tax_ids=[],
        sale_completed_at=sale.completed_at,
    )
    await sale_item.insert()

    payment = Payment(
        tenant_id=tenant_id,
        sale_id=str(sale.id),
        method=payload.payment_method or PaymentMethod.OTHER,
        amount_cents=plan.price_cents,
        reference=payload.reference,
        sale_completed_at=sale.completed_at,
    )
    await payment.insert()

    receipt = Receipt(
        tenant_id=tenant_id,
        sale_id=str(sale.id),
        receipt_number=f"SUB-{datetime.utcnow().strftime('%Y%m%d%H%M%S')}-{tenant_id[-4:]}",
        rendered={
            "sale_id": str(sale.id),
            "channel": sale.channel,
            "items": [
                {
                    "product_name": plan.name,
                    "quantity": 1,
                    "unit_price_cents": plan.price_cents,
                    "line_total_cents": plan.price_cents,
                }
            ],
            "payments": [
                {
                    "method": payment.method,
                    "amount_cents": payment.amount_cents,
                    "reference": payment.reference,
                }
            ],
            "totals": {
                "subtotal_cents": plan.price_cents,
                "discount_cents": 0,
                "tax_cents": 0,
                "total_cents": plan.price_cents,
            },
        },
    )
    await receipt.insert()

    invoice.status = "paid"
    invoice.paid_at = datetime.utcnow()
    invoice.sale_id = str(sale.id)
    await invoice.save()

    subscription.last_billed_date = invoice.due_date
    subscription.next_billing_date = _add_interval(invoice.due_date, plan.interval, plan.interval_count)
    await subscription.save()

    next_invoice = SubscriptionInvoice(
        tenant_id=tenant_id,
        subscription_id=str(subscription.id),
        amount_cents=plan.price_cents,
        due_date=subscription.next_billing_date,
        status="due",
    )
    await next_invoice.insert()

    return invoice
