# POS Implementation Plan

## Step-by-step tasks
1) Add POS permissions and entitlements enforcement
   - Extend `PermissionCode` with POS permissions.
   - Update role seeding to include POS access for company_admin and staff where appropriate.
   - Add a POS entitlement guard similar to Tasks.

2) Define POS data models (Beanie)
   - Add documents for Location, Register, RegisterSession, CashMovement, CashCount.
   - Add catalog models: Category, Product, Variant, Tax, Discount, Customer.
   - Add sales models: Sale, SaleItem, Payment, Receipt.
   - Add refunds: Refund, RefundItem.
   - Add inventory: StockOnHand, InventoryLedger.
   - Add indexes for tenant, date range, SKU/barcode, product/category, and register session lookups.
   - Export in `backend/app/models/__init__.py` and register in `backend/app/db.py`.

3) Add POS schemas (Pydantic)
   - Requests/responses for product lookup, sale draft/update, finalize, payments, refunds, register sessions, cash movements, inventory adjustments, analytics.

4) Implement POS services
   - pricing_service: totals, tax/discount rules, rounding policy.
   - inventory_service: validate stock, atomic update, ledger writes.
   - register_service: open/close sessions, cash movements, cash count validation.
   - sales_service: create/update draft, finalize sale (transactional), generate receipt snapshot.
   - refunds_service: partial/full refunds with restock policy.
   - analytics_service: aggregations for KPIs, trends, top products/categories, payment breakdown, low stock.

5) Implement POS API routes
   - CRUD-lite for catalog (search/lookup for POS usage).
   - Sale draft + finalize endpoints.
   - Register session + cash movement endpoints.
   - Refund endpoints.
   - Inventory adjustment + low stock endpoints.
   - Analytics endpoints (owner-only).
   - Audit logging on critical actions.

6) Frontend POS UI (Next.js)
   - New POS pages under `frontend/app/modules/pos`:
     - New Sale, Checkout, Receipt view, Returns/Refunds, Sales History, Inventory, Register Session.
   - React Query hooks for POS APIs.
   - Use existing UI components and styling conventions.

7) Owner dashboard analytics integration
   - Add POS analytics widgets to Company Admin dashboard only when `user.is_owner`.
   - Add charts using Recharts and existing widget patterns.

8) Testing
   - Backend pytest: pricing engine unit tests, finalize sale integration, refunds + inventory, register sessions, analytics access control.
   - Frontend: add test runner (Vitest) and component tests for cart/checkout/refund.
   - Run tests and fix until green.

9) Documentation + quality gates
   - Add usage, ops/maintenance, release notes.
   - Run lint/format tools.
   - Verify DB initialization and indexes.
