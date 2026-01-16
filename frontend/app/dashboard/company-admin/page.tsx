"use client";

import { useState, useRef, useEffect } from "react";
import { useSessionStore } from "@/lib/store";
import Link from "next/link";
import { api } from "@/lib/api";
import { 
  Users, CheckSquare, CreditCard, LayoutDashboard, 
  Plus, Bell, Bot, Send, ArrowRight, Sparkles,
  TrendingUp, Calendar, LogOut, Mail, Loader2, X,
  MessageSquare
} from "lucide-react";

// Charts
import { ModuleUsageChart } from "@/components/dashboard/charts/ModuleUsageChart";
import { TaskTrendChart } from "@/components/dashboard/charts/TaskTrendChart";

// Widgets
import { StatCard } from "@/components/dashboard/widgets/StatCard";
import { RecentTasksWidget } from "@/components/dashboard/widgets/RecentTasksWidget";
import { TeamOverviewWidget } from "@/components/dashboard/widgets/TeamOverviewWidget";
import { ModuleAccessWidget } from "@/components/dashboard/widgets/ModuleAccessWidget";
import { UpcomingDeadlinesWidget } from "@/components/dashboard/widgets/UpcomingDeadlinesWidget";
import { ActivityFeedWidget } from "@/components/dashboard/widgets/ActivityFeedWidget";
import { QuickActionsWidget } from "@/components/dashboard/widgets/QuickActionsWidget";
import { AIInsightsWidget } from "@/components/dashboard/widgets/AIInsightsWidget";

// API Hooks
import {
  useCompanyStats,
  useCompanyModuleUsage,
  useCompanyTaskTrends,
  useCompanyTeamOverview,
  useCompanyRecentTasks,
  useCompanyUpcomingDeadlines,
  useCompanyActivity,
  useCompanyModules,
  sendAIChatMessage,
  AIChatMessage,
} from "@/hooks/useDashboard";

export default function CompanyAdminDashboard() {
  const { user, entitlements, clearSession } = useSessionStore();
  const [aiPrompt, setAiPrompt] = useState("");
  const [aiLoading, setAiLoading] = useState(false);
  const [aiResponse, setAiResponse] = useState<string | null>(null);
  const [showAiChat, setShowAiChat] = useState(false);
  const [chatHistory, setChatHistory] = useState<AIChatMessage[]>([
    { role: "assistant", content: "Hello! I'm your AI business assistant. I can help you manage tasks, check your data, and provide insights. How can I help you today?" }
  ]);
  const chatEndRef = useRef<HTMLDivElement>(null);

  // Scroll to bottom when chat updates
  useEffect(() => {
    if (chatEndRef.current) {
      chatEndRef.current.scrollIntoView({ behavior: "smooth" });
    }
  }, [chatHistory]);

  const handleAiSubmit = async () => {
    if (!aiPrompt.trim() || aiLoading) return;

    const userMessage: AIChatMessage = { role: "user", content: aiPrompt };
    const newHistory = [...chatHistory, userMessage];
    setChatHistory(newHistory);
    setAiPrompt("");
    setAiLoading(true);
    setShowAiChat(true);

    try {
      const reply = await sendAIChatMessage(newHistory);
      setChatHistory([...newHistory, { role: "assistant", content: reply }]);
      setAiResponse(reply);
    } catch (err: any) {
      const errorMsg = err.response?.status === 403 
        ? "AI access not enabled. Please check your subscription."
        : "Sorry, I encountered an error. Please try again.";
      setChatHistory([...newHistory, { role: "assistant", content: errorMsg }]);
    } finally {
      setAiLoading(false);
    }
  };

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      handleAiSubmit();
    }
  };

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
  const { data: stats, isLoading: statsLoading } = useCompanyStats();
  const { data: moduleUsage, isLoading: moduleUsageLoading } = useCompanyModuleUsage();
  const { data: taskTrends, isLoading: taskTrendsLoading } = useCompanyTaskTrends();
  const { data: teamMembers, isLoading: teamLoading } = useCompanyTeamOverview(5);
  const { data: recentTasks, isLoading: tasksLoading } = useCompanyRecentTasks(5);
  const { data: deadlines, isLoading: deadlinesLoading } = useCompanyUpcomingDeadlines(5);
  const { data: activity, isLoading: activityLoading } = useCompanyActivity(10);
  const { data: modules, isLoading: modulesLoading } = useCompanyModules();

  const loading = statsLoading;

  // Transform data for components
  const moduleUsageData = moduleUsage || [];
  const taskTrendData = taskTrends || [];
  
  const recentTasksData = (recentTasks || []).map(task => ({
    id: task.id,
    title: task.title,
    status: task.status,
    statusColor: task.status_color,
    project: task.project || undefined,
    dueDate: task.due_date || undefined,
    isOverdue: task.is_overdue,
  }));

  const teamMembersData = (teamMembers || []).map(member => ({
    id: member.id,
    name: member.name,
    email: member.email,
    role: member.role,
    tasksCompleted: member.tasks_completed,
    lastActive: member.last_active || undefined,
  }));

  const modulesData = (modules || []).map(m => ({
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

  const quickActions = [
    { label: "Create New Task", href: "/modules/tasks", icon: CheckSquare, gradient: "btn-gradient-purple" },
    { label: "Invite Team Member", href: "/admin/users", icon: Users, gradient: "btn-gradient-blue" },
    { label: "View Reports", href: "/modules/tasks", icon: TrendingUp, gradient: "btn-gradient-cyan" },
    { label: "Manage Subscription", href: "/billing", icon: CreditCard, gradient: "btn-gradient-orange" },
  ];

  // Tabs that match the white navigation bar routes
  const tabs = [
    { label: "Overview", href: "/dashboard", active: true },
    { label: "Tasks", href: "/modules/tasks", active: false },
    { label: "Users", href: "/admin/users", active: false },
    { label: "Settings", href: "/onboarding", active: false },
  ];

  // Calculate stats
  const totalTasksThisWeek = taskTrendData.reduce((sum, d) => sum + d.completed + d.created, 0);
  const completedThisWeek = taskTrendData.reduce((sum, d) => sum + d.completed, 0);
  const totalActions = moduleUsageData.reduce((s, m) => s + m.usage, 0);
  const completionRate = totalTasksThisWeek > 0 ? Math.round((completedThisWeek / totalTasksThisWeek) * 100) : 0;

  return (
    <div className="min-h-screen">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">
            {stats?.company_name || "Company"} Dashboard
          </h1>
        </div>
        <div className="flex items-center gap-3">
          {/* Email */}
          <div className="hidden sm:flex items-center gap-2 px-3 py-2 rounded-xl bg-white/80 shadow-sm">
            <Mail className="h-4 w-4 text-purple-500" />
            <span className="text-sm text-gray-600 font-medium">{user?.email || "user@example.com"}</span>
          </div>
          <button className="w-10 h-10 rounded-xl bg-white/80 shadow-sm flex items-center justify-center hover:bg-white transition">
            <Bell className="h-5 w-5 text-gray-600" />
          </button>
          <div className="w-10 h-10 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center text-white font-semibold">
            {user?.email?.charAt(0).toUpperCase() || "A"}
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
          <p className="text-gray-500">Loading dashboard...</p>
        </div>
      ) : (
        <div className="space-y-6">
          {/* AI Assistant */}
          <div className="glass rounded-2xl p-5 relative">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 rounded-xl bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center shadow-lg shadow-purple-500/20">
                <Bot className="h-5 w-5 text-white" />
              </div>
              <div>
                <h3 className="text-gray-900 font-semibold">Ask Axiom9 AI</h3>
                <p className="text-gray-500 text-xs">Get insights and recommendations for your business</p>
              </div>
              {entitlements.some(e => e.module_code === 'ai' && e.enabled) && (
                <span className="ml-auto flex items-center gap-1 text-xs text-purple-600 bg-purple-100 px-2 py-1 rounded-full">
                  <Sparkles className="h-3 w-3" /> AI Enabled
                </span>
              )}
              {showAiChat && (
                <button
                  onClick={() => setShowAiChat(false)}
                  className="ml-2 p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition"
                >
                  <X className="h-4 w-4" />
                </button>
              )}
            </div>
            
            {/* Chat History Panel */}
            {showAiChat && (
              <div className="mb-4 max-h-64 overflow-y-auto rounded-xl bg-gray-50 border border-gray-100 p-3 space-y-3">
                {chatHistory.map((msg, idx) => (
                  <div 
                    key={idx} 
                    className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}
                  >
                    <div 
                      className={`max-w-[85%] px-4 py-2.5 rounded-2xl text-sm ${
                        msg.role === 'user' 
                          ? 'bg-gradient-to-r from-purple-500 to-pink-500 text-white' 
                          : 'bg-white border border-gray-200 text-gray-700'
                      }`}
                    >
                      {msg.content}
                    </div>
                  </div>
                ))}
                {aiLoading && (
                  <div className="flex justify-start">
                    <div className="bg-white border border-gray-200 px-4 py-2.5 rounded-2xl flex items-center gap-2">
                      <Loader2 className="h-4 w-4 animate-spin text-purple-500" />
                      <span className="text-sm text-gray-500">Thinking...</span>
                    </div>
                  </div>
                )}
                <div ref={chatEndRef} />
              </div>
            )}
            
            <div className="flex gap-3">
              <input
                type="text"
                value={aiPrompt}
                onChange={(e) => setAiPrompt(e.target.value)}
                onKeyDown={handleKeyPress}
                placeholder="What should we focus on today? Ask about tasks, team performance, or insights..."
                className="flex-1 px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100 transition"
                disabled={aiLoading}
              />
              <button 
                onClick={handleAiSubmit}
                disabled={aiLoading || !aiPrompt.trim()}
                className="px-6 py-3 btn-gradient-purple rounded-xl font-medium flex items-center gap-2 hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {aiLoading ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <Send className="h-4 w-4" />
                )}
                Ask
              </button>
            </div>
            
            {/* Quick suggestions */}
            {!showAiChat && (
              <div className="flex flex-wrap gap-2 mt-3">
                {[
                  "What tasks are overdue?",
                  "Show my team's progress",
                  "Create a new task"
                ].map((suggestion) => (
                  <button
                    key={suggestion}
                    onClick={() => {
                      setAiPrompt(suggestion);
                    }}
                    className="text-xs px-3 py-1.5 rounded-full bg-purple-50 text-purple-600 hover:bg-purple-100 transition"
                  >
                    {suggestion}
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* Stats Grid */}
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
              title="Team Members"
              value={stats?.total_users || 0}
              icon={<Users className="h-6 w-6 text-white" />}
              gradient="from-blue-500 to-cyan-500"
              href="/admin/users"
              trend={{ value: 12, isPositive: true }}
            />
            <StatCard
              title="Active Modules"
              value={stats?.enabled_modules || 0}
              icon={<LayoutDashboard className="h-6 w-6 text-white" />}
              gradient="from-purple-500 to-pink-500"
            />
            <StatCard
              title="Tasks This Week"
              value={stats?.tasks_this_week || 0}
              icon={<CheckSquare className="h-6 w-6 text-white" />}
              gradient="from-emerald-500 to-teal-500"
              href="/modules/tasks"
              trend={{ value: 8, isPositive: true }}
            />
            <StatCard
              title="Subscription"
              value={stats?.subscription_status === 'active' ? 'Active' : 'Inactive'}
              icon={<CreditCard className="h-6 w-6 text-white" />}
              gradient="from-orange-500 to-amber-500"
              href="/billing"
            />
          </div>

          {/* Charts Row */}
          <div className="grid gap-4 lg:grid-cols-2">
            {/* Module Usage */}
            <div className="glass rounded-2xl p-5">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-gray-900">Module Usage</h3>
                <span className="text-gray-500 text-sm">This week</span>
              </div>
              {moduleUsageLoading ? (
                <div className="h-[200px] flex items-center justify-center">
                  <div className="w-6 h-6 border-2 border-purple-500 border-t-transparent rounded-full animate-spin" />
                </div>
              ) : moduleUsageData.length > 0 ? (
                <ModuleUsageChart data={moduleUsageData} />
              ) : (
                <div className="h-[200px] flex items-center justify-center text-gray-400">
                  No usage data available
                </div>
              )}
              <div className="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
                <span className="text-gray-500 text-sm">Total actions: {totalActions}</span>
                <Link href="/modules" className="text-purple-600 text-sm hover:text-purple-700 flex items-center gap-1">
                  View Details <ArrowRight className="h-3 w-3" />
                </Link>
              </div>
            </div>

            {/* Task Trends */}
            <div className="glass rounded-2xl p-5">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-gray-900">Task Trends</h3>
                <div className="flex items-center gap-4 text-xs text-gray-500">
                  <span className="flex items-center gap-1">
                    <span className="w-2 h-2 rounded-full bg-emerald-500" /> Completed
                  </span>
                  <span className="flex items-center gap-1">
                    <span className="w-2 h-2 rounded-full bg-purple-500" /> Created
                  </span>
                </div>
              </div>
              {taskTrendsLoading ? (
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
              <div className="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 gap-4">
                <div>
                  <p className="text-gray-500 text-xs">Completion Rate</p>
                  <p className="text-emerald-600 font-semibold">{completionRate}%</p>
                </div>
                <div>
                  <p className="text-gray-500 text-xs">Completed This Week</p>
                  <p className="text-blue-600 font-semibold">{completedThisWeek}</p>
                </div>
              </div>
            </div>
          </div>

          {/* AI Insights Section */}
          <div className="grid gap-4 lg:grid-cols-2">
            <AIInsightsWidget />
            <div className="glass rounded-2xl p-5">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-gray-900">Quick Actions</h3>
              </div>
              <div className="grid grid-cols-2 gap-3">
                {quickActions.map((action) => (
                  <Link
                    key={action.label}
                    href={action.href}
                    className={`flex items-center gap-3 p-4 rounded-xl ${action.gradient} text-white hover:opacity-90 transition shadow-sm`}
                  >
                    <action.icon className="h-5 w-5" />
                    <span className="text-sm font-medium">{action.label}</span>
                  </Link>
                ))}
              </div>
            </div>
          </div>

          {/* Main Content Grid */}
          <div className="grid gap-4 lg:grid-cols-3">
            {/* Left Column - Tasks & Deadlines */}
            <div className="lg:col-span-2 space-y-4">
              <RecentTasksWidget 
                tasks={recentTasksData} 
                title="Recent Tasks"
              />
              <UpcomingDeadlinesWidget deadlines={deadlinesData} />
            </div>

            {/* Right Column - Team & Modules */}
            <div className="space-y-4">
              <TeamOverviewWidget 
                members={teamMembersData} 
                totalCount={stats?.total_users} 
              />
              <ModuleAccessWidget modules={modulesData} />
            </div>
          </div>

          {/* Bottom Row - Activity Feed */}
          <ActivityFeedWidget activities={activityData} />
        </div>
      )}
    </div>
  );
}
