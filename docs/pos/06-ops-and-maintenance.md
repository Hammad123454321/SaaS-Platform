# POS Ops and Maintenance

## Deployment notes
- MongoDB must run as a replica set to support multi-document transactions used in sale finalization and refunds.
- Ensure `mongodb_uri` points to a replica set and includes credentials for production.

## Indexes
- POS collections define indexes in their `Settings.indexes` (Beanie). These are created during app startup in `init_db()`.
- Key indexed fields: tenant_id, SKU/barcode, completed_at, product/category IDs, register/session status.

## Observability
- Audit logs are written for sale creation/finalization, refunds, register open/close, cash movements, and inventory adjustments.
- Use application logs + `audit_log` collection for investigations.

## Performance
- Analytics endpoints use aggregation pipelines over indexed fields and are scoped by tenant and date range.
- For high-volume tenants, consider adding scheduled rollups (daily summaries) and caching layers.

## Data integrity
- Inventory is maintained via StockOnHand + InventoryLedger.
- All stock changes (sale/refund/adjustment) are recorded in the ledger for traceability.

## Security
- All POS endpoints enforce tenant isolation and POS-specific permissions.
- Owner-only analytics are enforced at the API level; frontend also hides these widgets for non-owners.

## Maintenance tasks
- Monitor low-stock alerts and keep reorder points updated via inventory adjustments.
- Review register session discrepancies for cash handling issues.
- Periodically archive old receipts if storage becomes large.
