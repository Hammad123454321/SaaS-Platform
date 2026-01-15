"""Escalation Service - Stage 5

Escalation rules for overdue required tasks.
"""
from datetime import datetime, timedelta
from typing import List, Optional
from sqlmodel import Session, select

from app.models.workflows import EscalationRule, EscalationEvent
from app.models.tasks import Task
from app.models.user import User
from app.models.role import Role, UserRole
# Note: get_owner_for_tenant is defined below to avoid circular import


def check_and_escalate_overdue_tasks(session: Session, tenant_id: int) -> List[EscalationEvent]:
    """Check for overdue required tasks and escalate if needed."""
    escalation_events = []
    
    # Get active escalation rules
    rules = session.exec(
        select(EscalationRule).where(
            EscalationRule.tenant_id == tenant_id,
            EscalationRule.is_active == True
        )
    ).all()
    
    if not rules:
        return escalation_events
    
    # Get overdue required tasks
    now = datetime.utcnow()
    overdue_tasks = session.exec(
        select(Task).where(
            Task.tenant_id == tenant_id,
            Task.is_required == True,
            Task.due_date.isnot(None),
            Task.completion_percentage < 100
        )
    ).all()
    
    for task in overdue_tasks:
        if not task.due_date:
            continue
        
        # Check if task is overdue (considering escalation delay)
        for rule in rules:
            if rule.trigger_condition == "overdue_required_task":
                escalation_time = datetime.combine(task.due_date, datetime.min.time()) + timedelta(hours=rule.escalation_delay_hours)
                
                if now >= escalation_time:
                    # Check if already escalated
                    existing = session.exec(
                        select(EscalationEvent).where(
                            EscalationEvent.task_id == task.id,
                            EscalationEvent.rule_id == rule.id,
                            EscalationEvent.resolved_at.is_(None)
                        )
                    ).first()
                    
                    if existing:
                        continue  # Already escalated
                    
                    # Get users to notify
                    notified_users = _get_users_to_notify(session, tenant_id, rule, task)
                    
                    if notified_users:
                        # Create escalation event
                        event = EscalationEvent(
                            tenant_id=tenant_id,
                            task_id=task.id,
                            rule_id=rule.id,
                            notified_user_ids=[u.id for u in notified_users]
                        )
                        session.add(event)
                        escalation_events.append(event)
                        
                        # Send notifications
                        _send_escalation_notifications(session, task, notified_users, rule)
    
    session.commit()
    for event in escalation_events:
        session.refresh(event)
    
    return escalation_events


def get_owner_for_tenant(session: Session, tenant_id: int) -> Optional[User]:
    """Get owner user for tenant."""
    from app.models.onboarding import OwnerConfirmation
    owner_confirmation = session.exec(
        select(OwnerConfirmation).where(OwnerConfirmation.tenant_id == tenant_id)
    ).first()
    if owner_confirmation:
        return session.get(User, owner_confirmation.owner_user_id)
    return None


def _get_users_to_notify(
    session: Session,
    tenant_id: int,
    rule: EscalationRule,
    task: Task
) -> List[User]:
    """Get list of users to notify based on escalation rule."""
    users_to_notify = []
    
    # Get notify roles from rule
    notify_roles = rule.notify_roles or []
    
    # Get Owner
    if "owner" in notify_roles:
        owner = get_owner_for_tenant(session, tenant_id)
        if owner:
            users_to_notify.append(owner)
    
    # Get Managers
    if "manager" in notify_roles:
        manager_role = session.exec(
            select(Role).where(
                Role.tenant_id == tenant_id,
                Role.name == "manager"
            )
        ).first()
        
        if manager_role:
            manager_users = session.exec(
                select(User).join(UserRole).where(
                    UserRole.role_id == manager_role.id,
                    User.tenant_id == tenant_id,
                    User.is_active == True
                )
            ).all()
            users_to_notify.extend(manager_users)
    
    # Get task assignees
    from app.models.tasks import TaskAssignment
    assignees = session.exec(
        select(User).join(TaskAssignment).where(
            TaskAssignment.task_id == task.id,
            User.tenant_id == tenant_id,
            User.is_active == True
        )
    ).all()
    users_to_notify.extend(assignees)
    
    # Get project creator/owner
    project = session.get(task.project_id, type_=task.__class__.__bases__[0])
    if project:
        project_creator = session.get(User, project.created_by)
        if project_creator and project_creator not in users_to_notify:
            users_to_notify.append(project_creator)
    
    # Remove duplicates
    seen = set()
    unique_users = []
    for user in users_to_notify:
        if user.id not in seen:
            seen.add(user.id)
            unique_users.append(user)
    
    return unique_users


def _send_escalation_notifications(
    session: Session,
    task: Task,
    users: List[User],
    rule: EscalationRule
) -> None:
    """Send escalation notifications to users."""
    # For now, we'll just log. Email notifications can be added later.
    import logging
    logger = logging.getLogger(__name__)
    
    for user in users:
        logger.warning(
            f"Escalation: Task '{task.title}' (ID: {task.id}) is overdue. "
            f"Notifying user {user.email} (ID: {user.id})"
        )
        # TODO: Send email notification via email_service


def create_default_escalation_rule(session: Session, tenant_id: int) -> EscalationRule:
    """Create default escalation rule for tenant."""
    existing = session.exec(
        select(EscalationRule).where(
            EscalationRule.tenant_id == tenant_id,
            EscalationRule.trigger_condition == "overdue_required_task"
        )
    ).first()
    
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
    session.add(rule)
    session.commit()
    session.refresh(rule)
    return rule

