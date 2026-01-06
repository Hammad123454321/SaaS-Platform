"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { useSessionStore } from "@/lib/store";
import Link from "next/link";
import { Users, Settings, CreditCard, Building2, Shield } from "lucide-react";

type CompanyStats = {
  total_users: number;
  enabled_modules: number;
  subscription_status: string;
  company_name: string;
};

export default function CompanyAdminDashboard() {
  const { user, entitlements } = useSessionStore();
  const [stats, setStats] = useState<CompanyStats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchStats = async () => {
      try {
        const res = await api.get<CompanyStats>("/company/stats");
        setStats(res.data);
      } catch (err: any) {
        // If endpoint doesn't exist yet, use fallback data
        if (err?.response?.status === 404) {
          setStats({
            total_users: 0,
            enabled_modules: enabledModules.length,
            subscription_status: "inactive",
            company_name: "Your Company",
          });
        } else {
          console.error("Failed to fetch company stats:", err);
        }
      } finally {
        setLoading(false);
      }
    };
    fetchStats();
  }, [enabledModules.length]);

  const enabledModules = entitlements.filter((e) => e.enabled);

  return (
    <div className="space-y-8">
      <header className="glass rounded-2xl px-6 py-5 shadow-xl">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Company Admin</p>
            <h1 className="text-3xl font-semibold text-white">
              {stats?.company_name || "Company"} Dashboard
            </h1>
            <p className="text-sm text-gray-200/80">Manage your team, modules, and settings.</p>
          </div>
          <Shield className="h-8 w-8 text-blue-300" />
        </div>
      </header>

      {loading ? (
        <div className="glass rounded-2xl p-6 text-center">
          <p className="text-gray-200">Loading...</p>
        </div>
      ) : (
        <>
          <section className="grid gap-4 sm:grid-cols-3">
            <div className="glass rounded-xl p-5 shadow">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-200/80">Team Members</p>
                  <p className="text-2xl font-bold text-white">{stats?.total_users || 0}</p>
                </div>
                <Users className="h-8 w-8 text-cyan-300" />
              </div>
            </div>

            <div className="glass rounded-xl p-5 shadow">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-200/80">Active Modules</p>
                  <p className="text-2xl font-bold text-white">{enabledModules.length}</p>
                </div>
                <Settings className="h-8 w-8 text-emerald-300" />
              </div>
            </div>

            <div className="glass rounded-xl p-5 shadow">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-200/80">Subscription</p>
                  <p className="text-lg font-semibold text-white capitalize">
                    {stats?.subscription_status || "Inactive"}
                  </p>
                </div>
                <CreditCard className="h-8 w-8 text-yellow-300" />
              </div>
            </div>
          </section>

          <section className="grid gap-4 sm:grid-cols-2">
            <Link
              href="/admin/users"
              className="glass rounded-2xl p-6 shadow-xl transition hover:-translate-y-1"
            >
              <div className="flex items-center gap-3">
                <Users className="h-6 w-6 text-cyan-300" />
                <h2 className="text-lg font-semibold text-white">Manage Users</h2>
              </div>
              <p className="mt-2 text-sm text-gray-200/80">
                Add team members, assign roles, and manage permissions.
              </p>
            </Link>

            <Link
              href="/onboarding"
              className="glass rounded-2xl p-6 shadow-xl transition hover:-translate-y-1"
            >
              <div className="flex items-center gap-3">
                <Settings className="h-6 w-6 text-emerald-300" />
                <h2 className="text-lg font-semibold text-white">Company Settings</h2>
              </div>
              <p className="mt-2 text-sm text-gray-200/80">
                Update company info, modules, and branding.
              </p>
            </Link>
          </section>
        </>
      )}
    </div>
  );
}

