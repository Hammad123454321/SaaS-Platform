from datetime import datetime
from typing import Optional

from beanie import Document
from pydantic import Field


class VendorCredential(Document):
    tenant_id: str = Field(..., index=True)
    vendor: str = Field(..., index=True)
    credentials: dict = Field(default_factory=dict)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)

    class Settings:
        name = "vendor_credentials"
        indexes = [
            "tenant_id",
            "vendor",
        ]
