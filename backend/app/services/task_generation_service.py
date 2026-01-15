"""Task Generation Service - Stage 5

Auto-generate onboarding tasks based on modules, industry, and compliance rules.
"""
from datetime import datetime, timedelta
from typing import List, Optional
from sqlmodel import Session, select

from app.models.workflows import OnboardingTask, OnboardingTaskSource
from app.models.onboarding import BusinessProfile, TenantComplianceRule
from app.models.entitlement import ModuleEntitlement, ModuleCode
from app.models.tasks import Task, Project, TaskStatus
from app.models.user import User


def generate_onboarding_tasks_for_tenant(
    session: Session,
    tenant_id: int,
    assigned_to_user_id: Optional[int] = None
) -> List[OnboardingTask]:
    """
    Generate onboarding tasks based on:
    - Modules selected
    - Industry selected
    - Compliance rules activated
    """
    tasks = []
    
    # Get business profile
    business_profile = session.exec(
        select(BusinessProfile).where(BusinessProfile.tenant_id == tenant_id)
    ).first()
    
    if not business_profile:
        return tasks  # No business profile, no tasks
    
    # Get enabled modules
    enabled_modules = session.exec(
        select(ModuleEntitlement).where(
            ModuleEntitlement.tenant_id == tenant_id,
            ModuleEntitlement.enabled == True
        )
    ).all()
    
    # Get compliance rules
    compliance_rules = session.exec(
        select(TenantComplianceRule).where(
            TenantComplianceRule.tenant_id == tenant_id
        )
    ).all()
    
    # Generate tasks from modules
    for module in enabled_modules:
        module_tasks = _generate_module_tasks(module.module_code, tenant_id, assigned_to_user_id)
        tasks.extend(module_tasks)
    
    # Generate tasks from industry
    if business_profile.province:
        industry_tasks = _generate_industry_tasks(
            business_profile.province.value,
            tenant_id,
            assigned_to_user_id
        )
        tasks.extend(industry_tasks)
    
    # Generate tasks from compliance rules
    for rule in compliance_rules:
        compliance_tasks = _generate_compliance_tasks(
            rule.rule_code.value,
            tenant_id,
            assigned_to_user_id
        )
        tasks.extend(compliance_tasks)
    
    # Save all tasks
    for task in tasks:
        session.add(task)
    
    session.commit()
    for task in tasks:
        session.refresh(task)
    
    return tasks


def _generate_module_tasks(
    module_code: ModuleCode,
    tenant_id: int,
    assigned_to_user_id: Optional[int]
) -> List[OnboardingTask]:
    """Generate tasks based on enabled module."""
    tasks = []
    
    module_task_map = {
        ModuleCode.TASKS: [
            {"title": "Set up task management workflows", "description": "Configure task statuses and priorities"},
            {"title": "Create your first project", "description": "Set up a project to organize tasks"},
        ],
        ModuleCode.HRM: [
            {"title": "Configure HR module settings", "description": "Set up employee management and policies"},
            {"title": "Upload employee handbook", "description": "Add your company's employee handbook"},
        ],
        ModuleCode.FINANCE: [
            {"title": "Connect payment processor", "description": "Set up Stripe or other payment methods"},
            {"title": "Configure billing settings", "description": "Set up billing cycles and invoicing"},
        ],
        ModuleCode.BOOKINGS: [
            {"title": "Set up booking calendar", "description": "Configure availability and booking rules"},
            {"title": "Add service offerings", "description": "Define services that can be booked"},
        ],
        ModuleCode.POS: [
            {"title": "Configure POS system", "description": "Set up point of sale hardware and software"},
            {"title": "Add product catalog", "description": "Set up your product inventory"},
        ],
        ModuleCode.WEBSITE: [
            {"title": "Set up website domain", "description": "Configure your website domain and hosting"},
            {"title": "Customize website template", "description": "Personalize your website design"},
        ],
        ModuleCode.MARKETING: [
            {"title": "Configure marketing campaigns", "description": "Set up email marketing and campaigns"},
            {"title": "Connect social media accounts", "description": "Link your social media profiles"},
        ],
    }
    
    module_tasks = module_task_map.get(module_code, [])
    
    for task_data in module_tasks:
        task = OnboardingTask(
            tenant_id=tenant_id,
            source=OnboardingTaskSource.MODULE,
            source_id=module_code.value,
            title=task_data["title"],
            description=task_data.get("description"),
            is_required=True,
            assigned_to_user_id=assigned_to_user_id,
            due_date=datetime.utcnow() + timedelta(days=7)  # 1 week from now
        )
        tasks.append(task)
    
    return tasks


def _generate_industry_tasks(
    province: str,
    tenant_id: int,
    assigned_to_user_id: Optional[int]
) -> List[OnboardingTask]:
    """Generate tasks based on industry/province."""
    tasks = []
    
    # Ontario-specific tasks
    if province == "ON":
        tasks.append(OnboardingTask(
            tenant_id=tenant_id,
            source=OnboardingTaskSource.INDUSTRY,
            source_id="ON",
            title="Complete PAWS training (if applicable)",
            description="Complete Provincial Animal Welfare Services training if you handle animals",
            is_required=False,  # Not required for all businesses
            assigned_to_user_id=assigned_to_user_id,
            due_date=datetime.utcnow() + timedelta(days=14)
        ))
    
    # Add more industry-specific tasks as needed
    
    return tasks


def _generate_compliance_tasks(
    rule_code: str,
    tenant_id: int,
    assigned_to_user_id: Optional[int]
) -> List[OnboardingTask]:
    """Generate tasks based on activated compliance rules."""
    tasks = []
    
    compliance_task_map = {
        "PAWS": [
            {"title": "Review PAWS requirements", "description": "Familiarize yourself with Provincial Animal Welfare Services requirements"},
            {"title": "Complete PAWS compliance checklist", "description": "Ensure all PAWS requirements are met"},
        ],
        "WSIB": [
            {"title": "Register for WSIB", "description": "Complete WSIB registration if required"},
            {"title": "Review WSIB coverage", "description": "Verify your WSIB coverage is appropriate"},
        ],
        "CFIA": [
            {"title": "Review CFIA requirements", "description": "Ensure compliance with Canadian Food Inspection Agency regulations"},
        ],
        "PIPEDA": [
            {"title": "Review PIPEDA compliance", "description": "Ensure your privacy practices comply with PIPEDA"},
        ],
        "CASL": [
            {"title": "Review CASL compliance", "description": "Ensure email marketing practices comply with CASL"},
        ],
    }
    
    rule_tasks = compliance_task_map.get(rule_code, [])
    
    for task_data in rule_tasks:
        task = OnboardingTask(
            tenant_id=tenant_id,
            source=OnboardingTaskSource.COMPLIANCE_RULE,
            source_id=rule_code,
            title=task_data["title"],
            description=task_data.get("description"),
            is_required=True,
            assigned_to_user_id=assigned_to_user_id,
            due_date=datetime.utcnow() + timedelta(days=14)
        )
        tasks.append(task)
    
    return tasks


def create_task_from_onboarding_task(
    session: Session,
    onboarding_task: OnboardingTask,
    project_id: int,
    created_by_user_id: int
) -> Task:
    """Create an actual Task from an OnboardingTask."""
    # Get default status for tenant
    default_status = session.exec(
        select(TaskStatus).where(
            TaskStatus.tenant_id == onboarding_task.tenant_id
        ).limit(1)
    ).first()
    
    if not default_status:
        raise ValueError("No task status found for tenant")
    
    task = Task(
        tenant_id=onboarding_task.tenant_id,
        project_id=project_id,
        title=onboarding_task.title,
        description=onboarding_task.description,
        status_id=default_status.id,
        is_required=onboarding_task.is_required,
        created_by=created_by_user_id,
        due_date=onboarding_task.due_date.date() if onboarding_task.due_date else None
    )
    
    if onboarding_task.assigned_to_user_id:
        # Assign task to user
        from app.models.tasks import TaskAssignment
        session.add(task)
        session.flush()
        assignment = TaskAssignment(
            task_id=task.id,
            user_id=onboarding_task.assigned_to_user_id,
            is_primary=True
        )
        session.add(assignment)
    
    session.add(task)
    session.commit()
    session.refresh(task)
    
    # Link onboarding task to created task
    onboarding_task.task_id = task.id
    session.add(onboarding_task)
    session.commit()
    
    return task

