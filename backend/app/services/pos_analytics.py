from __future__ import annotations

from datetime import datetime, date, timedelta
from typing import List, Dict, Any

from app.models.pos import (
    Sale,
    Refund,
    SaleItem,
    Payment,
    StockOnHand,
    Product,
    Variant,
    LoyaltyLedger,
    CustomerFeedback,
)
from beanie import PydanticObjectId


def _date_range(start_date: date, end_date: date) -> tuple[datetime, datetime]:
    start_dt = datetime.combine(start_date, datetime.min.time())
    end_dt = datetime.combine(end_date, datetime.max.time())
    return start_dt, end_dt


async def get_sales_kpis(tenant_id: str, start_date: date, end_date: date) -> Dict[str, Any]:
    start_dt, end_dt = _date_range(start_date, end_date)
    sales_collection = Sale.get_motor_collection()

    pipeline = [
        {
            "$match": {
                "tenant_id": tenant_id,
                "status": "completed",
                "completed_at": {"$gte": start_dt, "$lte": end_dt},
            }
        },
        {
            "$group": {
                "_id": None,
                "gross_sales": {"$sum": "$total_cents"},
                "taxes": {"$sum": "$tax_cents"},
                "discounts": {"$sum": "$discount_cents"},
                "orders": {"$sum": 1},
                "items": {"$sum": "$items_count"},
            }
        },
    ]

    result = await sales_collection.aggregate(pipeline).to_list(length=1)
    summary = result[0] if result else {}

    refund_collection = Refund.get_motor_collection()
    refund_pipeline = [
        {
            "$match": {
                "tenant_id": tenant_id,
                "status": "completed",
                "created_at": {"$gte": start_dt, "$lte": end_dt},
            }
        },
        {"$group": {"_id": None, "refunds": {"$sum": "$total_cents"}}},
    ]
    refund_result = await refund_collection.aggregate(refund_pipeline).to_list(length=1)
    refunds_total = refund_result[0]["refunds"] if refund_result else 0

    gross_sales = summary.get("gross_sales", 0)
    orders = summary.get("orders", 0) or 0
    items = summary.get("items", 0) or 0

    avg_order = int(gross_sales / orders) if orders else 0
    items_per_order = round(items / orders, 2) if orders else 0

    return {
        "gross_sales_cents": gross_sales,
        "net_sales_cents": gross_sales - refunds_total,
        "refunds_cents": refunds_total,
        "discounts_cents": summary.get("discounts", 0),
        "taxes_cents": summary.get("taxes", 0),
        "orders": orders,
        "items": items,
        "avg_order_cents": avg_order,
        "items_per_order": items_per_order,
    }


async def get_sales_trends(
    tenant_id: str,
    start_date: date,
    end_date: date,
    granularity: str = "day",
) -> List[Dict[str, Any]]:
    start_dt, end_dt = _date_range(start_date, end_date)

    if granularity == "month":
        group_key = {
            "$dateToString": {"format": "%Y-%m", "date": "$completed_at"}
        }
    elif granularity == "week":
        group_key = {"year": {"$isoWeekYear": "$completed_at"}, "week": {"$isoWeek": "$completed_at"}}
    else:
        group_key = {
            "$dateToString": {"format": "%Y-%m-%d", "date": "$completed_at"}
        }

    pipeline = [
        {
            "$match": {
                "tenant_id": tenant_id,
                "status": "completed",
                "completed_at": {"$gte": start_dt, "$lte": end_dt},
            }
        },
        {
            "$group": {
                "_id": group_key,
                "gross_sales": {"$sum": "$total_cents"},
                "orders": {"$sum": 1},
            }
        },
        {"$sort": {"_id": 1}},
    ]

    collection = Sale.get_motor_collection()
    rows = await collection.aggregate(pipeline).to_list(length=None)

    results = []
    for row in rows:
        label = row["_id"]
        if isinstance(label, dict):
            label = f"{label['year']}-W{label['week']}"
        results.append({
            "period": label,
            "gross_sales_cents": row.get("gross_sales", 0),
            "orders": row.get("orders", 0),
        })

    return results


async def get_top_products(
    tenant_id: str,
    start_date: date,
    end_date: date,
    limit: int = 10,
) -> List[Dict[str, Any]]:
    start_dt, end_dt = _date_range(start_date, end_date)
    collection = SaleItem.get_motor_collection()

    pipeline = [
        {
            "$match": {
                "tenant_id": tenant_id,
                "sale_completed_at": {"$gte": start_dt, "$lte": end_dt},
            }
        },
        {
            "$group": {
                "_id": {
                    "product_id": "$product_id",
                    "variant_id": "$variant_id",
                    "name": "$product_name",
                    "variant": "$variant_name",
                },
                "qty": {"$sum": "$quantity"},
                "sales": {"$sum": "$line_total_cents"},
            }
        },
        {"$sort": {"sales": -1}},
        {"$limit": limit},
    ]

    rows = await collection.aggregate(pipeline).to_list(length=None)
    return [
        {
            "product_id": row["_id"]["product_id"],
            "variant_id": row["_id"]["variant_id"],
            "name": row["_id"]["name"],
            "variant": row["_id"]["variant"],
            "quantity": row.get("qty", 0),
            "sales_cents": row.get("sales", 0),
        }
        for row in rows
    ]


async def get_top_categories(
    tenant_id: str,
    start_date: date,
    end_date: date,
    limit: int = 10,
) -> List[Dict[str, Any]]:
    start_dt, end_dt = _date_range(start_date, end_date)
    collection = SaleItem.get_motor_collection()

    pipeline = [
        {
            "$match": {
                "tenant_id": tenant_id,
                "sale_completed_at": {"$gte": start_dt, "$lte": end_dt},
            }
        },
        {
            "$group": {
                "_id": "$category_id",
                "qty": {"$sum": "$quantity"},
                "sales": {"$sum": "$line_total_cents"},
            }
        },
        {"$sort": {"sales": -1}},
        {"$limit": limit},
    ]

    rows = await collection.aggregate(pipeline).to_list(length=None)
    return [
        {
            "category_id": row.get("_id"),
            "quantity": row.get("qty", 0),
            "sales_cents": row.get("sales", 0),
        }
        for row in rows
    ]


async def get_payment_breakdown(
    tenant_id: str,
    start_date: date,
    end_date: date,
) -> List[Dict[str, Any]]:
    start_dt, end_dt = _date_range(start_date, end_date)
    collection = Payment.get_motor_collection()

    pipeline = [
        {
            "$match": {
                "tenant_id": tenant_id,
                "sale_completed_at": {"$gte": start_dt, "$lte": end_dt},
            }
        },
        {
            "$group": {
                "_id": "$method",
                "amount": {"$sum": "$amount_cents"},
            }
        },
        {"$sort": {"amount": -1}},
    ]

    rows = await collection.aggregate(pipeline).to_list(length=None)
    return [
        {"method": row.get("_id"), "amount_cents": row.get("amount", 0)}
        for row in rows
    ]


async def get_low_stock(tenant_id: str, limit: int = 20) -> List[Dict[str, Any]]:
    items = await StockOnHand.find(
        StockOnHand.tenant_id == tenant_id,
        {"reorder_point": {"$gt": 0}},
    ).to_list()

    items = [item for item in items if item.qty_on_hand <= item.reorder_point][:limit]

    product_ids = {item.product_id for item in items if item.product_id}
    variant_ids = {item.variant_id for item in items if item.variant_id}

    product_map: Dict[str, Product] = {}
    variant_map: Dict[str, Variant] = {}

    if product_ids:
        product_objects = [PydanticObjectId(pid) for pid in product_ids if pid]
        products = await Product.find({"_id": {"$in": product_objects}}).to_list()
        product_map = {str(product.id): product for product in products}

    if variant_ids:
        variant_objects = [PydanticObjectId(vid) for vid in variant_ids if vid]
        variants = await Variant.find({"_id": {"$in": variant_objects}}).to_list()
        variant_map = {str(variant.id): variant for variant in variants}

    results = []
    for item in items:
        product = product_map.get(item.product_id)
        variant = variant_map.get(item.variant_id)
        results.append(
            {
                "location_id": item.location_id,
                "product_id": item.product_id,
                "variant_id": item.variant_id,
                "product_name": product.name if product else None,
                "variant_name": variant.name if variant else None,
                "qty_on_hand": item.qty_on_hand,
                "reorder_point": item.reorder_point,
            }
        )

    return results


async def get_employee_performance(
    tenant_id: str,
    start_date: date,
    end_date: date,
) -> List[Dict[str, Any]]:
    start_dt, end_dt = _date_range(start_date, end_date)
    collection = Sale.get_motor_collection()
    pipeline = [
        {
            "$match": {
                "tenant_id": tenant_id,
                "status": "completed",
                "completed_at": {"$gte": start_dt, "$lte": end_dt},
            }
        },
        {
            "$group": {
                "_id": "$cashier_id",
                "orders": {"$sum": 1},
                "sales": {"$sum": "$total_cents"},
            }
        },
        {"$sort": {"sales": -1}},
    ]
    rows = await collection.aggregate(pipeline).to_list(length=None)
    return [{"cashier_id": row["_id"], "orders": row["orders"], "sales_cents": row["sales"]} for row in rows]


async def get_inventory_valuation(tenant_id: str) -> Dict[str, Any]:
    stocks = await StockOnHand.find(StockOnHand.tenant_id == tenant_id).to_list()
    product_ids = {s.product_id for s in stocks if s.product_id}
    variant_ids = {s.variant_id for s in stocks if s.variant_id}

    product_map: Dict[str, Product] = {}
    variant_map: Dict[str, Variant] = {}
    if product_ids:
        product_objects = [PydanticObjectId(pid) for pid in product_ids if pid]
        products = await Product.find({"_id": {"$in": product_objects}}).to_list()
        product_map = {str(p.id): p for p in products}
    if variant_ids:
        variant_objects = [PydanticObjectId(vid) for vid in variant_ids if vid]
        variants = await Variant.find({"_id": {"$in": variant_objects}}).to_list()
        variant_map = {str(v.id): v for v in variants}

    total_value = 0
    for stock in stocks:
        cost = 0
        if stock.variant_id and stock.variant_id in variant_map and variant_map[stock.variant_id].cost_cents:
            cost = variant_map[stock.variant_id].cost_cents or 0
        elif stock.product_id and stock.product_id in product_map and product_map[stock.product_id].cost_cents:
            cost = product_map[stock.product_id].cost_cents or 0
        total_value += cost * int(stock.qty_on_hand)

    return {"inventory_value_cents": total_value}


async def get_loyalty_summary(tenant_id: str, start_date: date, end_date: date) -> Dict[str, Any]:
    start_dt, end_dt = _date_range(start_date, end_date)
    collection = LoyaltyLedger.get_motor_collection()
    pipeline = [
        {
            "$match": {
                "tenant_id": tenant_id,
                "created_at": {"$gte": start_dt, "$lte": end_dt},
            }
        },
        {
            "$group": {
                "_id": "$reason",
                "points": {"$sum": "$points_delta"},
            }
        },
    ]
    rows = await collection.aggregate(pipeline).to_list(length=None)
    summary = {row["_id"]: row["points"] for row in rows}
    return {
        "points_earned": max(summary.get("earn", 0), 0),
        "points_redeemed": abs(min(summary.get("redeem", 0), 0)),
    }


async def get_feedback_summary(tenant_id: str, start_date: date, end_date: date) -> Dict[str, Any]:
    start_dt, end_dt = _date_range(start_date, end_date)
    collection = CustomerFeedback.get_motor_collection()
    pipeline = [
        {
            "$match": {
                "tenant_id": tenant_id,
                "created_at": {"$gte": start_dt, "$lte": end_dt},
            }
        },
        {
            "$group": {
                "_id": None,
                "avg_rating": {"$avg": "$rating"},
                "count": {"$sum": 1},
            }
        },
    ]
    rows = await collection.aggregate(pipeline).to_list(length=1)
    if not rows:
        return {"average_rating": 0, "feedback_count": 0}
    return {"average_rating": round(rows[0].get("avg_rating", 0), 2), "feedback_count": rows[0].get("count", 0)}
