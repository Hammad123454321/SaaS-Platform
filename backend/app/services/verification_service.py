"""Service for email verification and token management (Mongo/Beanie)."""
import secrets
import hashlib
from datetime import datetime, timedelta
from typing import Optional

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


async def create_verification_token(user_id: str) -> tuple[str, str]:
    """Create a new email verification token for a user in MongoDB."""
    # Delete any existing token for this user
    existing = await EmailVerificationToken.find_one(
        EmailVerificationToken.user_id == user_id
    )
    if existing:
        await existing.delete()

    token, token_hash = generate_verification_token()
    expires_at = datetime.utcnow() + timedelta(hours=24)

    verification_token = EmailVerificationToken(
        user_id=user_id,
        token=token_hash,
        expires_at=expires_at,
    )
    await verification_token.insert()

    return token, token_hash


async def verify_email_token(token: str) -> Optional[User]:
    """Verify an email token and activate the user account (Mongo/Beanie)."""
    token_hash = hashlib.sha256(token.encode()).hexdigest()

    verification = await EmailVerificationToken.find_one(
        EmailVerificationToken.token == token_hash
    )
    if not verification:
        return None

    if verification.verified_at is not None:
        return None  # Already verified

    if verification.expires_at < datetime.utcnow():
        return None  # Expired

    # Get user
    user = await User.get(verification.user_id)
    if not user:
        return None

    # Mark token as verified
    verification.verified_at = datetime.utcnow()

    # Activate user and tenant
    user.email_verified = True
    user.is_active = True

    # Activate tenant (no longer draft)
    tenant = None
    if user.tenant_id:
        tenant = await Tenant.get(user.tenant_id)
        if tenant:
            tenant.is_draft = False
            await tenant.save()

    await user.save()
    await verification.save()

    # Log verification event
    await log_registration_event(
        user_id=str(user.id),
        tenant_id=str(user.tenant_id) if user.tenant_id else "",
        event_type="verification",
    )

    return user


async def send_verification_email(user: User, resend: bool = False) -> bool:
    """Create verification token and send email to user (Mongo/Beanie)."""
    token, _ = await create_verification_token(str(user.id))

    # Log resend event if applicable
    if resend:
        await log_registration_event(
            user_id=str(user.id),
            tenant_id=str(user.tenant_id) if user.tenant_id else "",
            event_type="resend_verification",
        )

    return email_service.send_verification_email(
        to_email=user.email,
        verification_token=token,
        user_name=user.email.split("@")[0],
    )

