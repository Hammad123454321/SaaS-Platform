# SaaS Platform Monorepo

Milestone 1 delivers the core scaffolding for a multi-tenant SaaS:

- Backend (`backend/`): FastAPI, Postgres, JWT auth, RBAC models, SendGrid wiring placeholder, Stripe placeholders, billing webhook + history, module entitlements endpoints.
- Frontend (`frontend/`): Next.js 14 shell with tenant-aware routing hooks (stub), basic auth UI, Tailwind setup.
- Infra: Docker Compose for Postgres, CI workflow for backend lint/tests, sample env files (`env.example` in each package).

## Quickstart (local)
1) Start Postgres: `docker compose up -d db`
2) Backend:
   ```bash
   cd backend
   poetry install
   cp env.example .env  # adjust secrets
   poetry run uvicorn app.main:app --reload --port 8000
   ```
3) Frontend:
   ```bash
   cd frontend
   npm install
   cp env.example .env.local
   npm run dev
   ```

## Notes
- JWT embeds `user_id:tenant_id`. Password policy enforced on signup.
- SendGrid + Stripe keys are placeholders; replace in your `.env`.
- Auth now includes refresh, password reset, logout, and impersonation (audited). RBAC middleware (`require_permission`) enforces permissions on entitlements, vendor credentials, billing, and module access. Auth cookies are httpOnly and sent cross-site via CORS (set `CORS_ORIGINS` / cookie flags in `.env`).
- Alembic migrations not generated yet; run `SQLModel.metadata.create_all` on startup for now.
- Frontend: signup/login, password reset (request/confirm), entitlement-aware dashboard, module stubs; session rehydrates via cookies.
- AI service endpoints now require JWT (`verify_jwt`) and rate limiting; tenant derived from token subject/claim.
- Billing: `POST /billing/webhook` (Stripe events, signature verified, idempotent) and `GET /billing/history`. Entitlements: `GET /entitlements`, `POST /entitlements/{module_code}` (super admin).

