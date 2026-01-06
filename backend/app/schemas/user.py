from pydantic import BaseModel, EmailStr
from typing import Optional


class UserRead(BaseModel):
    id: int
    tenant_id: int
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

