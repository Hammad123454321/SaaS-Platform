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


class InsightsResponse(BaseModel):
    summary: str
    overdue_tasks: int
    upcoming_deadlines: int
    pending_items: int
    suggestions: List[str]


class TaskAIRequest(BaseModel):
    title: str
    context: str = ""  # Optional context like project name, industry, etc.


class TaskAIResponse(BaseModel):
    description: str
    suggested_priority: str
    suggested_due_days: int


@router.get("/insights", response_model=InsightsResponse)
async def get_ai_insights(
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> InsightsResponse:
    """Get AI-powered insights and 'What Needs Attention' summary for the dashboard."""
    from app.services.ai.insights import generate_insights
    
    result = await generate_insights(
        user=current_user,
        session=session,
    )
    return InsightsResponse(**result)


@router.post("/generate-task", response_model=TaskAIResponse)
async def generate_task_description(
    payload: TaskAIRequest,
    current_user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
) -> TaskAIResponse:
    """Generate AI-powered task description from a title."""
    from app.services.ai.task_generator import generate_task_details
    
    result = await generate_task_details(
        title=payload.title,
        context=payload.context,
        user=current_user,
        session=session,
    )
    return TaskAIResponse(**result)