from __future__ import annotations

from datetime import date, datetime
from typing import Optional, List, Dict

from pydantic import BaseModel, Field

from app.models.pos import (
    DiscountType,
    PaymentMethod,
    CashMovementType,
    SalesChannel,
    FulfillmentType,
    FulfillmentStatus,
    CampaignStatus,
    LoyaltyLedgerReason,
    FeedbackStatus,
    PurchaseOrderStatus,
    StockTransferStatus,
    StockCountStatus,
    TimeClockStatus,
    WorkOrderStatus,
    AppointmentStatus,
    SubscriptionStatus,
)


class ProductLookupItem(BaseModel):
    product_id: str
    variant_id: Optional[str] = None
    name: str
    sku: Optional[str] = None
    barcode: Optional[str] = None
    image_url: Optional[str] = None
    price_cents: int
    tax_ids: List[str] = Field(default_factory=list)
    category_id: Optional[str] = None
    is_service: bool = False
    is_subscription: bool = False
    is_kitchen_item: bool = False
    requires_id_check: bool = False
    minimum_age: Optional[int] = None


class AddressInput(BaseModel):
    line1: str
    line2: Optional[str] = None
    city: str
    state: str
    postal_code: str
    country: str = "US"


class FulfillmentInput(BaseModel):
    fulfillment_type: FulfillmentType
    status: Optional[FulfillmentStatus] = None
    shipping_address: Optional[AddressInput] = None
    delivery_instructions: Optional[str] = None
    scheduled_for: Optional[datetime] = None
    carrier: Optional[str] = None
    tracking_number: Optional[str] = None
    shipping_cost_cents: Optional[int] = Field(default=0, ge=0)


class IdVerificationInput(BaseModel):
    id_type: str
    id_last4: str
    birth_date: Optional[date] = None
    minimum_age: Optional[int] = Field(default=None, ge=0)


class SaleItemInput(BaseModel):
    product_id: Optional[str] = None
    variant_id: Optional[str] = None
    quantity: int = Field(..., ge=1)
    price_override_cents: Optional[int] = Field(default=None, ge=0)
    discount_id: Optional[str] = None
    discount_type: Optional[DiscountType] = None
    discount_bps: Optional[int] = Field(default=None, ge=0)
    discount_cents: Optional[int] = Field(default=None, ge=0)
    tax_ids: Optional[List[str]] = None


class SaleDiscountInput(BaseModel):
    discount_id: Optional[str] = None
    discount_type: Optional[DiscountType] = None
    discount_bps: Optional[int] = Field(default=None, ge=0)
    discount_cents: Optional[int] = Field(default=None, ge=0)


class SaleDraftRequest(BaseModel):
    location_id: Optional[str] = None
    register_id: Optional[str] = None
    customer_id: Optional[str] = None
    channel: Optional[SalesChannel] = None
    fulfillment: Optional[FulfillmentInput] = None
    coupon_code: Optional[str] = None
    loyalty_points_redeemed: Optional[int] = Field(default=None, ge=0)
    items: List[SaleItemInput]
    order_discount: Optional[SaleDiscountInput] = None


class SaleDraftUpdateRequest(BaseModel):
    customer_id: Optional[str] = None
    channel: Optional[SalesChannel] = None
    fulfillment: Optional[FulfillmentInput] = None
    coupon_code: Optional[str] = None
    loyalty_points_redeemed: Optional[int] = Field(default=None, ge=0)
    items: List[SaleItemInput]
    order_discount: Optional[SaleDiscountInput] = None


class PaymentInput(BaseModel):
    method: PaymentMethod
    amount_cents: int = Field(..., ge=0)
    reference: Optional[str] = None


class FinalizeSaleRequest(BaseModel):
    payments: List[PaymentInput]
    customer_id: Optional[str] = None
    channel: Optional[SalesChannel] = None
    fulfillment: Optional[FulfillmentInput] = None
    coupon_code: Optional[str] = None
    loyalty_points_redeemed: Optional[int] = Field(default=0, ge=0)
    id_verification: Optional[IdVerificationInput] = None


class RegisterSessionOpenRequest(BaseModel):
    opening_cash_cents: int = Field(default=0, ge=0)
    denominations: Optional[Dict[str, int]] = None


class RegisterSessionCloseRequest(BaseModel):
    closing_cash_cents: int = Field(..., ge=0)
    denominations: Optional[Dict[str, int]] = None


class CashMovementRequest(BaseModel):
    register_session_id: str
    movement_type: CashMovementType
    amount_cents: int = Field(..., ge=0)
    reason: Optional[str] = ""


class RefundItemInput(BaseModel):
    sale_item_id: str
    quantity: int = Field(..., ge=1)
    restock: bool = True


class RefundRequest(BaseModel):
    sale_id: str
    items: List[RefundItemInput]
    reason: Optional[str] = ""
    payment_method: Optional[PaymentMethod] = None


class InventoryAdjustmentRequest(BaseModel):
    location_id: str
    product_id: Optional[str] = None
    variant_id: Optional[str] = None
    qty_delta: int
    reorder_point: Optional[int] = Field(default=None, ge=0)
    reason: Optional[str] = "adjustment"
    notes: Optional[str] = ""


class CustomerCreateRequest(BaseModel):
    name: str
    email: Optional[str] = None
    phone: Optional[str] = None
    notes: Optional[str] = ""


class CategoryCreateRequest(BaseModel):
    name: str
    parent_id: Optional[str] = None
    sort_order: int = 0
    is_active: bool = True


class TaxCreateRequest(BaseModel):
    name: str
    rate_bps: int = Field(..., ge=0)
    is_inclusive: bool = False
    is_active: bool = True


class DiscountCreateRequest(BaseModel):
    name: str
    discount_type: DiscountType
    value_bps: Optional[int] = Field(default=None, ge=0)
    value_cents: Optional[int] = Field(default=None, ge=0)
    applies_to: str = "order"
    is_active: bool = True


class ProductCreateRequest(BaseModel):
    name: str
    description: Optional[str] = ""
    category_id: Optional[str] = None
    sku: Optional[str] = None
    barcode: Optional[str] = None
    image_url: Optional[str] = None
    base_price_cents: int = Field(..., ge=0)
    cost_cents: Optional[int] = Field(default=None, ge=0)
    tax_ids: Optional[List[str]] = None
    is_service: bool = False
    is_subscription: bool = False
    is_kitchen_item: bool = False
    requires_id_check: bool = False
    minimum_age: Optional[int] = Field(default=None, ge=0)
    is_active: bool = True


class VariantCreateRequest(BaseModel):
    product_id: str
    name: str
    sku: Optional[str] = None
    barcode: Optional[str] = None
    image_url: Optional[str] = None
    price_cents: int = Field(..., ge=0)
    cost_cents: Optional[int] = Field(default=None, ge=0)
    attributes: Optional[Dict[str, str]] = None
    tax_ids: Optional[List[str]] = None
    is_service: bool = False
    is_subscription: bool = False
    is_kitchen_item: bool = False
    requires_id_check: bool = False
    minimum_age: Optional[int] = Field(default=None, ge=0)
    is_active: bool = True


class BulkProductItem(BaseModel):
    product_id: Optional[str] = None
    name: str
    description: Optional[str] = ""
    category_id: Optional[str] = None
    sku: Optional[str] = None
    barcode: Optional[str] = None
    image_url: Optional[str] = None
    base_price_cents: int = Field(..., ge=0)
    cost_cents: Optional[int] = Field(default=None, ge=0)
    tax_ids: Optional[List[str]] = None
    is_service: bool = False
    is_subscription: bool = False
    is_kitchen_item: bool = False
    requires_id_check: bool = False
    minimum_age: Optional[int] = Field(default=None, ge=0)
    is_active: bool = True


class BulkProductUpsertRequest(BaseModel):
    items: List[BulkProductItem]


class LocationCreateRequest(BaseModel):
    name: str
    code: str
    address: Optional[str] = None
    timezone: Optional[str] = "UTC"
    is_active: bool = True


class RegisterCreateRequest(BaseModel):
    location_id: str
    name: str
    is_active: bool = True


class SalesHistoryFilters(BaseModel):
    start_date: Optional[date] = None
    end_date: Optional[date] = None
    location_id: Optional[str] = None
    register_id: Optional[str] = None
    cashier_id: Optional[str] = None
    status: Optional[str] = None


class AnalyticsDateRange(BaseModel):
    start_date: date
    end_date: date


class StorefrontSettingsRequest(BaseModel):
    name: str
    slug: str
    headline: Optional[str] = None
    description: Optional[str] = None
    primary_color: str = "#7c3aed"
    accent_color: str = "#f59e0b"
    logo_url: Optional[str] = None
    hero_image_url: Optional[str] = None
    show_out_of_stock: bool = False
    is_published: bool = False


class PromotionCampaignCreateRequest(BaseModel):
    name: str
    description: Optional[str] = None
    status: CampaignStatus = CampaignStatus.DRAFT
    starts_at: Optional[datetime] = None
    ends_at: Optional[datetime] = None
    discount_id: Optional[str] = None


class CouponCreateRequest(BaseModel):
    code: str
    discount_id: str
    campaign_id: Optional[str] = None
    usage_limit: Optional[int] = Field(default=None, ge=0)
    starts_at: Optional[datetime] = None
    ends_at: Optional[datetime] = None
    is_active: bool = True


class LoyaltyProgramRequest(BaseModel):
    name: str
    points_per_currency_unit: int = Field(default=1, ge=0)
    redeem_rate_cents_per_point: int = Field(default=1, ge=0)
    is_active: bool = True


class LoyaltyAdjustRequest(BaseModel):
    customer_id: str
    points_delta: int
    reason: LoyaltyLedgerReason = LoyaltyLedgerReason.ADJUST


class EmployeeProfileRequest(BaseModel):
    user_id: str
    job_title: Optional[str] = None
    hourly_rate_cents: Optional[int] = Field(default=None, ge=0)
    pos_pin: Optional[str] = None
    location_ids: Optional[List[str]] = None
    is_active: bool = True
    hire_date: Optional[date] = None


class TimeClockRequest(BaseModel):
    location_id: Optional[str] = None
    break_minutes: Optional[int] = Field(default=0, ge=0)


class PayrollSummaryRequest(BaseModel):
    start_date: date
    end_date: date


class CustomerFeedbackCreateRequest(BaseModel):
    sale_id: Optional[str] = None
    customer_id: Optional[str] = None
    rating: int = Field(..., ge=1, le=5)
    comment: Optional[str] = None


class CustomerFeedbackRespondRequest(BaseModel):
    status: FeedbackStatus = FeedbackStatus.RESPONDED
    response: Optional[str] = None


class VendorCreateRequest(BaseModel):
    name: str
    contact_name: Optional[str] = None
    email: Optional[str] = None
    phone: Optional[str] = None
    address: Optional[str] = None
    notes: Optional[str] = None
    is_active: bool = True


class PurchaseOrderItemInput(BaseModel):
    product_id: Optional[str] = None
    variant_id: Optional[str] = None
    quantity: int = Field(..., ge=1)
    unit_cost_cents: int = Field(..., ge=0)


class PurchaseOrderCreateRequest(BaseModel):
    vendor_id: str
    location_id: str
    items: List[PurchaseOrderItemInput]
    status: PurchaseOrderStatus = PurchaseOrderStatus.DRAFT
    expected_at: Optional[datetime] = None


class PurchaseOrderReceiveItemInput(BaseModel):
    product_id: Optional[str] = None
    variant_id: Optional[str] = None
    received_quantity: int = Field(..., ge=0)


class PurchaseOrderReceiveRequest(BaseModel):
    items: List[PurchaseOrderReceiveItemInput]


class StockTransferItemInput(BaseModel):
    product_id: Optional[str] = None
    variant_id: Optional[str] = None
    quantity: int = Field(..., ge=1)


class StockTransferCreateRequest(BaseModel):
    from_location_id: str
    to_location_id: str
    items: List[StockTransferItemInput]
    status: StockTransferStatus = StockTransferStatus.DRAFT


class StockTransferReceiveRequest(BaseModel):
    status: StockTransferStatus = StockTransferStatus.RECEIVED


class StockCountItemInput(BaseModel):
    product_id: Optional[str] = None
    variant_id: Optional[str] = None
    counted_qty: int = Field(..., ge=0)


class StockCountCreateRequest(BaseModel):
    location_id: str


class StockCountCompleteRequest(BaseModel):
    items: List[StockCountItemInput]
    status: StockCountStatus = StockCountStatus.COMPLETED


class WorkOrderItemInput(BaseModel):
    product_id: Optional[str] = None
    variant_id: Optional[str] = None
    description: Optional[str] = None
    serial_number: Optional[str] = None


class WorkOrderCreateRequest(BaseModel):
    title: str
    description: Optional[str] = None
    location_id: Optional[str] = None
    customer_id: Optional[str] = None
    sale_id: Optional[str] = None
    status: WorkOrderStatus = WorkOrderStatus.OPEN
    priority: Optional[str] = None
    assigned_to_user_id: Optional[str] = None
    due_at: Optional[datetime] = None
    items: Optional[List[WorkOrderItemInput]] = None
    notes: Optional[str] = None


class WorkOrderUpdateRequest(BaseModel):
    status: Optional[WorkOrderStatus] = None
    priority: Optional[str] = None
    assigned_to_user_id: Optional[str] = None
    due_at: Optional[datetime] = None
    items: Optional[List[WorkOrderItemInput]] = None
    notes: Optional[str] = None


class AppointmentCreateRequest(BaseModel):
    customer_id: Optional[str] = None
    service_product_id: Optional[str] = None
    assigned_to_user_id: Optional[str] = None
    location_id: Optional[str] = None
    start_at: datetime
    end_at: datetime
    status: AppointmentStatus = AppointmentStatus.SCHEDULED
    notes: Optional[str] = None


class AppointmentUpdateRequest(BaseModel):
    status: Optional[AppointmentStatus] = None
    start_at: Optional[datetime] = None
    end_at: Optional[datetime] = None
    assigned_to_user_id: Optional[str] = None
    notes: Optional[str] = None


class SubscriptionPlanCreateRequest(BaseModel):
    name: str
    description: Optional[str] = None
    price_cents: int = Field(..., ge=0)
    interval: str = "month"
    interval_count: int = Field(default=1, ge=1)
    is_active: bool = True


class CustomerSubscriptionCreateRequest(BaseModel):
    customer_id: str
    plan_id: str
    status: SubscriptionStatus = SubscriptionStatus.ACTIVE
    start_date: Optional[date] = None
    next_billing_date: Optional[date] = None


class SubscriptionInvoicePayRequest(BaseModel):
    payment_method: PaymentMethod = PaymentMethod.OTHER
    reference: Optional[str] = None
