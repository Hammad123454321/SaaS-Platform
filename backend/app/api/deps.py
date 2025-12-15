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
    # Temporarily disabled auth check; returns first active user for simplicity (dev only).
    user = session.exec(select(User).where(User.is_active == True)).first()  # noqa: E712
    if not user:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="User not found.")
    session.refresh(user, attribute_names=["roles"])
    return user

