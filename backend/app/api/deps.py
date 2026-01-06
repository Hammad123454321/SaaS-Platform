from fastapi import Depends, Header, HTTPException, status, Query
from sqlmodel import Session, select

from app.core.security import decode_token
from app.db import get_session
from app.models import User


def get_current_user(
    authorization: str | None = Header(default=None),
    token_param: str | None = Query(default=None, alias="token"),
    session: Session = Depends(get_session),
) -> User:
    token = None
    if authorization and authorization.startswith("Bearer "):
        token = authorization.split(" ")[1]
    elif token_param:
        token = token_param
    
    if not token:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Authentication required.",
            headers={"WWW-Authenticate": "Bearer"},
        )
    
    try:
        data = decode_token(token, refresh=False)
        if not data or "sub" not in data:
            raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid token.")
        
        user_id_str, tenant_id_str = data["sub"].split(":")
        user_id = int(user_id_str)
        tenant_id = int(tenant_id_str)
        
        user = session.get(User, user_id)
        if not user or not user.is_active or user.tenant_id != tenant_id:
            raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="User not found or inactive.")
        
        session.refresh(user, attribute_names=["roles"])
        return user
    except (ValueError, KeyError) as e:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid token format.") from e

