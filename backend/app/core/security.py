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
    import logging
    logger = logging.getLogger(__name__)
    
    now = datetime.utcnow()
    exp_time = now + expires_delta
    payload: dict[str, Any] = {
        "sub": subject,
        "iat": int(now.timestamp()),
        "exp": int(exp_time.timestamp()),
    }
    if roles:
        payload["roles"] = list(roles)
    if impersonated_by is not None:
        payload["impersonated_by"] = impersonated_by
    
    logger.debug(f"Creating token: expires_in_minutes={expires_delta.total_seconds() / 60}")
    encoded = jwt.encode(payload, secret, algorithm=settings.jwt_algorithm)
    return encoded


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
    import logging
    from datetime import datetime
    logger = logging.getLogger(__name__)
    
    secret = settings.jwt_refresh_secret_key if refresh else settings.jwt_secret_key
    secret_type = 'refresh' if refresh else 'access'
    
    try:
        # Decode without verification first to check expiration and get token info
        unverified = jwt.decode(token, options={"verify_signature": False})
        current_time = int(datetime.utcnow().timestamp())
        exp_time = unverified.get("exp")
        iat_time = unverified.get("iat")
        
        if exp_time and iat_time:
            time_until_exp = exp_time - current_time
            if exp_time < current_time:
                logger.debug(f"Token expired by {current_time - exp_time} seconds")
                return None
        
        # Verify signature with the secret
        
        # First verify signature without checking expiration (we already checked it)
        # Then manually verify expiration with leeway
        try:
            # Decode with signature verification but skip expiration check
            decoded = jwt.decode(
                token, 
                secret, 
                algorithms=[settings.jwt_algorithm],
                options={"verify_exp": False}  # Skip expiration check, we'll do it manually
            )
            
            # Manually check expiration with leeway
            current_time = int(datetime.utcnow().timestamp())
            token_exp = decoded.get("exp")
            if token_exp:
                time_until_exp = token_exp - current_time
                leeway_seconds = 60
                if time_until_exp < -leeway_seconds:  # Expired beyond leeway
                    raise jwt.ExpiredSignatureError("Token has expired")
            
            return decoded
        except jwt.InvalidSignatureError:
            raise  # Re-raise signature errors
        except jwt.ExpiredSignatureError:
            raise  # Re-raise expiration errors
        
    except jwt.ExpiredSignatureError:
        logger.debug("Token has expired")
        return None
    except jwt.InvalidSignatureError:
        logger.warning("Token signature verification failed")
        return None
    except jwt.InvalidTokenError:
        logger.warning("Invalid token")
        return None
    except jwt.PyJWTError:
        logger.warning("JWT processing error")
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

