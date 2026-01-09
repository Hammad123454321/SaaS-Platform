"""
Migration script to add new columns to subscriptions table.

Run this once to update the existing subscriptions table with new fields.
"""
import sys
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from sqlalchemy import text
from app.db import engine
from app.config import settings

def migrate_subscription_table():
    """Add missing columns to subscriptions table."""
    with engine.connect() as conn:
        # Check if columns exist before adding
        result = conn.execute(text("""
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'subscriptions'
        """))
        existing_columns = {row[0] for row in result}
        
        print(f"Existing columns: {existing_columns}")
        
        # Add missing columns
        migrations = []
        
        if 'stripe_price_id' not in existing_columns:
            migrations.append("ALTER TABLE subscriptions ADD COLUMN stripe_price_id VARCHAR")
        
        if 'amount' not in existing_columns:
            migrations.append("ALTER TABLE subscriptions ADD COLUMN amount INTEGER DEFAULT 0")
        
        if 'currency' not in existing_columns:
            migrations.append("ALTER TABLE subscriptions ADD COLUMN currency VARCHAR DEFAULT 'usd'")
        
        if 'interval' not in existing_columns:
            migrations.append("ALTER TABLE subscriptions ADD COLUMN interval VARCHAR DEFAULT 'month'")
        
        if 'current_period_start' not in existing_columns:
            migrations.append("ALTER TABLE subscriptions ADD COLUMN current_period_start TIMESTAMP")
        
        if 'current_period_end' not in existing_columns:
            migrations.append("ALTER TABLE subscriptions ADD COLUMN current_period_end TIMESTAMP")
        
        if 'modules' not in existing_columns:
            migrations.append("ALTER TABLE subscriptions ADD COLUMN modules JSONB DEFAULT '{}'")
        
        if not migrations:
            print("✓ All columns already exist. No migration needed.")
            return
        
        print(f"\nExecuting {len(migrations)} migration(s)...")
        for migration in migrations:
            print(f"  → {migration}")
            conn.execute(text(migration))
        
        conn.commit()
        print("\n✓ Migration completed successfully!")

if __name__ == "__main__":
    try:
        migrate_subscription_table()
    except Exception as e:
        print(f"\n✗ Migration failed: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)





