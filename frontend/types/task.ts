export interface Task {
  id: string;
  title: string;
  description?: string;
  status_id: string;
  status_name?: string;
  status_color?: string;
  priority_id?: string;
  priority_name?: string;
  priority_color?: string;
  project_id: string;
  project?: {
    id: string;
    title: string;
  };
  due_date?: string;
  start_date?: string;
  completion_percentage: number;
  assignees?: TaskAssignee[];
  user_id?: string[];
  created_at?: string;
  updated_at?: string;
  is_favorite?: boolean;
  is_pinned?: boolean;
  parent_id?: string;
  subtasks?: Task[];
  subtasks_count?: number;
}

export interface TaskAssignee {
  id: string;
  first_name: string;
  last_name: string;
  email: string;
}

export interface TaskFormData {
  title: string;
  description?: string;
  project_id: string;
  status_id: string;
  priority_id?: string;
  due_date?: string;
  start_date?: string;
  user_id?: string[];
  completion_percentage?: number;
}

export interface TaskFilters {
  project_id?: string;
  status_id?: string;
  priority_id?: string;
  assignee_id?: string;
  search?: string;
  due_date_from?: string;
  due_date_to?: string;
}

export interface KanbanColumn {
  status_id: string;
  status_name: string;
  status_color: string;
  tasks: Task[];
  count: number;
}




