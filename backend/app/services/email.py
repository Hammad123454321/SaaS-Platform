import logging
from sendgrid import SendGridAPIClient
from sendgrid.helpers.mail import Mail

from app.config import settings

logger = logging.getLogger(__name__)


def send_password_reset(email: str, reset_link: str) -> None:
    """Send password reset email via SendGrid. In M1, failures are logged but not fatal."""
    message = Mail(
        from_email=settings.email_from,
        to_emails=email,
        subject="Reset your password",
        html_content=f"<p>Click to reset your password:</p><p><a href='{reset_link}'>Reset</a></p>",
    )
    try:
        sg = SendGridAPIClient(settings.sendgrid_api_key)
        sg.send(message)
    except Exception as exc:  # noqa: BLE001
        logger.warning("Failed to send password reset email", exc_info=exc)

