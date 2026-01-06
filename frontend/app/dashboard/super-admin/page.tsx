"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { useSessionStore } from "@/lib/store";
import Link from "next/link";
import { ShieldCheck, Users, Building2, CreditCard, Settings, TrendingUp } from "lucide-react";

type Stats = {
  total_tenants: number;
  total_users: number;
  active_subscriptions: number;
  total_revenue: number;
};

export default function SuperAdminDashboard() {
  const { user } = useSessionStore();
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchStats = async () => {
      try {
        const res = await api.get<Stats>("/admin/stats");
        setStats(res.data);
      } catch (err: any) {
        // If endpoint doesn't exist yet, use fallback data
        if (err?.response?.status === 404) {
          setStats({
            total_tenants: 0,
            total_users: 0,
            active_subscriptions: 0,
            total_revenue: 0,
          });
        } else {
          console.error("Failed to fetch stats:", err);
        }
      } finally {
        setLoading(false);
      }
    };
    fetchStats();
  }, []);

  if (!user?.is_super_admin) {
    return (
      <div className="glass rounded-2xl p-6 text-center">
        <p className="text-red-400">Access denied. Super Admin only.</p>
      </div>
    );
  }

  return (
    <div className="space-y-8">
      <header className="glass rounded-2xl px-6 py-5 shadow-xl">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Super Admin</p>
            <h1 className="text-3xl font-semibold text-white">Platform Overview</h1>
            <p className="text-sm text-gray-200/80">Manage tenants, users, and platform settings.</p>
          </div>
          <ShieldCheck className="h-8 w-8 text-emerald-300" />
        </div>
      </header>

      {loading ? (
        <div className="glass rounded-2xl p-6 text-center">
          <p className="text-gray-200">Loading statistics...</p>
        </div>
      ) : (
        <>
          <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Link
              href="/admin/tenants"
              className="glass rounded-xl p-5 shadow transition hover:-translate-y-1 hover:shadow-2xl"
            >
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-200/80">Total Tenants</p>
                  <p className="text-2xl font-bold text-white">{stats?.total_tenants || 0}</p>
                </div>
                <Building2 className="h-8 w-8 text-cyan-300" />
              </div>
            </Link>

            <Link
              href="/admin/users"
              className="glass rounded-xl p-5 shadow transition hover:-translate-y-1 hover:shadow-2xl"
            >
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-200/80">Total Users</p>
                  <p className="text-2xl font-bold text-white">{stats?.total_users || 0}</p>
                </div>
                <Users className="h-8 w-8 text-blue-300" />
              </div>
            </Link>

            <div className="glass rounded-xl p-5 shadow">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-200/80">Active Subscriptions</p>
                  <p className="text-2xl font-bold text-white">{stats?.active_subscriptions || 0}</p>
                </div>
                <CreditCard className="h-8 w-8 text-emerald-300" />
              </div>
            </div>

            <div className="glass rounded-xl p-5 shadow">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-200/80">Total Revenue</p>
                  <p className="text-2xl font-bold text-white">${stats?.total_revenue?.toLocaleString() || 0}</p>
                </div>
                <TrendingUp className="h-8 w-8 text-yellow-300" />
              </div>
            </div>
          </section>

          <section className="grid gap-4 sm:grid-cols-2">
            <Link
              href="/admin/tenants"
              className="glass rounded-2xl p-6 shadow-xl transition hover:-translate-y-1"
            >
              <div className="flex items-center gap-3">
                <Building2 className="h-6 w-6 text-cyan-300" />
                <h2 className="text-lg font-semibold text-white">Manage Tenants</h2>
              </div>
              <p className="mt-2 text-sm text-gray-200/80">
                View and manage all tenant accounts, subscriptions, and entitlements.
              </p>
            </Link>

            <Link
              href="/billing"
              className="glass rounded-2xl p-6 shadow-xl transition hover:-translate-y-1"
            >
              <div className="flex items-center gap-3">
                <CreditCard className="h-6 w-6 text-emerald-300" />
                <h2 className="text-lg font-semibold text-white">Billing & Plans</h2>
              </div>
              <p className="mt-2 text-sm text-gray-200/80">
                Configure pricing plans, manage subscriptions, and view revenue.
              </p>
            </Link>
          </section>
        </>
      )}
    </div>
  );
}

