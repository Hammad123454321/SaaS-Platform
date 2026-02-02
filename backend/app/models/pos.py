from __future__ import annotations

from datetime import datetime, date
from enum import StrEnum
from typing import Optional, List, Dict

from beanie import Document
from pydantic import Field, BaseModel


class RegisterSessionStatus(StrEnum):
    OPEN = "open"
    CLOSED = "closed"


class CashMovementType(StrEnum):
    PAID_IN = "paid_in"
    PAID_OUT = "paid_out"


class DiscountType(StrEnum):
    PERCENT = "percent"
    FIXED = "fixed"


class PaymentMethod(StrEnum):
    CASH = "cash"
    CARD = "card"
    OTHER = "other"


class SaleStatus(StrEnum):
    DRAFT = "draft"
    COMPLETED = "completed"
    VOIDED = "voided"
    REFUNDED = "refunded"


class RefundStatus(StrEnum):
    PENDING = "pending"
    COMPLETED = "completed"


class InventoryReason(StrEnum):
    SALE = "sale"
    REFUND = "refund"
    ADJUSTMENT = "adjustment"
    PURCHASE = "purchase"
    TRANSFER_OUT = "transfer_out"
    TRANSFER_IN = "transfer_in"
    COUNT_ADJUSTMENT = "count_adjustment"


class SalesChannel(StrEnum):
    POS = "pos"
    ONLINE = "online"
    PHONE = "phone"
    WHOLESALE = "wholesale"


class FulfillmentType(StrEnum):
    IN_STORE = "in_store"
    PICKUP = "pickup"
    DELIVERY = "delivery"
    SHIPPING = "shipping"


class FulfillmentStatus(StrEnum):
    PENDING = "pending"
    IN_PROGRESS = "in_progress"
    READY = "ready"
    SHIPPED = "shipped"
    DELIVERED = "delivered"
    PICKED_UP = "picked_up"
    CANCELLED = "cancelled"


class KitchenStatus(StrEnum):
    QUEUED = "queued"
    IN_PROGRESS = "in_progress"
    READY = "ready"
    SERVED = "served"


class CampaignStatus(StrEnum):
    DRAFT = "draft"
    ACTIVE = "active"
    PAUSED = "paused"
    COMPLETED = "completed"


class LoyaltyLedgerReason(StrEnum):
    EARN = "earn"
    REDEEM = "redeem"
    ADJUST = "adjust"


class FeedbackStatus(StrEnum):
    NEW = "new"
    RESPONDED = "responded"
    ARCHIVED = "archived"


class PurchaseOrderStatus(StrEnum):
    DRAFT = "draft"
    ORDERED = "ordered"
    PARTIALLY_RECEIVED = "partially_received"
    RECEIVED = "received"
    CANCELLED = "cancelled"


class StockTransferStatus(StrEnum):
    DRAFT = "draft"
    IN_TRANSIT = "in_transit"
    RECEIVED = "received"
    CANCELLED = "cancelled"


class StockCountStatus(StrEnum):
    OPEN = "open"
    COMPLETED = "completed"
    CANCELLED = "cancelled"


class TimeClockStatus(StrEnum):
    OPEN = "open"
    CLOSED = "closed"


class WorkOrderStatus(StrEnum):
    OPEN = "open"
    IN_PROGRESS = "in_progress"
    COMPLETED = "completed"
    CANCELLED = "cancelled"


class AppointmentStatus(StrEnum):
    SCHEDULED = "scheduled"
    COMPLETED = "completed"
    NO_SHOW = "no_show"
    CANCELLED = "cancelled"


class SubscriptionStatus(StrEnum):
    ACTIVE = "active"
    PAUSED = "paused"
    CANCELLED = "cancelled"
    PAST_DUE = "past_due"


class Address(BaseModel):
    line1: str
    line2: Optional[str] = None
    city: str
    state: str
    postal_code: str
    country: str = "US"


class FulfillmentInfo(BaseModel):
    fulfillment_type: FulfillmentType
    status: FulfillmentStatus = FulfillmentStatus.PENDING
    shipping_address: Optional[Address] = None
    delivery_instructions: Optional[str] = None
    scheduled_for: Optional[datetime] = None
    carrier: Optional[str] = None
    tracking_number: Optional[str] = None
    shipped_at: Optional[datetime] = None
    delivered_at: Optional[datetime] = None
    shipping_cost_cents: int = 0


class IdVerification(BaseModel):
    id_type: str
    id_last4: str
    birth_date: Optional[date] = None
    verified_at: datetime = Field(default_factory=datetime.utcnow)
    verified_by_user_id: Optional[str] = None
    minimum_age: Optional[int] = None


class PurchaseOrderItem(BaseModel):
    product_id: Optional[str] = None
    variant_id: Optional[str] = None
    quantity: int = Field(..., ge=1)
    unit_cost_cents: int = Field(..., ge=0)
    received_quantity: int = 0


class StockTransferItem(BaseModel):
    product_id: Optional[str] = None
    variant_id: Optional[str] = None
    quantity: int = Field(..., ge=1)


class StockCountItem(BaseModel):
    product_id: Optional[str] = None
    variant_id: Optional[str] = None
    expected_qty: int = 0
    counted_qty: int = 0


class WorkOrderItem(BaseModel):
    product_id: Optional[str] = None
    variant_id: Optional[str] = None
    description: Optional[str] = None
    serial_number: Optional[str] = None


class Location(Document):
    tenant_id: str = Field(..., index=True)
    name: str = Field(..., index=True)
    code: str = Field(..., index=True)
    address: Optional[str] = None
    timezone: str = "UTC"
    is_active: bool = True
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_locations"
        indexes = [
            "tenant_id",
            "code",
            ("tenant_id", "code"),
        ]


class Register(Document):
    tenant_id: str = Field(..., index=True)
    location_id: str = Field(..., index=True)
    name: str = Field(..., index=True)
    is_active: bool = True
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_registers"
        indexes = [
            "tenant_id",
            "location_id",
            ("tenant_id", "location_id"),
        ]


class RegisterSession(Document):
    tenant_id: str = Field(..., index=True)
    register_id: str = Field(..., index=True)
    location_id: str = Field(..., index=True)
    opened_by_user_id: str = Field(..., index=True)
    opened_at: datetime = Field(default_factory=datetime.utcnow, index=True)
    opening_cash_cents: int = 0
    status: RegisterSessionStatus = RegisterSessionStatus.OPEN
    closed_by_user_id: Optional[str] = None
    closed_at: Optional[datetime] = None
    closing_cash_cents: Optional[int] = None
    expected_cash_cents: int = 0
    cash_difference_cents: Optional[int] = None

    class Settings:
        name = "pos_register_sessions"
        indexes = [
            "tenant_id",
            "register_id",
            "status",
            ("tenant_id", "register_id", "status"),
            ("tenant_id", "opened_by_user_id"),
        ]


class CashMovement(Document):
    tenant_id: str = Field(..., index=True)
    register_session_id: str = Field(..., index=True)
    movement_type: CashMovementType = Field(..., index=True)
    amount_cents: int = Field(...)
    reason: str = Field(default="")
    created_by_user_id: str = Field(..., index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_cash_movements"
        indexes = [
            "tenant_id",
            "register_session_id",
            "movement_type",
        ]


class CashCount(Document):
    tenant_id: str = Field(..., index=True)
    register_session_id: str = Field(..., index=True)
    denominations: Dict[str, int] = Field(default_factory=dict)
    total_cents: int = 0
    created_by_user_id: str = Field(..., index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_cash_counts"
        indexes = [
            "tenant_id",
            "register_session_id",
        ]


class Category(Document):
    tenant_id: str = Field(..., index=True)
    name: str = Field(..., index=True)
    parent_id: Optional[str] = Field(default=None, index=True)
    sort_order: int = 0
    is_active: bool = True
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_categories"
        indexes = [
            "tenant_id",
            "name",
            "parent_id",
        ]


class Tax(Document):
    tenant_id: str = Field(..., index=True)
    name: str = Field(..., index=True)
    rate_bps: int = Field(..., ge=0)
    is_inclusive: bool = False
    is_active: bool = True
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_taxes"
        indexes = [
            "tenant_id",
            "name",
        ]


class Discount(Document):
    tenant_id: str = Field(..., index=True)
    name: str = Field(..., index=True)
    discount_type: DiscountType = Field(..., index=True)
    value_bps: Optional[int] = Field(default=None, ge=0)
    value_cents: Optional[int] = Field(default=None, ge=0)
    applies_to: str = Field(default="order", index=True)  # order, line, category, product
    is_active: bool = True
    starts_at: Optional[datetime] = None
    ends_at: Optional[datetime] = None
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_discounts"
        indexes = [
            "tenant_id",
            "discount_type",
            "applies_to",
        ]


class Product(Document):
    tenant_id: str = Field(..., index=True)
    name: str = Field(..., index=True)
    description: str = Field(default="")
    category_id: Optional[str] = Field(default=None, index=True)
    sku: Optional[str] = Field(default=None, index=True)
    barcode: Optional[str] = Field(default=None, index=True)
    image_url: Optional[str] = None
    base_price_cents: int = Field(..., ge=0)
    cost_cents: Optional[int] = Field(default=None, ge=0)
    tax_ids: List[str] = Field(default_factory=list)
    is_service: bool = False
    is_subscription: bool = False
    is_kitchen_item: bool = False
    requires_id_check: bool = False
    minimum_age: Optional[int] = Field(default=None, ge=0)
    is_active: bool = True
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_products"
        indexes = [
            "tenant_id",
            "name",
            "sku",
            "barcode",
            ("tenant_id", "sku"),
            ("tenant_id", "barcode"),
        ]


class Variant(Document):
    tenant_id: str = Field(..., index=True)
    product_id: str = Field(..., index=True)
    name: str = Field(..., index=True)
    sku: Optional[str] = Field(default=None, index=True)
    barcode: Optional[str] = Field(default=None, index=True)
    image_url: Optional[str] = None
    price_cents: int = Field(..., ge=0)
    cost_cents: Optional[int] = Field(default=None, ge=0)
    attributes: Dict[str, str] = Field(default_factory=dict)
    tax_ids: List[str] = Field(default_factory=list)
    is_service: bool = False
    is_subscription: bool = False
    is_kitchen_item: bool = False
    requires_id_check: bool = False
    minimum_age: Optional[int] = Field(default=None, ge=0)
    is_active: bool = True
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_variants"
        indexes = [
            "tenant_id",
            "product_id",
            "sku",
            "barcode",
            ("tenant_id", "product_id"),
            ("tenant_id", "sku"),
            ("tenant_id", "barcode"),
        ]


class Customer(Document):
    tenant_id: str = Field(..., index=True)
    name: str = Field(..., index=True)
    email: Optional[str] = Field(default=None, index=True)
    phone: Optional[str] = Field(default=None, index=True)
    notes: str = Field(default="")
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_customers"
        indexes = [
            "tenant_id",
            "email",
            "phone",
        ]


class Sale(Document):
    tenant_id: str = Field(..., index=True)
    location_id: Optional[str] = Field(default=None, index=True)
    register_id: Optional[str] = Field(default=None, index=True)
    register_session_id: Optional[str] = Field(default=None, index=True)
    cashier_id: str = Field(..., index=True)
    customer_id: Optional[str] = Field(default=None, index=True)
    channel: SalesChannel = Field(default=SalesChannel.POS, index=True)
    fulfillment: Optional[FulfillmentInfo] = None
    status: SaleStatus = Field(default=SaleStatus.DRAFT, index=True)
    subtotal_cents: int = 0
    discount_cents: int = 0
    tax_cents: int = 0
    shipping_cents: int = 0
    total_cents: int = 0
    order_discount_type: Optional[DiscountType] = None
    order_discount_bps: Optional[int] = None
    order_discount_cents: Optional[int] = None
    paid_cents: int = 0
    change_due_cents: int = 0
    items_count: int = 0
    applied_coupon_code: Optional[str] = Field(default=None, index=True)
    campaign_id: Optional[str] = Field(default=None, index=True)
    loyalty_points_redeemed: int = 0
    loyalty_points_earned: int = 0
    id_verification: Optional[IdVerification] = None
    created_at: datetime = Field(default_factory=datetime.utcnow, index=True)
    completed_at: Optional[datetime] = Field(default=None, index=True)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_sales"
        indexes = [
            "tenant_id",
            "status",
            "created_at",
            "completed_at",
            "location_id",
            "register_id",
            "cashier_id",
            "channel",
            "applied_coupon_code",
            "campaign_id",
        ]


class SaleItem(Document):
    tenant_id: str = Field(..., index=True)
    sale_id: str = Field(..., index=True)
    product_id: Optional[str] = Field(default=None, index=True)
    variant_id: Optional[str] = Field(default=None, index=True)
    category_id: Optional[str] = Field(default=None, index=True)
    product_name: str = Field(...)
    variant_name: Optional[str] = None
    sku: Optional[str] = Field(default=None, index=True)
    quantity: int = Field(..., ge=1)
    unit_price_cents: int = Field(..., ge=0)
    discount_cents: int = 0
    tax_cents: int = 0
    line_total_cents: int = 0
    tax_ids: List[str] = Field(default_factory=list)
    is_kitchen_item: bool = False
    kitchen_status: Optional[KitchenStatus] = None
    is_service: bool = False
    is_subscription: bool = False
    requires_id_check: bool = False
    minimum_age: Optional[int] = Field(default=None, ge=0)
    lot_number: Optional[str] = None
    serial_numbers: List[str] = Field(default_factory=list)
    sale_completed_at: Optional[datetime] = Field(default=None, index=True)

    class Settings:
        name = "pos_sale_items"
        indexes = [
            "tenant_id",
            "sale_id",
            "product_id",
            "category_id",
            "sale_completed_at",
            "kitchen_status",
            "is_kitchen_item",
        ]


class Payment(Document):
    tenant_id: str = Field(..., index=True)
    sale_id: str = Field(..., index=True)
    method: PaymentMethod = Field(..., index=True)
    amount_cents: int = Field(..., ge=0)
    reference: Optional[str] = None
    created_at: datetime = Field(default_factory=datetime.utcnow, index=True)
    sale_completed_at: Optional[datetime] = Field(default=None, index=True)

    class Settings:
        name = "pos_payments"
        indexes = [
            "tenant_id",
            "sale_id",
            "method",
            "sale_completed_at",
        ]


class Receipt(Document):
    tenant_id: str = Field(..., index=True)
    sale_id: str = Field(..., index=True)
    receipt_number: str = Field(..., index=True)
    rendered: Dict = Field(default_factory=dict)
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_receipts"
        indexes = [
            "tenant_id",
            "receipt_number",
            "sale_id",
        ]


class Refund(Document):
    tenant_id: str = Field(..., index=True)
    sale_id: str = Field(..., index=True)
    created_by_user_id: str = Field(..., index=True)
    reason: str = Field(default="")
    status: RefundStatus = Field(default=RefundStatus.PENDING, index=True)
    total_cents: int = 0
    payment_method: Optional[PaymentMethod] = None
    created_at: datetime = Field(default_factory=datetime.utcnow, index=True)

    class Settings:
        name = "pos_refunds"
        indexes = [
            "tenant_id",
            "sale_id",
            "status",
        ]


class RefundItem(Document):
    tenant_id: str = Field(..., index=True)
    refund_id: str = Field(..., index=True)
    sale_item_id: str = Field(..., index=True)
    quantity: int = Field(..., ge=1)
    amount_cents: int = Field(..., ge=0)
    restock: bool = True

    class Settings:
        name = "pos_refund_items"
        indexes = [
            "tenant_id",
            "refund_id",
            "sale_item_id",
        ]


class StockOnHand(Document):
    tenant_id: str = Field(..., index=True)
    location_id: str = Field(..., index=True)
    product_id: Optional[str] = Field(default=None, index=True)
    variant_id: Optional[str] = Field(default=None, index=True)
    qty_on_hand: int = Field(default=0)
    reorder_point: int = Field(default=0)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_stock_on_hand"
        indexes = [
            "tenant_id",
            "location_id",
            "product_id",
            "variant_id",
            ("tenant_id", "location_id", "product_id", "variant_id"),
        ]


class InventoryLedger(Document):
    tenant_id: str = Field(..., index=True)
    location_id: str = Field(..., index=True)
    product_id: Optional[str] = Field(default=None, index=True)
    variant_id: Optional[str] = Field(default=None, index=True)
    qty_delta: int = Field(...)
    reason: InventoryReason = Field(..., index=True)
    related_sale_id: Optional[str] = Field(default=None, index=True)
    related_refund_id: Optional[str] = Field(default=None, index=True)
    created_by_user_id: str = Field(..., index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow, index=True)
    notes: str = Field(default="")

    class Settings:
        name = "pos_inventory_ledger"
        indexes = [
            "tenant_id",
            "location_id",
            "reason",
            "created_at",
            ("tenant_id", "location_id", "created_at"),
        ]


class StorefrontSettings(Document):
    tenant_id: str = Field(..., index=True)
    name: str = Field(..., index=True)
    slug: str = Field(..., index=True)
    headline: Optional[str] = None
    description: Optional[str] = None
    primary_color: str = "#7c3aed"
    accent_color: str = "#f59e0b"
    logo_url: Optional[str] = None
    hero_image_url: Optional[str] = None
    show_out_of_stock: bool = False
    is_published: bool = False
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_storefront_settings"
        indexes = [
            "tenant_id",
            "slug",
            ("tenant_id", "slug"),
        ]


class PromotionCampaign(Document):
    tenant_id: str = Field(..., index=True)
    name: str = Field(..., index=True)
    description: Optional[str] = None
    status: CampaignStatus = Field(default=CampaignStatus.DRAFT, index=True)
    starts_at: Optional[datetime] = None
    ends_at: Optional[datetime] = None
    discount_id: Optional[str] = Field(default=None, index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_promotion_campaigns"
        indexes = [
            "tenant_id",
            "status",
            "starts_at",
            "ends_at",
        ]


class Coupon(Document):
    tenant_id: str = Field(..., index=True)
    code: str = Field(..., index=True)
    discount_id: str = Field(..., index=True)
    campaign_id: Optional[str] = Field(default=None, index=True)
    usage_limit: Optional[int] = Field(default=None, ge=0)
    usage_count: int = 0
    starts_at: Optional[datetime] = None
    ends_at: Optional[datetime] = None
    is_active: bool = True
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_coupons"
        indexes = [
            "tenant_id",
            "code",
            ("tenant_id", "code"),
        ]


class LoyaltyProgram(Document):
    tenant_id: str = Field(..., index=True)
    name: str = Field(..., index=True)
    points_per_currency_unit: int = Field(default=1, ge=0)  # points per $1
    redeem_rate_cents_per_point: int = Field(default=1, ge=0)
    is_active: bool = True
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_loyalty_programs"
        indexes = [
            "tenant_id",
            "is_active",
        ]


class LoyaltyAccount(Document):
    tenant_id: str = Field(..., index=True)
    customer_id: str = Field(..., index=True)
    points_balance: int = 0
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_loyalty_accounts"
        indexes = [
            "tenant_id",
            "customer_id",
            ("tenant_id", "customer_id"),
        ]


class LoyaltyLedger(Document):
    tenant_id: str = Field(..., index=True)
    customer_id: str = Field(..., index=True)
    sale_id: Optional[str] = Field(default=None, index=True)
    points_delta: int = 0
    reason: LoyaltyLedgerReason = Field(..., index=True)
    created_by_user_id: Optional[str] = Field(default=None, index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_loyalty_ledger"
        indexes = [
            "tenant_id",
            "customer_id",
            "reason",
            "created_at",
        ]


class EmployeeProfile(Document):
    tenant_id: str = Field(..., index=True)
    user_id: str = Field(..., index=True)
    job_title: Optional[str] = None
    hourly_rate_cents: Optional[int] = Field(default=None, ge=0)
    pos_pin: Optional[str] = None
    location_ids: List[str] = Field(default_factory=list)
    is_active: bool = True
    hire_date: Optional[date] = None
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_employee_profiles"
        indexes = [
            "tenant_id",
            "user_id",
        ]


class TimeClockEntry(Document):
    tenant_id: str = Field(..., index=True)
    user_id: str = Field(..., index=True)
    location_id: Optional[str] = Field(default=None, index=True)
    status: TimeClockStatus = Field(default=TimeClockStatus.OPEN, index=True)
    clock_in: datetime = Field(default_factory=datetime.utcnow)
    clock_out: Optional[datetime] = None
    break_minutes: int = 0
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_time_clock_entries"
        indexes = [
            "tenant_id",
            "user_id",
            "status",
            "clock_in",
        ]


class CustomerFeedback(Document):
    tenant_id: str = Field(..., index=True)
    sale_id: Optional[str] = Field(default=None, index=True)
    customer_id: Optional[str] = Field(default=None, index=True)
    rating: int = Field(..., ge=1, le=5)
    comment: Optional[str] = None
    response: Optional[str] = None
    status: FeedbackStatus = Field(default=FeedbackStatus.NEW, index=True)
    responded_by_user_id: Optional[str] = Field(default=None, index=True)
    responded_at: Optional[datetime] = None
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_customer_feedback"
        indexes = [
            "tenant_id",
            "rating",
            "status",
        ]


class Vendor(Document):
    tenant_id: str = Field(..., index=True)
    name: str = Field(..., index=True)
    contact_name: Optional[str] = None
    email: Optional[str] = None
    phone: Optional[str] = None
    address: Optional[str] = None
    notes: Optional[str] = None
    is_active: bool = True
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_vendors"
        indexes = [
            "tenant_id",
            "name",
        ]


class PurchaseOrder(Document):
    tenant_id: str = Field(..., index=True)
    vendor_id: str = Field(..., index=True)
    location_id: str = Field(..., index=True)
    status: PurchaseOrderStatus = Field(default=PurchaseOrderStatus.DRAFT, index=True)
    created_by_user_id: str = Field(..., index=True)
    items: List[PurchaseOrderItem] = Field(default_factory=list)
    total_cost_cents: int = 0
    ordered_at: Optional[datetime] = None
    expected_at: Optional[datetime] = None
    received_at: Optional[datetime] = None
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_purchase_orders"
        indexes = [
            "tenant_id",
            "vendor_id",
            "location_id",
            "status",
            "ordered_at",
        ]


class StockTransfer(Document):
    tenant_id: str = Field(..., index=True)
    from_location_id: str = Field(..., index=True)
    to_location_id: str = Field(..., index=True)
    status: StockTransferStatus = Field(default=StockTransferStatus.DRAFT, index=True)
    created_by_user_id: str = Field(..., index=True)
    items: List[StockTransferItem] = Field(default_factory=list)
    shipped_at: Optional[datetime] = None
    received_at: Optional[datetime] = None
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_stock_transfers"
        indexes = [
            "tenant_id",
            "from_location_id",
            "to_location_id",
            "status",
            "created_at",
        ]


class StockCount(Document):
    tenant_id: str = Field(..., index=True)
    location_id: str = Field(..., index=True)
    status: StockCountStatus = Field(default=StockCountStatus.OPEN, index=True)
    counted_by_user_id: str = Field(..., index=True)
    items: List[StockCountItem] = Field(default_factory=list)
    started_at: datetime = Field(default_factory=datetime.utcnow)
    completed_at: Optional[datetime] = None
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_stock_counts"
        indexes = [
            "tenant_id",
            "location_id",
            "status",
            "started_at",
        ]


class WorkOrder(Document):
    tenant_id: str = Field(..., index=True)
    location_id: Optional[str] = Field(default=None, index=True)
    customer_id: Optional[str] = Field(default=None, index=True)
    sale_id: Optional[str] = Field(default=None, index=True)
    title: str = Field(..., index=True)
    description: Optional[str] = None
    status: WorkOrderStatus = Field(default=WorkOrderStatus.OPEN, index=True)
    priority: Optional[str] = None
    assigned_to_user_id: Optional[str] = Field(default=None, index=True)
    due_at: Optional[datetime] = None
    items: List[WorkOrderItem] = Field(default_factory=list)
    notes: Optional[str] = None
    created_by_user_id: str = Field(..., index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_work_orders"
        indexes = [
            "tenant_id",
            "status",
            "assigned_to_user_id",
            "due_at",
        ]


class Appointment(Document):
    tenant_id: str = Field(..., index=True)
    customer_id: Optional[str] = Field(default=None, index=True)
    service_product_id: Optional[str] = Field(default=None, index=True)
    assigned_to_user_id: Optional[str] = Field(default=None, index=True)
    location_id: Optional[str] = Field(default=None, index=True)
    status: AppointmentStatus = Field(default=AppointmentStatus.SCHEDULED, index=True)
    start_at: datetime = Field(..., index=True)
    end_at: datetime = Field(..., index=True)
    notes: Optional[str] = None
    created_by_user_id: str = Field(..., index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_appointments"
        indexes = [
            "tenant_id",
            "status",
            "start_at",
            "assigned_to_user_id",
        ]


class SubscriptionPlan(Document):
    tenant_id: str = Field(..., index=True)
    name: str = Field(..., index=True)
    description: Optional[str] = None
    price_cents: int = Field(..., ge=0)
    interval: str = Field(default="month", index=True)
    interval_count: int = Field(default=1, ge=1)
    is_active: bool = True
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_subscription_plans"
        indexes = [
            "tenant_id",
            "interval",
            "is_active",
        ]


class CustomerSubscription(Document):
    tenant_id: str = Field(..., index=True)
    customer_id: str = Field(..., index=True)
    plan_id: str = Field(..., index=True)
    status: SubscriptionStatus = Field(default=SubscriptionStatus.ACTIVE, index=True)
    start_date: date = Field(default_factory=date.today)
    next_billing_date: date = Field(default_factory=date.today)
    last_billed_date: Optional[date] = None
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_customer_subscriptions"
        indexes = [
            "tenant_id",
            "customer_id",
            "plan_id",
            "status",
        ]


class SubscriptionInvoice(Document):
    tenant_id: str = Field(..., index=True)
    subscription_id: str = Field(..., index=True)
    amount_cents: int = Field(..., ge=0)
    due_date: date = Field(..., index=True)
    status: str = Field(default="due", index=True)
    paid_at: Optional[datetime] = None
    sale_id: Optional[str] = Field(default=None, index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "pos_subscription_invoices"
        indexes = [
            "tenant_id",
            "subscription_id",
            "status",
            "due_date",
        ]
