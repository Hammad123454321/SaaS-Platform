# POS Release Notes

## Backend
New models (Beanie)
- `pos_locations`, `pos_registers`, `pos_register_sessions`, `pos_cash_movements`, `pos_cash_counts`
- `pos_categories`, `pos_products`, `pos_variants`, `pos_taxes`, `pos_discounts`, `pos_customers`
- `pos_sales`, `pos_sale_items`, `pos_payments`, `pos_receipts`
- `pos_refunds`, `pos_refund_items`
- `pos_stock_on_hand`, `pos_inventory_ledger`

New services
- `app/services/pos_pricing.py`
- `app/services/pos_inventory.py`
- `app/services/pos_registers.py`
- `app/services/pos_sales.py`
- `app/services/pos_refunds.py`
- `app/services/pos_analytics.py`
- `app/services/pos_catalog.py`

Permissions
- Added POS permissions to `PermissionCode` and default role seed.
- Owner-only analytics enforced via `User.is_owner`.

New routes (prefix `/api/v1/modules/pos`)
- Catalog: `GET/POST /products`, `POST /variants`, `GET/POST /categories`, `GET/POST /taxes`, `GET/POST /discounts`
- Registers: `GET/POST /locations`, `GET/POST /registers`, `POST /registers/{id}/open`, `POST /registers/{id}/close`, `POST /registers/cash-movements`
- Sales: `POST /sales`, `PATCH /sales/{id}`, `POST /sales/{id}/finalize`, `GET /sales`, `GET /sales/{id}`
- Receipts: `GET /receipts/{saleId}`
- Refunds: `POST /refunds`
- Inventory: `POST /inventory/adjustments`, `GET /inventory/low-stock`
- Analytics (owner-only): `GET /analytics/kpis`, `GET /analytics/trends`, `GET /analytics/top-products`, `GET /analytics/top-categories`, `GET /analytics/payments`, `GET /analytics/low-stock`

## Frontend
New POS pages under `frontend/app/modules/pos`
- New Sale, Checkout, Receipt, Returns, History, Inventory, Register

New hooks/components
- `frontend/hooks/usePos.ts`
- `frontend/lib/pos-store.ts`
- `frontend/lib/pos-utils.ts`
- `frontend/components/dashboard/charts/PosSalesTrendChart.tsx`
- `frontend/components/dashboard/charts/PosPaymentBreakdownChart.tsx`

Owner dashboard analytics
- POS analytics widgets added to Company Admin dashboard (owner-only).

## Tests
Backend (from `backend/`)
- `python -m pytest`

Frontend (from `frontend/`)
- `npm test`

## Notes
- MongoDB transactions require a replica set in production.
- Beanie indexes are created on startup; no SQL migrations are used.
