"use client";

import { useSessionStore } from "@/lib/store";
import Link from "next/link";
import { api } from "@/lib/api";
import { 
  CheckSquare, Clock, AlertCircle, Bell,
  Calendar, Timer, TrendingUp, Sparkles, LogOut, Mail
} from "lucide-react";

// Widgets
import { StatCard } from "@/components/dashboard/widgets/StatCard";
import { RecentTasksWidget } from "@/components/dashboard/widgets/RecentTasksWidget";
import { ModuleAccessWidget } from "@/components/dashboard/widgets/ModuleAccessWidget";
import { UpcomingDeadlinesWidget } from "@/components/dashboard/widgets/UpcomingDeadlinesWidget";
import { ActivityFeedWidget } from "@/components/dashboard/widgets/ActivityFeedWidget";
import { QuickActionsWidget } from "@/components/dashboard/widgets/QuickActionsWidget";

// Charts
import { TaskTrendChart } from "@/components/dashboard/charts/TaskTrendChart";

// API Hooks
import {
  useStaffStats,
  useStaffMyTasks,
  useStaffTaskTrends,
  useStaffUpcomingDeadlines,
  useStaffActivity,
  useCompanyModules,
} from "@/hooks/useDashboard";

const moduleLabels: Record<string, string> = {
  crm: "CRM",
  hrm: "HRM",
  pos: "POS",
  tasks: "Tasks",
  booking: "Booking",
  landing: "Landing Builder",
  ai: "AI Assistant",
};

export default function StaffDashboard() {
  const { entitlements, user, clearSession } = useSessionStore();

  const handleLogout = async () => {
    try {
      await api.post("/auth/logout");
    } catch (err) {
      // ignore
    } finally {
      clearSession();
      if (typeof window !== "undefined") {
        window.location.href = "/login";
      }
    }
  };

  // Fetch data from APIs
  const { data: stats, isLoading: statsLoading } = useStaffStats();
  const { data: myTasks, isLoading: tasksLoading } = useStaffMyTasks(10);
  const { data: taskTrends, isLoading: trendsLoading } = useStaffTaskTrends();
  const { data: deadlines, isLoading: deadlinesLoading } = useStaffUpcomingDeadlines(5);
  const { data: activity, isLoading: activityLoading } = useStaffActivity(10);
  const { data: modules, isLoading: modulesLoading } = useCompanyModules();

  const enabledModules = entitlements.filter((e) => e.enabled);
  const loading = statsLoading;

  // Transform API data for components
  const taskTrendData = taskTrends || [];

  const recentTasksData = (myTasks || []).slice(0, 5).map(task => ({
    id: task.id,
    title: task.title,
    status: task.status,
    statusColor: task.status_color,
    project: task.project || undefined,
    dueDate: task.due_date || undefined,
    isOverdue: task.is_overdue,
  }));

  const modulesData = (modules || enabledModules.map(m => ({
    code: m.module_code,
    name: moduleLabels[m.module_code] || m.module_code.toUpperCase(),
    enabled: m.enabled,
    ai_access: m.ai_access,
    usage_count: 0,
  }))).map(m => ({
    code: m.code,
    name: m.name,
    enabled: m.enabled,
    aiAccess: m.ai_access,
    usageCount: m.usage_count,
  }));

  const deadlinesData = (deadlines || []).map(d => ({
    id: d.id,
    title: d.title,
    dueDate: d.due_date,
    daysLeft: d.days_left,
    project: d.project || undefined,
  }));

  const activityData = (activity || []).map(a => ({
    id: a.id,
    type: a.type as "task" | "user" | "booking" | "ai" | "document",
    title: a.title,
    description: a.description,
    timestamp: a.timestamp,
    user: a.user || undefined,
  }));

  // Calculate stats
  const tasksStats = stats || {
    total: 0,
    pending: 0,
    in_progress: 0,
    completed: 0,
    overdue: 0,
  };

  const completedThisWeek = taskTrendData.reduce((s, d) => s + d.completed, 0);
  const totalThisWeek = taskTrendData.reduce((s, d) => s + d.completed + d.created, 0);
  const completionRate = totalThisWeek > 0 ? Math.round((completedThisWeek / totalThisWeek) * 100) : 0;

  const quickActions = [
    { label: "View My Tasks", href: "/modules/tasks", icon: CheckSquare, gradient: "btn-gradient-purple" },
    { label: "Check Calendar", href: "/modules/booking", icon: Calendar, gradient: "btn-gradient-orange" },
    { label: "Start Time Tracker", href: "/modules/tasks", icon: Timer, gradient: "btn-gradient-cyan" },
  ];

  // Add CRM action if enabled
  if (enabledModules.some(m => m.module_code === "crm")) {
    quickActions.push({ label: "View CRM", href: "/modules/crm", icon: TrendingUp, gradient: "btn-gradient-pink" });
  }

  // Tabs that match the white navigation bar routes for Staff
  const tabs = [
    { label: "Overview", href: "/dashboard", active: true },
    { label: "Tasks", href: "/modules/tasks", active: false },
  ];

  return (
    <div className="min-h-screen">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <p className="text-gray-500 text-sm mb-1">Welcome back</p>
          <h1 className="text-3xl font-bold text-gray-900">{user?.email?.split("@")[0] || "Staff"}&apos;s Dashboard</h1>
          <p className="text-gray-500 mt-1">Track your tasks, time, and progress</p>
        </div>
        <div className="flex items-center gap-3">
          {/* Email */}
          <div className="hidden sm:flex items-center gap-2 px-3 py-2 rounded-xl bg-white/80 shadow-sm">
            <Mail className="h-4 w-4 text-purple-500" />
            <span className="text-sm text-gray-600 font-medium">{user?.email || "user@example.com"}</span>
          </div>
          <button className="w-10 h-10 rounded-xl bg-white/80 shadow-sm flex items-center justify-center hover:bg-white transition relative">
            <Bell className="h-5 w-5 text-gray-600" />
            {tasksStats.overdue > 0 && (
              <span className="absolute -top-1 -right-1 w-4 h-4 bg-rose-500 rounded-full text-[10px] text-white flex items-center justify-center">
                {tasksStats.overdue}
              </span>
            )}
          </button>
          <div className="w-10 h-10 rounded-full bg-gradient-to-r from-cyan-500 to-blue-500 flex items-center justify-center text-white font-semibold">
            {user?.email?.charAt(0).toUpperCase() || "S"}
          </div>
          {/* Logout Button */}
          <button
            onClick={handleLogout}
            className="flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-rose-500 to-pink-500 text-white font-medium text-sm shadow-sm hover:opacity-90 transition"
          >
            <LogOut className="h-4 w-4" />
            <span className="hidden sm:inline">Logout</span>
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex items-center gap-1 mb-6 border-b border-purple-200 pb-2">
        {tabs.map((tab) => (
          <Link
            key={tab.label}
            href={tab.href}
            className={`px-4 py-2 text-sm font-medium rounded-t-lg transition ${
              tab.active 
                ? "text-purple-700 border-b-2 border-purple-500 bg-white/50" 
                : "text-gray-500 hover:text-gray-700 hover:bg-white/30"
            }`}
          >
            {tab.label}
          </Link>
        ))}
      </div>

      {loading ? (
        <div className="glass rounded-2xl p-12 text-center">
          <div className="w-8 h-8 border-2 border-purple-500 border-t-transparent rounded-full animate-spin mx-auto mb-4" />
          <p className="text-gray-500">Loading your dashboard...</p>
        </div>
      ) : (
        <div className="space-y-6">
          {/* Stats Grid */}
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <StatCard
              title="Total Tasks"
              value={tasksStats.total}
              icon={<CheckSquare className="h-6 w-6 text-white" />}
              gradient="from-slate-500 to-slate-600"
              href="/modules/tasks"
            />
            <StatCard
              title="Pending"
              value={tasksStats.pending}
              icon={<Clock className="h-6 w-6 text-white" />}
              gradient="from-amber-500 to-orange-500"
              href="/modules/tasks"
            />
            <StatCard
              title="In Progress"
              value={tasksStats.in_progress}
              icon={<TrendingUp className="h-6 w-6 text-white" />}
              gradient="from-blue-500 to-indigo-500"
              href="/modules/tasks"
            />
            <StatCard
              title="Completed"
              value={tasksStats.completed}
              icon={<CheckSquare className="h-6 w-6 text-white" />}
              gradient="from-emerald-500 to-teal-500"
              href="/modules/tasks"
              trend={{ value: 15, isPositive: true }}
            />
            <StatCard
              title="Overdue"
              value={tasksStats.overdue}
              icon={<AlertCircle className="h-6 w-6 text-white" />}
              gradient="from-rose-500 to-pink-500"
              href="/modules/tasks"
            />
          </div>

          {/* Main Content */}
          <div className="grid gap-4 lg:grid-cols-3">
            {/* Left Column - Tasks & Progress */}
            <div className="lg:col-span-2 space-y-4">
              {/* Task Progress Chart */}
              <div className="glass rounded-2xl p-5">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-lg font-semibold text-gray-900">My Task Progress</h3>
                  <div className="flex items-center gap-4 text-xs">
                    <span className="flex items-center gap-1 text-gray-500">
                      <span className="w-2 h-2 rounded-full bg-emerald-500" /> Completed
                    </span>
                    <span className="flex items-center gap-1 text-gray-500">
                      <span className="w-2 h-2 rounded-full bg-purple-500" /> Assigned
                    </span>
                  </div>
                </div>
                {trendsLoading ? (
                  <div className="h-[200px] flex items-center justify-center">
                    <div className="w-6 h-6 border-2 border-purple-500 border-t-transparent rounded-full animate-spin" />
                  </div>
                ) : taskTrendData.length > 0 ? (
                  <TaskTrendChart data={taskTrendData} />
                ) : (
                  <div className="h-[200px] flex items-center justify-center text-gray-400">
                    No task data available
                  </div>
                )}
                <div className="mt-4 pt-4 border-t border-gray-100 grid grid-cols-3 gap-4 text-center">
                  <div>
                    <p className="text-gray-500 text-xs">This Week</p>
                    <p className="text-emerald-600 font-bold text-lg">{completedThisWeek}</p>
                  </div>
                  <div>
                    <p className="text-gray-500 text-xs">Completion Rate</p>
                    <p className="text-blue-600 font-bold text-lg">{completionRate}%</p>
                  </div>
                  <div>
                    <p className="text-gray-500 text-xs">Total Assigned</p>
                    <p className="text-purple-600 font-bold text-lg flex items-center justify-center gap-1">
                      {tasksStats.total}
                    </p>
                  </div>
                </div>
              </div>

              {/* Recent Tasks */}
              <RecentTasksWidget 
                tasks={recentTasksData} 
                title="My Tasks"
              />

              {/* Upcoming Deadlines */}
              <UpcomingDeadlinesWidget deadlines={deadlinesData} />
            </div>

            {/* Right Column */}
            <div className="space-y-4">
              {/* Module Access */}
              <ModuleAccessWidget modules={modulesData} />

              {/* Activity Feed */}
              <ActivityFeedWidget activities={activityData} />

              {/* Quick Actions */}
              <QuickActionsWidget actions={quickActions} />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
