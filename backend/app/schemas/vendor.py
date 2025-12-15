from pydantic import BaseModel


class VendorCredentialCreate(BaseModel):
    vendor: str
    credentials: dict


class VendorCredentialRead(BaseModel):
    id: int
    vendor: str
    credentials: dict

    class Config:
        from_attributes = True

