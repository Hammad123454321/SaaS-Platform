export interface ApiResponse<T> {
  data: T;
  message?: string;
  success?: boolean;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  per_page: number;
  total_pages: number;
}

export interface DropdownItem {
  id: string;
  name: string;
  title?: string;
  color?: string;
  email?: string;
  status?: number;
  client_id?: string;
}





