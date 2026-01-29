from pydantic import BaseModel, EmailStr
from typing import Optional


class UserRead(BaseModel):
    # Mongo-backed IDs are strings, not integers
    id: str
    tenant_id: str
    email: EmailStr
    is_active: bool
    is_super_admin: bool
    roles: list[str] = []

    class Config:
        from_attributes = True


class UserCreate(BaseModel):
    email: EmailStr
    password: str
    role_names: Optional[list[str]] = None


class UserUpdate(BaseModel):
    email: Optional[EmailStr] = None
    password: Optional[str] = None
    is_active: Optional[bool] = None
    role_names: Optional[list[str]] = None
    
    class Config:
        from_attributes = True

