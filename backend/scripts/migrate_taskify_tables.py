"""
Migration script to create Taskify-related tables.

Run this once to create the new TenantTaskifyConfig and TaskifyUserMapping tables.
"""
import sys
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from sqlmodel import SQLModel
from app.db import engine
from app.models.taskify_config import TenantTaskifyConfig, TaskifyUserMapping

def migrate_taskify_tables():
    """Create Taskify configuration tables."""
    print("Creating Taskify configuration tables...")
    
    # Create tables
    SQLModel.metadata.create_all(engine, tables=[
        TenantTaskifyConfig.__table__,
        TaskifyUserMapping.__table__,
    ])
    
    print("✓ Taskify tables created successfully!")

if __name__ == "__main__":
    try:
        migrate_taskify_tables()
    except Exception as e:
        print(f"\n✗ Migration failed: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)





















