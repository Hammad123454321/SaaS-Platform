export interface Task {
  id: number;
  title: string;
  description?: string;
  status_id: number;
  status_name?: string;
  status_color?: string;
  priority_id?: number;
  priority_name?: string;
  priority_color?: string;
  project_id: number;
  project?: {
    id: number;
    title: string;
  };
  due_date?: string;
  start_date?: string;
  completion_percentage: number;
  assignees?: TaskAssignee[];
  user_id?: number[];
  created_at?: string;
  updated_at?: string;
  is_favorite?: boolean;
  is_pinned?: boolean;
  subtasks?: Task[];
  subtasks_count?: number;
}

export interface TaskAssignee {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
}

export interface TaskFormData {
  title: string;
  description?: string;
  project_id: number;
  status_id: number;
  priority_id?: number;
  due_date?: string;
  start_date?: string;
  user_id?: number[];
  completion_percentage?: number;
}

export interface TaskFilters {
  project_id?: number;
  status_id?: number;
  priority_id?: number;
  assignee_id?: number;
  search?: string;
  due_date_from?: string;
  due_date_to?: string;
}

export interface KanbanColumn {
  status_id: number;
  status_name: string;
  status_color: string;
  tasks: Task[];
  count: number;
}





