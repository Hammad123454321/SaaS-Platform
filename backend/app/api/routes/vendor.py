from fastapi import APIRouter, Depends, HTTPException, status
from sqlmodel import Session, select

from app.api.deps import get_current_user
from app.api.authz import require_permission
from app.db import get_session
from app.models import VendorCredential, User
from app.schemas import VendorCredentialCreate, VendorCredentialRead
from app.models.role import PermissionCode
from app.services.audit import log_audit

router = APIRouter(prefix="/vendor-credentials", tags=["vendors"])


@router.get("", response_model=list[VendorCredentialRead])
def list_credentials(
    current_user: User = Depends(get_current_user), session: Session = Depends(get_session)
) -> list[VendorCredentialRead]:
    creds = session.exec(
        select(VendorCredential).where(VendorCredential.tenant_id == current_user.tenant_id)
    ).all()
    return [VendorCredentialRead.model_validate(c) for c in creds]


@router.post("", response_model=VendorCredentialRead, status_code=status.HTTP_201_CREATED)
def upsert_credentials(
    payload: VendorCredentialCreate,
    current_user: User = Depends(require_permission(PermissionCode.MANAGE_VENDOR_CREDENTIALS)),
    session: Session = Depends(get_session),
) -> VendorCredentialRead:
    cred = session.exec(
        select(VendorCredential).where(
            VendorCredential.tenant_id == current_user.tenant_id,
            VendorCredential.vendor == payload.vendor,
        )
    ).first()
    if not cred:
        cred = VendorCredential(tenant_id=current_user.tenant_id, vendor=payload.vendor)
    cred.credentials = payload.credentials
    session.add(cred)
    session.commit()
    session.refresh(cred)
    log_audit(
        session,
        tenant_id=current_user.tenant_id,
        actor_user_id=current_user.id,  # type: ignore[arg-type]
        action="vendor_credentials.upsert",
        target=payload.vendor,
        details={"has_credentials": bool(payload.credentials)},
    )
    return VendorCredentialRead.model_validate(cred)

