"""
Script to create task management tables.

Run this after the main migrations to create all task management tables.
"""
import sys
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from sqlmodel import SQLModel, create_engine
from app.models.tasks import (
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
    task_assignments_table,
    task_tags_table,
)
from app.config import settings

def create_task_tables():
    """Create all task management tables."""
    engine = create_engine(str(settings.database_url))
    
    print("Creating task management tables...")
    
    # Create all tables
    SQLModel.metadata.create_all(engine, tables=[
        Client.__table__,
        Project.__table__,
        TaskStatus.__table__,
        TaskPriority.__table__,
        TaskList.__table__,
        Task.__table__,
        TaskComment.__table__,
        TaskAttachment.__table__,
        TaskFavorite.__table__,
        TaskPin.__table__,
        Tag.__table__,
        Milestone.__table__,
        TimeEntry.__table__,
        TimeTracker.__table__,
        CommentAttachment.__table__,
        DocumentFolder.__table__,
        DocumentCategory.__table__,
        ResourceAllocation.__table__,
        ActivityLog.__table__,
        task_assignments_table,
        task_tags_table,
    ])
    
    print("✓ Task management tables created successfully!")

if __name__ == "__main__":
    create_task_tables()

