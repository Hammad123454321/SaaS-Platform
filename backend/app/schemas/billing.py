from datetime import datetime
from typing import Optional

from pydantic import BaseModel


class BillingHistoryRead(BaseModel):
    event_type: str
    amount: Optional[int] = None
    currency: Optional[str] = None
    created_at: datetime

    class Config:
        from_attributes = True

