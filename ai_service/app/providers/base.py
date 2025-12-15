from __future__ import annotations

from abc import ABC, abstractmethod
from typing import Any, List


class LLMProvider(ABC):
    @abstractmethod
    async def chat(self, messages: list[dict[str, str]], tenant_id: str, temperature: float = 0.7) -> str:
        ...

    @abstractmethod
    async def propose_actions(self, messages: list[dict[str, str]], tenant_id: str) -> list[dict[str, Any]]:
        ...

    @abstractmethod
    async def embed(self, texts: list[str], tenant_id: str) -> list[list[float]]:
        ...


class StubProvider(LLMProvider):
    async def chat(self, messages: list[dict[str, str]], tenant_id: str) -> str:
        return "Stub response based on your messages."

    async def propose_actions(self, messages: list[dict[str, str]], tenant_id: str) -> list[dict[str, Any]]:
        return [{"type": "create_task", "payload": {"title": "Follow up", "tenant_id": tenant_id}}]

    async def embed(self, texts: list[str], tenant_id: str) -> list[list[float]]:
        return [[0.0 for _ in range(8)] for _ in texts]

