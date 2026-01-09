"""
Run all database migrations.

This script updates the database schema to match the current models.
Run this after pulling new code or when you see column errors.
"""
import sys
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from sqlalchemy import text, inspect
from sqlmodel import SQLModel
from app.db import engine
from app.models.taskify_config import TenantTaskifyConfig, TaskifyUserMapping

def table_exists(table_name: str) -> bool:
    """Check if a table exists."""
    inspector = inspect(engine)
    return table_name in inspector.get_table_names()

def column_exists(table_name: str, column_name: str) -> bool:
    """Check if a column exists in a table."""
    inspector = inspect(engine)
    if not table_exists(table_name):
        return False
    columns = [col['name'] for col in inspector.get_columns(table_name)]
    return column_name in columns

def migrate_subscriptions():
    """Add missing columns to subscriptions table."""
    print("Checking subscriptions table...")
    
    if not table_exists('subscriptions'):
        print("  ⚠ subscriptions table doesn't exist. It will be created by SQLModel.")
        return
    
    with engine.connect() as conn:
        migrations = []
        
        if not column_exists('subscriptions', 'stripe_price_id'):
            migrations.append("ALTER TABLE subscriptions ADD COLUMN stripe_price_id VARCHAR")
        
        if not column_exists('subscriptions', 'amount'):
            migrations.append("ALTER TABLE subscriptions ADD COLUMN amount INTEGER DEFAULT 0")
        
        if not column_exists('subscriptions', 'currency'):
            migrations.append("ALTER TABLE subscriptions ADD COLUMN currency VARCHAR DEFAULT 'usd'")
        
        if not column_exists('subscriptions', 'interval'):
            migrations.append("ALTER TABLE subscriptions ADD COLUMN interval VARCHAR DEFAULT 'month'")
        
        if not column_exists('subscriptions', 'current_period_start'):
            migrations.append("ALTER TABLE subscriptions ADD COLUMN current_period_start TIMESTAMP")
        
        if not column_exists('subscriptions', 'current_period_end'):
            migrations.append("ALTER TABLE subscriptions ADD COLUMN current_period_end TIMESTAMP")
        
        if not column_exists('subscriptions', 'modules'):
            # Use JSON for PostgreSQL
            migrations.append("ALTER TABLE subscriptions ADD COLUMN modules JSONB DEFAULT '{}'")
        
        if not migrations:
            print("  ✓ All columns already exist.")
            return
        
        print(f"  → Adding {len(migrations)} column(s)...")
        for migration in migrations:
            print(f"    {migration}")
            conn.execute(text(migration))
        
        conn.commit()
        print("  ✓ Subscriptions table migrated!")

def migrate_taskify_tables():
    """Create Taskify configuration tables."""
    print("Checking Taskify tables...")
    
    # Create tables using SQLModel
    SQLModel.metadata.create_all(engine, tables=[
        TenantTaskifyConfig.__table__,
        TaskifyUserMapping.__table__,
    ])
    
    if table_exists('tenant_taskify_configs'):
        print("  ✓ tenant_taskify_configs table exists")
    else:
        print("  ✓ Created tenant_taskify_configs table")
    
    if table_exists('taskify_user_mappings'):
        print("  ✓ taskify_user_mappings table exists")
    else:
        print("  ✓ Created taskify_user_mappings table")

def main():
    """Run all migrations."""
    print("=" * 60)
    print("Running Database Migrations")
    print("=" * 60)
    print()
    
    try:
        migrate_subscriptions()
        print()
        migrate_taskify_tables()
        print()
        print("=" * 60)
        print("✓ All migrations completed successfully!")
        print("=" * 60)
    except Exception as e:
        print()
        print("=" * 60)
        print(f"✗ Migration failed: {e}")
        print("=" * 60)
        import traceback
        traceback.print_exc()
        sys.exit(1)

if __name__ == "__main__":
    main()





