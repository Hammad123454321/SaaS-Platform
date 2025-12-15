from __future__ import annotations

from typing import Any

from app.providers.base import LLMProvider


class MistralStubProvider(LLMProvider):
    """Placeholder provider standing in for Mistral/hosted instruct API."""

    async def chat(self, messages: list[dict[str, str]], tenant_id: str, temperature: float = 0.7) -> str:
        user_msg = messages[-1]["content"] if messages else ""
        return f"[stub-mistral] Tenant {tenant_id}: {user_msg}"

    async def propose_actions(self, messages: list[dict[str, str]], tenant_id: str) -> list[dict[str, Any]]:
        return [
            {
                "type": "draft_email",
                "payload": {"to": "customer@example.com", "subject": "Hello", "body": "Hi there"},
            }
        ]

    async def embed(self, texts: list[str], tenant_id: str) -> list[list[float]]:
        # Deterministic toy embedding: length and hash-derived floats
        return [[float(len(t)), float(hash(t) % 1000) / 1000.0] for t in texts]

