"""AI Task Generator Service

Generates task descriptions, priorities, and due date suggestions using AI.
"""
import json
from typing import Optional

from langchain_openai import ChatOpenAI
from sqlmodel import Session, select

from app.config import settings
from app.models import User
from app.models.onboarding import BusinessProfile


async def generate_task_details(
    title: str,
    context: str,
    user: User,
    session: Session
) -> dict:
    """Generate AI-powered task details from a title.
    
    Args:
        title: The task title
        context: Optional context (project name, industry, etc.)
        user: Current user
        session: Database session
        
    Returns:
        Dictionary with description, suggested_priority, suggested_due_days
    """
    tenant_id = int(user.tenant_id)
    
    # Get business context
    business_context = _get_business_context(session, tenant_id)
    
    # Combine contexts
    full_context = f"{business_context}\n{context}" if context else business_context
    
    # Generate with AI
    result = await _generate_with_ai(title, full_context)
    
    return result


def _get_business_context(session: Session, tenant_id: int) -> str:
    """Get business profile context for better AI suggestions."""
    profile = session.exec(
        select(BusinessProfile).where(BusinessProfile.tenant_id == tenant_id)
    ).first()
    
    if not profile:
        return "Business context: General business"
    
    context_parts = []
    
    if profile.legal_name:
        context_parts.append(f"Business: {profile.legal_name}")
    
    if profile.province:
        context_parts.append(f"Location: {profile.province.value}")
    
    return " | ".join(context_parts) if context_parts else "Business context: General business"


async def _generate_with_ai(title: str, context: str) -> dict:
    """Generate task details using GPT."""
    
    # Check if OpenAI is configured
    if not settings.openai_api_key:
        return _generate_fallback(title)
    
    try:
        llm = ChatOpenAI(
            api_key=settings.openai_api_key,
            model=settings.openai_model or "gpt-3.5-turbo",
            temperature=0.7,
        )
        
        prompt = f"""You are a helpful business assistant. Generate a detailed task description based on the title provided.

TASK TITLE: {title}

BUSINESS CONTEXT: {context}

Provide:
1. A clear, detailed description (2-4 sentences) explaining what this task involves
2. A suggested priority level (low, medium, high, urgent)
3. Suggested number of days until due date (1-30)

Consider the business context when making suggestions. Be specific and actionable.

Respond in this exact JSON format:
{{"description": "Your detailed description here", "suggested_priority": "medium", "suggested_due_days": 7}}
"""
        
        response = await llm.ainvoke(prompt)
        content = response.content
        
        # Parse JSON response
        try:
            # Handle potential markdown code blocks
            if "```json" in content:
                content = content.split("```json")[1].split("```")[0]
            elif "```" in content:
                content = content.split("```")[1].split("```")[0]
            
            result = json.loads(content.strip())
            
            # Validate and normalize
            return {
                "description": result.get("description", _generate_basic_description(title)),
                "suggested_priority": _normalize_priority(result.get("suggested_priority", "medium")),
                "suggested_due_days": _normalize_due_days(result.get("suggested_due_days", 7))
            }
        except json.JSONDecodeError:
            return _generate_fallback(title)
            
    except Exception as e:
        return _generate_fallback(title)


def _generate_fallback(title: str) -> dict:
    """Generate fallback task details without AI."""
    return {
        "description": _generate_basic_description(title),
        "suggested_priority": _infer_priority(title),
        "suggested_due_days": _infer_due_days(title)
    }


def _generate_basic_description(title: str) -> str:
    """Generate a basic description from the title."""
    title_lower = title.lower()
    
    # Common task patterns
    if any(word in title_lower for word in ["review", "check", "verify"]):
        return f"Review and verify the following: {title}. Document any findings and take necessary actions."
    
    if any(word in title_lower for word in ["create", "set up", "setup", "build"]):
        return f"Set up and configure: {title}. Ensure all requirements are met and document the process."
    
    if any(word in title_lower for word in ["meeting", "call", "discuss"]):
        return f"Schedule and prepare for: {title}. Create an agenda and send invitations to relevant participants."
    
    if any(word in title_lower for word in ["update", "modify", "change"]):
        return f"Update the following: {title}. Review current state, make necessary changes, and verify results."
    
    if any(word in title_lower for word in ["report", "document", "write"]):
        return f"Create documentation for: {title}. Include all relevant details and share with stakeholders."
    
    if any(word in title_lower for word in ["fix", "resolve", "bug"]):
        return f"Investigate and resolve: {title}. Document the root cause and solution implemented."
    
    if any(word in title_lower for word in ["train", "onboard", "teach"]):
        return f"Complete training for: {title}. Prepare materials and ensure understanding of key concepts."
    
    # Default
    return f"Complete the following task: {title}. Review requirements, execute the work, and document completion."


def _infer_priority(title: str) -> str:
    """Infer priority from title keywords."""
    title_lower = title.lower()
    
    # Urgent indicators
    if any(word in title_lower for word in ["urgent", "asap", "critical", "emergency", "immediately"]):
        return "urgent"
    
    # High priority indicators
    if any(word in title_lower for word in ["important", "deadline", "client", "customer", "compliance", "legal"]):
        return "high"
    
    # Low priority indicators
    if any(word in title_lower for word in ["optional", "nice to have", "when possible", "backlog"]):
        return "low"
    
    # Default to medium
    return "medium"


def _infer_due_days(title: str) -> int:
    """Infer due days from title keywords."""
    title_lower = title.lower()
    
    # Immediate
    if any(word in title_lower for word in ["urgent", "asap", "today", "immediately"]):
        return 1
    
    # Short term
    if any(word in title_lower for word in ["this week", "soon", "quick"]):
        return 3
    
    # Meeting/call typically soon
    if any(word in title_lower for word in ["meeting", "call"]):
        return 2
    
    # Setup/training typically longer
    if any(word in title_lower for word in ["setup", "training", "onboard", "configure"]):
        return 14
    
    # Report/document medium term
    if any(word in title_lower for word in ["report", "document", "review"]):
        return 7
    
    # Default to 7 days
    return 7


def _normalize_priority(priority: str) -> str:
    """Normalize priority value."""
    priority = priority.lower().strip()
    
    if priority in ["urgent", "critical"]:
        return "urgent"
    if priority in ["high", "important"]:
        return "high"
    if priority in ["low", "minor"]:
        return "low"
    
    return "medium"


def _normalize_due_days(days) -> int:
    """Normalize due days value."""
    try:
        days = int(days)
        return max(1, min(30, days))  # Clamp between 1 and 30
    except (ValueError, TypeError):
        return 7

