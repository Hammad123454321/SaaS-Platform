/* eslint-disable react-hooks/rules-of-hooks */
// @ts-nocheck
"use client";
import Link from "next/link";
import { ReactNode, useMemo, useEffect } from "react";
import { LayoutDashboard, LogOut, Sparkles, Palette } from "lucide-react";
import { useSessionStore } from "@/lib/store";
import { useBranding } from "@/hooks/useBranding";
import { api } from "@/lib/api";

type Props = {
  children: ReactNode;
};

export function AppShell({ children }: Props) {
  const { user, entitlements, accessToken, setSession, setEntitlements, clearSession } = useSessionStore();
  useBranding();

  const enabledModules = useMemo(
    () => entitlements.filter((m) => m.enabled).map((m) => m.module_code),
    [entitlements]
  );

  useEffect(() => {
    const bootstrap = async () => {
      const tokenStr: any = accessToken ?? "";
      if (!tokenStr) return; // nothing to do if not logged in
      try {
        if (entitlements.length === 0) {
          const res = await api.get("/entitlements");
          setEntitlements(res.data);
        }
      } catch (err) {
        clearSession(); // invalid token; reset
      }
    };
    bootstrap();
  }, [accessToken, user, entitlements.length, setSession, setEntitlements, clearSession]);

  const handleLogout = async () => {
    try {
      await api.post("/auth/logout");
    } catch (err) {
      // ignore
    } finally {
      clearSession();
    }
  };

  return (
    <div className="space-y-4">
      <header className="glass sticky top-4 z-20 flex items-center justify-between rounded-2xl px-4 py-3 shadow-xl">
        <div className="flex items-center gap-3">
          <Sparkles className="h-6 w-6 text-cyan-200" />
          <Link href="/dashboard" className="text-lg font-semibold text-white">
            {process.env.NEXT_PUBLIC_APP_NAME || "SaaS"}
          </Link>
        </div>
        <div className="flex items-center gap-3 text-sm text-gray-100">
          <Palette className="h-4 w-4 text-emerald-300" />
          {user?.email ?? "Guest"}
          <button
            onClick={handleLogout}
            className="rounded-full border border-white/20 px-3 py-1 text-xs font-semibold text-white transition hover:border-cyan-400 hover:bg-white/10"
          >
            <div className="flex items-center gap-1">
              <LogOut className="h-3 w-3" /> Sign out
            </div>
          </button>
        </div>
      </header>

      <nav className="glass flex flex-wrap items-center gap-3 rounded-2xl px-4 py-3 text-sm font-semibold text-white shadow">
        <Link className="flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-white/10" href="/dashboard">
          <LayoutDashboard className="h-4 w-4 text-cyan-200" />
          Dashboard
        </Link>
        {["crm", "tasks", "booking", "hrm", "pos", "landing", "ai"].map((m) => {
          if (!enabledModules.includes(m)) return null;
          return (
            <Link key={m} className="rounded-lg px-3 py-2 hover:bg-white/10" href={`/modules/${m}`}>
              {m.toUpperCase()}
            </Link>
          );
        })}
        {user?.is_super_admin && (
          <>
            <Link className="rounded-lg px-3 py-2 hover:bg-white/10" href="/billing">
              Billing
            </Link>
            <Link className="rounded-lg px-3 py-2 hover:bg-white/10" href="/onboarding">
              Onboarding
            </Link>
          </>
        )}
      </nav>

      <div>{children}</div>
    </div>
  );
}

