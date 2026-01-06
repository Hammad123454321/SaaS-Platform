"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useSessionStore } from "@/lib/store";
import { AppShell } from "@/components/AppShell";
import SuperAdminDashboard from "./super-admin/page";
import CompanyAdminDashboard from "./company-admin/page";
import StaffDashboard from "./staff/page";

export default function DashboardPage() {
  const router = useRouter();
  const { user, accessToken } = useSessionStore();

  useEffect(() => {
    if (!user || !accessToken) {
      router.push("/login");
      return;
    }
  }, [user, accessToken, router]);

  if (!user || !accessToken) {
    return null;
  }

  // Route to appropriate dashboard based on role
  let dashboardContent;
  if (user.is_super_admin) {
    dashboardContent = <SuperAdminDashboard />;
  } else {
    // Check if user has company_admin role
    const isCompanyAdmin = user.roles?.includes("company_admin") || user.roles?.includes("admin");
    if (isCompanyAdmin) {
      dashboardContent = <CompanyAdminDashboard />;
    } else {
      // Default to staff dashboard
      dashboardContent = <StaffDashboard />;
    }
  }

  return <AppShell>{dashboardContent}</AppShell>;
}

