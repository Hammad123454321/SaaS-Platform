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
      if (!tokenStr) {
        // Redirect to login if no token (unless already on auth pages)
        if (typeof window !== "undefined" && 
            !window.location.pathname.startsWith("/login") && 
            !window.location.pathname.startsWith("/signup") &&
            !window.location.pathname.startsWith("/reset")) {
          window.location.href = "/login";
        }
        return;
      }
      try {
        // Refresh user info if missing
        if (!user) {
          const me = await api.get("/auth/me");
          setSession({
            accessToken,
            refreshToken: useSessionStore.getState().refreshToken,
            user: {
              email: me.data.email,
              is_super_admin: me.data.is_super_admin,
              roles: me.data.roles || [],
            },
          });
        }
        // Load entitlements if not loaded
        if (entitlements.length === 0) {
          const res = await api.get("/entitlements");
          setEntitlements(res.data);
        }
      } catch (err: any) {
        // If 401, clear session and redirect
        if (err?.response?.status === 401) {
          clearSession();
          if (typeof window !== "undefined") {
            window.location.href = "/login";
          }
        }
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
      if (typeof window !== "undefined") {
        window.location.href = "/login";
      }
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
          <span className="font-medium">{user?.email ?? "Guest"}</span>
          {user?.is_super_admin && (
            <span className="rounded-full bg-yellow-500/20 px-2 py-0.5 text-xs font-semibold text-yellow-300">
              Super Admin
            </span>
          )}
          <button
            onClick={handleLogout}
            className="flex items-center gap-2 rounded-lg border border-red-400/50 bg-red-500/10 px-4 py-2 text-sm font-semibold text-red-300 transition hover:border-red-400 hover:bg-red-500/20"
          >
            <LogOut className="h-4 w-4" />
            <span>Logout</span>
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
        {/* Super Admin Navigation */}
        {user?.is_super_admin && (
          <>
            <Link className="rounded-lg px-3 py-2 hover:bg-white/10" href="/admin/tenants">
              Tenants
            </Link>
            <Link className="rounded-lg px-3 py-2 hover:bg-white/10" href="/billing">
              Billing
            </Link>
          </>
        )}
        {/* Company Admin Navigation */}
        {(user?.roles?.includes("company_admin") || user?.roles?.includes("admin")) && !user?.is_super_admin && (
          <>
            <Link className="rounded-lg px-3 py-2 hover:bg-white/10" href="/admin/users">
              Users
            </Link>
            <Link className="rounded-lg px-3 py-2 hover:bg-white/10" href="/onboarding">
              Settings
            </Link>
          </>
        )}
        {/* All authenticated users can access AI */}
        {user && enabledModules.includes("ai") && (
          <Link className="rounded-lg px-3 py-2 hover:bg-white/10" href="/modules/ai">
            AI Chat
          </Link>
        )}
      </nav>

      <div>{children}</div>
    </div>
  );
}

