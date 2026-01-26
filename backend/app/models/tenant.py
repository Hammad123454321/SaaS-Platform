from datetime import datetime
from typing import Optional

from beanie import Document
from pydantic import Field


class Tenant(Document):
    """Tenant model for MongoDB."""
    
    name: str = Field(..., index=True, unique=True)
    is_draft: bool = Field(default=True)  # Stage 0: Draft until owner verifies email
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "tenants"
        indexes = [
            "name",
        ]
