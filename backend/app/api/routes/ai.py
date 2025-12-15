from typing import List, Literal

from fastapi import APIRouter, Depends
from pydantic import BaseModel
from sqlmodel import Session

from app.api.deps import get_current_user
from app.db import get_session
from app.models import User
from app.services.ai.agent import run_agent_chat


router = APIRouter(prefix="/ai", tags=["ai"])


class ChatMessage(BaseModel):
    role: Literal["user", "assistant", "system"]
    content: str


class AgentChatRequest(BaseModel):
    messages: List[ChatMessage]


class AgentChatResponse(BaseModel):
    reply: str


@router.post("/chat", response_model=AgentChatResponse)
async def ai_chat(
    payload: AgentChatRequest,
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> AgentChatResponse:
    """Chat with the LangChain + GPT-3.5-turbo business agent.

    The agent uses function calling tools to read data from CRM/HRM/POS/Tasks/Booking
    and perform simple actions like creating tasks, drafting emails, and logging notes.
    """
    reply = await run_agent_chat(
        user=current_user,
        session=session,
        messages=[m.model_dump() for m in payload.messages],
    )
    return AgentChatResponse(reply=reply)

{
  "cells": [],
  "metadata": {
    "language_info": {
      "name": "python"
    }
  },
  "nbformat": 4,
  "nbformat_minor": 2
}