from __future__ import annotations

from typing import Optional
from datetime import datetime

from fastapi import HTTPException, status

from app.models.pos import StorefrontSettings


async def get_storefront_settings(tenant_id: str) -> Optional[StorefrontSettings]:
    return await StorefrontSettings.find_one(StorefrontSettings.tenant_id == tenant_id)


async def upsert_storefront_settings(tenant_id: str, payload) -> StorefrontSettings:
    existing = await StorefrontSettings.find_one(StorefrontSettings.tenant_id == tenant_id)
    if existing:
        existing.name = payload.name
        existing.slug = payload.slug
        existing.headline = payload.headline
        existing.description = payload.description
        existing.primary_color = payload.primary_color
        existing.accent_color = payload.accent_color
        existing.logo_url = payload.logo_url
        existing.hero_image_url = payload.hero_image_url
        existing.show_out_of_stock = payload.show_out_of_stock
        existing.is_published = payload.is_published
        existing.updated_at = datetime.utcnow()
        await existing.save()
        return existing

    settings = StorefrontSettings(
        tenant_id=tenant_id,
        name=payload.name,
        slug=payload.slug,
        headline=payload.headline,
        description=payload.description,
        primary_color=payload.primary_color,
        accent_color=payload.accent_color,
        logo_url=payload.logo_url,
        hero_image_url=payload.hero_image_url,
        show_out_of_stock=payload.show_out_of_stock,
        is_published=payload.is_published,
    )
    await settings.insert()
    return settings
