from datetime import datetime, timedelta
from typing import Any, Optional, Iterable

import jwt
from passlib.context import CryptContext

from app.config import settings

pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")


def hash_password(password: str) -> str:
    return pwd_context.hash(password)


def verify_password(password: str, hashed: str) -> bool:
    return pwd_context.verify(password, hashed)


def _create_token(
    subject: str,
    secret: str,
    expires_delta: timedelta,
    roles: Optional[Iterable[str]] = None,
    impersonated_by: int | None = None,
) -> str:
    now = datetime.utcnow()
    payload: dict[str, Any] = {
        "sub": subject,
        "iat": int(now.timestamp()),
        "exp": int((now + expires_delta).timestamp()),
    }
    if roles:
        payload["roles"] = list(roles)
    if impersonated_by is not None:
        payload["impersonated_by"] = impersonated_by
    return jwt.encode(payload, secret, algorithm=settings.jwt_algorithm)


def create_access_token(
    subject: str, roles: Optional[Iterable[str]] = None, impersonated_by: int | None = None
) -> str:
    return _create_token(
        subject,
        settings.jwt_secret_key,
        timedelta(minutes=settings.access_token_exp_minutes),
        roles=roles,
        impersonated_by=impersonated_by,
    )


def create_refresh_token(
    subject: str, roles: Optional[Iterable[str]] = None, impersonated_by: int | None = None
) -> str:
    return _create_token(
        subject,
        settings.jwt_refresh_secret_key,
        timedelta(minutes=settings.refresh_token_exp_minutes),
        roles=roles,
        impersonated_by=impersonated_by,
    )


def decode_token(token: str, refresh: bool = False) -> Optional[dict[str, Any]]:
    secret = settings.jwt_refresh_secret_key if refresh else settings.jwt_secret_key
    try:
        return jwt.decode(token, secret, algorithms=[settings.jwt_algorithm])
    except jwt.PyJWTError:
        return None


def cookie_params() -> dict[str, Any]:
    """Centralized cookie flags for auth tokens."""
    secure_flag = settings.cookie_secure
    if secure_flag is None:
        secure_flag = False  # default to False for local http; set true via env in prod
    return {
        "httponly": True,
        "secure": secure_flag,
        "samesite": settings.cookie_samesite,
        "path": "/",
        "max_age": 60 * 60 * 24 * 7,
    }

