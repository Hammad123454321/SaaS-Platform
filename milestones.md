## Milestones — Aligned to Staged Onboarding (Stages 0–8)

This milestone plan maps directly to `requirement_project.txt`:
each milestone corresponds to one or more onboarding STAGES (0–8) and
captures concrete implementation work needed to support those flows.

---

- **Milestone 0 – Account Creation & Access Gate (Stage 0)**
  - Backend:
    - Registration endpoint accepting email, password, and communication preferences.
    - Password policy enforcement and storage of policy version + timestamp with the user record.
    - Draft Tenant creation tied to the registering user.
    - Email verification token model + endpoint; verification marks account as active and tenant as eligible for onboarding.
    - Event/audit logging for registration and verification.
  - Integrations:
    - Transactional email via SendGrid (or equivalent) for verification emails.
  - Frontend:
    - Register page with email/password fields, Privacy Policy + Terms of Service acceptance, and communication preferences.
    - Post‑verification UX: clear “verified / next step” state; block further progress until verification is complete.

---

- **Milestone 1 – Business Profile & Jurisdiction (Stage 1)**
  - Backend:
    - Business profile entity linked to Tenant (legal name, operating name, jurisdiction, time zone, primary location).
    - Logic for jurisdiction mapping → applicable rulesets (e.g., Ontario → PAWS, WSIB).
    - State management for “unconfirmed” vs confirmed business profile.
  - Frontend:
    - Business profile step in onboarding wizard capturing all fields from Stage 1.
    - Read‑only summary view showing derived laws/regulators for transparency.
  - Compliance:
    - Persisted mapping between jurisdiction and activated compliance rules; suitable for later audits.

---

- **Milestone 2 – Roles, Owner & Team Access (Stage 2)**
  - Backend:
    - Owner role flag on a user, immutable deletion rules for the Owner record.
    - Role templates (Manager, Staff, Accountant) plus custom role creation with RBAC scopes.
    - Invite model (pending user) with policy‑acceptance tracking per invitee.
  - Frontend:
    - Onboarding steps for:
      - Owner confirmation with responsibility disclaimer.
      - Role creation UI (choose from templates or define custom).
      - Team invitation screen (email entry, role assignment).
    - Clear indication that the Owner role is locked and must exist for high‑risk confirmations.
  - Compliance:
    - Audit events for role creation, Owner confirmation, and invitations.

---

- **Milestone 3 – Module & Industry Selection (Stage 3)**
  - Backend:
    - Module configuration for: Website, Bookings, POS, HR, Finance, Marketing.
    - Per‑tenant module enable/disable state with enforcement checks on access.
    - Industry field on Tenant/Business profile with allowed values (Grooming, Daycare, Retail, Food, Other).
    - Rule activation engine tying modules + industry to:
      - Enabled compliance rules.
      - Auto‑generated tasks (for later phases).
  - Frontend:
    - Onboarding step to select modules (multi‑select) and persist to backend.
    - Industry selection UI with contextual helper text.
  - Compliance:
    - Data model to relate selected industry and modules to the rules they trigger.

---

- **Milestone 4 – Compliance Confirmations (Privacy, Marketing, Finance, HR) (Stage 4)**
  - Backend:
    - Storage for customizable privacy wording and CASL language with versioning and timestamps.
    - Financial setup structure for payroll type, pay schedule, WSIB class (if Finance enabled).
    - HR policy entities (Health & Safety, Harassment, Training requirements) with required/optional flags.
    - Confirmation records:
      - Privacy wording confirmation.
      - CASL confirmation.
      - Financial “I confirm these values are correct” acknowledgement (if Finance enabled).
      - HR policy acceptance and per‑employee acknowledgement requirements.
  - Frontend:
    - Wizard steps for:
      - Reviewing/editing privacy wording and CASL text before confirming.
      - Entering payroll type, pay schedule, WSIB class with “suggested values” display and disclaimer.
      - Reviewing HR & Safety policy text and marking required policies as acknowledged.
    - Lock indicators showing that confirmed wording/policies are used downstream in forms/emails.
  - Compliance:
    - These confirmations are treated as legal records and must be queryable by tenant and date.

---

- **Milestone 5 – Generated Tasks, Incident Rules & Escalations (Stage 5)**
  - Backend:
    - Task generation engine that:
      - Creates onboarding tasks from earlier decisions (modules, industry, HR policies, finance).
      - Marks certain tasks as “required / non‑deletable”.
      - Links tasks to relevant compliance rules for traceability.
    - Incident and safety task templates with locked workflows and export/audit capabilities.
  - Frontend:
    - “Onboarding checklist” UI for reviewing auto‑generated tasks, assigning owners, and adjusting due dates.
    - Incident rules configuration/confirmation screen tied to these locked tasks.
  - Compliance:
    - Escalation and audit trail for incomplete high‑risk tasks (e.g., safety training not completed).

---

- **Milestone 6 – Website & AI Writer Touchpoints (Stage 6, if Website enabled)**
  - Backend:
    - Integration layer to provision a WordPress Multisite site per tenant when Website is selected.
    - Flags and settings for:
      - Cookie banner enabled/disabled.
      - Accessibility helpers enabled/disabled.
      - AI Writer (e.g., “Writer NC5”) enabled + associated disclaimers.
  - Frontend:
    - Website setup step:
      - Site name, template selection, domain or placeholder.
      - Toggle/confirmation for cookie banner and accessibility helpers.
    - AI Writer orientation step:
      - Display AI usage rules and approval requirements for publishing.
      - Explicit confirmation to enable AI Writer features.
  - Compliance:
    - Record of AI usage acceptance and public‑facing policy configuration (cookie banner, accessibility).

---

- **Milestone 7 – Compliance Checklist & Go‑Live (Stage 7)**
  - Backend:
    - Aggregated “Compliance Checklist” endpoint showing:
      - Required items and their completion status.
      - Pending confirmations.
      - Non‑blocking warnings.
    - Owner go‑live confirmation model capturing:
      - “I confirm my setup is accurate.”
      - “I understand Axiom9 does not file on my behalf.”
    - Flag to unlock production features only when all hard gates are satisfied.
  - Frontend:
    - Final review screen summarizing:
      - Required vs optional items.
      - Outstanding confirmations and warnings.
    - Owner‑only go‑live confirmation UI with the two explicit statements from the requirements.
    - Clear transition from “onboarding” to “production” state.
  - Compliance:
    - Go‑live event logged with timestamps and Owner identity.

---

- **Milestone 8 – Post‑Onboarding Automation (First 30 Days, Stage 8)**
  - Backend:
    - Scheduler/cron or background jobs to:
      - Send weekly compliance reminders.
      - Generate an AI “What needs attention” summary from the tenant’s data and outstanding tasks.
      - Detect expiring confirmations and send alerts.
      - Propose suggested optimizations based on behavior and missed items.
  - Frontend:
    - Tenant‑facing “First 30 Days” view:
      - Upcoming reminders, alerts, and AI summaries.
      - Quick links back to tasks, policies, and configurations that need attention.
  - Compliance:
    - Logs for reminders, alerts, and AI summaries to support audits and retrospectives.


