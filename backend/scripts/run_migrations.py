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
from sqlmodel import SQLModel, Session, select
from app.db import engine
from app.models.taskify_config import TenantTaskifyConfig, TaskifyUserMapping
from app.models import Tenant
from app.services.tasks import ensure_default_statuses

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
            print("  [OK] All columns already exist.")
            return
        
        print(f"  -> Adding {len(migrations)} column(s)...")
        for migration in migrations:
            print(f"    {migration}")
            conn.execute(text(migration))
        
        conn.commit()
        print("  [OK] Subscriptions table migrated!")

def migrate_taskify_tables():
    """Create Taskify configuration tables."""
    print("Checking Taskify tables...")
    
    # Create tables using SQLModel
    SQLModel.metadata.create_all(engine, tables=[
        TenantTaskifyConfig.__table__,
        TaskifyUserMapping.__table__,
    ])
    
    if table_exists('tenant_taskify_configs'):
        print("  [OK] tenant_taskify_configs table exists")
    else:
        print("  [OK] Created tenant_taskify_configs table")
    
    if table_exists('taskify_user_mappings'):
        print("  [OK] taskify_user_mappings table exists")
    else:
        print("  [OK] Created taskify_user_mappings table")

def migrate_users_password_change_required():
    """Add password_change_required column to users table."""
    print("Checking users table for password_change_required column...")
    
    if not table_exists('users'):
        print("  [WARNING] users table doesn't exist. It will be created by SQLModel.")
        return
    
    with engine.connect() as conn:
        if not column_exists('users', 'password_change_required'):
            print("  -> Adding password_change_required column...")
            conn.execute(text("ALTER TABLE users ADD COLUMN password_change_required BOOLEAN NOT NULL DEFAULT FALSE"))
            conn.commit()
            print("  [OK] password_change_required column added!")
        else:
            print("  [OK] password_change_required column already exists.")

def migrate_tasks_is_required():
    """Add is_required column to tasks table."""
    print("Checking tasks table for is_required column...")
    
    if not table_exists('tasks'):
        print("  [WARNING] tasks table doesn't exist. It will be created by SQLModel.")
        return
    
    with engine.connect() as conn:
        if not column_exists('tasks', 'is_required'):
            print("  -> Adding is_required column...")
            conn.execute(text("ALTER TABLE tasks ADD COLUMN is_required BOOLEAN NOT NULL DEFAULT FALSE"))
            conn.commit()
            print("  [OK] is_required column added!")
        else:
            print("  [OK] is_required column already exists.")

def migrate_default_task_statuses():
    """Create default task statuses for all existing tenants."""
    print("Checking default task statuses...")
    
    if not table_exists('tenants'):
        print("  [WARNING] tenants table doesn't exist.")
        return
    
    if not table_exists('task_statuses'):
        print("  [WARNING] task_statuses table doesn't exist. It will be created by SQLModel.")
        return
    
    with Session(engine) as session:
        tenants = session.exec(select(Tenant)).all()
        if not tenants:
            print("  [OK] No tenants found. Default statuses will be created when tenants are created.")
            return
        
        print(f"  -> Creating default statuses for {len(tenants)} tenant(s)...")
        for tenant in tenants:
            try:
                ensure_default_statuses(session, tenant.id)
            except Exception as e:
                print(f"  ⚠ Failed to create default statuses for tenant {tenant.id}: {e}")
        
        print("  [OK] Default statuses created for all tenants!")

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
        migrate_users_password_change_required()
        print()
        migrate_tasks_is_required()
        print()
        migrate_default_task_statuses()
        print()
        print("=" * 60)
        print("[SUCCESS] All migrations completed successfully!")
        print("=" * 60)
    except Exception as e:
        print()
        print("=" * 60)
        print(f"[ERROR] Migration failed: {e}")
        print("=" * 60)
        import traceback
        traceback.print_exc()
        sys.exit(1)

if __name__ == "__main__":
    main()















