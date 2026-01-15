import apiClient from "./client";

// ========== Stage 5: Task Templates ==========

export interface TaskTemplate {
  id: number;
  tenant_id: number;
  template_name: string;
  template_type: string;
  title: string;
  description: string | null;
  priority: string | null;
  status: string | null;
  is_locked: boolean;
  metadata: Record<string, any> | null;
  created_at: string;
  updated_at: string;
}

export interface TaskTemplateCreate {
  template_name: string;
  template_type: "incident" | "safety" | "custom";
  title: string;
  description?: string;
  priority?: string;
  status?: string;
  is_locked?: boolean;
  metadata?: Record<string, any>;
}

export const createTaskTemplate = async (data: TaskTemplateCreate): Promise<TaskTemplate> => {
  const response = await apiClient.post("/workflows/templates", data);
  return response.data;
};

export const getTaskTemplates = async (templateType?: string): Promise<TaskTemplate[]> => {
  const url = templateType 
    ? `/workflows/templates?template_type=${templateType}`
    : "/workflows/templates";
  const response = await apiClient.get(url);
  return response.data;
};

export const updateTaskTemplate = async (templateId: number, data: TaskTemplateCreate): Promise<TaskTemplate> => {
  const response = await apiClient.put(`/workflows/templates/${templateId}`, data);
  return response.data;
};

export const deleteTaskTemplate = async (templateId: number): Promise<void> => {
  await apiClient.delete(`/workflows/templates/${templateId}`);
};

export const createTaskFromTemplate = async (
  templateId: number,
  projectId: number,
  title?: string,
  description?: string
): Promise<{ task_id: number }> => {
  const response = await apiClient.post(`/workflows/templates/${templateId}/create-task`, {
    template_id: templateId,
    project_id: projectId,
    title,
    description,
  });
  return response.data;
};

// ========== Stage 5: Onboarding Tasks ==========

export interface OnboardingTask {
  id: number;
  tenant_id: number;
  task_id: number | null;
  source: "module" | "industry" | "compliance_rule";
  source_id: string | null;
  title: string;
  description: string | null;
  is_required: boolean;
  is_completed: boolean;
  completed_at: string | null;
  due_date: string | null;
  assigned_to_user_id: number | null;
  created_at: string;
}

export const generateOnboardingTasks = async (assignedToUserId?: number): Promise<{ tasks_generated: number; tasks: OnboardingTask[] }> => {
  const url = assignedToUserId
    ? `/workflows/onboarding-tasks/generate?assigned_to_user_id=${assignedToUserId}`
    : "/workflows/onboarding-tasks/generate";
  const response = await apiClient.post(url);
  return response.data;
};

export const getOnboardingTasks = async (): Promise<OnboardingTask[]> => {
  const response = await apiClient.get("/workflows/onboarding-tasks");
  return response.data;
};

export const createTaskFromOnboardingTask = async (
  onboardingTaskId: number,
  projectId: number
): Promise<{ task_id: number }> => {
  const response = await apiClient.post(`/workflows/onboarding-tasks/${onboardingTaskId}/create-task?project_id=${projectId}`);
  return response.data;
};

// ========== Stage 5: Escalation Rules ==========

export interface EscalationRule {
  id: number;
  tenant_id: number;
  rule_name: string;
  trigger_condition: string;
  escalation_delay_hours: number;
  notify_roles: string[] | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface EscalationRuleCreate {
  rule_name: string;
  trigger_condition: "overdue_required_task" | "not_completed_by_due_date";
  escalation_delay_hours?: number;
  notify_roles?: string[];
}

export const createEscalationRule = async (data: EscalationRuleCreate): Promise<EscalationRule> => {
  const response = await apiClient.post("/workflows/escalation-rules", data);
  return response.data;
};

export const getEscalationRules = async (): Promise<EscalationRule[]> => {
  const response = await apiClient.get("/workflows/escalation-rules");
  return response.data;
};

export const checkEscalations = async (): Promise<{ escalations_triggered: number; events: Array<{ id: number; task_id: number }> }> => {
  const response = await apiClient.post("/workflows/escalation-rules/check");
  return response.data;
};

