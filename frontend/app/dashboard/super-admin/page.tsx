"use client";

import { useSessionStore } from "@/lib/store";
import Link from "next/link";
import { api } from "@/lib/api";
import { 
  ShieldCheck, Users, Building2, CreditCard, Settings, 
  TrendingUp, DollarSign, Bell, LogOut, Mail
} from "lucide-react";

// Charts
import { TenantGrowthChart } from "@/components/dashboard/charts/TenantGrowthChart";
import { RevenueChart } from "@/components/dashboard/charts/RevenueChart";
import { ModulePopularityChart } from "@/components/dashboard/charts/ModulePopularityChart";

// Widgets
import { StatCard } from "@/components/dashboard/widgets/StatCard";
import { RecentTenantsWidget } from "@/components/dashboard/widgets/RecentTenantsWidget";
import { SystemHealthWidget } from "@/components/dashboard/widgets/SystemHealthWidget";
import { QuickActionsWidget } from "@/components/dashboard/widgets/QuickActionsWidget";
import { ActivityFeedWidget } from "@/components/dashboard/widgets/ActivityFeedWidget";

// API Hooks
import {
  useAdminStats,
  useAdminGrowthData,
  useAdminRevenueData,
  useAdminModulePopularity,
  useAdminRecentTenants,
  useAdminSystemHealth,
  useAdminActivity,
} from "@/hooks/useDashboard";

export default function SuperAdminDashboard() {
  const { user, clearSession } = useSessionStore();

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
  const { data: stats, isLoading: statsLoading } = useAdminStats();
  const { data: growthData, isLoading: growthLoading } = useAdminGrowthData();
  const { data: revenueData, isLoading: revenueLoading } = useAdminRevenueData();
  const { data: modulePopularity, isLoading: popularityLoading } = useAdminModulePopularity();
  const { data: recentTenants, isLoading: tenantsLoading } = useAdminRecentTenants(5);
  const { data: systemHealth, isLoading: healthLoading } = useAdminSystemHealth();
  const { data: activity, isLoading: activityLoading } = useAdminActivity(10);

  const loading = statsLoading;

  if (!user?.is_super_admin) {
    return (
      <div className="glass rounded-2xl p-12 text-center">
        <ShieldCheck className="h-12 w-12 text-rose-500 mx-auto mb-4" />
        <p className="text-rose-600 text-lg font-medium">Access Denied</p>
        <p className="text-gray-500 mt-2">Super Admin privileges required</p>
      </div>
    );
  }

  // Transform API data for components
  const growthChartData = growthData || [];
  const revenueChartData = revenueData || [];
  const modulePopularityData = modulePopularity || [];

  const recentTenantsData = (recentTenants || []).map(t => ({
    id: t.id,
    name: t.name,
    slug: t.slug,
    createdAt: t.created_at,
    userCount: t.user_count,
    status: t.status,
  }));

  const systemServicesData = (systemHealth || []).map(s => ({
    name: s.name,
    status: s.status,
    latency: s.latency,
    icon: s.icon,
  }));

  const activityData = (activity || []).map(a => ({
    id: a.id,
    type: a.type as "task" | "user" | "booking" | "ai" | "document",
    title: a.title,
    description: a.description,
    timestamp: a.timestamp,
    user: a.user || undefined,
  }));

  // Calculate growth metrics
  const newTenants30d = growthChartData.length >= 2 
    ? (growthChartData[growthChartData.length - 1]?.tenants || 0) - (growthChartData[0]?.tenants || 0)
    : 0;
  const newUsers30d = growthChartData.length >= 2
    ? (growthChartData[growthChartData.length - 1]?.users || 0) - (growthChartData[0]?.users || 0)
    : 0;

  // Calculate revenue growth
  const currentRevenue = revenueChartData[revenueChartData.length - 1]?.revenue || 0;
  const previousRevenue = revenueChartData[revenueChartData.length - 2]?.revenue || 0;
  const revenueGrowth = previousRevenue > 0 
    ? Math.round(((currentRevenue - previousRevenue) / previousRevenue) * 100)
    : 0;

  const quickActions = [
    { label: "Manage Tenants", href: "/admin/tenants", icon: Building2, gradient: "btn-gradient-purple" },
    { label: "User Management", href: "/admin/users", icon: Users, gradient: "btn-gradient-blue" },
    { label: "Billing & Plans", href: "/billing", icon: CreditCard, gradient: "btn-gradient-green" },
    { label: "Platform Settings", href: "/admin/task-templates", icon: Settings, gradient: "btn-gradient-orange" },
  ];

  // Tabs that match the white navigation bar routes for Super Admin
  const tabs = [
    { label: "Overview", href: "/dashboard", active: true },
    { label: "Tenants", href: "/admin/tenants", active: false },
    { label: "Billing", href: "/billing", active: false },
  ];

  return (
    <div className="min-h-screen">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <p className="text-gray-500 text-sm mb-1">Platform Administration</p>
          <h1 className="text-3xl font-bold text-gray-900">Super Admin Dashboard</h1>
          <p className="text-gray-500 mt-1">Monitor platform health, manage tenants, and track growth</p>
        </div>
        <div className="flex items-center gap-3">
          {/* Email */}
          <div className="hidden sm:flex items-center gap-2 px-3 py-2 rounded-xl bg-white/80 shadow-sm">
            <Mail className="h-4 w-4 text-purple-500" />
            <span className="text-sm text-gray-600 font-medium">{user?.email || "admin@example.com"}</span>
          </div>
          <div className="flex items-center gap-2 px-4 py-2 rounded-full bg-purple-100 border border-purple-200">
            <ShieldCheck className="h-4 w-4 text-purple-600" />
            <span className="text-purple-700 text-sm font-medium">Super Admin</span>
          </div>
          <button className="w-10 h-10 rounded-xl bg-white/80 shadow-sm flex items-center justify-center hover:bg-white transition relative">
            <Bell className="h-5 w-5 text-gray-600" />
            <span className="absolute -top-1 -right-1 w-4 h-4 bg-amber-500 rounded-full text-[10px] text-white flex items-center justify-center">
              {recentTenantsData.length}
            </span>
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
          <p className="text-gray-500">Loading platform data...</p>
        </div>
      ) : (
        <div className="space-y-6">
          {/* Stats Grid */}
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
              title="Total Tenants"
              value={stats?.total_tenants || 0}
              icon={<Building2 className="h-6 w-6 text-white" />}
              gradient="from-purple-500 to-violet-500"
              href="/admin/tenants"
              trend={{ value: 12, isPositive: true }}
            />
            <StatCard
              title="Total Users"
              value={stats?.total_users || 0}
              icon={<Users className="h-6 w-6 text-white" />}
              gradient="from-blue-500 to-cyan-500"
              href="/admin/users"
              trend={{ value: 8, isPositive: true }}
            />
            <StatCard
              title="Active Subscriptions"
              value={stats?.active_subscriptions || 0}
              icon={<CreditCard className="h-6 w-6 text-white" />}
              gradient="from-emerald-500 to-teal-500"
              href="/billing"
              trend={{ value: 5, isPositive: true }}
            />
            <StatCard
              title="Monthly Revenue"
              value={`$${(stats?.total_revenue || 0).toLocaleString()}`}
              icon={<DollarSign className="h-6 w-6 text-white" />}
              gradient="from-amber-500 to-orange-500"
              href="/billing"
              trend={{ value: revenueGrowth, isPositive: revenueGrowth >= 0 }}
            />
          </div>

          {/* Charts Row */}
          <div className="grid gap-4 lg:grid-cols-2">
            {/* Growth Chart */}
            <div className="glass rounded-2xl p-5">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-gray-900">Platform Growth</h3>
                <div className="flex items-center gap-4 text-xs">
                  <span className="flex items-center gap-1 text-gray-500">
                    <span className="w-2 h-2 rounded-full bg-purple-500" /> Tenants
                  </span>
                  <span className="flex items-center gap-1 text-gray-500">
                    <span className="w-2 h-2 rounded-full bg-cyan-500" /> Users
                  </span>
                </div>
              </div>
              {growthLoading ? (
                <div className="h-[200px] flex items-center justify-center">
                  <div className="w-6 h-6 border-2 border-purple-500 border-t-transparent rounded-full animate-spin" />
                </div>
              ) : growthChartData.length > 0 ? (
                <TenantGrowthChart data={growthChartData} />
              ) : (
                <div className="h-[200px] flex items-center justify-center text-gray-400">
                  No growth data available
                </div>
              )}
              <div className="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 gap-4">
                <div>
                  <p className="text-gray-500 text-xs">New Tenants (6mo)</p>
                  <p className="text-purple-600 font-semibold">+{newTenants30d}</p>
                </div>
                <div>
                  <p className="text-gray-500 text-xs">New Users (6mo)</p>
                  <p className="text-cyan-600 font-semibold">+{newUsers30d}</p>
                </div>
              </div>
            </div>

            {/* Revenue Chart */}
            <div className="glass rounded-2xl p-5">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-gray-900">Revenue Trend</h3>
                <span className={`text-sm font-medium flex items-center gap-1 ${revenueGrowth >= 0 ? 'text-emerald-600' : 'text-rose-600'}`}>
                  <TrendingUp className="h-4 w-4" /> {revenueGrowth >= 0 ? '+' : ''}{revenueGrowth}% vs last month
                </span>
              </div>
              {revenueLoading ? (
                <div className="h-[200px] flex items-center justify-center">
                  <div className="w-6 h-6 border-2 border-purple-500 border-t-transparent rounded-full animate-spin" />
                </div>
              ) : revenueChartData.length > 0 ? (
                <RevenueChart data={revenueChartData} />
              ) : (
                <div className="h-[200px] flex items-center justify-center text-gray-400">
                  No revenue data available
                </div>
              )}
              <div className="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 gap-4">
                <div>
                  <p className="text-gray-500 text-xs">MRR</p>
                  <p className="text-blue-600 font-semibold">${(stats?.total_revenue || 0).toLocaleString()}</p>
                </div>
                <div>
                  <p className="text-gray-500 text-xs">ARR</p>
                  <p className="text-emerald-600 font-semibold">${((stats?.total_revenue || 0) * 12).toLocaleString()}</p>
                </div>
              </div>
            </div>
          </div>

          {/* Module Popularity & System Health */}
          <div className="grid gap-4 lg:grid-cols-2">
            {/* Module Popularity */}
            <div className="glass rounded-2xl p-5">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-gray-900">Module Subscriptions</h3>
                <span className="text-gray-500 text-sm">Active subscriptions by module</span>
              </div>
              {popularityLoading ? (
                <div className="h-[140px] flex items-center justify-center">
                  <div className="w-6 h-6 border-2 border-purple-500 border-t-transparent rounded-full animate-spin" />
                </div>
              ) : modulePopularityData.length > 0 ? (
                <ModulePopularityChart data={modulePopularityData} />
              ) : (
                <div className="h-[140px] flex items-center justify-center text-gray-400">
                  No module data available
                </div>
              )}
            </div>

            {/* System Health */}
            <SystemHealthWidget services={systemServicesData} />
          </div>

          {/* Bottom Row */}
          <div className="grid gap-4 lg:grid-cols-3">
            {/* Recent Tenants */}
            <div className="lg:col-span-2">
              <RecentTenantsWidget tenants={recentTenantsData} />
            </div>

            {/* Quick Actions */}
            <QuickActionsWidget actions={quickActions} />
          </div>

          {/* Activity Feed */}
          <ActivityFeedWidget activities={activityData} />
        </div>
      )}
    </div>
  );
}
