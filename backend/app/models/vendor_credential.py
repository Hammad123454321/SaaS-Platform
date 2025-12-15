from datetime import datetime
from typing import Optional

from sqlmodel import SQLModel, Field, Column, JSON


class VendorCredential(SQLModel, table=True):
    __tablename__ = "vendor_credentials"

    id: Optional[int] = Field(default=None, primary_key=True)
    tenant_id: int = Field(foreign_key="tenants.id", index=True)
    vendor: str = Field(index=True)
    credentials: dict = Field(default_factory=dict, sa_column=Column(JSON))
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

