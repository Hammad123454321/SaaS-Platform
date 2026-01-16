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
    <div>
      <div>{children}</div>
    </div>
  );
}

