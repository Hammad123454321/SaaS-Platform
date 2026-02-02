"use client";
import Link from "next/link";
import { ReactNode, useMemo, useEffect, useState } from "react";
import { usePathname } from "next/navigation";
import {
  LayoutDashboard,
  LogOut,
  ClipboardList,
  Boxes,
  Users,
  CreditCard,
  Building2,
  Megaphone,
  ShoppingBag,
  Calendar,
  Globe,
  Bot,
  Settings,
  Menu,
  X,
} from "lucide-react";
import { useSessionStore } from "@/lib/store";
import { useBranding } from "@/hooks/useBranding";
import { api } from "@/lib/api";

type Props = {
  children: ReactNode;
};

export function AppShell({ children }: Props) {
  const { user, entitlements, accessToken, setSession, setEntitlements, clearSession } = useSessionStore();
  useBranding();
  const pathname = usePathname();
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const enabledModules = useMemo(
    () => entitlements.filter((m) => m.enabled).map((m) => m.module_code.toLowerCase()),
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
              id: me.data.id,
              email: me.data.email,
              is_super_admin: me.data.is_super_admin,
              is_owner: me.data.is_owner,
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

  const isAdmin = !!user?.roles?.some((r) => r === "company_admin" || r === "admin");
  const isSuperAdmin = !!user?.is_super_admin;

  const moduleNav = [
    { code: "tasks", label: "Tasks", href: "/modules/tasks", icon: ClipboardList },
    { code: "crm", label: "CRM", href: "/modules/crm", icon: Users },
    { code: "pos", label: "POS", href: "/modules/pos", icon: ShoppingBag },
    { code: "booking", label: "Booking", href: "/modules/booking", icon: Calendar },
    { code: "landing", label: "Landing", href: "/modules/landing", icon: Globe },
    { code: "hrm", label: "HRM", href: "/modules/hrm", icon: Building2 },
    { code: "ai", label: "AI", href: "/modules/ai", icon: Bot },
  ];

  const adminNav = [
    { label: "Users", href: "/admin/users", icon: Users, visible: !!(isAdmin || isSuperAdmin) },
    { label: "Tenants", href: "/admin/tenants", icon: Building2, visible: !!isSuperAdmin },
    { label: "Billing", href: "/billing", icon: CreditCard, visible: !!(isAdmin || isSuperAdmin || user?.is_owner) },
    { label: "Settings", href: "/settings", icon: Settings, visible: !!(isAdmin || isSuperAdmin) },
  ];

  const isActive = (href: string) => {
    if (href === "/dashboard") {
      return pathname === "/dashboard";
    }
    return pathname?.startsWith(href);
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Mobile top bar */}
      <div className="sticky top-0 z-40 flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3 lg:hidden">
        <button
          type="button"
          onClick={() => setSidebarOpen(true)}
          className="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 text-gray-700"
        >
          <Menu className="h-5 w-5" />
        </button>
        <Link href="/dashboard" className="text-sm font-semibold text-gray-900">
          Dashboard
        </Link>
        <div className="h-9 w-9" />
      </div>

      {/* Mobile overlay */}
      {sidebarOpen && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <div className="absolute inset-0 bg-black/40" onClick={() => setSidebarOpen(false)} />
          <aside className="absolute left-0 top-0 h-full w-72 bg-white shadow-xl">
            <div className="flex items-center justify-between border-b border-gray-200 px-4 py-4">
              <Link href="/dashboard" className="text-lg font-semibold text-gray-900">
                Platform
              </Link>
              <button
                type="button"
                onClick={() => setSidebarOpen(false)}
                className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 text-gray-700"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
            <SidebarContent
              enabledModules={enabledModules}
              moduleNav={moduleNav}
              adminNav={adminNav}
              onLogout={handleLogout}
              isActive={isActive}
              onNavigate={() => setSidebarOpen(false)}
            />
          </aside>
        </div>
      )}

      <div className="mx-auto flex min-h-screen max-w-[1600px]">
        {/* Desktop sidebar */}
        <aside className="hidden w-72 flex-shrink-0 border-r border-gray-200 bg-white lg:block">
          <div className="border-b border-gray-200 px-6 py-5">
            <Link href="/dashboard" className="text-lg font-semibold text-gray-900">
              Platform
            </Link>
            <p className="mt-1 text-xs text-gray-500">Workspace navigation</p>
          </div>
          <SidebarContent
            enabledModules={enabledModules}
            moduleNav={moduleNav}
            adminNav={adminNav}
            onLogout={handleLogout}
            isActive={isActive}
          />
        </aside>

        {/* Main content */}
        <main className="flex-1 px-4 py-6 lg:px-8">{children}</main>
      </div>
    </div>
  );
}

function SidebarContent({
  enabledModules,
  moduleNav,
  adminNav,
  onLogout,
  isActive,
  onNavigate,
}: {
  enabledModules: string[];
  moduleNav: Array<{ code: string; label: string; href: string; icon: any }>;
  adminNav: Array<{ label: string; href: string; icon: any; visible: boolean }>;
  onLogout: () => void;
  isActive: (href: string) => boolean;
  onNavigate?: () => void;
}) {
  return (
    <div className="flex h-[calc(100vh-76px)] flex-col justify-between px-4 py-5">
      <div className="space-y-6">
        <nav className="space-y-1">
          <Link
            href="/dashboard"
            onClick={onNavigate}
            className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium ${
              isActive("/dashboard")
                ? "bg-purple-100 text-purple-700"
                : "text-gray-700 hover:bg-gray-100"
            }`}
          >
            <LayoutDashboard className="h-4 w-4" />
            Dashboard
          </Link>
        </nav>

        <div>
          <p className="px-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
            Modules
          </p>
          <nav className="mt-2 space-y-1">
            {moduleNav
              .filter((item) => enabledModules.includes(item.code))
              .map((item) => {
                const Icon = item.icon;
                return (
                  <Link
                    key={item.code}
                    href={item.href}
                    onClick={onNavigate}
                    className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium ${
                      isActive(item.href)
                        ? "bg-purple-100 text-purple-700"
                        : "text-gray-700 hover:bg-gray-100"
                    }`}
                  >
                    <Icon className="h-4 w-4" />
                    {item.label}
                  </Link>
                );
              })}
            {enabledModules.length === 0 && (
              <div className="px-3 py-2 text-xs text-gray-500">
                No modules enabled
              </div>
            )}
          </nav>
        </div>

        <div>
          <p className="px-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
            Admin
          </p>
          <nav className="mt-2 space-y-1">
            {adminNav
              .filter((item) => item.visible)
              .map((item) => {
                const Icon = item.icon;
                return (
                  <Link
                    key={item.href}
                    href={item.href}
                    onClick={onNavigate}
                    className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium ${
                      isActive(item.href)
                        ? "bg-purple-100 text-purple-700"
                        : "text-gray-700 hover:bg-gray-100"
                    }`}
                  >
                    <Icon className="h-4 w-4" />
                    {item.label}
                  </Link>
                );
              })}
          </nav>
        </div>
      </div>

      <div className="space-y-3">
        <button
          type="button"
          onClick={onLogout}
          className="flex w-full items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          <LogOut className="h-4 w-4" />
          Log out
        </button>
      </div>
    </div>
  );
}
