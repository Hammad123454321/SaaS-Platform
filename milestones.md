## Phase 1 Milestones (5-part plan)

- **Milestone 1 – SaaS Core Foundations**
  - Stand up repos, CI, env configs, secrets handling.
  - Multi-tenant domain model (company, user, role, permission, module entitlement).
  - Auth service (signup/login, JWT with tenant/roles), password policy, session hooks.
  - RBAC middleware reusable across BFF/services; Super Admin impersonation hook (audited).

- **Milestone 2 – Billing & Entitlements**
  - Stripe plan mapping to modules, seats, AI access; pricing surfaced to onboarding.
  - Webhooks for subscription lifecycle; idempotent handlers and signature verification.
  - Module enable/disable toggles per tenant; downgrade approval path if data deletion required.
  - Billing history endpoint + basic admin controls for refunds/suspension.

- **Milestone 3 – Module Wrappers**
  - FastAPI wrappers for CRM, HRM, POS, Tasks, Booking, Landing Builder paid scripts.
  - Tenant→vendor account mapping, credential storage, error/shape normalization.
  - Read endpoints needed for insights (sales, attendance, bookings, leads); write actions (create task, CRM note, draft email proxy).
  - Health checks and contract tests with mocked vendor APIs.

- **Milestone 4 – Frontend Shell & Dashboards**
  - Next.js shell with tenant-aware routing and unified session handling.
  - Dashboards for Super Admin, Company Admin, Staff with nav based on entitlements.
  - Onboarding wizard (company info, industry, module selection, initial roles, branding).
  - Branding (logo/color) applied to shell; module navigation and chat entry point wired to BFF.

- **Milestone 5 – AI Business Agent v1 (separate service, MVP) & Hardening**
  - Standalone AI service (FastAPI) with minimal endpoints: `POST /chat` (LLM + RAG), `POST /actions/call` (returns proposed actions only), `POST /embeddings/index` (ingest text blobs) — start with hosted instruct model (Mistral API or equivalent); keep provider abstraction so we can later pull a Mistral base model from Hugging Face and self-host if desired.
  - Per-tenant RAG namespace in vector store; BFF sends recent messages + entitlements; AI returns text + proposed actions for BFF to confirm/execute via module wrappers.
  - Auth via signed JWT (tenant_id, user_id, roles) with JWKS verification; allowlisted actions, basic output filtering, per-tenant rate limits.
  - Chat UI wired to AI service; audit records and telemetry (tenant_id, trace_id) emitted/returned; health checks + minimal SLA/rate-limit safeguards.

---

### Status / Feedback
- **Milestone 1 – SaaS Core Foundations:** Backend scaffolded (multi-tenant models, signup/login JWT with password policy, RBAC structures, SendGrid placeholder, entitlements seeded, health checks, CI, env samples). Frontend shell present. Pending: Alembic migrations, auth/RBAC tests, full RBAC middleware + impersonation/audit, password reset endpoint, frontend signup page + session hardening (httpOnly cookie proxy).
- **Milestone 2 – Billing & Entitlements:** Backend webhook/idempotency/subscription tracking/history/entitlement toggles are in. Stripe plan→module mapping is placeholder (needs real price IDs/metadata), downgrade approval path, refunds/suspension admin controls, and billing UI tied to Stripe Checkout/Customer Portal are missing. Tests/migrations still pending.
- **Milestone 3 – Module Wrappers:** Stub-only. Credential storage per tenant and stubbed endpoints (health, list/create records, notes, draft email) with normalized shape and entitlement checks. Pending: real PHP vendor API integration for CRM/HRM/POS/Tasks/Booking/Landing, vendor health checks, contract tests/mocks, migrations, and production-grade error/shape normalization.
- **Milestone 4 – Frontend Shell & Dashboards:** Stubbed. Modern UI, JWT store, entitlement-aware dashboard, module pages (stub data), onboarding wizard (company/modules/branding), branding via CSS vars, role-aware nav, billing history view. Pending: httpOnly cookie session proxy, Stripe Checkout/Portal wiring, backend onboarding endpoint + pricing/paywall, role-specific dashboard data, tests, and real module data wiring.
- **Milestone 5 – AI Business Agent v1:** Partial. Separate FastAPI AI service with `/chat`, `/actions/call`, `/embeddings/index`, JWT/JWKS + rate limit + allowlist, provider abstraction (HF or stub). Pending: stable model deployment (HF needs correct torch wheel / hardware), per-tenant vector store for RAG, real embeddings model, action/output filtering hardening, audit/telemetry sinks, chat UI wiring from frontend, and integration with module wrappers for action execution.

