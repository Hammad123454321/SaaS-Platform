# POS Scope and Requirements

## Goal
Deliver a production-grade, end-to-end POS module integrated into the existing SaaS platform, aligned with current UI/UX patterns and secured with tenant isolation + RBAC. POS analytics must appear only for Business Owners.

## Core POS Capabilities (baseline)
- Product catalog with categories, variants, barcodes/SKUs, taxes, and discounts.
- Fast product search (name/SKU/barcode), category filters, and barcode input.
- Cart management: add/remove items, quantity edits, line-level discounts/taxes.
- Customer attach/create and customer order history.
- Register workflow: open/close register session, cash count, paid-in/paid-out.
- Checkout with multiple payment methods and split payments.
- Sales finalization with receipt/invoice generation.
- Refunds/returns (partial/full) with restock policies.
- Inventory: stock on hand, adjustments, low-stock thresholds, ledger history.
- Sales history with filters (date, location, register, cashier, status).
- Audit logging for sensitive actions (sale finalize, refund, register open/close, stock adjustment).

## Analytics (Owner-only)
- Gross sales, net sales, taxes, discounts, refunds.
- Sales trends over time (day/week/month).
- Top products and categories.
- Average order value, items per order.
- Payment method breakdown.
- Low-stock summary.

## Non-Functional Requirements
- Multi-tenant isolation on every query and document.
- RBAC/permissions for POS operations; Business Owner (User.is_owner) is the only role that can access analytics.
- Atomic sale finalization: payments, inventory ledger, and receipt creation must be in a single transaction.
- Concurrency-safe inventory updates to avoid overselling.
- Observability: structured logging and audit trails for critical operations.
- No placeholders or stubs; all flows must work end-to-end.

## UI/UX Requirements
- Must reuse existing Tailwind + shadcn UI patterns, gradients, and card styles.
- Must match existing spacing, typography, and layout conventions.
- POS screens required:
  1) New Sale
  2) Customer attach/create
  3) Checkout/Payments (split payments)
  4) Receipt view/export
  5) Returns/Refunds
  6) Register open/close + cash movements
  7) Sales history
  8) Inventory adjustments + low-stock

## Definition of Done
- POS module fully functional end-to-end with backend + frontend.
- Analytics visible only for Business Owner in the company dashboard.
- Backend and frontend tests added and passing.
- Migrations/indexes applied cleanly (Mongo/Beanie indexes created on startup).
- Documentation complete: setup, usage, ops/maintenance, release notes.
