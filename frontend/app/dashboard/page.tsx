"use client";

import { useEffect, useState } from "react";
import { useSessionStore } from "@/lib/store";
import SuperAdminDashboard from "./super-admin/page";
import CompanyAdminDashboard from "./company-admin/page";
import StaffDashboard from "./staff/page";

export default function DashboardPage() {
  const { user, accessToken, setSession } = useSessionStore();
  const [isHydrated, setIsHydrated] = useState(false);
  const [isLoadingUser, setIsLoadingUser] = useState(false);

  // Wait for Zustand to hydrate from sessionStorage
  useEffect(() => {
    setIsHydrated(true);
  }, []);

  // Fetch user data if missing
  useEffect(() => {
    if (isHydrated && accessToken && !user && !isLoadingUser) {
      setIsLoadingUser(true);
      const apiBaseURL = process.env.NEXT_PUBLIC_API_BASE_URL || "";
      const baseURL = apiBaseURL.endsWith('/api/v1') 
        ? apiBaseURL 
        : `${apiBaseURL.replace(/\/$/, '')}/api/v1`;
      
      fetch(`${baseURL}/auth/me`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${accessToken}`,
          'Content-Type': 'application/json',
        },
        credentials: 'include',
      })
        .then(res => {
          if (!res.ok) throw new Error(`HTTP ${res.status}`);
          return res.json();
        })
        .then(meData => {
          if (meData) {
            const userData = {
              id: meData.id,
              email: meData.email,
              is_super_admin: meData.is_super_admin,
              is_owner: meData.is_owner,
              roles: meData.roles || []
            };
            setSession({
              accessToken,
              refreshToken: useSessionStore.getState().refreshToken,
              user: userData,
            });
          }
        })
        .catch(err => {
          console.error("[Dashboard] Failed to fetch user data:", err);
        })
        .finally(() => {
          setIsLoadingUser(false);
        });
    }
  }, [isHydrated, accessToken, user, isLoadingUser, setSession]);

  // Show loading while hydrating or fetching user data
  if (!isHydrated || (accessToken && !user && isLoadingUser)) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <div className="w-8 h-8 border-2 border-purple-500 border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  // Route to appropriate dashboard based on role
  let dashboardContent;
  if (user?.is_super_admin) {
    dashboardContent = <SuperAdminDashboard />;
  } else {
    // Check if user has company_admin role
    const isCompanyAdmin = user?.roles?.includes("company_admin") || user?.roles?.includes("admin");
    if (isCompanyAdmin) {
      dashboardContent = <CompanyAdminDashboard />;
    } else {
      // Default to staff dashboard
      dashboardContent = <StaffDashboard />;
    }
  }

  return dashboardContent;
}
