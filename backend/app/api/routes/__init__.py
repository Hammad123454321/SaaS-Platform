from fastapi import APIRouter

from app.api.routes import health, auth, entitlements, billing, vendor, modules, ai

api_router = APIRouter()
api_router.include_router(health.router)
api_router.include_router(auth.router)
api_router.include_router(entitlements.router)
api_router.include_router(billing.router)
api_router.include_router(vendor.router)
api_router.include_router(modules.router)
api_router.include_router(ai.router)

