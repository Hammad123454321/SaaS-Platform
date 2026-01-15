from datetime import datetime
from typing import Optional

from sqlmodel import SQLModel, Field, Relationship


class Tenant(SQLModel, table=True):
    __tablename__ = "tenants"

    id: Optional[int] = Field(default=None, primary_key=True)
    name: str = Field(index=True, unique=True)
    is_draft: bool = Field(default=True)  # Stage 0: Draft until owner verifies email
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    users: list["User"] = Relationship(back_populates="tenant")  # noqa: F821

