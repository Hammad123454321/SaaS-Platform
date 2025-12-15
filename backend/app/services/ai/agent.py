"""LangChain agent using GPT-3.5-turbo and module tools."""
from typing import Any, Dict, List, Tuple

from langchain_openai import ChatOpenAI
from langchain.agents import AgentExecutor, create_openai_functions_agent
from langchain_core.prompts import ChatPromptTemplate, MessagesPlaceholder
from sqlmodel import Session

from app.config import settings
from app.models import User
from app.services.ai.tools import get_all_tools


SYSTEM_PROMPT = (
    "You are a helpful, generic business assistant for small and medium businesses. "
    "You understand CRM, HRM, POS, Tasks, Booking, and Landing Page concepts. "
    "You ALWAYS respect tenant boundaries and only act using the provided tools. "
    "You keep answers concise, practical, and business-focused."
)


def build_agent(user: User, session: Session) -> AgentExecutor:
    """Build a LangChain agent for a given user and tenant.

    The agent uses OpenAI GPT-3.5-turbo with function calling tools
    that wrap the tenant's module APIs.
    """
    tenant_id = int(user.tenant_id)  # type: ignore[arg-type]

    tools = get_all_tools(tenant_id=tenant_id, user=user, session=session)

    llm = ChatOpenAI(
        api_key=settings.openai_api_key,
        model=settings.openai_model or "gpt-3.5-turbo",
        temperature=settings.openai_temperature,
        streaming=False,  # FastAPI streaming can be added later
    )

    prompt = ChatPromptTemplate.from_messages(
        [
            ("system", SYSTEM_PROMPT),
            MessagesPlaceholder("chat_history"),
            ("user", "{input}"),
        ]
    )

    agent = create_openai_functions_agent(llm, tools, prompt)

    return AgentExecutor(agent=agent, tools=tools, verbose=False)


def _convert_history(
    messages: List[Dict[str, str]],
) -> Tuple[List[Tuple[str, str]], str]:
    """Convert simple role/content messages into LangChain chat_history + last user input."""
    chat_history: List[Tuple[str, str]] = []
    last_user_message: str | None = None

    for m in messages:
        role = m.get("role")
        content = m.get("content", "")
        if role == "user":
            if last_user_message is None:
                last_user_message = content
            else:
                chat_history.append(("human", content))
        elif role == "assistant":
            chat_history.append(("ai", content))
        elif role == "system":
            chat_history.append(("system", content))

    if last_user_message is None:
        last_user_message = "Hello"

    return chat_history, last_user_message


async def run_agent_chat(
    user: User, session: Session, messages: List[Dict[str, str]]
) -> str:
    """Run the agent for a chat-style request.

    Args:
        user: Current authenticated user
        session: Database session
        messages: List of {\"role\": \"user\"|\"assistant\"|\"system\", \"content\": \"...\"}

    Returns:
        Assistant reply as plain text.
    """
    chat_history, last_user_message = _convert_history(messages)

    agent = build_agent(user, session)

    result: Dict[str, Any] = await agent.ainvoke(
        {
            "input": last_user_message,
            "chat_history": chat_history,
        }
    )

    # LangChain agents typically return {\"output\": \"...\"}
    if isinstance(result, dict) and "output" in result:
        return str(result["output"])

    return str(result)

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