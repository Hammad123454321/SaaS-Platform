from motor.motor_asyncio import AsyncIOMotorClient
from beanie import init_beanie
import logging

from app.config import settings
from app.seed import seed_permissions, ensure_roles_for_tenant
from app.models import (
    # Core models
    User,
    Tenant,
    Role,
    Permission,
    RolePermission,
    UserRole,
    # Entitlements
    ModuleEntitlement,
    Subscription,
    BillingHistory,
    WebhookEvent,
    # Vendor
    VendorCredential,
    # Auth and Audit
    PasswordResetToken,
    ImpersonationAudit,
    AuditLog,
    # Taskify Config
    TenantTaskifyConfig,
    TaskifyUserMapping,
    # Onboarding
    EmailVerificationToken,
    PolicyAcceptance,
    CommunicationPreferences,
    RegistrationEvent,
    BusinessProfile,
    TenantComplianceRule,
    OwnerConfirmation,
    TeamInvitation,
    StaffOnboardingTask,
    # Compliance
    FinancialSetup,
    HRPolicy,
    PolicyAcknowledgement,
    PrivacyWording,
    # Workflows
    TaskTemplate,
    OnboardingTask,
    EscalationRule,
    EscalationEvent,
    # Permissions
    UserPermission,
    # Tasks
    Client,
    Project,
    Task,
    TaskStatus,
    TaskPriority,
    TaskList,
    TaskComment,
    TaskAttachment,
    TaskFavorite,
    TaskPin,
    Tag,
    Milestone,
    TimeEntry,
    TimeTracker,
    CommentAttachment,
    DocumentFolder,
    DocumentCategory,
    ResourceAllocation,
    ActivityLog,
    TaskDependency,
    RecurringTask,
    TaskAssignment,
    TaskTagLink,
)

logger = logging.getLogger(__name__)

# Global MongoDB client
client: AsyncIOMotorClient | None = None


async def init_db() -> None:
    """Initialize MongoDB connection and Beanie."""
    global client
    
    try:
        # Create Motor client
        client = AsyncIOMotorClient(settings.mongodb_uri)
        
        # Test connection
        await client.admin.command('ping')
        logger.info(f"Connected to MongoDB: {settings.mongodb_db_name}")
        
        # Initialize Beanie with all document models
        await init_beanie(
            database=client[settings.mongodb_db_name],
            document_models=[
                # Core models
                User,
                Tenant,
                Role,
                Permission,
                RolePermission,
                UserRole,
                # Entitlements
                ModuleEntitlement,
                Subscription,
                BillingHistory,
                WebhookEvent,
                # Vendor
                VendorCredential,
                # Auth and Audit
                PasswordResetToken,
                ImpersonationAudit,
                AuditLog,
                # Taskify Config
                TenantTaskifyConfig,
                TaskifyUserMapping,
                # Onboarding
                EmailVerificationToken,
                PolicyAcceptance,
                CommunicationPreferences,
                RegistrationEvent,
                BusinessProfile,
                TenantComplianceRule,
                OwnerConfirmation,
                TeamInvitation,
                StaffOnboardingTask,
                # Compliance
                FinancialSetup,
                HRPolicy,
                PolicyAcknowledgement,
                PrivacyWording,
                # Workflows
                TaskTemplate,
                OnboardingTask,
                EscalationRule,
                EscalationEvent,
                # Permissions
                UserPermission,
                # Tasks
                Client,
                Project,
                Task,
                TaskStatus,
                TaskPriority,
                TaskList,
                TaskComment,
                TaskAttachment,
                TaskFavorite,
                TaskPin,
                Tag,
                Milestone,
                TimeEntry,
                TimeTracker,
                CommentAttachment,
                DocumentFolder,
                DocumentCategory,
                ResourceAllocation,
                ActivityLog,
                TaskDependency,
                RecurringTask,
                TaskAssignment,
                TaskTagLink,
            ]
        )
        
        logger.info("Beanie initialized successfully")
        
        # Seed permissions and roles
        await seed_permissions()
        
        # Update roles for all existing tenants
        tenants = await Tenant.find_all().to_list()
        for tenant in tenants:
            try:
                await ensure_roles_for_tenant(str(tenant.id))
                logger.info(f"Updated roles for tenant {tenant.id} ({tenant.name})")
            except Exception as e:
                logger.warning(f"Failed to update roles for tenant {tenant.id}: {e}")
        
        logger.info("Database initialization completed")
        
    except Exception as e:
        logger.error(f"Failed to initialize database: {e}")
        raise


async def close_db() -> None:
    """Close MongoDB connection."""
    global client
    if client:
        client.close()
        logger.info("MongoDB connection closed")
