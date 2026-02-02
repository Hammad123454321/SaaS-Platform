# POS System Understanding

This document captures the current repo architecture and conventions to align the POS implementation.

## Frontend (Next.js, React)
- Framework: Next.js 14 (App Router) with React 18 and TypeScript. Routes live under `frontend/app` with route groups `(app)` and `(auth)`.
- State/data: Zustand for session state (`frontend/lib/store.ts`) and React Query (`frontend/lib/providers/query-provider.tsx`, hooks in `frontend/hooks`).
- API client: Axios wrapper in `frontend/lib/api.ts` with `/api/v1` base URL, Bearer token injection, and 401 auto-logout.
- Auth/onboarding guard: `frontend/app/(app)/layout.tsx` checks token + onboarding status; `frontend/components/AppShell.tsx` hydrates user + entitlements.
- UI system: Tailwind CSS + shadcn-style components (Radix) in `frontend/components/ui`. Theme tokens + gradients in `frontend/app/globals.css`.
- Charts: Recharts (dashboard chart components in `frontend/components/dashboard/charts`).
- i18n: none detected.
- Dashboards: Role-based entry in `frontend/app/dashboard/page.tsx` -> `company-admin`, `staff`, `super-admin`. Data via `frontend/hooks/useDashboard.ts`.
- Modules: Generic module view at `frontend/app/modules/[module]/page.tsx`. Tasks module has dedicated pages; POS currently has a placeholder dashboard at `frontend/app/modules/pos/dashboard/page.tsx`.

How to add new UI routes/pages/components:
- Create a new route under `frontend/app/...` (App Router).
- Use `frontend/components/ui` for base UI elements; compose feature components under `frontend/components/<feature>`.
- Use `frontend/lib/api.ts` + React Query hooks for data fetching.
- Gate access via entitlements (see `useSessionStore` and module checks in `frontend/app/modules/[module]/page.tsx`).

Note: The repo currently uses Next.js (React) rather than Vue. The POS UI will follow the existing Next.js/Tailwind patterns.

## Backend (FastAPI + MongoDB/Beanie)
- Framework: FastAPI app in `backend/app/main.py` with `/api/v1` prefix.
- Database/ORM: MongoDB via Motor + Beanie documents. Initialization in `backend/app/db.py` with `init_beanie`.
- Auth: JWT access/refresh tokens in `backend/app/core/security.py`. `get_current_user` parses `sub` as `user_id:tenant_id` (`backend/app/api/deps.py`).
- RBAC: Permission catalog in `PermissionCode` (`backend/app/models/role.py`); enforced via `require_permission` (`backend/app/api/authz.py`). Roles stored via `Role`, `UserRole`, `RolePermission`.
- Business owner identifier: `User.is_owner` flag. Owner also receives `company_admin` role by default (see `backend/app/services/owner_service.py`).
- Tenant isolation: All tenant-scoped models have `tenant_id` and queries filter by current tenant.
- API structure: Routers in `backend/app/api/routes`, services in `backend/app/services`, models in `backend/app/models`.
- Validation/errors: Pydantic models in `backend/app/schemas` (plus inline dict responses). Errors use `HTTPException` with detail strings.
- Logging/observability: structlog configured in `backend/app/main.py` and standard logging calls throughout.
- Pagination/filtering: Typically `limit` + `offset` in services (see `list_tasks` in `backend/app/services/tasks.py`).
- Background jobs/caching/websockets: none detected.
- Migrations: None. Schema managed via Beanie documents; indexes declared in `Document.Settings.indexes` and created at init time.

How to add new backend routers/services/models:
- Add Beanie `Document` models under `backend/app/models` and export them in `backend/app/models/__init__.py`.
- Register models in `backend/app/db.py` `init_beanie(document_models=[...])`.
- Add Pydantic schemas under `backend/app/schemas` if needed.
- Implement business logic in `backend/app/services` and expose via routers in `backend/app/api/routes`.
- Enforce tenant isolation and RBAC via `get_current_user` + `require_permission`.

## Tests and migrations
- Backend tests: `backend/tests` uses pytest. Run with `python -m pytest` from `backend/` (or `poetry run pytest`).
- Frontend tests: no dedicated test runner detected in `frontend/package.json` (only `lint`).
- Migrations: none (Mongo/Beanie). Index updates are applied via model definitions at app startup.
