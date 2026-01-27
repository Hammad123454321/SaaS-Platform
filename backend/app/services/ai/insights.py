"""AI Insights Service - 'What Needs Attention' Summary

Generates AI-powered insights for the dashboard based on tasks, deadlines, and compliance status.
"""
import json
from datetime import datetime, timedelta
from typing import List, Optional

from langchain_openai import ChatOpenAI

from app.config import settings
from app.models import User
from app.models.tasks import Task, TaskStatus, Project, TaskStatusCategory
from app.models.workflows import OnboardingTask


async def generate_insights(user: User) -> dict:
    """Generate AI-powered insights for the current user's tenant.
    
    Returns:
        Dictionary with summary, counts, and AI-generated suggestions.
    """
    tenant_id = str(user.tenant_id)
    today = datetime.utcnow().date()
    week_from_now = today + timedelta(days=7)
    
    overdue_tasks = await _get_overdue_tasks(tenant_id)
    overdue_count = len(overdue_tasks)
    
    upcoming_deadlines = await _get_upcoming_deadlines(tenant_id, week_from_now)
    deadline_count = len(upcoming_deadlines)
    
    pending_onboarding = await _get_pending_onboarding(tenant_id)
    pending_count = len(pending_onboarding)
    
    in_progress_tasks = await _get_in_progress_tasks(tenant_id)
    
    recent_completed = await _get_recently_completed(tenant_id)
    
    context = _build_context(
        overdue_tasks=overdue_tasks,
        upcoming_deadlines=upcoming_deadlines,
        pending_onboarding=pending_onboarding,
        in_progress_tasks=in_progress_tasks,
        recent_completed=recent_completed,
        user_name=user.email.split('@')[0] if user.email else "User"
    )
    
    summary, suggestions = await _generate_ai_summary(context)
    
    return {
        "summary": summary,
        "overdue_tasks": overdue_count,
        "upcoming_deadlines": deadline_count,
        "pending_items": pending_count,
        "suggestions": suggestions
    }


async def _get_overdue_tasks(tenant_id: str) -> List[Task]:
    """Get all overdue tasks for the tenant."""
    today = datetime.utcnow().date()
    
    done_statuses = await TaskStatus.find(
        TaskStatus.tenant_id == tenant_id,
        {"category": {"$in": [TaskStatusCategory.DONE, TaskStatusCategory.CANCELLED]}}
    ).to_list()
    done_status_ids = [str(s.id) for s in done_statuses]
    
    query_filter = {
        "tenant_id": tenant_id,
        "due_date": {"$lt": today},
    }
    if done_status_ids:
        query_filter["status_id"] = {"$nin": done_status_ids}
    
    return await Task.find(query_filter).limit(10).to_list()


async def _get_upcoming_deadlines(tenant_id: str, until_date) -> List[Task]:
    """Get tasks with upcoming deadlines."""
    today = datetime.utcnow().date()
    
    done_statuses = await TaskStatus.find(
        TaskStatus.tenant_id == tenant_id,
        {"category": {"$in": [TaskStatusCategory.DONE, TaskStatusCategory.CANCELLED]}}
    ).to_list()
    done_status_ids = [str(s.id) for s in done_statuses]
    
    query_filter = {
        "tenant_id": tenant_id,
        "due_date": {"$gte": today, "$lte": until_date},
    }
    if done_status_ids:
        query_filter["status_id"] = {"$nin": done_status_ids}
    
    return await Task.find(query_filter).sort(+Task.due_date).limit(10).to_list()


async def _get_pending_onboarding(tenant_id: str) -> List[OnboardingTask]:
    """Get pending onboarding tasks."""
    return await OnboardingTask.find(
        OnboardingTask.tenant_id == tenant_id,
        OnboardingTask.completed_at == None
    ).limit(10).to_list()


async def _get_in_progress_tasks(tenant_id: str) -> List[Task]:
    """Get tasks currently in progress."""
    in_progress_statuses = await TaskStatus.find(
        TaskStatus.tenant_id == tenant_id,
        TaskStatus.category == TaskStatusCategory.IN_PROGRESS
    ).to_list()
    
    if not in_progress_statuses:
        return []
    
    in_progress_ids = [str(s.id) for s in in_progress_statuses]
    
    return await Task.find(
        Task.tenant_id == tenant_id,
        {"status_id": {"$in": in_progress_ids}}
    ).limit(10).to_list()


async def _get_recently_completed(tenant_id: str) -> List[Task]:
    """Get recently completed tasks (last 7 days)."""
    week_ago = datetime.utcnow() - timedelta(days=7)
    
    done_statuses = await TaskStatus.find(
        TaskStatus.tenant_id == tenant_id,
        TaskStatus.category == TaskStatusCategory.DONE
    ).to_list()
    
    if not done_statuses:
        return []
    
    done_ids = [str(s.id) for s in done_statuses]
    
    return await Task.find(
        Task.tenant_id == tenant_id,
        {"status_id": {"$in": done_ids}},
        Task.updated_at >= week_ago
    ).sort(-Task.updated_at).limit(5).to_list()


def _build_context(
    overdue_tasks: List[Task],
    upcoming_deadlines: List[Task],
    pending_onboarding: List[OnboardingTask],
    in_progress_tasks: List[Task],
    recent_completed: List[Task],
    user_name: str
) -> str:
    """Build context string for AI summary generation."""
    context_parts = [f"User: {user_name}"]
    
    if overdue_tasks:
        overdue_items = [f"- {t.title} (due: {t.due_date})" for t in overdue_tasks[:5]]
        context_parts.append(f"OVERDUE TASKS ({len(overdue_tasks)} total):\n" + "\n".join(overdue_items))
    else:
        context_parts.append("OVERDUE TASKS: None - great job!")
    
    if upcoming_deadlines:
        deadline_items = [f"- {t.title} (due: {t.due_date})" for t in upcoming_deadlines[:5]]
        context_parts.append(f"UPCOMING DEADLINES (next 7 days, {len(upcoming_deadlines)} total):\n" + "\n".join(deadline_items))
    else:
        context_parts.append("UPCOMING DEADLINES: None in the next 7 days")
    
    if pending_onboarding:
        onboarding_items = [f"- {t.title}" for t in pending_onboarding[:5]]
        context_parts.append(f"PENDING SETUP ITEMS ({len(pending_onboarding)} total):\n" + "\n".join(onboarding_items))
    
    if in_progress_tasks:
        progress_items = [f"- {t.title}" for t in in_progress_tasks[:5]]
        context_parts.append(f"IN PROGRESS ({len(in_progress_tasks)} total):\n" + "\n".join(progress_items))
    
    if recent_completed:
        context_parts.append(f"RECENTLY COMPLETED: {len(recent_completed)} tasks this week")
    
    return "\n\n".join(context_parts)


async def _generate_ai_summary(context: str) -> tuple[str, List[str]]:
    """Generate AI summary and suggestions using GPT."""
    
    if not settings.openai_api_key:
        return _generate_fallback_summary(context)
    
    try:
        llm = ChatOpenAI(
            api_key=settings.openai_api_key,
            model=settings.openai_model or "gpt-3.5-turbo",
            temperature=0.7,
        )
        
        prompt = f"""You are a helpful business assistant. Based on the following business status, provide:
1. A brief, friendly summary (2-3 sentences) of what needs attention today
2. 3-4 specific, actionable suggestions

Keep the tone professional but encouraging. Focus on priorities and quick wins.

BUSINESS STATUS:
{context}

Respond in this exact JSON format:
{{"summary": "Your summary here", "suggestions": ["Suggestion 1", "Suggestion 2", "Suggestion 3"]}}
"""
        
        response = await llm.ainvoke(prompt)
        content = response.content
        
        try:
            if "```json" in content:
                content = content.split("```json")[1].split("```")[0]
            elif "```" in content:
                content = content.split("```")[1].split("```")[0]
            
            result = json.loads(content.strip())
            return result.get("summary", ""), result.get("suggestions", [])
        except json.JSONDecodeError:
            return content[:200], []
            
    except Exception as e:
        return _generate_fallback_summary(context)


def _generate_fallback_summary(context: str) -> tuple[str, List[str]]:
    """Generate a fallback summary without AI."""
    summary_parts = []
    suggestions = []
    
    if "OVERDUE TASKS: None" not in context:
        if "OVERDUE TASKS (" in context:
            count = context.split("OVERDUE TASKS (")[1].split(" ")[0]
            summary_parts.append(f"You have {count} overdue tasks that need attention")
            suggestions.append("Review and update overdue tasks - consider adjusting due dates or priorities")
    
    if "UPCOMING DEADLINES (next 7 days," in context:
        count = context.split("UPCOMING DEADLINES (next 7 days, ")[1].split(" ")[0]
        summary_parts.append(f"{count} deadlines coming up this week")
        suggestions.append("Plan your week around upcoming deadlines")
    
    if "PENDING SETUP ITEMS" in context:
        suggestions.append("Complete your business setup to unlock full platform capabilities")
    
    if "IN PROGRESS" in context:
        suggestions.append("Focus on completing in-progress tasks before starting new ones")
    
    if not summary_parts:
        summary = "Great job! You're on top of things. No urgent items need attention right now."
        suggestions = [
            "Review your goals for this week",
            "Consider planning ahead for upcoming projects",
            "Take time to organize and prioritize future work"
        ]
    else:
        summary = ". ".join(summary_parts) + "."
    
    return summary, suggestions[:4]
