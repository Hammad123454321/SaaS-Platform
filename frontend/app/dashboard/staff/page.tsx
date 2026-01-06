"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { useSessionStore } from "@/lib/store";
import Link from "next/link";
import { LayoutDashboard, Wand2, CheckSquare } from "lucide-react";

type Entitlement = {
  module_code: string;
  enabled: boolean;
  seats: number;
  ai_access: boolean;
};

const moduleLabels: Record<string, string> = {
  crm: "CRM",
  hrm: "HRM",
  pos: "POS",
  tasks: "Tasks",
  booking: "Booking",
  landing: "Landing Builder",
  ai: "AI",
};

export default function StaffDashboard() {
  const { entitlements, setEntitlements } = useSessionStore();
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const fetchEntitlements = async () => {
      setLoading(true);
      try {
        const res = await api.get<Entitlement[]>("/entitlements");
        setEntitlements(res.data);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchEntitlements();
  }, [setEntitlements]);

  const enabledModules = entitlements.filter((e) => e.enabled);

  return (
    <div className="space-y-8">
      <header className="glass rounded-2xl px-6 py-5 shadow-xl">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Workspace</p>
            <h1 className="text-3xl font-semibold text-white">My Dashboard</h1>
            <p className="text-sm text-gray-200/80">
              Access your enabled modules and quick actions.
            </p>
          </div>
          <div className="flex items-center gap-3 text-sm text-gray-100">
            <CheckSquare className="h-5 w-5 text-emerald-300" />
            Staff Access
          </div>
        </div>
      </header>

      <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {enabledModules.length === 0 && !loading && (
          <div className="glass col-span-full flex items-center justify-between rounded-xl p-4">
            <div>
              <p className="font-semibold text-white">No modules enabled</p>
              <p className="text-sm text-gray-200/80">
                Contact your administrator to enable modules for your account.
              </p>
            </div>
            <LayoutDashboard className="h-6 w-6 text-cyan-200" />
          </div>
        )}
        {enabledModules.map((m) => (
          <Link
            key={m.module_code}
            href={`/modules/${m.module_code}`}
            className="glass rounded-xl p-4 shadow transition hover:-translate-y-1 hover:shadow-2xl"
          >
            <div className="flex items-center justify-between">
              <div className="text-lg font-semibold text-white">
                {moduleLabels[m.module_code] || m.module_code.toUpperCase()}
              </div>
              <LayoutDashboard className="h-5 w-5 text-cyan-200" />
            </div>
            <p className="mt-2 text-sm text-gray-200/80">
              {m.ai_access ? "AI enabled" : "Standard access"}
            </p>
          </Link>
        ))}
      </section>

      <section className="glass rounded-2xl p-5 shadow-xl">
        <div className="flex items-center gap-2">
          <Wand2 className="h-5 w-5 text-emerald-300" />
          <h2 className="text-lg font-semibold text-white">Quick Actions</h2>
        </div>
        <div className="mt-4 grid gap-3 sm:grid-cols-3">
          {[
            { label: "Create Task", href: "/modules/tasks" },
            { label: "View CRM", href: "/modules/crm" },
            { label: "Chat with AI", href: "/modules/ai" },
          ].map((action) => (
            <Link
              key={action.label}
              href={action.href}
              className="rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:border-cyan-400 hover:bg-white/10"
            >
              {action.label}
            </Link>
          ))}
        </div>
      </section>
    </div>
  );
}

