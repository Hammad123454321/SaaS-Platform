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
    
    logger.info(f"Creating token: iat={payload['iat']} ({now.isoformat()}), exp={payload['exp']} ({exp_time.isoformat()}), expires_delta={expires_delta}, expires_in_minutes={expires_delta.total_seconds() / 60}")
    encoded = jwt.encode(payload, secret, algorithm=settings.jwt_algorithm)
    logger.info(f"Token created successfully, length={len(encoded)}")
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
            logger.info(f"Token validation (unverified): iat={iat_time} ({datetime.fromtimestamp(iat_time).isoformat()}), exp={exp_time} ({datetime.fromtimestamp(exp_time).isoformat()}), current={current_time} ({datetime.fromtimestamp(current_time).isoformat()}), time_until_exp={time_until_exp} seconds")
            if exp_time < current_time:
                logger.error(f"Token expired: exp={exp_time} ({datetime.fromtimestamp(exp_time).isoformat()}), current={current_time} ({datetime.fromtimestamp(current_time).isoformat()}), expired_by={current_time - exp_time} seconds")
                return None
            else:
                logger.info(f"Token expiration check passed: valid for {time_until_exp} more seconds")
        
        # Now verify signature with the secret
        logger.info(f"Verifying token signature with {secret_type} secret (length={len(secret)})")
        logger.debug(f"Secret key (first 10 chars): {secret[:10]}...")
        
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
                    logger.error(f"Token expired beyond leeway: exp={token_exp}, current={current_time}, expired_by={current_time - token_exp} seconds")
                    raise jwt.ExpiredSignatureError("Token has expired")
                elif time_until_exp < 0:
                    logger.warning(f"Token expired but within leeway: exp={token_exp}, current={current_time}, expired_by={current_time - token_exp} seconds (allowing with leeway)")
                else:
                    logger.info(f"Token is valid: expires in {time_until_exp} seconds")
            
            logger.info(f"Token decoded and verified successfully. Using {secret_type} secret")
            return decoded
        except jwt.InvalidSignatureError:
            raise  # Re-raise signature errors
        except jwt.ExpiredSignatureError:
            raise  # Re-raise expiration errors
        
    except jwt.ExpiredSignatureError as e:
        logger.error(f"Token has expired: {e}")
        # Log detailed expiration info
        try:
            unverified = jwt.decode(token, options={"verify_signature": False, "verify_exp": False})
            exp_time = unverified.get("exp")
            current_time = int(datetime.utcnow().timestamp())
            if exp_time:
                expired_by = current_time - exp_time
                logger.error(f"Token exp={exp_time} ({datetime.fromtimestamp(exp_time).isoformat()}), current={current_time} ({datetime.fromtimestamp(current_time).isoformat()}), expired_by={expired_by} seconds")
        except:
            pass
        return None
    except jwt.InvalidSignatureError as e:
        logger.error(f"Token signature is invalid - secret key mismatch! Error: {e}")
        logger.error(f"Using {secret_type} secret key (length={len(secret)}, first 10 chars: {secret[:10]}...)")
        logger.error("This usually means the token was signed with a different secret key than the one being used to verify it.")
        return None
    except jwt.InvalidTokenError as e:
        logger.warning(f"Invalid token: {e}")
        return None
    except jwt.PyJWTError as e:
        logger.warning(f"JWT error: {e}")
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

