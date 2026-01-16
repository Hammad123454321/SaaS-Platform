/**
 * Dashboard API Hooks
 * 
 * Provides React Query hooks for fetching dashboard data from the backend.
 */
import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";

// ==================== Types ====================

export interface ModuleUsageItem {
  module: string;
  usage: number;
  color: string;
}

export interface TaskTrendItem {
  date: string;
  completed: number;
  created: number;
}

export interface TeamMemberItem {
  id: number;
  name: string;
  email: string;
  role: string;
  tasks_completed: number;
  last_active: string | null;
}

export interface RecentTaskItem {
  id: number;
  title: string;
  status: string;
  status_color: string;
  project: string | null;
  due_date: string | null;
  is_overdue: boolean;
}

export interface DeadlineItem {
  id: number;
  title: string;
  due_date: string;
  days_left: number;
  project: string | null;
}

export interface ActivityItem {
  id: number;
  type: "task" | "user" | "booking" | "ai" | "document";
  title: string;
  description: string;
  timestamp: string;
  user: string | null;
}

export interface ModuleItem {
  code: string;
  name: string;
  enabled: boolean;
  ai_access: boolean;
  usage_count: number;
}

export interface CompanyStats {
  company_name: string;
  total_users: number;
  enabled_modules: number;
  tasks_this_week: number;
  subscription_status: string;
}

export interface StaffStats {
  total: number;
  pending: number;
  in_progress: number;
  completed: number;
  overdue: number;
}

export interface AdminStats {
  total_tenants: number;
  total_users: number;
  active_subscriptions: number;
  total_revenue: number;
}

export interface GrowthDataItem {
  month: string;
  tenants: number;
  users: number;
}

export interface RevenueDataItem {
  month: string;
  revenue: number;
}

export interface ModulePopularityItem {
  name: string;
  value: number;
  color: string;
}

export interface TenantItem {
  id: number;
  name: string;
  slug: string;
  created_at: string;
  user_count: number;
  status: "active" | "trial" | "inactive";
}

export interface ServiceStatusItem {
  name: string;
  status: "online" | "degraded" | "offline";
  latency: number | null;
  icon: "server" | "globe" | "database" | "activity" | "wifi" | "shield";
}

// ==================== Company Admin Hooks ====================

export function useCompanyStats() {
  return useQuery<CompanyStats>({
    queryKey: ["dashboard", "company", "stats"],
    queryFn: async () => {
      const res = await api.get<CompanyStats>("/dashboard/company/stats");
      return res.data;
    },
    staleTime: 30000, // 30 seconds
  });
}

export function useCompanyModuleUsage() {
  return useQuery<ModuleUsageItem[]>({
    queryKey: ["dashboard", "company", "module-usage"],
    queryFn: async () => {
      const res = await api.get<ModuleUsageItem[]>("/dashboard/company/module-usage");
      return res.data;
    },
    staleTime: 60000, // 1 minute
  });
}

export function useCompanyTaskTrends() {
  return useQuery<TaskTrendItem[]>({
    queryKey: ["dashboard", "company", "task-trends"],
    queryFn: async () => {
      const res = await api.get<TaskTrendItem[]>("/dashboard/company/task-trends");
      return res.data;
    },
    staleTime: 60000,
  });
}

export function useCompanyTeamOverview(limit: number = 10) {
  return useQuery<TeamMemberItem[]>({
    queryKey: ["dashboard", "company", "team-overview", limit],
    queryFn: async () => {
      const res = await api.get<TeamMemberItem[]>(`/dashboard/company/team-overview?limit=${limit}`);
      return res.data;
    },
    staleTime: 60000,
  });
}

export function useCompanyRecentTasks(limit: number = 5) {
  return useQuery<RecentTaskItem[]>({
    queryKey: ["dashboard", "company", "recent-tasks", limit],
    queryFn: async () => {
      const res = await api.get<RecentTaskItem[]>(`/dashboard/company/recent-tasks?limit=${limit}`);
      return res.data;
    },
    staleTime: 30000,
  });
}

export function useCompanyUpcomingDeadlines(limit: number = 5) {
  return useQuery<DeadlineItem[]>({
    queryKey: ["dashboard", "company", "upcoming-deadlines", limit],
    queryFn: async () => {
      const res = await api.get<DeadlineItem[]>(`/dashboard/company/upcoming-deadlines?limit=${limit}`);
      return res.data;
    },
    staleTime: 60000,
  });
}

export function useCompanyActivity(limit: number = 10) {
  return useQuery<ActivityItem[]>({
    queryKey: ["dashboard", "company", "activity", limit],
    queryFn: async () => {
      const res = await api.get<ActivityItem[]>(`/dashboard/company/activity?limit=${limit}`);
      return res.data;
    },
    staleTime: 30000,
  });
}

export function useCompanyModules() {
  return useQuery<ModuleItem[]>({
    queryKey: ["dashboard", "company", "modules"],
    queryFn: async () => {
      const res = await api.get<ModuleItem[]>("/dashboard/company/modules");
      return res.data;
    },
    staleTime: 60000,
  });
}

// ==================== Staff Dashboard Hooks ====================

export function useStaffStats() {
  return useQuery<StaffStats>({
    queryKey: ["dashboard", "staff", "stats"],
    queryFn: async () => {
      const res = await api.get<StaffStats>("/dashboard/staff/stats");
      return res.data;
    },
    staleTime: 30000,
  });
}

export function useStaffMyTasks(limit: number = 10) {
  return useQuery<RecentTaskItem[]>({
    queryKey: ["dashboard", "staff", "my-tasks", limit],
    queryFn: async () => {
      const res = await api.get<RecentTaskItem[]>(`/dashboard/staff/my-tasks?limit=${limit}`);
      return res.data;
    },
    staleTime: 30000,
  });
}

export function useStaffTaskTrends() {
  return useQuery<TaskTrendItem[]>({
    queryKey: ["dashboard", "staff", "task-trends"],
    queryFn: async () => {
      const res = await api.get<TaskTrendItem[]>("/dashboard/staff/task-trends");
      return res.data;
    },
    staleTime: 60000,
  });
}

export function useStaffUpcomingDeadlines(limit: number = 5) {
  return useQuery<DeadlineItem[]>({
    queryKey: ["dashboard", "staff", "upcoming-deadlines", limit],
    queryFn: async () => {
      const res = await api.get<DeadlineItem[]>(`/dashboard/staff/upcoming-deadlines?limit=${limit}`);
      return res.data;
    },
    staleTime: 60000,
  });
}

export function useStaffActivity(limit: number = 10) {
  return useQuery<ActivityItem[]>({
    queryKey: ["dashboard", "staff", "activity", limit],
    queryFn: async () => {
      const res = await api.get<ActivityItem[]>(`/dashboard/staff/activity?limit=${limit}`);
      return res.data;
    },
    staleTime: 30000,
  });
}

// ==================== Super Admin Dashboard Hooks ====================

export function useAdminStats() {
  return useQuery<AdminStats>({
    queryKey: ["dashboard", "admin", "stats"],
    queryFn: async () => {
      const res = await api.get<AdminStats>("/dashboard/admin/stats");
      return res.data;
    },
    staleTime: 30000,
  });
}

export function useAdminGrowthData() {
  return useQuery<GrowthDataItem[]>({
    queryKey: ["dashboard", "admin", "growth"],
    queryFn: async () => {
      const res = await api.get<GrowthDataItem[]>("/dashboard/admin/growth");
      return res.data;
    },
    staleTime: 60000,
  });
}

export function useAdminRevenueData() {
  return useQuery<RevenueDataItem[]>({
    queryKey: ["dashboard", "admin", "revenue"],
    queryFn: async () => {
      const res = await api.get<RevenueDataItem[]>("/dashboard/admin/revenue");
      return res.data;
    },
    staleTime: 60000,
  });
}

export function useAdminModulePopularity() {
  return useQuery<ModulePopularityItem[]>({
    queryKey: ["dashboard", "admin", "module-popularity"],
    queryFn: async () => {
      const res = await api.get<ModulePopularityItem[]>("/dashboard/admin/module-popularity");
      return res.data;
    },
    staleTime: 60000,
  });
}

export function useAdminRecentTenants(limit: number = 5) {
  return useQuery<TenantItem[]>({
    queryKey: ["dashboard", "admin", "recent-tenants", limit],
    queryFn: async () => {
      const res = await api.get<TenantItem[]>(`/dashboard/admin/recent-tenants?limit=${limit}`);
      return res.data;
    },
    staleTime: 60000,
  });
}

export function useAdminSystemHealth() {
  return useQuery<ServiceStatusItem[]>({
    queryKey: ["dashboard", "admin", "system-health"],
    queryFn: async () => {
      const res = await api.get<ServiceStatusItem[]>("/dashboard/admin/system-health");
      return res.data;
    },
    staleTime: 30000,
    refetchInterval: 60000, // Refresh every minute
  });
}

export function useAdminActivity(limit: number = 10) {
  return useQuery<ActivityItem[]>({
    queryKey: ["dashboard", "admin", "activity", limit],
    queryFn: async () => {
      const res = await api.get<ActivityItem[]>(`/dashboard/admin/activity?limit=${limit}`);
      return res.data;
    },
    staleTime: 30000,
  });
}

// ==================== AI Hooks ====================

export interface AIInsights {
  summary: string;
  overdue_tasks: number;
  upcoming_deadlines: number;
  pending_items: number;
  suggestions: string[];
}

export interface AITaskSuggestion {
  description: string;
  suggested_priority: string;
  suggested_due_days: number;
}

export interface AIChatMessage {
  role: "user" | "assistant" | "system";
  content: string;
}

export function useAIInsights() {
  return useQuery<AIInsights>({
    queryKey: ["ai", "insights"],
    queryFn: async () => {
      const res = await api.get<AIInsights>("/ai/insights");
      return res.data;
    },
    staleTime: 120000, // 2 minutes
    retry: 1,
  });
}

export async function sendAIChatMessage(messages: AIChatMessage[]): Promise<string> {
  const res = await api.post<{ reply: string }>("/ai/chat", { messages });
  return res.data.reply;
}

export async function generateTaskWithAI(title: string, context: string = ""): Promise<AITaskSuggestion> {
  const res = await api.post<AITaskSuggestion>("/ai/generate-task", { title, context });
  return res.data;
}

