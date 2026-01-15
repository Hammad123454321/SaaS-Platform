"""Script to add password_change_required column to users table."""
import sys
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from sqlmodel import Session, text
from app.db import engine


def add_password_change_required_column():
    """Add password_change_required column to users table if it doesn't exist."""
    with Session(engine) as session:
        try:
            # Check if column exists
            result = session.exec(
                text("""
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_name = 'users' 
                    AND column_name = 'password_change_required'
                """)
            ).first()
            
            if result:
                print("Column 'password_change_required' already exists in users table.")
                return
            
            # Add column
            session.exec(
                text("""
                    ALTER TABLE users 
                    ADD COLUMN password_change_required BOOLEAN NOT NULL DEFAULT FALSE
                """)
            )
            session.commit()
            print("Successfully added 'password_change_required' column to users table.")
            
        except Exception as e:
            session.rollback()
            print(f"Error adding column: {e}")
            raise


if __name__ == "__main__":
    add_password_change_required_column()

