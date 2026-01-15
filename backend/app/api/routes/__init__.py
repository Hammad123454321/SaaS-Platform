from fastapi import APIRouter

from app.api.routes import health, auth, entitlements, billing, vendor, modules, ai, onboarding, admin, company, users, tasks, onboarding_stages, compliance_stages, workflows_stages

api_router = APIRouter()
api_router.include_router(health.router)
api_router.include_router(auth.router)
api_router.include_router(entitlements.router)
api_router.include_router(billing.router)
api_router.include_router(vendor.router)
api_router.include_router(tasks.router)  # Tasks module has dedicated routes - must be before modules.router
api_router.include_router(modules.router)
api_router.include_router(ai.router)
api_router.include_router(onboarding.router)
api_router.include_router(onboarding_stages.router)  # Stages 0-2 onboarding
api_router.include_router(compliance_stages.router)  # Stage 4 compliance
api_router.include_router(workflows_stages.router)  # Stage 5 workflows
api_router.include_router(admin.router)
api_router.include_router(company.router)
api_router.include_router(users.router)

