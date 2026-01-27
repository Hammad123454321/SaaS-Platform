from fastapi import APIRouter, Depends, HTTPException, status

from app.api.deps import get_current_user
from app.models import ModuleEntitlement, ModuleCode, Tenant, User
from app.schemas import (
    OnboardingRequest,
    OnboardingResponse,
    TaskifyOnboardingRequest,
    TaskifyOnboardingResponse,
)
from app.services.audit import log_audit
from app.services.module_onboarding import onboard_tenant_to_taskify, verify_taskify_connection


router = APIRouter(prefix="/onboarding", tags=["onboarding"])


@router.post("", response_model=OnboardingResponse)
async def onboarding(
    payload: OnboardingRequest,
    current_user: User = Depends(get_current_user),
) -> OnboardingResponse:
    tenant_id = str(current_user.tenant_id)
    tenant = await Tenant.get(current_user.tenant_id)
    if not tenant:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Tenant not found.")

    if payload.company.name and payload.company.name != tenant.name:
        conflict = await Tenant.find_one(
            Tenant.name == payload.company.name,
            Tenant.id != tenant.id
        )
        if conflict:
            raise HTTPException(
                status_code=status.HTTP_409_CONFLICT,
                detail="Company name already in use.",
            )
        tenant.name = payload.company.name
        await tenant.save()

    selected = {module for module in payload.modules}
    if selected:
        existing = await ModuleEntitlement.find(
            ModuleEntitlement.tenant_id == tenant_id
        ).to_list()
        existing_by_code = {ent.module_code: ent for ent in existing}
        for module_code in selected:
            entitlement = existing_by_code.get(module_code)
            if not entitlement:
                entitlement = ModuleEntitlement(
                    tenant_id=tenant_id,
                    module_code=ModuleCode(module_code),
                )
            entitlement.enabled = True
            await entitlement.save()

    updated_entitlements = await ModuleEntitlement.find(
        ModuleEntitlement.tenant_id == tenant_id
    ).to_list()
    
    await log_audit(
        tenant_id=tenant_id,
        actor_user_id=str(current_user.id),
        action="onboarding.complete",
        target=tenant_id,
        details={
            "modules": [m.value for m in selected],
            "branding": payload.branding.model_dump() if payload.branding else None,
            "industry": payload.company.industry,
        },
    )

    return OnboardingResponse(
        status="ok",
        entitlements=[
            {
                "module_code": ent.module_code,
                "enabled": ent.enabled,
                "seats": ent.seats,
                "ai_access": ent.ai_access,
            }
            for ent in updated_entitlements
        ],
    )


@router.post("/taskify", response_model=TaskifyOnboardingResponse)
async def onboard_taskify(
    payload: TaskifyOnboardingRequest,
    current_user: User = Depends(get_current_user),
) -> TaskifyOnboardingResponse:
    tenant_id = str(current_user.tenant_id)
    
    await onboard_tenant_to_taskify(
        tenant_id=tenant_id,
        taskify_base_url=payload.base_url,
        api_token=payload.api_token,
        workspace_id=payload.workspace_id,
    )

    health = None
    if payload.verify:
        health = await verify_taskify_connection(tenant_id=tenant_id)

    await log_audit(
        tenant_id=tenant_id,
        actor_user_id=str(current_user.id),
        action="onboarding.taskify",
        target="tasks",
        details={"base_url": payload.base_url, "workspace_id": payload.workspace_id, "verified": payload.verify},
    )

    return TaskifyOnboardingResponse(status="ok", health=health)
