export interface Project {
  id: string;
  title: string;
  name?: string;
  description?: string;
  client_id: string;
  client?: {
    id: string;
    first_name: string;
    last_name: string;
    email: string;
  };
  budget?: number;
  start_date?: string;
  end_date?: string;
  deadline?: string;
  status: "active" | "completed" | "on_hold" | "cancelled";
  created_at?: string;
  updated_at?: string;
}

export interface ProjectFormData {
  title: string;
  name?: string;
  description?: string;
  client_id: string;
  budget?: number;
  start_date?: string;
  end_date?: string;
  deadline?: string;
  status?: "active" | "completed" | "on_hold" | "cancelled";
}





