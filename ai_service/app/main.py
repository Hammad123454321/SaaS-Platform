import logging
import os
from typing import Any, Dict

import structlog
from fastapi import FastAPI, Depends

from app.config import settings
from app.security import enforce_rate_limit, verify_jwt
from app.schemas import (
    ChatRequest,
    ChatResponse,
    ActionProposalRequest,
    ActionProposalResponse,
    EmbeddingRequest,
    EmbeddingResponse,
)
from app.providers.base import LLMProvider, StubProvider
from app.providers.mistral_stub import MistralStubProvider
from app.providers.hf_local import HFLocalProvider


def configure_logging() -> None:
    structlog.configure(
        processors=[
            structlog.processors.add_log_level,
            structlog.processors.TimeStamper(fmt="iso"),
            structlog.processors.JSONRenderer(),
        ]
    )
    logging.basicConfig(level=settings.log_level)


def provider_factory() -> LLMProvider:
    if settings.hf_model_id:
        return HFLocalProvider(settings.hf_model_id, auth_token=settings.hf_token)
    return MistralStubProvider()


def create_app() -> FastAPI:
    # Ensure HF cache path is set to reuse downloads
    os.environ.setdefault("HF_HOME", r"C:\Users\MINDRIND\.cache\huggingface")
    configure_logging()
    app = FastAPI(title=settings.app_name, debug=settings.debug)

    @app.get("/health", tags=["health"])
    async def health() -> dict[str, str]:
        return {"status": "ok"}

    SYSTEM_PROMPT = ("""
    You are a agent, that answer to the greetings precisely and sticks to the topic only,
    and you dont answer to the questions that are related to maths. 
    """
    )

    def _tenant_from_payload(payload: dict[str, Any]) -> str:
        if payload.get("tenant_id"):
            return str(payload["tenant_id"])
        if payload.get("sub") and ":" in str(payload["sub"]):
            try:
                _, tenant_part = str(payload["sub"]).split(":", 1)
                return tenant_part
            except ValueError:
                return "unknown"
        return "unknown"

    @app.post("/chat", response_model=ChatResponse, tags=["chat"])
    async def chat(payload: ChatRequest) -> ChatResponse:
        tenant_id = "anonymous"
        provider = provider_factory()
        messages = [{"role": "system", "content": SYSTEM_PROMPT}] + [
            m.model_dump() for m in payload.messages
        ]
        reply = await provider.chat(messages, tenant_id, temperature=payload.temperature)
        return ChatResponse(reply=reply, trace_id=payload.trace_id)

    @app.post("/actions/call", response_model=ActionProposalResponse, tags=["actions"])
    async def actions_call(payload: ActionProposalRequest) -> ActionProposalResponse:
        tenant_id = "anonymous"
        provider = provider_factory()
        actions = await provider.propose_actions(
            [m.model_dump() for m in payload.messages], tenant_id
        )
        # Allowlist enforcement
        allowed = settings.allowlisted_actions_list()
        filtered = [a for a in actions if a.get("type") in allowed]
        return ActionProposalResponse(
            actions=filtered,
            trace_id=payload.trace_id,
        )

    @app.post("/embeddings/index", response_model=EmbeddingResponse, tags=["embeddings"])
    async def embeddings_index(payload: EmbeddingRequest) -> EmbeddingResponse:
        tenant_id = "anonymous"
        provider = provider_factory()
        embeddings = await provider.embed(payload.texts, tenant_id)
        return EmbeddingResponse(embeddings=embeddings, trace_id=payload.trace_id)

    return app


app = create_app()

