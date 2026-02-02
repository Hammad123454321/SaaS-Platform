from datetime import date, datetime
from typing import Optional

from fastapi import APIRouter, Depends, HTTPException, status, Query

from app.api.authz import require_permission
from app.models import User, ModuleEntitlement, ModuleCode
from app.models.pos import (
    Sale,
    SaleItem,
    Payment,
    Receipt,
)
from app.models.role import PermissionCode
from app.schemas.pos import (
    SaleDraftRequest,
    SaleDraftUpdateRequest,
    FinalizeSaleRequest,
    RegisterSessionOpenRequest,
    RegisterSessionCloseRequest,
    CashMovementRequest,
    RefundRequest,
    InventoryAdjustmentRequest,
    CustomerCreateRequest,
    CategoryCreateRequest,
    TaxCreateRequest,
    DiscountCreateRequest,
    BulkProductUpsertRequest,
    ProductCreateRequest,
    VariantCreateRequest,
    LocationCreateRequest,
    RegisterCreateRequest,
    StorefrontSettingsRequest,
    PromotionCampaignCreateRequest,
    CouponCreateRequest,
    LoyaltyProgramRequest,
    LoyaltyAdjustRequest,
    EmployeeProfileRequest,
    TimeClockRequest,
    PayrollSummaryRequest,
    CustomerFeedbackCreateRequest,
    CustomerFeedbackRespondRequest,
    VendorCreateRequest,
    PurchaseOrderCreateRequest,
    PurchaseOrderReceiveRequest,
    StockTransferCreateRequest,
    StockTransferReceiveRequest,
    StockCountCreateRequest,
    StockCountCompleteRequest,
    WorkOrderCreateRequest,
    WorkOrderUpdateRequest,
    AppointmentCreateRequest,
    AppointmentUpdateRequest,
    SubscriptionPlanCreateRequest,
    CustomerSubscriptionCreateRequest,
    SubscriptionInvoicePayRequest,
    FulfillmentInput,
)
from app.services.pos_catalog import (
    create_category,
    create_tax,
    create_discount,
    create_product,
    create_variant,
    create_customer,
    list_customers,
    create_location,
    create_register,
    list_locations,
    list_registers,
    list_categories,
    list_taxes,
    list_discounts,
    search_products,
    bulk_upsert_products,
)
from app.services.pos_sales import create_sale_draft, update_sale_draft, finalize_sale, list_sales
from app.services.pos_registers import (
    open_register_session,
    close_register_session,
    record_cash_movement,
)
from app.services.pos_refunds import create_refund
from app.services.pos_inventory import adjust_inventory
from app.services.pos_analytics import (
    get_sales_kpis,
    get_sales_trends,
    get_top_products,
    get_top_categories,
    get_payment_breakdown,
    get_low_stock,
    get_employee_performance,
    get_inventory_valuation,
    get_loyalty_summary,
    get_feedback_summary,
)
from app.services.pos_storefront import get_storefront_settings, upsert_storefront_settings
from app.services.pos_marketing import create_campaign, list_campaigns, create_coupon, list_coupons
from app.services.pos_loyalty import get_active_loyalty_program, adjust_loyalty_points, get_or_create_loyalty_account
from app.services.pos_staff import upsert_employee_profile, list_employee_profiles, clock_in, clock_out, list_time_entries
from app.services.pos_payroll import get_payroll_summary
from app.services.pos_reputation import create_feedback, list_feedback, respond_feedback
from app.services.pos_supply import create_vendor, list_vendors, create_purchase_order, list_purchase_orders, receive_purchase_order
from app.services.pos_fulfillment import update_fulfillment, list_fulfillment_orders
from app.services.pos_kitchen import list_kitchen_items, update_kitchen_status
from app.services.pos_work_orders import create_work_order, list_work_orders, update_work_order
from app.services.pos_appointments import create_appointment, list_appointments, update_appointment
from app.services.pos_subscriptions import (
    create_subscription_plan,
    list_subscription_plans,
    create_customer_subscription,
    list_customer_subscriptions,
    list_subscription_invoices,
    pay_subscription_invoice,
)
from app.services.pos_inventory import (
    create_stock_transfer,
    receive_stock_transfer,
    create_stock_count,
    complete_stock_count,
    list_stock_transfers,
    list_stock_counts,
)
from app.services.audit import log_audit


router = APIRouter(prefix="/modules/pos", tags=["pos"])


async def _require_pos_entitlement(tenant_id: str) -> None:
    ent = await ModuleEntitlement.find_one(
        ModuleEntitlement.tenant_id == tenant_id,
        ModuleEntitlement.module_code == ModuleCode.POS,
        ModuleEntitlement.enabled == True,
    )
    if not ent:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="POS module not enabled")


def _ensure_owner(current_user: User) -> None:
    if not current_user.is_owner:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Owner access required")


async def _sale_detail(tenant_id: str, sale_id: str) -> dict:
    sale = await Sale.get(sale_id)
    if not sale or sale.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Sale not found")

    items = await SaleItem.find(SaleItem.sale_id == sale_id, SaleItem.tenant_id == tenant_id).to_list()
    payments = await Payment.find(Payment.sale_id == sale_id, Payment.tenant_id == tenant_id).to_list()
    receipt = await Receipt.find_one(Receipt.sale_id == sale_id, Receipt.tenant_id == tenant_id)

    return {
        "sale": sale.model_dump(),
        "items": [item.model_dump() for item in items],
        "payments": [payment.model_dump() for payment in payments],
        "receipt": receipt.model_dump() if receipt else None,
    }


@router.get("/locations")
async def get_locations(
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return [loc.model_dump() for loc in await list_locations(tenant_id)]


@router.post("/locations")
async def create_location_endpoint(
    payload: LocationCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_REGISTERS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    location = await create_location(tenant_id, payload)
    await log_audit(tenant_id, str(current_user.id), "pos.location.create", target=str(location.id))
    return location.model_dump()


@router.get("/registers")
async def get_registers(
    location_id: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    registers = await list_registers(tenant_id, location_id=location_id)
    return [reg.model_dump() for reg in registers]


@router.post("/registers")
async def create_register_endpoint(
    payload: RegisterCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_REGISTERS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    register = await create_register(tenant_id, payload)
    await log_audit(tenant_id, str(current_user.id), "pos.register.create", target=str(register.id))
    return register.model_dump()


@router.post("/registers/{register_id}/open")
async def open_register(
    register_id: str,
    payload: RegisterSessionOpenRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_REGISTERS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    session = await open_register_session(
        tenant_id=tenant_id,
        register_id=register_id,
        opening_cash_cents=payload.opening_cash_cents,
        user_id=str(current_user.id),
        denominations=payload.denominations,
    )
    await log_audit(tenant_id, str(current_user.id), "pos.register.open", target=str(session.id))
    return session.model_dump()


@router.post("/registers/{register_id}/close")
async def close_register(
    register_id: str,
    payload: RegisterSessionCloseRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_REGISTERS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    session = await close_register_session(
        tenant_id=tenant_id,
        register_id=register_id,
        closing_cash_cents=payload.closing_cash_cents,
        user_id=str(current_user.id),
        denominations=payload.denominations,
    )
    await log_audit(tenant_id, str(current_user.id), "pos.register.close", target=str(session.id))
    return session.model_dump()


@router.post("/registers/cash-movements")
async def cash_movement(
    payload: CashMovementRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_REGISTERS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)

    movement = await record_cash_movement(
        tenant_id=tenant_id,
        register_session_id=payload.register_session_id,
        movement_type=payload.movement_type,
        amount_cents=payload.amount_cents,
        reason=payload.reason or "",
        user_id=str(current_user.id),
    )
    await log_audit(tenant_id, str(current_user.id), "pos.register.cash_movement", target=str(movement.id))
    return movement.model_dump()


@router.get("/categories")
async def get_categories(
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return [cat.model_dump() for cat in await list_categories(tenant_id)]


@router.post("/categories")
async def create_category_endpoint(
    payload: CategoryCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_CATALOG)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    category = await create_category(tenant_id, payload)
    return category.model_dump()


@router.get("/taxes")
async def get_taxes(
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return [tax.model_dump() for tax in await list_taxes(tenant_id)]


@router.post("/taxes")
async def create_tax_endpoint(
    payload: TaxCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_CATALOG)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    tax = await create_tax(tenant_id, payload)
    return tax.model_dump()


@router.get("/discounts")
async def get_discounts(
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return [discount.model_dump() for discount in await list_discounts(tenant_id)]


@router.post("/discounts")
async def create_discount_endpoint(
    payload: DiscountCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_CATALOG)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    discount = await create_discount(tenant_id, payload)
    return discount.model_dump()


@router.get("/products")
async def get_products(
    search: Optional[str] = None,
    barcode: Optional[str] = None,
    category_id: Optional[str] = None,
    limit: int = 50,
    offset: int = 0,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return await search_products(
        tenant_id=tenant_id,
        search=search,
        barcode=barcode,
        category_id=category_id,
        limit=limit,
        offset=offset,
    )


@router.post("/products")
async def create_product_endpoint(
    payload: ProductCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_CATALOG)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    product = await create_product(tenant_id, payload)
    return product.model_dump()


@router.post("/products/bulk")
async def bulk_upsert_products_endpoint(
    payload: BulkProductUpsertRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_CATALOG)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    products = await bulk_upsert_products(tenant_id, payload.items)
    await log_audit(
        tenant_id,
        str(current_user.id),
        "pos.products.bulk_upsert",
        details={"count": len(products)},
    )
    return [product.model_dump() for product in products]


@router.post("/variants")
async def create_variant_endpoint(
    payload: VariantCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_CATALOG)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    variant = await create_variant(tenant_id, payload)
    return variant.model_dump()


@router.post("/customers")
async def create_customer_endpoint(
    payload: CustomerCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    customer = await create_customer(tenant_id, payload)
    return customer.model_dump()


@router.get("/customers")
async def get_customers(
    search: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return [c.model_dump() for c in await list_customers(tenant_id, search=search)]


@router.post("/sales")
async def create_sale_endpoint(
    payload: SaleDraftRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_PROCESS_SALES)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    sale = await create_sale_draft(tenant_id, str(current_user.id), payload)
    return sale.model_dump()


@router.patch("/sales/{sale_id}")
async def update_sale_endpoint(
    sale_id: str,
    payload: SaleDraftUpdateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_PROCESS_SALES)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    sale = await update_sale_draft(tenant_id, sale_id, str(current_user.id), payload)
    return sale.model_dump()


@router.post("/sales/{sale_id}/finalize")
async def finalize_sale_endpoint(
    sale_id: str,
    payload: FinalizeSaleRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_PROCESS_SALES)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    sale = await finalize_sale(tenant_id, sale_id, str(current_user.id), payload)
    return sale.model_dump()


@router.get("/sales")
async def list_sales_endpoint(
    start_date: Optional[date] = Query(default=None),
    end_date: Optional[date] = Query(default=None),
    location_id: Optional[str] = None,
    register_id: Optional[str] = None,
    cashier_id: Optional[str] = None,
    status_filter: Optional[str] = Query(default=None, alias="status"),
    limit: int = 50,
    offset: int = 0,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)

    start_dt = None
    end_dt = None
    if start_date:
        start_dt = datetime.combine(start_date, datetime.min.time())
    if end_date:
        end_dt = datetime.combine(end_date, datetime.max.time())

    sales = await list_sales(
        tenant_id=tenant_id,
        start_date=start_dt,
        end_date=end_dt,
        location_id=location_id,
        register_id=register_id,
        cashier_id=cashier_id,
        status=status_filter,
        limit=limit,
        offset=offset,
    )
    return [sale.model_dump() for sale in sales]


@router.get("/sales/{sale_id}")
async def get_sale_endpoint(
    sale_id: str,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return await _sale_detail(tenant_id, sale_id)


@router.get("/receipts/{sale_id}")
async def get_receipt_endpoint(
    sale_id: str,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    receipt = await Receipt.find_one(Receipt.sale_id == sale_id, Receipt.tenant_id == tenant_id)
    if not receipt:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Receipt not found")
    return receipt.model_dump()


@router.post("/refunds")
async def refund_endpoint(
    payload: RefundRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_REFUNDS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    refund = await create_refund(tenant_id, str(current_user.id), payload)
    return refund.model_dump()


@router.post("/inventory/adjustments")
async def inventory_adjustment_endpoint(
    payload: InventoryAdjustmentRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_INVENTORY)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    if not payload.product_id and not payload.variant_id:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="product_id or variant_id required")
    notes = payload.notes or ""
    if payload.reason and payload.reason != "adjustment":
        notes = f"{payload.reason}: {notes}".strip(": ")
    new_qty = await adjust_inventory(
        tenant_id=tenant_id,
        location_id=payload.location_id,
        product_id=payload.product_id,
        variant_id=payload.variant_id,
        qty_delta=payload.qty_delta,
        user_id=str(current_user.id),
        notes=notes,
        reorder_point=payload.reorder_point,
    )
    await log_audit(
        tenant_id,
        str(current_user.id),
        "pos.inventory.adjust",
        details={"qty_delta": payload.qty_delta},
    )
    return {"qty_on_hand": new_qty}


@router.get("/inventory/low-stock")
async def low_stock_endpoint(
    limit: int = 20,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_INVENTORY)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return await get_low_stock(tenant_id, limit=limit)


@router.get("/analytics/kpis")
async def analytics_kpis(
    start_date: date,
    end_date: date,
    current_user: User = Depends(require_permission(PermissionCode.POS_VIEW_ANALYTICS)),
):
    _ensure_owner(current_user)
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return await get_sales_kpis(tenant_id, start_date, end_date)


@router.get("/analytics/trends")
async def analytics_trends(
    start_date: date,
    end_date: date,
    granularity: str = "day",
    current_user: User = Depends(require_permission(PermissionCode.POS_VIEW_ANALYTICS)),
):
    _ensure_owner(current_user)
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return await get_sales_trends(tenant_id, start_date, end_date, granularity)


@router.get("/analytics/top-products")
async def analytics_top_products(
    start_date: date,
    end_date: date,
    limit: int = 10,
    current_user: User = Depends(require_permission(PermissionCode.POS_VIEW_ANALYTICS)),
):
    _ensure_owner(current_user)
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return await get_top_products(tenant_id, start_date, end_date, limit)


@router.get("/analytics/top-categories")
async def analytics_top_categories(
    start_date: date,
    end_date: date,
    limit: int = 10,
    current_user: User = Depends(require_permission(PermissionCode.POS_VIEW_ANALYTICS)),
):
    _ensure_owner(current_user)
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return await get_top_categories(tenant_id, start_date, end_date, limit)


@router.get("/analytics/payments")
async def analytics_payments(
    start_date: date,
    end_date: date,
    current_user: User = Depends(require_permission(PermissionCode.POS_VIEW_ANALYTICS)),
):
    _ensure_owner(current_user)
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return await get_payment_breakdown(tenant_id, start_date, end_date)


@router.get("/analytics/low-stock")
async def analytics_low_stock(
    limit: int = 20,
    current_user: User = Depends(require_permission(PermissionCode.POS_VIEW_ANALYTICS)),
):
    _ensure_owner(current_user)
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return await get_low_stock(tenant_id, limit=limit)


@router.get("/analytics/employees")
async def analytics_employees(
    start_date: date,
    end_date: date,
    current_user: User = Depends(require_permission(PermissionCode.POS_VIEW_ANALYTICS)),
):
    _ensure_owner(current_user)
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return await get_employee_performance(tenant_id, start_date, end_date)


@router.get("/analytics/inventory-valuation")
async def analytics_inventory_valuation(
    current_user: User = Depends(require_permission(PermissionCode.POS_VIEW_ANALYTICS)),
):
    _ensure_owner(current_user)
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return await get_inventory_valuation(tenant_id)


@router.get("/analytics/loyalty")
async def analytics_loyalty(
    start_date: date,
    end_date: date,
    current_user: User = Depends(require_permission(PermissionCode.POS_VIEW_ANALYTICS)),
):
    _ensure_owner(current_user)
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return await get_loyalty_summary(tenant_id, start_date, end_date)


@router.get("/analytics/feedback")
async def analytics_feedback(
    start_date: date,
    end_date: date,
    current_user: User = Depends(require_permission(PermissionCode.POS_VIEW_ANALYTICS)),
):
    _ensure_owner(current_user)
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return await get_feedback_summary(tenant_id, start_date, end_date)


@router.get("/storefront/settings")
async def get_storefront(
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    settings = await get_storefront_settings(tenant_id)
    return settings.model_dump() if settings else None


@router.put("/storefront/settings")
async def update_storefront(
    payload: StorefrontSettingsRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_CATALOG)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    settings = await upsert_storefront_settings(tenant_id, payload)
    return settings.model_dump()


@router.get("/marketing/campaigns")
async def get_campaigns(
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_CATALOG)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return [c.model_dump() for c in await list_campaigns(tenant_id)]


@router.post("/marketing/campaigns")
async def create_campaign_endpoint(
    payload: PromotionCampaignCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_CATALOG)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    campaign = await create_campaign(tenant_id, payload)
    return campaign.model_dump()


@router.get("/marketing/coupons")
async def get_coupons(
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_CATALOG)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return [c.model_dump() for c in await list_coupons(tenant_id)]


@router.post("/marketing/coupons")
async def create_coupon_endpoint(
    payload: CouponCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_CATALOG)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    coupon = await create_coupon(tenant_id, payload)
    return coupon.model_dump()


@router.get("/loyalty/program")
async def get_loyalty_program(
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_CATALOG)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    program = await get_active_loyalty_program(tenant_id)
    return program.model_dump() if program else None


@router.post("/loyalty/program")
async def create_loyalty_program(
    payload: LoyaltyProgramRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_CATALOG)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    program = await get_active_loyalty_program(tenant_id)
    if program:
        program.name = payload.name
        program.points_per_currency_unit = payload.points_per_currency_unit
        program.redeem_rate_cents_per_point = payload.redeem_rate_cents_per_point
        program.is_active = payload.is_active
        await program.save()
        return program.model_dump()

    from app.models.pos import LoyaltyProgram
    program = LoyaltyProgram(
        tenant_id=tenant_id,
        name=payload.name,
        points_per_currency_unit=payload.points_per_currency_unit,
        redeem_rate_cents_per_point=payload.redeem_rate_cents_per_point,
        is_active=payload.is_active,
    )
    await program.insert()
    return program.model_dump()


@router.get("/loyalty/customers/{customer_id}")
async def get_loyalty_account(
    customer_id: str,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    account = await get_or_create_loyalty_account(tenant_id, customer_id)
    return account.model_dump()


@router.post("/loyalty/adjust")
async def adjust_loyalty(
    payload: LoyaltyAdjustRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_CATALOG)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    account = await adjust_loyalty_points(
        tenant_id,
        payload.customer_id,
        payload.points_delta,
        str(current_user.id),
        payload.reason,
    )
    return account.model_dump()


@router.get("/staff")
async def list_staff(
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_USERS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return [p.model_dump() for p in await list_employee_profiles(tenant_id)]


@router.post("/staff")
async def upsert_staff(
    payload: EmployeeProfileRequest,
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_USERS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    profile = await upsert_employee_profile(tenant_id, payload)
    return profile.model_dump()


@router.post("/staff/clock-in")
async def staff_clock_in(
    payload: TimeClockRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    entry = await clock_in(
        tenant_id=tenant_id,
        user_id=str(current_user.id),
        location_id=payload.location_id,
        break_minutes=payload.break_minutes or 0,
    )
    return entry.model_dump()


@router.post("/staff/clock-out")
async def staff_clock_out(
    payload: TimeClockRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    entry = await clock_out(
        tenant_id=tenant_id,
        user_id=str(current_user.id),
        break_minutes=payload.break_minutes or 0,
    )
    return entry.model_dump()


@router.get("/staff/time-entries")
async def staff_time_entries(
    start_date: Optional[date] = None,
    end_date: Optional[date] = None,
    user_id: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_USERS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    start_dt = datetime.combine(start_date, datetime.min.time()) if start_date else None
    end_dt = datetime.combine(end_date, datetime.max.time()) if end_date else None
    entries = await list_time_entries(tenant_id, user_id=user_id, start=start_dt, end=end_dt)
    return [e.model_dump() for e in entries]


@router.get("/payroll/summary")
async def payroll_summary(
    start_date: date,
    end_date: date,
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_USERS)),
):
    _ensure_owner(current_user)
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    start_dt = datetime.combine(start_date, datetime.min.time())
    end_dt = datetime.combine(end_date, datetime.max.time())
    return await get_payroll_summary(tenant_id, start_dt, end_dt)


@router.get("/reputation/feedback")
async def reputation_feedback(
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return [f.model_dump() for f in await list_feedback(tenant_id)]


@router.post("/reputation/feedback")
async def create_feedback_endpoint(
    payload: CustomerFeedbackCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    feedback = await create_feedback(tenant_id, payload)
    return feedback.model_dump()


@router.patch("/reputation/feedback/{feedback_id}")
async def respond_feedback_endpoint(
    feedback_id: str,
    payload: CustomerFeedbackRespondRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    feedback = await respond_feedback(tenant_id, feedback_id, str(current_user.id), payload)
    return feedback.model_dump()


@router.get("/vendors")
async def get_vendors(
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_INVENTORY)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return [v.model_dump() for v in await list_vendors(tenant_id)]


@router.post("/vendors")
async def create_vendor_endpoint(
    payload: VendorCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_INVENTORY)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    vendor = await create_vendor(tenant_id, payload)
    return vendor.model_dump()


@router.get("/purchase-orders")
async def get_purchase_orders(
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_INVENTORY)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return [o.model_dump() for o in await list_purchase_orders(tenant_id)]


@router.post("/purchase-orders")
async def create_purchase_order_endpoint(
    payload: PurchaseOrderCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_INVENTORY)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    order = await create_purchase_order(tenant_id, str(current_user.id), payload)
    await log_audit(tenant_id, str(current_user.id), "pos.purchase_order.create", target=str(order.id))
    return order.model_dump()


@router.post("/purchase-orders/{order_id}/receive")
async def receive_purchase_order_endpoint(
    order_id: str,
    payload: PurchaseOrderReceiveRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_INVENTORY)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    order = await receive_purchase_order(tenant_id, order_id, str(current_user.id), payload.items)
    await log_audit(tenant_id, str(current_user.id), "pos.purchase_order.receive", target=str(order.id))
    return order.model_dump()


@router.post("/inventory/transfers")
async def create_transfer_endpoint(
    payload: StockTransferCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_INVENTORY)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    transfer = await create_stock_transfer(
        tenant_id,
        payload.from_location_id,
        payload.to_location_id,
        payload.items,
        str(current_user.id),
        status=payload.status,
    )
    await log_audit(tenant_id, str(current_user.id), "pos.inventory.transfer.create", target=str(transfer.id))
    return transfer.model_dump()


@router.get("/inventory/transfers")
async def list_transfers_endpoint(
    status: Optional[str] = None,
    from_location_id: Optional[str] = None,
    to_location_id: Optional[str] = None,
    limit: int = 50,
    offset: int = 0,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_INVENTORY)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    status_enum = None
    if status:
        from app.models.pos import StockTransferStatus
        status_enum = StockTransferStatus(status)
    transfers = await list_stock_transfers(
        tenant_id,
        status=status_enum,
        from_location_id=from_location_id,
        to_location_id=to_location_id,
        limit=limit,
        offset=offset,
    )
    return [t.model_dump() for t in transfers]


@router.post("/inventory/transfers/{transfer_id}/receive")
async def receive_transfer_endpoint(
    transfer_id: str,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_INVENTORY)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    transfer = await receive_stock_transfer(tenant_id, transfer_id, str(current_user.id))
    await log_audit(tenant_id, str(current_user.id), "pos.inventory.transfer.receive", target=str(transfer.id))
    return transfer.model_dump()


@router.post("/inventory/counts")
async def create_stock_count_endpoint(
    payload: StockCountCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_INVENTORY)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    count = await create_stock_count(tenant_id, payload.location_id, str(current_user.id))
    await log_audit(tenant_id, str(current_user.id), "pos.inventory.count.create", target=str(count.id))
    return count.model_dump()


@router.get("/inventory/counts")
async def list_stock_counts_endpoint(
    status: Optional[str] = None,
    location_id: Optional[str] = None,
    limit: int = 50,
    offset: int = 0,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_INVENTORY)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    status_enum = None
    if status:
        from app.models.pos import StockCountStatus
        status_enum = StockCountStatus(status)
    counts = await list_stock_counts(
        tenant_id,
        status=status_enum,
        location_id=location_id,
        limit=limit,
        offset=offset,
    )
    return [c.model_dump() for c in counts]


@router.post("/inventory/counts/{count_id}/complete")
async def complete_stock_count_endpoint(
    count_id: str,
    payload: StockCountCompleteRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_INVENTORY)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    count = await complete_stock_count(tenant_id, count_id, str(current_user.id), payload.items)
    await log_audit(tenant_id, str(current_user.id), "pos.inventory.count.complete", target=str(count.id))
    return count.model_dump()


@router.get("/fulfillment")
async def get_fulfillment_orders(
    status_filter: Optional[str] = Query(default=None, alias="status"),
    location_id: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    status_enum = None
    if status_filter:
        from app.models.pos import FulfillmentStatus
        status_enum = FulfillmentStatus(status_filter)
    return [s.model_dump() for s in await list_fulfillment_orders(tenant_id, status_enum, location_id)]


@router.patch("/fulfillment/{sale_id}")
async def update_fulfillment_endpoint(
    sale_id: str,
    payload: FulfillmentInput,
    current_user: User = Depends(require_permission(PermissionCode.POS_PROCESS_SALES)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    sale = await update_fulfillment(tenant_id, sale_id, payload)
    return sale.model_dump()


@router.get("/kitchen")
async def kitchen_items(
    location_id: Optional[str] = None,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return await list_kitchen_items(tenant_id, location_id=location_id)


@router.patch("/kitchen/{sale_item_id}")
async def kitchen_update(
    sale_item_id: str,
    status: str = Query(...),
    current_user: User = Depends(require_permission(PermissionCode.POS_PROCESS_SALES)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    from app.models.pos import KitchenStatus

    status_enum = KitchenStatus(status)
    item = await update_kitchen_status(tenant_id, sale_item_id, status_enum)
    return item.model_dump()


@router.get("/work-orders")
async def work_orders(
    status_filter: Optional[str] = Query(default=None, alias="status"),
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    status_enum = None
    if status_filter:
        from app.models.pos import WorkOrderStatus
        status_enum = WorkOrderStatus(status_filter)
    return [o.model_dump() for o in await list_work_orders(tenant_id, status_enum)]


@router.post("/work-orders")
async def create_work_order_endpoint(
    payload: WorkOrderCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    order = await create_work_order(tenant_id, str(current_user.id), payload)
    return order.model_dump()


@router.patch("/work-orders/{work_order_id}")
async def update_work_order_endpoint(
    work_order_id: str,
    payload: WorkOrderUpdateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    order = await update_work_order(tenant_id, work_order_id, payload)
    return order.model_dump()


@router.get("/appointments")
async def appointments(
    status_filter: Optional[str] = Query(default=None, alias="status"),
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    status_enum = None
    if status_filter:
        from app.models.pos import AppointmentStatus
        status_enum = AppointmentStatus(status_filter)
    return [a.model_dump() for a in await list_appointments(tenant_id, status_enum)]


@router.post("/appointments")
async def create_appointment_endpoint(
    payload: AppointmentCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    appointment = await create_appointment(tenant_id, str(current_user.id), payload)
    return appointment.model_dump()


@router.patch("/appointments/{appointment_id}")
async def update_appointment_endpoint(
    appointment_id: str,
    payload: AppointmentUpdateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    appointment = await update_appointment(tenant_id, appointment_id, payload)
    return appointment.model_dump()


@router.get("/subscriptions/plans")
async def subscription_plans(
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return [p.model_dump() for p in await list_subscription_plans(tenant_id)]


@router.post("/subscriptions/plans")
async def create_subscription_plan_endpoint(
    payload: SubscriptionPlanCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_MANAGE_CATALOG)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    plan = await create_subscription_plan(tenant_id, payload)
    return plan.model_dump()


@router.get("/subscriptions")
async def subscriptions(
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return [s.model_dump() for s in await list_customer_subscriptions(tenant_id)]


@router.post("/subscriptions")
async def create_subscription_endpoint(
    payload: CustomerSubscriptionCreateRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    subscription = await create_customer_subscription(tenant_id, payload)
    return subscription.model_dump()


@router.get("/subscriptions/invoices")
async def subscription_invoices(
    current_user: User = Depends(require_permission(PermissionCode.POS_ACCESS)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    return [i.model_dump() for i in await list_subscription_invoices(tenant_id)]


@router.post("/subscriptions/invoices/{invoice_id}/pay")
async def pay_subscription_invoice_endpoint(
    invoice_id: str,
    payload: SubscriptionInvoicePayRequest,
    current_user: User = Depends(require_permission(PermissionCode.POS_PROCESS_SALES)),
):
    tenant_id = str(current_user.tenant_id)
    await _require_pos_entitlement(tenant_id)
    invoice = await pay_subscription_invoice(tenant_id, invoice_id, str(current_user.id), payload)
    return invoice.model_dump()
