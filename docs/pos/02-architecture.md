# POS Architecture

## Stack alignment
- Frontend: Next.js (App Router), React Query, Zustand, Tailwind + shadcn UI.
- Backend: FastAPI with MongoDB via Beanie.
- No SQL migrations; Mongo collections are created by Beanie with index definitions.

## Domain model (Mongo/Beanie)
Tenant-scoped documents include `tenant_id` and are always filtered by it.

Core entities:
- Location (Store)
  - tenant_id, name, code, address, timezone, is_active
- Register
  - tenant_id, location_id, name, status (active/inactive)
- RegisterSession
  - tenant_id, register_id, opened_by_user_id, opened_at, opening_cash, status (open/closed), closed_by_user_id, closed_at, closing_cash, expected_cash, cash_difference
- CashMovement
  - tenant_id, register_session_id, type (paid_in/paid_out), amount, reason, created_by_user_id, created_at
- CashCount
  - tenant_id, register_session_id, denominations (map), total, created_at

Catalog:
- Category
  - tenant_id, name, parent_id, sort_order, is_active
- Product
  - tenant_id, name, description, category_id, sku, barcode, base_price, cost, tax_ids, is_active
- Variant
  - tenant_id, product_id, name, sku, barcode, price, cost, attributes (map), is_active
- Tax
  - tenant_id, name, rate, is_inclusive, applies_to_category_ids
- Discount
  - tenant_id, name, type (percent/fixed), value, applies_to (order/line/category/product), active range
- Customer
  - tenant_id, name, email, phone, notes, created_at

Sales:
- Sale
  - tenant_id, location_id, register_id, cashier_id, customer_id, status (draft/complete/refunded/void), subtotal, tax_total, discount_total, total, paid_total, change_due, created_at, completed_at
- SaleItem
  - tenant_id, sale_id, product_id, variant_id, name_snapshot, sku_snapshot, qty, unit_price, discount_total, tax_total, line_total
- Payment
  - tenant_id, sale_id, method (cash/card/other), amount, reference, created_at
- Receipt
  - tenant_id, sale_id, receipt_number, rendered_data (json snapshot), created_at

Returns/Refunds:
- Refund
  - tenant_id, sale_id, created_by_user_id, reason, status (pending/complete), total, created_at
- RefundItem
  - tenant_id, refund_id, sale_item_id, qty, amount, restock (bool)

Inventory:
- StockOnHand
  - tenant_id, location_id, product_id, variant_id, qty_on_hand, reorder_point, updated_at
- InventoryLedger
  - tenant_id, location_id, product_id, variant_id, qty_delta, reason (sale/refund/adjustment), related_sale_id, related_refund_id, created_by_user_id, created_at

Audit:
- Use existing `AuditLog` for critical POS actions.

## Indexing strategy
- Product/Variant: `tenant_id`, `sku`, `barcode`, `name` (text/regex) for fast POS lookup.
- Sales: `tenant_id`, `created_at`, `completed_at`, `location_id`, `register_id`, `cashier_id`.
- SaleItem: `tenant_id`, `sale_id`, `product_id` for top product queries.
- Inventory: `tenant_id`, `location_id`, `product_id`, `variant_id` composite.
- RegisterSession: `tenant_id`, `register_id`, `status` for active session lookup.

## Service architecture
- pricing_service: tax + discount calculations and rounding rules.
- inventory_service: stock validation, ledger writes, atomic stock updates.
- register_service: open/close session, cash movements.
- sales_service: draft sales, finalize sale (transactional), receipt generation.
- refunds_service: partial/full refunds with restock rules.
- analytics_service: aggregation pipelines for owner KPIs.

## API layout (FastAPI)
- `POST /pos/registers/{id}/open` / `POST /pos/registers/{id}/close`
- `POST /pos/registers/{id}/cash-movements`
- `GET /pos/products` (search, barcode, pagination)
- `POST /pos/sales` (create draft)
- `PATCH /pos/sales/{id}` (update draft)
- `POST /pos/sales/{id}/finalize` (atomic)
- `GET /pos/sales` (history filters)
- `POST /pos/refunds` (partial/full)
- `GET /pos/receipts/{id}`
- `POST /pos/inventory/adjustments`
- `GET /pos/inventory/low-stock`
- `GET /pos/analytics/*` (owner-only)

## Security/RBAC
- Require POS module entitlement (ModuleEntitlement with module_code=pos).
- Add POS permission codes (access, manage, refunds, inventory, registers, analytics).
- Owner-only analytics: enforce `current_user.is_owner == True` for analytics endpoints.

## Concurrency/inventory correctness
- Finalize sale within a Mongo session transaction.
- For each item: use conditional updates on StockOnHand (`qty_on_hand >= qty`) when negative stock is disallowed.
- Record all inventory changes in InventoryLedger within the same transaction.

## Analytics strategy
- On-demand aggregation pipelines with date filters and supporting indexes.
- Owner-only endpoints return KPIs, trends, top products/categories, payment breakdown, and low-stock summary.

## UI alignment plan
- Reuse existing `Card`, `Button`, `Dialog`, `Table` patterns and gradient classes in `globals.css`.
- Follow dashboard layouts already used in `frontend/app/dashboard/company-admin/page.tsx`.
- POS screens live under `frontend/app/modules/pos` and use shared components under `frontend/components`.

## Testing strategy (high level)
- Backend: pytest with unit tests for pricing + integration tests for finalize sale, refunds, inventory, register sessions, and analytics access control.
- Frontend: component tests for cart/checkout/refund flows (add a test runner if none exists).
