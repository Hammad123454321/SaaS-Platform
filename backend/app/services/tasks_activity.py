"""
Activity Log Service

Tracks all changes and activities in the task management system.
"""
import logging
from datetime import datetime
from typing import Optional, List, Dict, Any

from sqlmodel import Session, select, and_, or_
from fastapi import HTTPException, status

from app.models import ActivityLog, User

logger = logging.getLogger(__name__)


def log_activity(
    session: Session,
    tenant_id: int,
    entity_type: str,
    entity_id: int,
    action: str,
    description: str,
    user_id: Optional[int] = None,
    changes: Optional[Dict[str, Any]] = None
) -> ActivityLog:
    """Log an activity."""
    import json
    
    activity = ActivityLog(
        tenant_id=tenant_id,
        user_id=user_id,
        entity_type=entity_type,
        entity_id=entity_id,
        action=action,
        description=description,
        changes=json.dumps(changes) if changes else None
    )
    session.add(activity)
    session.commit()
    session.refresh(activity)
    return activity


def list_activities(
    session: Session,
    tenant_id: int,
    entity_type: Optional[str] = None,
    entity_id: Optional[int] = None,
    user_id: Optional[int] = None,
    action: Optional[str] = None,
    start_date: Optional[datetime] = None,
    end_date: Optional[datetime] = None,
    limit: int = 100,
    offset: int = 0
) -> List[ActivityLog]:
    """List activities with filters."""
    query = select(ActivityLog).where(ActivityLog.tenant_id == tenant_id)
    
    if entity_type:
        query = query.where(ActivityLog.entity_type == entity_type)
    
    if entity_id:
        query = query.where(ActivityLog.entity_id == entity_id)
    
    if user_id:
        query = query.where(ActivityLog.user_id == user_id)
    
    if action:
        query = query.where(ActivityLog.action == action)
    
    if start_date:
        query = query.where(ActivityLog.created_at >= start_date)
    
    if end_date:
        query = query.where(ActivityLog.created_at <= end_date)
    
    return list(
        session.exec(
            query.order_by(ActivityLog.created_at.desc())
            .limit(limit)
            .offset(offset)
        ).all()
    )


def get_entity_activity(
    session: Session,
    tenant_id: int,
    entity_type: str,
    entity_id: int
) -> List[ActivityLog]:
    """Get all activity for a specific entity."""
    return list_activities(
        session,
        tenant_id,
        entity_type=entity_type,
        entity_id=entity_id
    )















