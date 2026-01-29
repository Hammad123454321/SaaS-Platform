"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useSessionStore } from "@/lib/store";
import { api } from "@/lib/api";
import { AppShell } from "@/components/AppShell";
import SuperAdminDashboard from "./super-admin/page";
import CompanyAdminDashboard from "./company-admin/page";
import StaffDashboard from "./staff/page";

export default function DashboardPage() {
  const router = useRouter();
  const { user, accessToken } = useSessionStore();
  const [checkingOnboarding, setCheckingOnboarding] = useState(true);

  useEffect(() => {
    if (!user || !accessToken) {
      router.push("/login");
      return;
    }
    
    // Check onboarding completion
    const checkOnboarding = async () => {
      try {
        const res = await api.get("/auth/onboarding-status");
        if (!res.data.onboarding_complete) {
          // Redirect to onboarding if not complete
          router.push("/onboarding");
          return;
        }
        setCheckingOnboarding(false);
      } catch (err: any) {
        console.error("Failed to check onboarding status:", err);
        // On error, redirect to login with error message
        const errorMessage = err?.response?.data?.detail || "Failed to verify onboarding status. Please try again.";
        router.push(`/login?error=${encodeURIComponent(errorMessage)}`);
        return;
      }
    };
    
    checkOnboarding();
  }, [user, accessToken, router]);

  if (!user || !accessToken || checkingOnboarding) {
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

