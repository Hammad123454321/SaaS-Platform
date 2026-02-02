from __future__ import annotations

from dataclasses import dataclass
from decimal import Decimal, ROUND_HALF_UP
from typing import List, Optional, Dict

from fastapi import HTTPException, status

from app.models.pos import Discount, DiscountType, Tax, Product, Variant
from app.schemas.pos import SaleItemInput, SaleDiscountInput


@dataclass
class PricedItem:
    product_id: Optional[str]
    variant_id: Optional[str]
    category_id: Optional[str]
    product_name: str
    variant_name: Optional[str]
    sku: Optional[str]
    quantity: int
    unit_price_cents: int
    discount_cents: int
    tax_cents: int
    line_total_cents: int
    tax_ids: List[str]
    is_kitchen_item: bool
    requires_id_check: bool
    minimum_age: Optional[int]
    is_service: bool
    is_subscription: bool


def _round_cents(value: Decimal) -> int:
    return int(value.quantize(Decimal("1"), rounding=ROUND_HALF_UP))


def _calc_percent_cents(base_cents: int, bps: int) -> int:
    return _round_cents(Decimal(base_cents) * Decimal(bps) / Decimal(10000))


def _resolve_discount_amount(
    base_cents: int,
    discount_type: Optional[DiscountType],
    discount_bps: Optional[int],
    discount_cents: Optional[int],
) -> int:
    if discount_type == DiscountType.PERCENT or discount_bps is not None:
        bps_value = discount_bps or 0
        return min(base_cents, _calc_percent_cents(base_cents, bps_value))
    if discount_type == DiscountType.FIXED or discount_cents is not None:
        return min(base_cents, discount_cents or 0)
    return 0


def _allocate_discount(total_discount: int, line_amounts: List[int]) -> List[int]:
    if total_discount <= 0 or not line_amounts:
        return [0 for _ in line_amounts]

    total_base = sum(line_amounts)
    if total_base <= 0:
        return [0 for _ in line_amounts]

    raw_shares = []
    allocations = []
    for amount in line_amounts:
        raw = Decimal(total_discount) * Decimal(amount) / Decimal(total_base)
        rounded = _round_cents(raw)
        rounded = min(rounded, amount)
        raw_shares.append(raw)
        allocations.append(rounded)

    remainder = total_discount - sum(allocations)
    if remainder == 0:
        return allocations

    order = sorted(
        range(len(line_amounts)),
        key=lambda idx: (raw_shares[idx] - Decimal(allocations[idx])),
        reverse=remainder > 0,
    )

    step = 1 if remainder > 0 else -1
    remaining = abs(remainder)
    i = 0
    while remaining > 0 and order:
        idx = order[i % len(order)]
        if allocations[idx] + step >= 0 and allocations[idx] + step <= line_amounts[idx]:
            allocations[idx] += step
            remaining -= 1
        i += 1
        if i > len(order) * 2 and remaining > 0:
            break

    return allocations


async def _load_discount(tenant_id: str, discount_id: Optional[str]) -> Optional[Discount]:
    if not discount_id:
        return None
    discount = await Discount.get(discount_id)
    if not discount or discount.tenant_id != tenant_id or not discount.is_active:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Discount not found")
    return discount


async def _load_taxes(tenant_id: str, tax_ids: List[str]) -> List[Tax]:
    taxes: List[Tax] = []
    for tax_id in tax_ids:
        tax = await Tax.get(tax_id)
        if tax and tax.tenant_id == tenant_id and tax.is_active:
            taxes.append(tax)
    return taxes


async def _resolve_product_variant(tenant_id: str, item: SaleItemInput) -> Dict:
    if not item.product_id and not item.variant_id:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Item must include product_id or variant_id")

    product = None
    variant = None

    if item.variant_id:
        variant = await Variant.get(item.variant_id)
        if not variant or variant.tenant_id != tenant_id or not variant.is_active:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Variant not found")
        product = await Product.get(variant.product_id)
    else:
        product = await Product.get(item.product_id)

    if not product or product.tenant_id != tenant_id or not product.is_active:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Product not found")

    return {
        "product": product,
        "variant": variant,
    }


async def calculate_pricing(
    tenant_id: str,
    items: List[SaleItemInput],
    order_discount: Optional[SaleDiscountInput] = None,
    extra_discount_cents: int = 0,
) -> Dict:
    priced_items: List[PricedItem] = []
    line_taxable_amounts: List[int] = []
    base_subtotals: List[int] = []

    for item in items:
        resolved = await _resolve_product_variant(tenant_id, item)
        product: Product = resolved["product"]
        variant: Variant | None = resolved["variant"]

        unit_price_cents = item.price_override_cents
        if unit_price_cents is None:
            unit_price_cents = variant.price_cents if variant else product.base_price_cents

        unit_price_cents = max(unit_price_cents, 0)
        base_subtotal = unit_price_cents * item.quantity

        discount = await _load_discount(tenant_id, item.discount_id)
        discount_type = item.discount_type or (discount.discount_type if discount else None)
        discount_bps = item.discount_bps if item.discount_bps is not None else (discount.value_bps if discount else None)
        discount_cents = item.discount_cents if item.discount_cents is not None else (discount.value_cents if discount else None)
        line_discount_cents = _resolve_discount_amount(base_subtotal, discount_type, discount_bps, discount_cents)

        tax_ids = item.tax_ids if item.tax_ids is not None else (variant.tax_ids if variant and variant.tax_ids else product.tax_ids)
        taxable_amount = max(base_subtotal - line_discount_cents, 0)
        is_kitchen_item = (variant.is_kitchen_item if variant else False) or product.is_kitchen_item
        requires_id_check = (variant.requires_id_check if variant else False) or product.requires_id_check
        minimum_age = variant.minimum_age if variant and variant.minimum_age is not None else product.minimum_age
        is_service = (variant.is_service if variant else False) or product.is_service
        is_subscription = (variant.is_subscription if variant else False) or product.is_subscription

        priced_items.append(
            PricedItem(
                product_id=str(product.id),
                variant_id=str(variant.id) if variant else None,
                category_id=product.category_id,
                product_name=product.name,
                variant_name=variant.name if variant else None,
                sku=variant.sku if variant and variant.sku else product.sku,
                quantity=item.quantity,
                unit_price_cents=unit_price_cents,
                discount_cents=line_discount_cents,
                tax_cents=0,
                line_total_cents=0,
                tax_ids=tax_ids,
                is_kitchen_item=is_kitchen_item,
                requires_id_check=requires_id_check,
                minimum_age=minimum_age,
                is_service=is_service,
                is_subscription=is_subscription,
            )
        )
        line_taxable_amounts.append(taxable_amount)
        base_subtotals.append(base_subtotal)

    order_discount_value = 0
    if order_discount:
        discount = await _load_discount(tenant_id, order_discount.discount_id)
        discount_type = order_discount.discount_type or (discount.discount_type if discount else None)
        discount_bps = order_discount.discount_bps if order_discount.discount_bps is not None else (discount.value_bps if discount else None)
        discount_cents = order_discount.discount_cents if order_discount.discount_cents is not None else (discount.value_cents if discount else None)
        order_discount_value = _resolve_discount_amount(sum(line_taxable_amounts), discount_type, discount_bps, discount_cents)

    if extra_discount_cents:
        order_discount_value = min(order_discount_value + max(extra_discount_cents, 0), sum(line_taxable_amounts))

    allocations = _allocate_discount(order_discount_value, line_taxable_amounts)

    total_tax_cents = 0
    total_line_total = 0

    for idx, priced in enumerate(priced_items):
        taxable_after_order_discount = max(line_taxable_amounts[idx] - allocations[idx], 0)
        taxes = await _load_taxes(tenant_id, priced.tax_ids)

        line_tax_total = 0
        line_total = taxable_after_order_discount
        for tax in taxes:
            rate = Decimal(tax.rate_bps) / Decimal(10000)
            if tax.is_inclusive:
                tax_amount = _round_cents(Decimal(taxable_after_order_discount) * rate / (Decimal(1) + rate))
            else:
                tax_amount = _round_cents(Decimal(taxable_after_order_discount) * rate)
                line_total += tax_amount
            line_tax_total += tax_amount

        priced.tax_cents = line_tax_total
        priced.line_total_cents = line_total
        total_tax_cents += line_tax_total
        total_line_total += line_total

    subtotal_cents = sum(base_subtotals)
    total_discount_cents = sum(p.discount_cents for p in priced_items) + order_discount_value
    items_count = sum(p.quantity for p in priced_items)

    return {
        "items": priced_items,
        "subtotal_cents": subtotal_cents,
        "discount_cents": total_discount_cents,
        "tax_cents": total_tax_cents,
        "total_cents": total_line_total,
        "items_count": items_count,
        "order_discount_cents": order_discount_value,
    }
