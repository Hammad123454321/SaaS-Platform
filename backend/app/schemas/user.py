from pydantic import BaseModel, EmailStr


class UserRead(BaseModel):
    id: int
    tenant_id: int
    email: EmailStr
    is_active: bool
    is_super_admin: bool
    roles: list[str] = []

    class Config:
        from_attributes = True

