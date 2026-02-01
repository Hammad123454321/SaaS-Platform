export interface Client {
  id: string;
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  company?: string;
  address?: string;
  notes?: string;
  created_at?: string;
  updated_at?: string;
}

export interface ClientFormData {
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  company?: string;
  address?: string;
  notes?: string;
}





