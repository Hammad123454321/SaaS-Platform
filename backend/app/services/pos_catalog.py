from __future__ import annotations

import re
from datetime import datetime
from typing import List, Optional, Dict

from fastapi import HTTPException, status
from beanie import PydanticObjectId

from app.models.pos import (
    Category,
    Tax,
    Discount,
    Product,
    Variant,
    Customer,
    Location,
    Register,
)
from app.schemas.pos import (
    CategoryCreateRequest,
    TaxCreateRequest,
    DiscountCreateRequest,
    ProductCreateRequest,
    VariantCreateRequest,
    CustomerCreateRequest,
    LocationCreateRequest,
    RegisterCreateRequest,
    BulkProductItem,
)


async def create_category(tenant_id: str, payload: CategoryCreateRequest) -> Category:
    category = Category(
        tenant_id=tenant_id,
        name=payload.name,
        parent_id=payload.parent_id,
        sort_order=payload.sort_order,
        is_active=payload.is_active,
    )
    await category.insert()
    return category


async def create_tax(tenant_id: str, payload: TaxCreateRequest) -> Tax:
    tax = Tax(
        tenant_id=tenant_id,
        name=payload.name,
        rate_bps=payload.rate_bps,
        is_inclusive=payload.is_inclusive,
        is_active=payload.is_active,
    )
    await tax.insert()
    return tax


async def create_discount(tenant_id: str, payload: DiscountCreateRequest) -> Discount:
    discount = Discount(
        tenant_id=tenant_id,
        name=payload.name,
        discount_type=payload.discount_type,
        value_bps=payload.value_bps,
        value_cents=payload.value_cents,
        applies_to=payload.applies_to,
        is_active=payload.is_active,
    )
    await discount.insert()
    return discount


async def create_product(tenant_id: str, payload: ProductCreateRequest) -> Product:
    if payload.sku:
        existing = await Product.find_one(Product.tenant_id == tenant_id, Product.sku == payload.sku)
        if existing:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="SKU already exists")

    if payload.barcode:
        existing = await Product.find_one(Product.tenant_id == tenant_id, Product.barcode == payload.barcode)
        if existing:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Barcode already exists")

    product = Product(
        tenant_id=tenant_id,
        name=payload.name,
        description=payload.description or "",
        category_id=payload.category_id,
        sku=payload.sku,
        barcode=payload.barcode,
        image_url=payload.image_url,
        base_price_cents=payload.base_price_cents,
        cost_cents=payload.cost_cents,
        tax_ids=payload.tax_ids or [],
        is_service=payload.is_service,
        is_subscription=payload.is_subscription,
        is_kitchen_item=payload.is_kitchen_item,
        requires_id_check=payload.requires_id_check,
        minimum_age=payload.minimum_age,
        is_active=payload.is_active,
    )
    await product.insert()
    return product


async def create_variant(tenant_id: str, payload: VariantCreateRequest) -> Variant:
    product = await Product.get(payload.product_id)
    if not product or product.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Product not found")

    if payload.sku:
        existing = await Variant.find_one(Variant.tenant_id == tenant_id, Variant.sku == payload.sku)
        if existing:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="SKU already exists")

    if payload.barcode:
        existing = await Variant.find_one(Variant.tenant_id == tenant_id, Variant.barcode == payload.barcode)
        if existing:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Barcode already exists")

    variant = Variant(
        tenant_id=tenant_id,
        product_id=payload.product_id,
        name=payload.name,
        sku=payload.sku,
        barcode=payload.barcode,
        image_url=payload.image_url,
        price_cents=payload.price_cents,
        cost_cents=payload.cost_cents,
        attributes=payload.attributes or {},
        tax_ids=payload.tax_ids or [],
        is_service=payload.is_service,
        is_subscription=payload.is_subscription,
        is_kitchen_item=payload.is_kitchen_item,
        requires_id_check=payload.requires_id_check,
        minimum_age=payload.minimum_age,
        is_active=payload.is_active,
    )
    await variant.insert()
    return variant


async def create_customer(tenant_id: str, payload: CustomerCreateRequest) -> Customer:
    customer = Customer(
        tenant_id=tenant_id,
        name=payload.name,
        email=payload.email,
        phone=payload.phone,
        notes=payload.notes or "",
    )
    await customer.insert()
    return customer


async def list_customers(tenant_id: str, search: Optional[str] = None) -> List[Customer]:
    conditions = [Customer.tenant_id == tenant_id]
    if search:
        regex = re.compile(f".*{re.escape(search)}.*", re.IGNORECASE)
        conditions.append(
            {
                "$or": [
                    {"name": {"$regex": regex}},
                    {"email": {"$regex": regex}},
                    {"phone": {"$regex": regex}},
                ]
            }
        )
    return await Customer.find(*conditions).sort(Customer.name).to_list()


async def create_location(tenant_id: str, payload: LocationCreateRequest) -> Location:
    existing = await Location.find_one(Location.tenant_id == tenant_id, Location.code == payload.code)
    if existing:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Location code already exists")

    location = Location(
        tenant_id=tenant_id,
        name=payload.name,
        code=payload.code,
        address=payload.address,
        timezone=payload.timezone or "UTC",
        is_active=payload.is_active,
    )
    await location.insert()
    return location


async def create_register(tenant_id: str, payload: RegisterCreateRequest) -> Register:
    location = await Location.get(payload.location_id)
    if not location or location.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Location not found")

    register = Register(
        tenant_id=tenant_id,
        location_id=payload.location_id,
        name=payload.name,
        is_active=payload.is_active,
    )
    await register.insert()
    return register


async def list_locations(tenant_id: str) -> List[Location]:
    return await Location.find(Location.tenant_id == tenant_id, Location.is_active == True).to_list()


async def list_registers(tenant_id: str, location_id: Optional[str] = None) -> List[Register]:
    conditions = [Register.tenant_id == tenant_id, Register.is_active == True]
    if location_id:
        conditions.append(Register.location_id == location_id)
    return await Register.find(*conditions).to_list()


async def list_categories(tenant_id: str) -> List[Category]:
    return await Category.find(Category.tenant_id == tenant_id, Category.is_active == True).sort(Category.sort_order).to_list()


async def list_taxes(tenant_id: str) -> List[Tax]:
    return await Tax.find(Tax.tenant_id == tenant_id, Tax.is_active == True).to_list()


async def list_discounts(tenant_id: str) -> List[Discount]:
    return await Discount.find(Discount.tenant_id == tenant_id, Discount.is_active == True).to_list()


async def search_products(
    tenant_id: str,
    search: Optional[str] = None,
    barcode: Optional[str] = None,
    category_id: Optional[str] = None,
    limit: int = 50,
    offset: int = 0,
) -> List[Dict]:
    results: List[Dict] = []

    if barcode:
        variant = await Variant.find_one(
            Variant.tenant_id == tenant_id,
            Variant.barcode == barcode,
            Variant.is_active == True,
        )
        if variant:
            product = await Product.get(variant.product_id)
            if product and product.tenant_id == tenant_id:
                results.append({
                    "product_id": str(product.id),
                    "variant_id": str(variant.id),
                    "name": f"{product.name} - {variant.name}",
                    "sku": variant.sku or product.sku,
                    "barcode": variant.barcode,
                    "image_url": variant.image_url or product.image_url,
                    "price_cents": variant.price_cents,
                    "tax_ids": variant.tax_ids or product.tax_ids,
                    "category_id": product.category_id,
                    "is_service": variant.is_service or product.is_service,
                    "is_subscription": variant.is_subscription or product.is_subscription,
                    "is_kitchen_item": variant.is_kitchen_item or product.is_kitchen_item,
                    "requires_id_check": variant.requires_id_check or product.requires_id_check,
                    "minimum_age": variant.minimum_age or product.minimum_age,
                })
        product = await Product.find_one(
            Product.tenant_id == tenant_id,
            Product.barcode == barcode,
            Product.is_active == True,
        )
        if product:
            results.append({
                "product_id": str(product.id),
                "variant_id": None,
                "name": product.name,
                "sku": product.sku,
                "barcode": product.barcode,
                "image_url": product.image_url,
                "price_cents": product.base_price_cents,
                "tax_ids": product.tax_ids,
                "category_id": product.category_id,
                "is_service": product.is_service,
                "is_subscription": product.is_subscription,
                "is_kitchen_item": product.is_kitchen_item,
                "requires_id_check": product.requires_id_check,
                "minimum_age": product.minimum_age,
            })
        return results

    search_regex = None
    if search:
        search_regex = re.compile(f".*{re.escape(search)}.*", re.IGNORECASE)

    product_conditions = [Product.tenant_id == tenant_id, Product.is_active == True]
    variant_conditions = [Variant.tenant_id == tenant_id, Variant.is_active == True]
    if category_id:
        product_conditions.append(Product.category_id == category_id)

    if search_regex:
        product_conditions.append({
            "$or": [
                {"name": {"$regex": search_regex}},
                {"sku": {"$regex": search_regex}},
                {"barcode": {"$regex": search_regex}},
            ]
        })
        variant_conditions.append({
            "$or": [
                {"name": {"$regex": search_regex}},
                {"sku": {"$regex": search_regex}},
                {"barcode": {"$regex": search_regex}},
            ]
        })

    products = await Product.find(*product_conditions).skip(offset).limit(limit).to_list()
    for product in products:
        results.append({
            "product_id": str(product.id),
            "variant_id": None,
            "name": product.name,
            "sku": product.sku,
            "barcode": product.barcode,
            "image_url": product.image_url,
            "price_cents": product.base_price_cents,
            "tax_ids": product.tax_ids,
            "category_id": product.category_id,
            "is_service": product.is_service,
            "is_subscription": product.is_subscription,
            "is_kitchen_item": product.is_kitchen_item,
            "requires_id_check": product.requires_id_check,
            "minimum_age": product.minimum_age,
        })

    variants = await Variant.find(*variant_conditions).skip(offset).limit(limit).to_list()
    if variants:
        product_ids = list({variant.product_id for variant in variants})
        object_ids = [PydanticObjectId(pid) for pid in product_ids if pid]
        products_map = {
            str(product.id): product
            for product in await Product.find({"_id": {"$in": object_ids}}).to_list()
        }
        for variant in variants:
            product = products_map.get(variant.product_id)
            if not product:
                continue
            results.append({
                "product_id": str(product.id),
                "variant_id": str(variant.id),
                "name": f"{product.name} - {variant.name}",
                "sku": variant.sku or product.sku,
                "barcode": variant.barcode,
                "image_url": variant.image_url or product.image_url,
                "price_cents": variant.price_cents,
                "tax_ids": variant.tax_ids or product.tax_ids,
                "category_id": product.category_id,
                "is_service": variant.is_service or product.is_service,
                "is_subscription": variant.is_subscription or product.is_subscription,
                "is_kitchen_item": variant.is_kitchen_item or product.is_kitchen_item,
                "requires_id_check": variant.requires_id_check or product.requires_id_check,
                "minimum_age": variant.minimum_age or product.minimum_age,
            })

    return results[:limit]


async def bulk_upsert_products(tenant_id: str, items: List[BulkProductItem]) -> List[Product]:
    results: List[Product] = []
    for item in items:
        category_id = item.category_id or None
        if item.product_id:
            product = await Product.get(item.product_id)
            if not product or product.tenant_id != tenant_id:
                raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Product not found")

            if item.sku and item.sku != product.sku:
                existing = await Product.find_one(Product.tenant_id == tenant_id, Product.sku == item.sku)
                if existing and str(existing.id) != str(product.id):
                    raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="SKU already exists")

            if item.barcode and item.barcode != product.barcode:
                existing = await Product.find_one(Product.tenant_id == tenant_id, Product.barcode == item.barcode)
                if existing and str(existing.id) != str(product.id):
                    raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Barcode already exists")

            product.name = item.name
            product.description = item.description or ""
            product.category_id = category_id
            product.sku = item.sku
            product.barcode = item.barcode
            product.image_url = item.image_url
            product.base_price_cents = item.base_price_cents
            product.cost_cents = item.cost_cents
            product.tax_ids = item.tax_ids or []
            product.is_service = item.is_service
            product.is_subscription = item.is_subscription
            product.is_kitchen_item = item.is_kitchen_item
            product.requires_id_check = item.requires_id_check
            product.minimum_age = item.minimum_age
            product.is_active = item.is_active
            product.updated_at = datetime.utcnow()
            await product.save()
            results.append(product)
            continue

        if item.sku:
            existing = await Product.find_one(Product.tenant_id == tenant_id, Product.sku == item.sku)
            if existing:
                raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="SKU already exists")

        if item.barcode:
            existing = await Product.find_one(Product.tenant_id == tenant_id, Product.barcode == item.barcode)
            if existing:
                raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Barcode already exists")

        product = Product(
            tenant_id=tenant_id,
            name=item.name,
            description=item.description or "",
            category_id=category_id,
            sku=item.sku,
            barcode=item.barcode,
            image_url=item.image_url,
            base_price_cents=item.base_price_cents,
            cost_cents=item.cost_cents,
            tax_ids=item.tax_ids or [],
            is_service=item.is_service,
            is_subscription=item.is_subscription,
            is_kitchen_item=item.is_kitchen_item,
            requires_id_check=item.requires_id_check,
            minimum_age=item.minimum_age,
            is_active=item.is_active,
            created_at=datetime.utcnow(),
            updated_at=datetime.utcnow(),
        )
        await product.insert()
        results.append(product)

    return results
