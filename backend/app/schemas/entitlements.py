from pydantic import BaseModel

from app.models.entitlement import ModuleCode


class EntitlementRead(BaseModel):
    module_code: ModuleCode
    enabled: bool
    seats: int
    ai_access: bool

    class Config:
        use_enum_values = True


class EntitlementToggleRequest(BaseModel):
    enabled: bool
    seats: int | None = None
    ai_access: bool | None = None

