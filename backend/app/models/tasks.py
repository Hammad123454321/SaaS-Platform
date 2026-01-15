"""
Task Management Module - Database Models

All models include tenant_id for multi-tenant isolation.
"""
from datetime import datetime, date, timezone
from decimal import Decimal
from typing import Optional, TYPE_CHECKING
from enum import Enum

from sqlalchemy import JSON, Column, ForeignKey, Text, CheckConstraint, DECIMAL, Integer
from sqlmodel import SQLModel, Field, Relationship

if TYPE_CHECKING:
    from app.models.user import User
    from app.models.tenant import Tenant


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
class TaskAssignment(SQLModel, table=True):
    """Link model for Task-User many-to-many relationship."""
    __tablename__ = "task_assignments"
    
    task_id: int = Field(
        sa_column=Column(Integer, ForeignKey("tasks.id", ondelete="CASCADE"), primary_key=True)
    )
    user_id: int = Field(
        sa_column=Column(Integer, ForeignKey("users.id", ondelete="CASCADE"), primary_key=True)
    )
    is_primary: bool = Field(default=False)
    assigned_at: datetime = Field(default_factory=datetime.utcnow)


class TaskTagLink(SQLModel, table=True):
    """Link model for Task-Tag many-to-many relationship."""
    __tablename__ = "task_tags"
    
    task_id: int = Field(
        sa_column=Column(Integer, ForeignKey("tasks.id", ondelete="CASCADE"), primary_key=True)
    )
    tag_id: int = Field(
        sa_column=Column(Integer, ForeignKey("tags.id", ondelete="CASCADE"), primary_key=True)
    )


# Keep legacy table references for backward compatibility with existing code
task_assignments_table = TaskAssignment.__table__
task_tags_table = TaskTagLink.__table__


# ========== Core Models ==========
class Client(SQLModel, table=True):
    """Client model - belongs to a tenant."""
    __tablename__ = "clients"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    
    # Client information
    first_name: str = Field(max_length=255)
    last_name: str = Field(max_length=255)
    email: str = Field(max_length=255, index=True)
    phone: Optional[str] = Field(default=None, max_length=50)
    company: Optional[str] = Field(default=None, max_length=255)
    address: Optional[str] = Field(default=None, sa_column=Column(Text))
    notes: Optional[str] = Field(default=None, sa_column=Column(Text))
    
    # Status
    is_active: bool = Field(default=True)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    projects: list["Project"] = Relationship(back_populates="client")


class Project(SQLModel, table=True):
    """Project model - belongs to a client and tenant."""
    __tablename__ = "projects"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    client_id: int = Field(foreign_key="clients.id", index=True)
    
    # Project information
    name: str = Field(max_length=255)
    description: Optional[str] = Field(default=None, sa_column=Column(Text))
    budget: Optional[Decimal] = Field(default=None, sa_column=Column(DECIMAL(10, 2), nullable=True))
    start_date: Optional[date] = Field(default=None)
    deadline: Optional[date] = Field(default=None)
    status: ProjectStatus = Field(default=ProjectStatus.ACTIVE)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    client: Optional["Client"] = Relationship(back_populates="projects")
    tasks: list["Task"] = Relationship(back_populates="project")
    milestones: list["Milestone"] = Relationship(back_populates="project")
    task_lists: list["TaskList"] = Relationship(back_populates="project")
    threads: list["TaskComment"] = Relationship(back_populates="project")
    documents: list["TaskAttachment"] = Relationship(back_populates="project")
    document_folders: list["DocumentFolder"] = Relationship(back_populates="project")
    resource_allocations: list["ResourceAllocation"] = Relationship(back_populates="project")


class TaskStatus(SQLModel, table=True):
    """Custom task status - per tenant."""
    __tablename__ = "task_statuses"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    
    # Status information
    name: str = Field(max_length=100)
    color: str = Field(default="#6b7280", max_length=7)  # Hex color
    category: TaskStatusCategory = Field(default=TaskStatusCategory.TODO)
    display_order: int = Field(default=0)  # For custom ordering
    is_default: bool = Field(default=False)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    tasks: list["Task"] = Relationship(back_populates="status")


class TaskPriority(SQLModel, table=True):
    """Custom task priority - per tenant."""
    __tablename__ = "task_priorities"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    
    # Priority information
    name: str = Field(max_length=100)
    color: str = Field(default="#6b7280", max_length=7)  # Hex color
    level: int = Field(default=0)  # 0=low, 1=medium, 2=high, 3=urgent
    display_order: int = Field(default=0)
    is_default: bool = Field(default=False)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    tasks: list["Task"] = Relationship(back_populates="priority")


class TaskList(SQLModel, table=True):
    """Task list within a project."""
    __tablename__ = "task_lists"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    project_id: int = Field(foreign_key="projects.id", index=True)
    
    # List information
    name: str = Field(max_length=255)
    description: Optional[str] = Field(default=None, sa_column=Column(Text))
    display_order: int = Field(default=0)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    project: Optional["Project"] = Relationship(back_populates="task_lists")
    tasks: list["Task"] = Relationship(back_populates="task_list")


class Task(SQLModel, table=True):
    """Main task model."""
    __tablename__ = "tasks"
    __table_args__ = (
        CheckConstraint("completion_percentage >= 0 AND completion_percentage <= 100", name="check_completion_percentage"),
    )

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    project_id: int = Field(foreign_key="projects.id", index=True)
    
    # Task information
    title: str = Field(max_length=255)
    description: Optional[str] = Field(default=None, sa_column=Column(Text))
    notes: Optional[str] = Field(default=None, sa_column=Column(Text))
    
    # Relationships
    status_id: int = Field(foreign_key="task_statuses.id", index=True)
    priority_id: Optional[int] = Field(default=None, foreign_key="task_priorities.id", index=True)
    task_list_id: Optional[int] = Field(default=None, foreign_key="task_lists.id", index=True)
    
    # Dates
    start_date: Optional[date] = Field(default=None)
    due_date: Optional[date] = Field(default=None)
    
    # Task hierarchy
    parent_id: Optional[int] = Field(default=None, foreign_key="tasks.id", index=True)
    
    # Progress
    completion_percentage: int = Field(default=0)
    
    # Stage 5: Required tasks (non-deletable)
    is_required: bool = Field(default=False)
    
    # Billing
    billing_type: BillingType = Field(default=BillingType.NONE)
    
    # Client visibility
    client_can_discuss: bool = Field(default=False)
    
    # Creator
    created_by: int = Field(foreign_key="users.id", index=True)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    project: Optional["Project"] = Relationship(back_populates="tasks")
    status: Optional["TaskStatus"] = Relationship(back_populates="tasks")
    priority: Optional["TaskPriority"] = Relationship(back_populates="tasks")
    task_list: Optional["TaskList"] = Relationship(back_populates="tasks")
    parent: Optional["Task"] = Relationship(
        back_populates="subtasks",
        sa_relationship_kwargs={"remote_side": "Task.id"}
    )
    subtasks: list["Task"] = Relationship(back_populates="parent")
    # Assignees via link model (one-sided relationship, no back_populates on User)
    assignees: list["User"] = Relationship(link_model=TaskAssignment)
    comments: list["TaskComment"] = Relationship(back_populates="task")
    attachments: list["TaskAttachment"] = Relationship(back_populates="task")
    favorites: list["TaskFavorite"] = Relationship(back_populates="task")
    pins: list["TaskPin"] = Relationship(back_populates="task")
    # Tags via link model (one-sided relationship, Tag references Task)
    tags: list["Tag"] = Relationship(link_model=TaskTagLink)
    time_entries: list["TimeEntry"] = Relationship(back_populates="task")
    time_trackers: list["TimeTracker"] = Relationship(back_populates="task")
    # dependencies relationship handled via TaskDependency model
    recurring_config: Optional["RecurringTask"] = Relationship(back_populates="task")


class TaskComment(SQLModel, table=True):
    """Comments/Threads on tasks - supports nesting."""
    __tablename__ = "task_comments"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    task_id: Optional[int] = Field(default=None, foreign_key="tasks.id", index=True)
    project_id: Optional[int] = Field(default=None, foreign_key="projects.id", index=True)  # For project-level threads
    user_id: int = Field(foreign_key="users.id", index=True)
    parent_id: Optional[int] = Field(default=None, foreign_key="task_comments.id", index=True)  # For nested replies
    
    # Comment content
    comment: str = Field(sa_column=Column(Text))
    is_edited: bool = Field(default=False)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    task: Optional["Task"] = Relationship(back_populates="comments")
    project: Optional["Project"] = Relationship(back_populates="threads")
    user: Optional["User"] = Relationship()  # User who made the comment
    parent: Optional["TaskComment"] = Relationship(
        back_populates="replies",
        sa_relationship_kwargs={"remote_side": "TaskComment.id"}
    )
    replies: list["TaskComment"] = Relationship(back_populates="parent")
    attachments: list["CommentAttachment"] = Relationship(back_populates="comment")


class TaskAttachment(SQLModel, table=True):
    """File attachments/documents for tasks - with versioning and organization."""
    __tablename__ = "task_attachments"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    task_id: Optional[int] = Field(default=None, foreign_key="tasks.id", index=True)
    project_id: Optional[int] = Field(default=None, foreign_key="projects.id", index=True)  # For project-level docs
    user_id: int = Field(foreign_key="users.id", index=True)  # Uploader
    parent_id: Optional[int] = Field(default=None, foreign_key="task_attachments.id", index=True)  # For document versions
    folder_id: Optional[int] = Field(default=None, foreign_key="document_folders.id", index=True)
    category_id: Optional[int] = Field(default=None, foreign_key="document_categories.id", index=True)
    
    # File information
    filename: str = Field(max_length=255)
    original_filename: str = Field(max_length=255)  # Original name before upload
    file_path: str = Field(max_length=500)  # Storage path
    file_size: int = Field()  # Size in bytes
    mime_type: str = Field(max_length=100)
    version: int = Field(default=1)  # Version number
    is_current_version: bool = Field(default=True)
    
    # Metadata
    description: Optional[str] = Field(default=None, sa_column=Column(Text))
    tags: Optional[str] = Field(default=None, sa_column=Column(JSON))  # JSON array of tags
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    task: Optional["Task"] = Relationship(back_populates="attachments")
    project: Optional["Project"] = Relationship(back_populates="documents")
    folder: Optional["DocumentFolder"] = Relationship(back_populates="documents")
    category: Optional["DocumentCategory"] = Relationship(back_populates="documents")
    parent_version: Optional["TaskAttachment"] = Relationship(
        back_populates="versions",
        sa_relationship_kwargs={"remote_side": "TaskAttachment.id"}
    )
    versions: list["TaskAttachment"] = Relationship(back_populates="parent_version")


class TaskFavorite(SQLModel, table=True):
    """User favorites for tasks."""
    __tablename__ = "task_favorites"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    task_id: int = Field(foreign_key="tasks.id", index=True)
    user_id: int = Field(foreign_key="users.id", index=True)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    task: Optional["Task"] = Relationship(back_populates="favorites")


class TaskPin(SQLModel, table=True):
    """Pinned tasks for users."""
    __tablename__ = "task_pins"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    task_id: int = Field(foreign_key="tasks.id", index=True)
    user_id: int = Field(foreign_key="users.id", index=True)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    task: Optional["Task"] = Relationship(back_populates="pins")


class Tag(SQLModel, table=True):
    """Tags for tasks."""
    __tablename__ = "tags"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    
    # Tag information
    name: str = Field(max_length=100, index=True)
    color: Optional[str] = Field(default=None, max_length=7)  # Hex color
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships (one-sided, Task has the primary relationship)
    # Access tasks via Task.tags relationship


class Milestone(SQLModel, table=True):
    """Project milestones."""
    __tablename__ = "milestones"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    project_id: int = Field(foreign_key="projects.id", index=True)
    
    # Milestone information
    title: str = Field(max_length=255)
    description: Optional[str] = Field(default=None, sa_column=Column(Text))
    due_date: Optional[date] = Field(default=None)
    is_completed: bool = Field(default=False)
    completed_at: Optional[datetime] = Field(default=None)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    project: Optional["Project"] = Relationship(back_populates="milestones")


class TimeEntry(SQLModel, table=True):
    """Time tracking entries for tasks."""
    __tablename__ = "time_entries"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    task_id: int = Field(foreign_key="tasks.id", index=True)
    user_id: int = Field(foreign_key="users.id", index=True)
    
    # Time information
    description: Optional[str] = Field(default=None, sa_column=Column(Text))
    hours: Decimal = Field(sa_column=Column("hours", DECIMAL(10, 2)))
    entry_date: date = Field()
    is_billable: bool = Field(default=True)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    task: Optional["Task"] = Relationship(back_populates="time_entries")


class TimeTracker(SQLModel, table=True):
    """Active time tracker sessions (start/stop timer)."""
    __tablename__ = "time_trackers"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    task_id: Optional[int] = Field(default=None, foreign_key="tasks.id", index=True)
    user_id: int = Field(foreign_key="users.id", index=True)
    
    # Timer information
    start_date_time: datetime = Field()
    end_date_time: Optional[datetime] = Field(default=None)
    duration: Optional[Decimal] = Field(default=None, sa_column=Column("duration", DECIMAL(10, 2), nullable=True))  # Calculated duration in hours
    message: Optional[str] = Field(default=None, sa_column=Column(Text))
    is_running: bool = Field(default=True)  # True if timer is currently running
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    task: Optional["Task"] = Relationship(back_populates="time_trackers")


class CommentAttachment(SQLModel, table=True):
    """File attachments for comments."""
    __tablename__ = "comment_attachments"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    comment_id: int = Field(foreign_key="task_comments.id", index=True)
    user_id: int = Field(foreign_key="users.id", index=True)
    
    # File information
    filename: str = Field(max_length=255)
    file_path: str = Field(max_length=500)
    file_size: int = Field()
    mime_type: str = Field(max_length=100)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    comment: Optional["TaskComment"] = Relationship(back_populates="attachments")


class DocumentFolder(SQLModel, table=True):
    """Folders for organizing documents."""
    __tablename__ = "document_folders"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    project_id: Optional[int] = Field(default=None, foreign_key="projects.id", index=True)
    parent_id: Optional[int] = Field(default=None, foreign_key="document_folders.id", index=True)  # Nested folders
    
    # Folder information
    name: str = Field(max_length=255)
    description: Optional[str] = Field(default=None, sa_column=Column(Text))
    display_order: int = Field(default=0)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    project: Optional["Project"] = Relationship(back_populates="document_folders")
    parent: Optional["DocumentFolder"] = Relationship(
        back_populates="subfolders",
        sa_relationship_kwargs={"remote_side": "DocumentFolder.id"}
    )
    subfolders: list["DocumentFolder"] = Relationship(back_populates="parent")
    documents: list["TaskAttachment"] = Relationship(back_populates="folder")


class DocumentCategory(SQLModel, table=True):
    """Categories for organizing documents."""
    __tablename__ = "document_categories"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    
    # Category information
    name: str = Field(max_length=100)
    color: Optional[str] = Field(default=None, max_length=7)  # Hex color
    description: Optional[str] = Field(default=None, sa_column=Column(Text))
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    documents: list["TaskAttachment"] = Relationship(back_populates="category")


class ResourceAllocation(SQLModel, table=True):
    """Resource allocation for projects."""
    __tablename__ = "resource_allocations"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    project_id: int = Field(foreign_key="projects.id", index=True)
    user_id: int = Field(foreign_key="users.id", index=True)
    
    # Allocation information
    allocated_hours: Decimal = Field(sa_column=Column("allocated_hours", DECIMAL(10, 2)))  # Total hours allocated
    start_date: date = Field()
    end_date: Optional[date] = Field(default=None)
    is_active: bool = Field(default=True)
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    project: Optional["Project"] = Relationship(back_populates="resource_allocations")


class ActivityLog(SQLModel, table=True):
    """Activity log for tracking all changes in task management."""
    __tablename__ = "activity_logs"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    user_id: Optional[int] = Field(default=None, foreign_key="users.id", index=True)
    
    # Activity information
    entity_type: str = Field(max_length=50, index=True)  # 'task', 'project', 'client', etc.
    entity_id: int = Field(index=True)
    action: str = Field(max_length=50)  # 'created', 'updated', 'deleted', 'assigned', etc.
    description: str = Field(sa_column=Column(Text))
    changes: Optional[str] = Field(default=None, sa_column=Column(JSON))  # JSON of field changes
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow, index=True)


class TaskDependency(SQLModel, table=True):
    """Task dependencies - defines blocking relationships between tasks."""
    __tablename__ = "task_dependencies"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    task_id: int = Field(foreign_key="tasks.id", index=True)  # Task that depends on another
    depends_on_task_id: int = Field(foreign_key="tasks.id", index=True)  # Task that must be completed first
    
    # Dependency type
    dependency_type: str = Field(default="blocks", max_length=50)  # 'blocks', 'blocks_start', 'blocks_completion'
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships - Note: SQLModel doesn't support multiple foreign keys to same table easily
    # We'll access tasks via queries instead


class RecurringTask(SQLModel, table=True):
    """Recurring task configuration."""
    __tablename__ = "recurring_tasks"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    task_id: int = Field(foreign_key="tasks.id", index=True, unique=True)  # Template task
    
    # Recurrence pattern
    recurrence_type: str = Field(max_length=50)  # 'daily', 'weekly', 'monthly', 'yearly', 'custom'
    recurrence_interval: int = Field(default=1)  # Every N days/weeks/months
    
    # Weekly recurrence
    days_of_week: Optional[str] = Field(default=None, sa_column=Column(JSON))  # [0,1,2,3,4,5,6] for Mon-Sun
    
    # Monthly recurrence
    day_of_month: Optional[int] = Field(default=None)  # Day of month (1-31)
    week_of_month: Optional[int] = Field(default=None)  # Week of month (1-4, -1 for last)
    weekday_of_month: Optional[int] = Field(default=None)  # Weekday (0-6)
    
    # Yearly recurrence
    month_of_year: Optional[int] = Field(default=None)  # Month (1-12)
    
    # Custom recurrence rule (RRULE format)
    custom_rule: Optional[str] = Field(default=None, sa_column=Column(Text))
    
    # End conditions
    end_date: Optional[date] = Field(default=None)  # Stop recurring after this date
    max_occurrences: Optional[int] = Field(default=None)  # Stop after N occurrences
    occurrence_count: int = Field(default=0)  # Number of occurrences created
    
    # Status
    is_active: bool = Field(default=True)
    last_created_at: Optional[datetime] = Field(default=None)  # Last time a task was created
    
    # Timestamps
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    
    # Relationships
    task: Optional["Task"] = Relationship(back_populates="recurring_config")
