export interface Status {
  id: string;
  title: string;
  name?: string;
  color: string;
  order?: number;
  category?: "todo" | "in-progress" | "done" | "cancelled";
}

export interface StatusFormData {
  title: string;
  name?: string;
  color: string;
  order?: number;
  category?: "todo" | "in-progress" | "done" | "cancelled";
}





