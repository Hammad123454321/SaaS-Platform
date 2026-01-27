"""Escalation Service - Stage 5

Escalation rules for overdue required tasks.
"""
import logging
from datetime import datetime, timedelta
from typing import List, Optional

from beanie import PydanticObjectId

from app.models.workflows import EscalationRule, EscalationEvent
from app.models.tasks import Task
from app.models.user import User
from app.models.role import Role, UserRole


logger = logging.getLogger(__name__)


async def check_and_escalate_overdue_tasks(tenant_id: str) -> List[EscalationEvent]:
    """Check for overdue required tasks and escalate if needed."""
    escalation_events = []
    
    # Get active escalation rules
    rules = await EscalationRule.find(
        EscalationRule.tenant_id == tenant_id,
        EscalationRule.is_active == True
    ).to_list()
    
    if not rules:
        return escalation_events
    
    # Get overdue required tasks
    now = datetime.utcnow()
    overdue_tasks = await Task.find(
        Task.tenant_id == tenant_id,
        Task.is_required == True,
        Task.due_date != None,
        Task.completion_percentage < 100
    ).to_list()
    
    for task in overdue_tasks:
        if not task.due_date:
            continue
        
        # Check if task is overdue (considering escalation delay)
        for rule in rules:
            if rule.trigger_condition == "overdue_required_task":
                escalation_time = datetime.combine(task.due_date, datetime.min.time()) + timedelta(hours=rule.escalation_delay_hours)
                
                if now >= escalation_time:
                    # Check if already escalated
                    existing = await EscalationEvent.find_one(
                        EscalationEvent.task_id == str(task.id),
                        EscalationEvent.rule_id == str(rule.id),
                        EscalationEvent.resolved_at == None
                    )
                    
                    if existing:
                        continue  # Already escalated
                    
                    # Get users to notify
                    notified_users = await _get_users_to_notify(tenant_id, rule, task)
                    
                    if notified_users:
                        # Create escalation event
                        event = EscalationEvent(
                            tenant_id=tenant_id,
                            task_id=str(task.id),
                            rule_id=str(rule.id),
                            notified_user_ids=[str(u.id) for u in notified_users]
                        )
                        await event.insert()
                        escalation_events.append(event)
                        
                        # Send notifications
                        await _send_escalation_notifications(task, notified_users, rule)
    
    return escalation_events


async def get_owner_for_tenant(tenant_id: str) -> Optional[User]:
    """Get owner user for tenant."""
    from app.models.onboarding import OwnerConfirmation
    owner_confirmation = await OwnerConfirmation.find_one(
        OwnerConfirmation.tenant_id == tenant_id
    )
    if owner_confirmation:
        return await User.get(owner_confirmation.owner_user_id)
    return None


async def _get_users_to_notify(
    tenant_id: str,
    rule: EscalationRule,
    task: Task
) -> List[User]:
    """Get list of users to notify based on escalation rule."""
    users_to_notify = []
    
    # Get notify roles from rule
    notify_roles = rule.notify_roles or []
    
    # Get Owner
    if "owner" in notify_roles:
        owner = await get_owner_for_tenant(tenant_id)
        if owner:
            users_to_notify.append(owner)
    
    # Get Managers
    if "manager" in notify_roles:
        manager_role = await Role.find_one(
            Role.tenant_id == tenant_id,
            Role.name == "manager"
        )
        
        if manager_role:
            user_roles = await UserRole.find(UserRole.role_id == str(manager_role.id)).to_list()
            for ur in user_roles:
                user = await User.get(ur.user_id)
                if user and user.tenant_id == tenant_id and user.is_active:
                    users_to_notify.append(user)
    
    # Get task assignees
    from app.models.tasks import TaskAssignment
    assignees = await TaskAssignment.find(TaskAssignment.task_id == str(task.id)).to_list()
    for assignment in assignees:
        user = await User.get(assignment.user_id)
        if user and user.tenant_id == tenant_id and user.is_active:
            users_to_notify.append(user)
    
    # Remove duplicates
    seen = set()
    unique_users = []
    for user in users_to_notify:
        if str(user.id) not in seen:
            seen.add(str(user.id))
            unique_users.append(user)
    
    return unique_users


async def _send_escalation_notifications(
    task: Task,
    users: List[User],
    rule: EscalationRule
) -> None:
    """Send escalation notifications to users."""
    for user in users:
        logger.warning(
            f"Escalation: Task '{task.title}' (ID: {task.id}) is overdue. "
            f"Notifying user {user.email} (ID: {user.id})"
        )


async def create_default_escalation_rule(tenant_id: str) -> EscalationRule:
    """Create default escalation rule for tenant."""
    existing = await EscalationRule.find_one(
        EscalationRule.tenant_id == tenant_id,
        EscalationRule.trigger_condition == "overdue_required_task"
    )
    
    if existing:
        return existing
    
    rule = EscalationRule(
        tenant_id=tenant_id,
        rule_name="Default Overdue Task Escalation",
        trigger_condition="overdue_required_task",
        escalation_delay_hours=24,
        notify_roles=["owner", "manager"],
        is_active=True
    )
    await rule.insert()
    return rule
