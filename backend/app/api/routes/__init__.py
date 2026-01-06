from fastapi import APIRouter

from app.api.routes import health, auth, entitlements, billing, vendor, modules, ai, onboarding, admin, company, users

api_router = APIRouter()
api_router.include_router(health.router)
api_router.include_router(auth.router)
api_router.include_router(entitlements.router)
api_router.include_router(billing.router)
api_router.include_router(vendor.router)
api_router.include_router(modules.router)
api_router.include_router(ai.router)
api_router.include_router(onboarding.router)
api_router.include_router(admin.router)
api_router.include_router(company.router)
api_router.include_router(users.router)

