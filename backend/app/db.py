from sqlmodel import SQLModel, create_engine, Session
from sqlalchemy import text, inspect
import logging

from app.config import settings
from app.seed import seed_permissions

engine = create_engine(str(settings.database_url), echo=settings.debug)
logger = logging.getLogger(__name__)


def _migrate_subscriptions_table() -> None:
    """Add missing columns to subscriptions table if they don't exist."""
    try:
        inspector = inspect(engine)
        
        if 'subscriptions' not in inspector.get_table_names():
            logger.info("subscriptions table doesn't exist yet, will be created by SQLModel")
            return
        
        columns = {col['name'] for col in inspector.get_columns('subscriptions')}
        migrations = []
        
        if 'stripe_price_id' not in columns:
            migrations.append("ALTER TABLE subscriptions ADD COLUMN stripe_price_id VARCHAR")
        
        if 'amount' not in columns:
            migrations.append("ALTER TABLE subscriptions ADD COLUMN amount INTEGER DEFAULT 0")
        
        if 'currency' not in columns:
            migrations.append("ALTER TABLE subscriptions ADD COLUMN currency VARCHAR DEFAULT 'usd'")
        
        if 'interval' not in columns:
            migrations.append("ALTER TABLE subscriptions ADD COLUMN interval VARCHAR DEFAULT 'month'")
        
        if 'current_period_start' not in columns:
            migrations.append("ALTER TABLE subscriptions ADD COLUMN current_period_start TIMESTAMP")
        
        if 'current_period_end' not in columns:
            migrations.append("ALTER TABLE subscriptions ADD COLUMN current_period_end TIMESTAMP")
        
        if 'modules' not in columns:
            migrations.append("ALTER TABLE subscriptions ADD COLUMN modules JSONB DEFAULT '{}'")
        
        if migrations:
            logger.info(f"Migrating subscriptions table: adding {len(migrations)} column(s)")
            with engine.connect() as conn:
                for migration in migrations:
                    conn.execute(text(migration))
                conn.commit()
            logger.info("✓ Subscriptions table migration completed")
    except Exception as e:
        logger.warning(f"Failed to migrate subscriptions table: {e}. This is OK if tables don't exist yet.")


def init_db() -> None:
    """Initialize database: create tables and run migrations."""
    # First, try to migrate existing tables
    _migrate_subscriptions_table()
    
    # Then create all tables (SQLModel will skip existing ones)
    SQLModel.metadata.create_all(engine)
    
    with Session(engine) as session:
        seed_permissions(session)


def get_session() -> Session:
    with Session(engine) as session:
        yield session

