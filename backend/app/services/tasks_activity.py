"""
Activity Log Service

Tracks all changes and activities in the task management system.
"""
import logging
import json
from datetime import datetime
from typing import Optional, List, Dict, Any

from app.models import ActivityLog, User

logger = logging.getLogger(__name__)


async def log_activity(
    tenant_id: str,
    entity_type: str,
    entity_id: str,
    action: str,
    description: str,
    user_id: Optional[str] = None,
    changes: Optional[Dict[str, Any]] = None
) -> ActivityLog:
    """Log an activity."""
    activity = ActivityLog(
        tenant_id=tenant_id,
        user_id=user_id,
        entity_type=entity_type,
        entity_id=entity_id,
        action=action,
        description=description,
        changes=json.dumps(changes) if changes else None
    )
    await activity.insert()
    return activity


async def list_activities(
    tenant_id: str,
    entity_type: Optional[str] = None,
    entity_id: Optional[str] = None,
    user_id: Optional[str] = None,
    action: Optional[str] = None,
    start_date: Optional[datetime] = None,
    end_date: Optional[datetime] = None,
    limit: int = 100,
    offset: int = 0
) -> List[ActivityLog]:
    """List activities with filters."""
    conditions = [ActivityLog.tenant_id == tenant_id]
    
    if entity_type:
        conditions.append(ActivityLog.entity_type == entity_type)
    
    if entity_id:
        conditions.append(ActivityLog.entity_id == entity_id)
    
    if user_id:
        conditions.append(ActivityLog.user_id == user_id)
    
    if action:
        conditions.append(ActivityLog.action == action)
    
    if start_date:
        conditions.append(ActivityLog.created_at >= start_date)
    
    if end_date:
        conditions.append(ActivityLog.created_at <= end_date)
    
    return await ActivityLog.find(*conditions).sort(-ActivityLog.created_at).skip(offset).limit(limit).to_list()


async def get_entity_activity(
    tenant_id: str,
    entity_type: str,
    entity_id: str
) -> List[ActivityLog]:
    """Get all activity for a specific entity."""
    return await list_activities(
        tenant_id,
        entity_type=entity_type,
        entity_id=entity_id
    )
