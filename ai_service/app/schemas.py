from typing import List, Optional

from pydantic import BaseModel


class Message(BaseModel):
    role: str
    content: str


class ChatRequest(BaseModel):
    messages: List[Message]
    trace_id: Optional[str] = None
    temperature: float = 0.3


class ChatResponse(BaseModel):
    reply: str
    trace_id: Optional[str] = None


class ActionProposalRequest(BaseModel):
    messages: List[Message]
    trace_id: Optional[str] = None


class ProposedAction(BaseModel):
    type: str
    payload: dict


class ActionProposalResponse(BaseModel):
    actions: List[ProposedAction]
    trace_id: Optional[str] = None


class EmbeddingRequest(BaseModel):
    texts: List[str]
    namespace: Optional[str] = None
    trace_id: Optional[str] = None


class EmbeddingResponse(BaseModel):
    embeddings: List[List[float]]
    trace_id: Optional[str] = None

