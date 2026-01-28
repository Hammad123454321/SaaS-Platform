"""Email service using SendGrid for transactional emails."""
import logging
from typing import Optional
from sendgrid import SendGridAPIClient
from sendgrid.helpers.mail import Mail, Email, To, Content

from app.config import settings

logger = logging.getLogger(__name__)


class EmailService:
    """Service for sending emails via SendGrid."""
    
    def __init__(self):
        self._client = None
        self._from_email = None
        self._initialized = False
    
    def _ensure_initialized(self) -> bool:
        """Lazy initialization to avoid crash on import if API key is missing."""
        if self._initialized:
            return self._client is not None
        
        self._initialized = True
        api_key = settings.sendgrid_api_key
        
        if not api_key:
            logger.warning("SendGrid API key not configured. Email functionality disabled.")
            return False
        
        try:
            self._client = SendGridAPIClient(api_key=api_key)
            # Extract email and name from settings
            email_from = settings.email_from or "noreply@example.com"
            email_parts = email_from.split("@")
            self._from_email = Email(email_from, email_parts[0] if len(email_parts) > 0 else "SaaS Platform")
            return True
        except Exception as e:
            logger.error(f"Failed to initialize SendGrid client: {e}")
            return False
    
    @property
    def client(self):
        self._ensure_initialized()
        return self._client
    
    @property
    def from_email(self):
        self._ensure_initialized()
        return self._from_email
    
    def get_frontend_base_url(self) -> str:
        """Get the frontend base URL from settings."""
        return settings.frontend_base_url
    
    def send_verification_email(self, to_email: str, verification_token: str, user_name: Optional[str] = None) -> bool:
        """Send email verification link to user."""
        if not self._ensure_initialized():
            logger.warning(f"Cannot send verification email to {to_email}: Email service not configured")
            return False
        
        try:
            verification_url = f"{settings.frontend_base_url}/auth/verify-email?token={verification_token}"
            
            subject = "Verify your email address"
            html_content = f"""
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                    <h2 style="color: #0ea5e9;">Verify Your Email Address</h2>
                    <p>Hello{(' ' + user_name) if user_name else ''},</p>
                    <p>Thank you for registering! Please verify your email address by clicking the link below:</p>
                    <p style="margin: 30px 0;">
                        <a href="{verification_url}" 
                           style="background-color: #0ea5e9; color: white; padding: 12px 24px; 
                                  text-decoration: none; border-radius: 5px; display: inline-block;">
                            Verify Email Address
                        </a>
                    </p>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style="word-break: break-all; color: #666; font-size: 12px;">{verification_url}</p>
                    <p style="margin-top: 30px; color: #666; font-size: 12px;">
                        This link will expire in 24 hours. If you didn't create an account, please ignore this email.
                    </p>
                </div>
            </body>
            </html>
            """
            
            message = Mail(
                from_email=self.from_email,
                to_emails=To(to_email),
                subject=subject,
                html_content=Content("text/html", html_content)
            )
            
            response = self.client.send(message)
            if response.status_code in [200, 201, 202]:
                logger.info(f"Verification email sent to {to_email}")
                return True
            else:
                logger.error(f"Failed to send verification email to {to_email}: {response.status_code}")
                return False
                
        except Exception as e:
            logger.error(f"Error sending verification email to {to_email}: {str(e)}")
            return False
    
    def send_team_invitation_email(self, to_email: str, invitation_token: str, inviter_name: str, tenant_name: str, role_name: Optional[str] = None) -> bool:
        """Send team invitation email."""
        if not self._ensure_initialized():
            logger.warning(f"Cannot send invitation email to {to_email}: Email service not configured")
            return False
        
        try:
            invitation_url = f"{settings.frontend_base_url}/auth/accept-invitation?token={invitation_token}"
            
            subject = f"Invitation to join {tenant_name}"
            html_content = f"""
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                    <h2 style="color: #0ea5e9;">You've been invited!</h2>
                    <p>Hello,</p>
                    <p><strong>{inviter_name}</strong> has invited you to join <strong>{tenant_name}</strong>.</p>
                    {f'<p>Your role: <strong>{role_name}</strong></p>' if role_name else ''}
                    <p>Click the link below to accept the invitation:</p>
                    <p style="margin: 30px 0;">
                        <a href="{invitation_url}" 
                           style="background-color: #0ea5e9; color: white; padding: 12px 24px; 
                                  text-decoration: none; border-radius: 5px; display: inline-block;">
                            Accept Invitation
                        </a>
                    </p>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style="word-break: break-all; color: #666; font-size: 12px;">{invitation_url}</p>
                    <p style="margin-top: 30px; color: #666; font-size: 12px;">
                        This invitation will expire in 7 days. If you didn't expect this invitation, please ignore this email.
                    </p>
                </div>
            </body>
            </html>
            """
            
            message = Mail(
                from_email=self.from_email,
                to_emails=To(to_email),
                subject=subject,
                html_content=Content("text/html", html_content)
            )
            
            response = self.client.send(message)
            if response.status_code in [200, 201, 202]:
                logger.info(f"Team invitation email sent to {to_email}")
                return True
            else:
                logger.error(f"Failed to send invitation email to {to_email}: {response.status_code}")
                return False
                
        except Exception as e:
            logger.error(f"Error sending invitation email to {to_email}: {str(e)}")
            return False
    
    def send_team_member_credentials_email(
        self, 
        to_email: str, 
        password: str, 
        inviter_name: str, 
        tenant_name: str, 
        role_name: Optional[str] = None,
        login_url: str = None
    ) -> bool:
        """Send team member credentials email with auto-generated password."""
        if not self._ensure_initialized():
            logger.warning(f"Cannot send credentials email to {to_email}: Email service not configured")
            return False
        
        try:
            if login_url is None:
                login_url = f"{settings.frontend_base_url}/login"
            
            subject = f"Welcome to {tenant_name} - Your Account Credentials"
            html_content = f"""
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                    <h2 style="color: #0ea5e9;">Welcome to {tenant_name}!</h2>
                    <p>Hello,</p>
                    <p><strong>{inviter_name}</strong> has added you as a team member to <strong>{tenant_name}</strong>.</p>
                    {f'<p>Your role: <strong>{role_name}</strong></p>' if role_name else ''}
                    <p>Your account has been created. Please use the following credentials to log in:</p>
                    <div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">
                        <p style="margin: 5px 0;"><strong>Email:</strong> {to_email}</p>
                        <p style="margin: 5px 0;"><strong>Password:</strong> <code style="background-color: #fff; padding: 2px 6px; border-radius: 3px; font-family: monospace;">{password}</code></p>
                    </div>
                    <p style="margin: 30px 0;">
                        <a href="{login_url}" 
                           style="background-color: #0ea5e9; color: white; padding: 12px 24px; 
                                  text-decoration: none; border-radius: 5px; display: inline-block;">
                            Log In Now
                        </a>
                    </p>
                    <p><strong>Important:</strong> For security reasons, you will be required to change your password on your first login.</p>
                    <p style="margin-top: 30px; color: #666; font-size: 12px;">
                        If you didn't expect this email, please contact your administrator or ignore this message.
                    </p>
                </div>
            </body>
            </html>
            """
            
            message = Mail(
                from_email=self.from_email,
                to_emails=To(to_email),
                subject=subject,
                html_content=Content("text/html", html_content)
            )
            
            response = self.client.send(message)
            if response.status_code in [200, 201, 202]:
                logger.info(f"Team member credentials email sent to {to_email}")
                return True
            else:
                logger.error(f"Failed to send credentials email to {to_email}: {response.status_code}")
                return False
                
        except Exception as e:
            logger.error(f"Error sending credentials email to {to_email}: {str(e)}")
            return False


email_service = EmailService()

