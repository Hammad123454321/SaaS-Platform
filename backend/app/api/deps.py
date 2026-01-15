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
    import logging
    logger = logging.getLogger(__name__)
    
    token = None
    if authorization and authorization.startswith("Bearer "):
        token = authorization.split(" ")[1]
    elif token_param:
        token = token_param
    
    if not token:
        logger.warning("No token provided in request")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Authentication required.",
            headers={"WWW-Authenticate": "Bearer"},
        )
    
    try:
        logger.debug(f"Attempting to decode token: {token[:20]}...")
        data = decode_token(token, refresh=False)
        if not data:
            logger.warning("Token decode returned None - invalid token or wrong secret")
            raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid token.")
        
        if "sub" not in data:
            logger.warning(f"Token missing 'sub' field. Token data: {data}")
            raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid token.")
        
        logger.debug(f"Token decoded successfully. Subject: {data['sub']}")
        user_id_str, tenant_id_str = data["sub"].split(":")
        user_id = int(user_id_str)
        tenant_id = int(tenant_id_str)
        
        logger.debug(f"Looking up user {user_id} in tenant {tenant_id}")
        user = session.get(User, user_id)
        if not user:
            logger.warning(f"User {user_id} not found")
            raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="User not found or inactive.")
        
        if not user.is_active:
            logger.warning(f"User {user_id} is inactive")
            raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="User not found or inactive.")
        
        if user.tenant_id != tenant_id:
            logger.warning(f"User {user_id} tenant mismatch: expected {tenant_id}, got {user.tenant_id}")
            raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="User not found or inactive.")
        
        session.refresh(user, attribute_names=["roles"])
        logger.debug(f"User {user_id} authenticated successfully")
        return user
    except (ValueError, KeyError) as e:
        logger.error(f"Token format error: {e}", exc_info=True)
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid token format.") from e
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Unexpected error in get_current_user: {e}", exc_info=True)
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Authentication failed.") from e

