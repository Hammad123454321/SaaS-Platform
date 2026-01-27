from fastapi import APIRouter, Depends, HTTPException, status

from app.api.deps import get_current_user
from app.api.authz import require_permission
from app.models import VendorCredential, User
from app.schemas import VendorCredentialCreate, VendorCredentialRead
from app.models.role import PermissionCode
from app.services.audit import log_audit

router = APIRouter(prefix="/vendor-credentials", tags=["vendors"])


@router.get("", response_model=list[VendorCredentialRead])
async def list_credentials(
    current_user: User = Depends(get_current_user),
) -> list[VendorCredentialRead]:
    tenant_id = str(current_user.tenant_id)
    creds = await VendorCredential.find(
        VendorCredential.tenant_id == tenant_id
    ).to_list()
    return [VendorCredentialRead.model_validate(c) for c in creds]


@router.post("", response_model=VendorCredentialRead, status_code=status.HTTP_201_CREATED)
async def upsert_credentials(
    payload: VendorCredentialCreate,
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_VENDOR_CREDENTIALS)),
) -> VendorCredentialRead:
    tenant_id = str(current_user.tenant_id)
    
    cred = await VendorCredential.find_one(
        VendorCredential.tenant_id == tenant_id,
        VendorCredential.vendor == payload.vendor,
    )
    if not cred:
        cred = VendorCredential(tenant_id=tenant_id, vendor=payload.vendor)
    
    cred.credentials = payload.credentials
    await cred.save()
    
    await log_audit(
        tenant_id=tenant_id,
        actor_user_id=str(current_user.id),
        action="vendor_credentials.upsert",
        target=payload.vendor,
        details={"has_credentials": bool(payload.credentials)},
    )
    return VendorCredentialRead.model_validate(cred)
