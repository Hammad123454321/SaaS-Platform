from __future__ import annotations

from datetime import datetime
from decimal import Decimal, ROUND_HALF_UP
from typing import List, Optional, Tuple

from fastapi import HTTPException, status

from app.models.pos import Coupon, Discount, PromotionCampaign, DiscountType


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


async def resolve_coupon_discount(
    tenant_id: str,
    code: str,
    base_cents: int,
) -> Tuple[Coupon, int]:
    coupon = await Coupon.find_one(
        Coupon.tenant_id == tenant_id,
        Coupon.code == code.upper(),
        Coupon.is_active == True,
    )
    if not coupon:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Coupon not found")

    now = datetime.utcnow()
    if coupon.starts_at and coupon.starts_at > now:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Coupon not active yet")
    if coupon.ends_at and coupon.ends_at < now:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Coupon expired")
    if coupon.usage_limit is not None and coupon.usage_count >= coupon.usage_limit:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Coupon usage limit reached")

    discount = await Discount.get(coupon.discount_id)
    if not discount or discount.tenant_id != tenant_id or not discount.is_active:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Coupon discount invalid")

    discount_amount = _resolve_discount_amount(
        base_cents,
        discount.discount_type,
        discount.value_bps,
        discount.value_cents,
    )
    return coupon, discount_amount


async def increment_coupon_usage(tenant_id: str, code: str, session=None) -> None:
    coupon = await Coupon.find_one(Coupon.tenant_id == tenant_id, Coupon.code == code.upper())
    if not coupon:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Coupon not found")
    if coupon.usage_limit is not None and coupon.usage_count >= coupon.usage_limit:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Coupon usage limit reached")

    collection = Coupon.get_motor_collection()
    await collection.update_one(
        {"_id": coupon.id},
        {"$inc": {"usage_count": 1}},
        session=session,
    )


async def create_campaign(tenant_id: str, payload) -> PromotionCampaign:
    campaign = PromotionCampaign(
        tenant_id=tenant_id,
        name=payload.name,
        description=payload.description,
        status=payload.status,
        starts_at=payload.starts_at,
        ends_at=payload.ends_at,
        discount_id=payload.discount_id,
    )
    await campaign.insert()
    return campaign


async def list_campaigns(tenant_id: str) -> List[PromotionCampaign]:
    return await PromotionCampaign.find(PromotionCampaign.tenant_id == tenant_id).sort(-PromotionCampaign.created_at).to_list()


async def create_coupon(tenant_id: str, payload) -> Coupon:
    code = payload.code.strip().upper()
    existing = await Coupon.find_one(Coupon.tenant_id == tenant_id, Coupon.code == code)
    if existing:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Coupon code already exists")

    discount = await Discount.get(payload.discount_id)
    if not discount or discount.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Discount not found")

    coupon = Coupon(
        tenant_id=tenant_id,
        code=code,
        discount_id=payload.discount_id,
        campaign_id=payload.campaign_id,
        usage_limit=payload.usage_limit,
        starts_at=payload.starts_at,
        ends_at=payload.ends_at,
        is_active=payload.is_active,
    )
    await coupon.insert()
    return coupon


async def list_coupons(tenant_id: str) -> List[Coupon]:
    return await Coupon.find(Coupon.tenant_id == tenant_id).sort(-Coupon.created_at).to_list()
