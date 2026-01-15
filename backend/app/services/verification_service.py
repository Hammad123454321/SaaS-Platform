"""Service for email verification and token management."""
import secrets
import hashlib
from datetime import datetime, timedelta
from typing import Optional
from sqlmodel import Session, select

from app.models.onboarding import EmailVerificationToken
from app.models.user import User
from app.models.tenant import Tenant
from app.services.email_service import email_service
from app.services.audit_service import log_registration_event


def generate_verification_token() -> tuple[str, str]:
    """Generate a secure token and its hash for storage."""
    token = secrets.token_urlsafe(32)
    token_hash = hashlib.sha256(token.encode()).hexdigest()
    return token, token_hash


def create_verification_token(session: Session, user_id: int) -> tuple[str, str]:
    """Create a new email verification token for a user."""
    # Delete any existing token for this user
    existing = session.exec(
        select(EmailVerificationToken).where(EmailVerificationToken.user_id == user_id)
    ).first()
    if existing:
        session.delete(existing)
        session.flush()
    
    token, token_hash = generate_verification_token()
    expires_at = datetime.utcnow() + timedelta(hours=24)
    
    verification_token = EmailVerificationToken(
        user_id=user_id,
        token=token_hash,
        expires_at=expires_at
    )
    session.add(verification_token)
    session.commit()
    session.refresh(verification_token)
    
    return token, token_hash


def verify_email_token(session: Session, token: str) -> Optional[User]:
    """Verify an email token and activate the user account."""
    token_hash = hashlib.sha256(token.encode()).hexdigest()
    
    verification = session.exec(
        select(EmailVerificationToken).where(EmailVerificationToken.token == token_hash)
    ).first()
    
    if not verification:
        return None
    
    if verification.verified_at is not None:
        return None  # Already verified
    
    if verification.expires_at < datetime.utcnow():
        return None  # Expired
    
    # Get user
    user = session.get(User, verification.user_id)
    if not user:
        return None
    
    # Mark token as verified
    verification.verified_at = datetime.utcnow()
    
    # Activate user and tenant
    user.email_verified = True
    user.is_active = True
    
    # Activate tenant (no longer draft)
    tenant = session.get(Tenant, user.tenant_id)
    if tenant:
        tenant.is_draft = False
    
    session.commit()
    session.refresh(user)
    
    # Log verification event
    log_registration_event(
        session=session,
        user_id=user.id,
        tenant_id=user.tenant_id,
        event_type="verification"
    )
    
    return user


def send_verification_email(session: Session, user: User, resend: bool = False) -> bool:
    """Create verification token and send email to user."""
    token, _ = create_verification_token(session, user.id)
    
    # Log resend event if applicable
    if resend:
        log_registration_event(
            session=session,
            user_id=user.id,
            tenant_id=user.tenant_id,
            event_type="resend_verification"
        )
    
    return email_service.send_verification_email(
        to_email=user.email,
        verification_token=token,
        user_name=user.email.split("@")[0]
    )

