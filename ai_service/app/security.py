from __future__ import annotations

import time
from typing import Any, Dict, Optional

import httpx
import jwt
from fastapi import Depends, HTTPException, Header, status

from app.config import settings


async def fetch_jwks() -> Optional[Dict[str, Any]]:
    if not settings.jwks_url:
        return None
    async with httpx.AsyncClient(timeout=5) as client:
        resp = await client.get(settings.jwks_url)
        resp.raise_for_status()
        return resp.json()


async def verify_jwt(authorization: str = Header(default=None)) -> dict[str, Any]:
    if not authorization or not authorization.lower().startswith("bearer "):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Missing token.")
    token = authorization.split(" ", 1)[1]

    options = {"verify_aud": bool(settings.jwt_audience)}
    audience = settings.jwt_audience
    issuer = settings.jwt_issuer

    # Try JWKS first if available
    if settings.jwks_url:
        jwks = await fetch_jwks()
        if not jwks or "keys" not in jwks:
            raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="JWKS not available.")
        for key in jwks["keys"]:
            try:
                payload = jwt.decode(
                    token,
                    key=jwt.algorithms.RSAAlgorithm.from_jwk(key),
                    algorithms=["RS256"],
                    audience=audience,
                    issuer=issuer,
                    options=options,
                )
                return payload
            except jwt.PyJWTError:
                continue

    # Fallback HS256 for local/dev
    if settings.fallback_jwt_secret:
        try:
            return jwt.decode(
                token,
                settings.fallback_jwt_secret,
                algorithms=["HS256"],
                audience=audience,
                issuer=issuer,
                options=options,
            )
        except jwt.PyJWTError as exc:
            raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid token.") from exc

    raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid token.")


class RateLimiter:
    def __init__(self, per_minute: int):
        self.per_minute = per_minute
        self.bucket: dict[str, list[float]] = {}

    def check(self, tenant_id: str) -> None:
        now = time.time()
        window_start = now - 60
        entries = self.bucket.get(tenant_id, [])
        entries = [ts for ts in entries if ts >= window_start]
        if len(entries) >= self.per_minute:
            raise HTTPException(
                status_code=status.HTTP_429_TOO_MANY_REQUESTS,
                detail="Rate limit exceeded.",
            )
        entries.append(now)
        self.bucket[tenant_id] = entries


rate_limiter = RateLimiter(settings.rate_limit_per_minute)


def enforce_rate_limit(payload: dict[str, Any]) -> None:
    tenant_id = str(payload.get("tenant_id") or payload.get("tid") or payload.get("sub", ""))
    if not tenant_id:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Missing tenant_id.")
    rate_limiter.check(tenant_id)

