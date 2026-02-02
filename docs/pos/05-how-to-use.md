# POS How-To Guide

## Setup
1) Backend env
- Configure `backend/.env` with MongoDB connection (e.g., `mongodb_uri`, `mongodb_db_name`).
- Ensure JWT secrets and CORS origins are set for your frontend domain.

2) Frontend env
- Set `NEXT_PUBLIC_API_BASE_URL` to the backend base URL (e.g., `http://localhost:8000`).

3) Start services
- Backend: `uvicorn app.main:app --reload` (from `backend/`).
- Frontend: `npm install` then `npm run dev` (from `frontend/`).

## Enable POS Module
- Ensure the tenant has a `ModuleEntitlement` with `module_code=pos` and `enabled=true`.
- Company Admin role includes POS permissions by default; staff can process sales and manage registers.

## Day-to-day POS usage
1) Create a Location + Register
- Go to **POS > Register** and create a location and register.

2) Open a Register Session
- In **POS > Register**, select a location/register and open a session with opening cash.

3) Create Products
- Go to **POS > Inventory** and add products (name, SKU, barcode, price, category).

4) New Sale
- Go to **POS > New Sale**, search/add items, apply line/order discounts, then proceed to checkout.

5) Checkout & Payments
- In **POS > Checkout**, split payments across cash/card/other, then finalize the sale.

6) Receipt
- After finalize, view/print the receipt from **POS > Receipt**.

7) Returns/Refunds
- Go to **POS > Returns**, lookup a sale by ID, select items and quantities, and process refund.

8) Inventory Adjustments
- In **POS > Inventory**, select a product and adjust stock on hand and reorder points.

## Owner Analytics
- Only Business Owners (User.is_owner) see POS analytics on the Company Admin dashboard.
- Use the date range picker to switch reporting windows.
