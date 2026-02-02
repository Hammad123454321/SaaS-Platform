"""
Task Management Module - Database Models for MongoDB

All models include tenant_id for multi-tenant isolation.
"""
from datetime import datetime, date
from decimal import Decimal
from typing import Optional, List
from enum import Enum

from beanie import Document
from pydantic import Field, field_validator
from bson.decimal128 import Decimal128


# ========== Enums ==========
class TaskStatusCategory(str, Enum):
    """Status categories for organizing task statuses."""
    TODO = "todo"
    IN_PROGRESS = "in_progress"
    DONE = "done"
    CANCELLED = "cancelled"


class ProjectStatus(str, Enum):
    """Project status options."""
    ACTIVE = "active"
    COMPLETED = "completed"
    ON_HOLD = "on_hold"
    CANCELLED = "cancelled"


class BillingType(str, Enum):
    """Task billing type."""
    NONE = "none"
    BILLABLE = "billable"
    NON_BILLABLE = "non_billable"


# ========== Link Models for Many-to-Many Relationships ==========
class TaskAssignment(Document):
    """Link model for Task-User many-to-many relationship."""
    
    task_id: str = Field(..., index=True)
    user_id: str = Field(..., index=True)
    is_primary: bool = Field(default=False)
    assigned_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "task_assignments"
        indexes = [
            "task_id",
            "user_id",
            ("task_id", "user_id"),  # Compound index
        ]


class TaskTagLink(Document):
    """Link model for Task-Tag many-to-many relationship."""
    
    task_id: str = Field(..., index=True)
    tag_id: str = Field(..., index=True)

    class Settings:
        name = "task_tags"
        indexes = [
            "task_id",
            "tag_id",
            ("task_id", "tag_id"),  # Compound index
        ]


# Legacy references (not used in MongoDB but kept for compatibility)
task_assignments_table = None
task_tags_table = None


# ========== Core Models ==========
class Client(Document):
    """Client model - belongs to a tenant."""
    
    tenant_id: str = Field(..., index=True)
    
    # Client information
    first_name: str = Field(max_length=255)
    last_name: str = Field(max_length=255)
    email: str = Field(max_length=255, index=True)
    phone: Optional[str] = Field(default=None, max_length=50)
    company: Optional[str] = Field(default=None, max_length=255)
    address: Optional[str] = None
    notes: Optional[str] = None
    
    # Status
    is_active: bool = Field(default=True)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "clients"
        indexes = [
            "tenant_id",
            "email",
        ]


class Project(Document):
    """Project model - belongs to a client and tenant."""
    
    tenant_id: str = Field(..., index=True)
    client_id: str = Field(..., index=True)
    
    # Project information
    name: str = Field(max_length=255)
    description: Optional[str] = None
    budget: Optional[Decimal] = None
    start_date: Optional[date] = None
    deadline: Optional[date] = None
    status: ProjectStatus = Field(default=ProjectStatus.ACTIVE)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "projects"
        indexes = [
            "tenant_id",
            "client_id",
        ]

    @field_validator("budget", mode="before")
    @classmethod
    def _coerce_budget_decimal128(cls, value):
        if isinstance(value, Decimal128):
            return value.to_decimal()
        return value


class TaskStatus(Document):
    """Custom task status - per tenant."""
    
    tenant_id: str = Field(..., index=True)
    
    # Status information
    name: str = Field(max_length=100)
    color: str = Field(default="#6b7280", max_length=7)  # Hex color
    category: TaskStatusCategory = Field(default=TaskStatusCategory.TODO)
    display_order: int = Field(default=0)  # For custom ordering
    is_default: bool = Field(default=False)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "task_statuses"
        indexes = [
            "tenant_id",
        ]


class TaskPriority(Document):
    """Custom task priority - per tenant."""
    
    tenant_id: str = Field(..., index=True)
    
    # Priority information
    name: str = Field(max_length=100)
    color: str = Field(default="#6b7280", max_length=7)  # Hex color
    level: int = Field(default=0)  # 0=low, 1=medium, 2=high, 3=urgent
    display_order: int = Field(default=0)
    is_default: bool = Field(default=False)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "task_priorities"
        indexes = [
            "tenant_id",
        ]


class TaskList(Document):
    """Task list within a project."""
    
    tenant_id: str = Field(..., index=True)
    project_id: str = Field(..., index=True)
    
    # List information
    name: str = Field(max_length=255)
    description: Optional[str] = None
    display_order: int = Field(default=0)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "task_lists"
        indexes = [
            "tenant_id",
            "project_id",
        ]


class Task(Document):
    """Main task model."""
    
    tenant_id: str = Field(..., index=True)
    project_id: str = Field(..., index=True)
    
    # Task information
    title: str = Field(max_length=255)
    description: Optional[str] = None
    notes: Optional[str] = None
    
    # Relationships (stored as IDs)
    status_id: str = Field(..., index=True)
    priority_id: Optional[str] = Field(default=None, index=True)
    task_list_id: Optional[str] = Field(default=None, index=True)
    
    # Dates
    start_date: Optional[date] = None
    due_date: Optional[date] = None
    
    # Task hierarchy
    parent_id: Optional[str] = Field(default=None, index=True)
    
    # Progress
    completion_percentage: int = Field(default=0, ge=0, le=100)
    
    # Stage 5: Required tasks (non-deletable)
    is_required: bool = Field(default=False)
    
    # Billing
    billing_type: BillingType = Field(default=BillingType.NONE)
    
    # Client visibility
    client_can_discuss: bool = Field(default=False)
    
    # Creator
    created_by: str = Field(..., index=True)
    
    # Assignees (stored as list of user IDs)
    assignee_ids: List[str] = Field(default_factory=list)
    
    # Tags (stored as list of tag IDs)
    tag_ids: List[str] = Field(default_factory=list)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "tasks"
        indexes = [
            "tenant_id",
            "project_id",
            "status_id",
            "priority_id",
            "task_list_id",
            "parent_id",
            "created_by",
        ]


class TaskComment(Document):
    """Comments/Threads on tasks - supports nesting."""
    
    tenant_id: str = Field(..., index=True)
    task_id: Optional[str] = Field(default=None, index=True)
    project_id: Optional[str] = Field(default=None, index=True)  # For project-level threads
    user_id: str = Field(..., index=True)
    parent_id: Optional[str] = Field(default=None, index=True)  # For nested replies
    
    # Comment content
    comment: str
    is_edited: bool = Field(default=False)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "task_comments"
        indexes = [
            "tenant_id",
            "task_id",
            "project_id",
            "user_id",
            "parent_id",
        ]


class TaskAttachment(Document):
    """File attachments/documents for tasks - with versioning and organization."""
    
    tenant_id: str = Field(..., index=True)
    task_id: Optional[str] = Field(default=None, index=True)
    project_id: Optional[str] = Field(default=None, index=True)  # For project-level docs
    user_id: str = Field(..., index=True)  # Uploader
    parent_id: Optional[str] = Field(default=None, index=True)  # For document versions
    folder_id: Optional[str] = Field(default=None, index=True)
    category_id: Optional[str] = Field(default=None, index=True)
    
    # File information
    filename: str = Field(max_length=255)
    original_filename: str = Field(max_length=255)  # Original name before upload
    file_path: str = Field(max_length=500)  # Storage path
    file_size: int  # Size in bytes
    mime_type: str = Field(max_length=100)
    version: int = Field(default=1)  # Version number
    is_current_version: bool = Field(default=True)
    
    # Metadata
    description: Optional[str] = None
    tags: Optional[List[str]] = Field(default=None)  # Array of tags
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "task_attachments"
        indexes = [
            "tenant_id",
            "task_id",
            "project_id",
            "user_id",
            "parent_id",
            "folder_id",
            "category_id",
        ]


class TaskFavorite(Document):
    """User favorites for tasks."""
    
    tenant_id: str = Field(..., index=True)
    task_id: str = Field(..., index=True)
    user_id: str = Field(..., index=True)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "task_favorites"
        indexes = [
            "tenant_id",
            "task_id",
            "user_id",
            ("task_id", "user_id"),  # Compound index
        ]


class TaskPin(Document):
    """Pinned tasks for users."""
    
    tenant_id: str = Field(..., index=True)
    task_id: str = Field(..., index=True)
    user_id: str = Field(..., index=True)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "task_pins"
        indexes = [
            "tenant_id",
            "task_id",
            "user_id",
            ("task_id", "user_id"),  # Compound index
        ]


class Tag(Document):
    """Tags for tasks."""
    
    tenant_id: str = Field(..., index=True)
    
    # Tag information
    name: str = Field(max_length=100, index=True)
    color: Optional[str] = Field(default=None, max_length=7)  # Hex color
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "tags"
        indexes = [
            "tenant_id",
            "name",
        ]


class Milestone(Document):
    """Project milestones."""
    
    tenant_id: str = Field(..., index=True)
    project_id: str = Field(..., index=True)
    
    # Milestone information
    title: str = Field(max_length=255)
    description: Optional[str] = None
    due_date: Optional[date] = None
    is_completed: bool = Field(default=False)
    completed_at: Optional[datetime] = None
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "milestones"
        indexes = [
            "tenant_id",
            "project_id",
        ]


class TimeEntry(Document):
    """Time tracking entries for tasks."""
    
    tenant_id: str = Field(..., index=True)
    task_id: str = Field(..., index=True)
    user_id: str = Field(..., index=True)
    
    # Time information
    description: Optional[str] = None
    hours: Decimal
    entry_date: date
    is_billable: bool = Field(default=True)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "time_entries"
        indexes = [
            "tenant_id",
            "task_id",
            "user_id",
        ]


class TimeTracker(Document):
    """Active time tracker sessions (start/stop timer)."""
    
    tenant_id: str = Field(..., index=True)
    task_id: Optional[str] = Field(default=None, index=True)
    user_id: str = Field(..., index=True)
    
    # Timer information
    start_date_time: datetime
    end_date_time: Optional[datetime] = None
    duration: Optional[Decimal] = None  # Calculated duration in hours
    message: Optional[str] = None
    is_running: bool = Field(default=True)  # True if timer is currently running
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "time_trackers"
        indexes = [
            "tenant_id",
            "task_id",
            "user_id",
        ]


class CommentAttachment(Document):
    """File attachments for comments."""
    
    tenant_id: str = Field(..., index=True)
    comment_id: str = Field(..., index=True)
    user_id: str = Field(..., index=True)
    
    # File information
    filename: str = Field(max_length=255)
    file_path: str = Field(max_length=500)
    file_size: int
    mime_type: str = Field(max_length=100)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "comment_attachments"
        indexes = [
            "tenant_id",
            "comment_id",
            "user_id",
        ]


class DocumentFolder(Document):
    """Folders for organizing documents."""
    
    tenant_id: str = Field(..., index=True)
    project_id: Optional[str] = Field(default=None, index=True)
    parent_id: Optional[str] = Field(default=None, index=True)  # Nested folders
    
    # Folder information
    name: str = Field(max_length=255)
    description: Optional[str] = None
    display_order: int = Field(default=0)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "document_folders"
        indexes = [
            "tenant_id",
            "project_id",
            "parent_id",
        ]


class DocumentCategory(Document):
    """Categories for organizing documents."""
    
    tenant_id: str = Field(..., index=True)
    
    # Category information
    name: str = Field(max_length=100)
    color: Optional[str] = Field(default=None, max_length=7)  # Hex color
    description: Optional[str] = None
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "document_categories"
        indexes = [
            "tenant_id",
        ]


class ResourceAllocation(Document):
    """Resource allocation for projects."""
    
    tenant_id: str = Field(..., index=True)
    project_id: str = Field(..., index=True)
    user_id: str = Field(..., index=True)
    
    # Allocation information
    allocated_hours: Decimal  # Total hours allocated
    start_date: date
    end_date: Optional[date] = None
    is_active: bool = Field(default=True)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "resource_allocations"
        indexes = [
            "tenant_id",
            "project_id",
            "user_id",
        ]


class ActivityLog(Document):
    """Activity log for tracking all changes in task management."""
    
    tenant_id: str = Field(..., index=True)
    user_id: Optional[str] = Field(default=None, index=True)
    
    # Activity information
    entity_type: str = Field(max_length=50, index=True)  # 'task', 'project', 'client', etc.
    entity_id: str = Field(index=True)
    action: str = Field(max_length=50)  # 'created', 'updated', 'deleted', 'assigned', etc.
    description: str
    changes: Optional[dict] = None  # Dict of field changes
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow, index=True)

    class Settings:
        name = "activity_logs"
        indexes = [
            "tenant_id",
            "user_id",
            "entity_type",
            "entity_id",
            "created_at",
        ]


class TaskDependency(Document):
    """Task dependencies - defines blocking relationships between tasks."""
    
    tenant_id: str = Field(..., index=True)
    task_id: str = Field(..., index=True)  # Task that depends on another
    depends_on_task_id: str = Field(..., index=True)  # Task that must be completed first
    
    # Dependency type
    dependency_type: str = Field(default="blocks", max_length=50)  # 'blocks', 'blocks_start', 'blocks_completion'
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "task_dependencies"
        indexes = [
            "tenant_id",
            "task_id",
            "depends_on_task_id",
        ]


class RecurringTask(Document):
    """Recurring task configuration."""
    
    tenant_id: str = Field(..., index=True)
    task_id: str = Field(..., index=True, unique=True)  # Template task
    
    # Recurrence pattern
    recurrence_type: str = Field(max_length=50)  # 'daily', 'weekly', 'monthly', 'yearly', 'custom'
    recurrence_interval: int = Field(default=1)  # Every N days/weeks/months
    
    # Weekly recurrence
    days_of_week: Optional[List[int]] = None  # [0,1,2,3,4,5,6] for Mon-Sun
    
    # Monthly recurrence
    day_of_month: Optional[int] = None  # Day of month (1-31)
    week_of_month: Optional[int] = None  # Week of month (1-4, -1 for last)
    weekday_of_month: Optional[int] = None  # Weekday (0-6)
    
    # Yearly recurrence
    month_of_year: Optional[int] = None  # Month (1-12)
    
    # Custom recurrence rule (RRULE format)
    custom_rule: Optional[str] = None
    
    # End conditions
    end_date: Optional[date] = None  # Stop recurring after this date
    max_occurrences: Optional[int] = None  # Stop after N occurrences
    occurrence_count: int = Field(default=0)  # Number of occurrences created
    
    # Status
    is_active: bool = Field(default=True)
    last_created_at: Optional[datetime] = None  # Last time a task was created
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "recurring_tasks"
        indexes = [
            "tenant_id",
            "task_id",
        ]
