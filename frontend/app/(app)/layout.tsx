"use client";

import { ReactNode, useEffect, useState } from "react";
import { useRouter, usePathname } from "next/navigation";
import { AppShell } from "@/components/AppShell";
import { useSessionStore } from "@/lib/store";
import { api } from "@/lib/api";
import "../globals.css";

export default function AppLayout({ children }: { children: ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { user, accessToken } = useSessionStore();
  const [checkingOnboarding, setCheckingOnboarding] = useState(true);

  useEffect(() => {
    // Redirect to login if not authenticated
    if (!accessToken || !user) {
      router.push("/login");
      return;
    }
    
    // Check onboarding completion (skip for onboarding route itself)
    if (pathname?.startsWith("/onboarding")) {
      setCheckingOnboarding(false);
      return;
    }
    
    const checkOnboarding = async () => {
      try {
        const res = await api.get("/auth/onboarding-status");
        if (!res.data.onboarding_complete) {
          // Redirect to onboarding if not complete
          router.push("/onboarding");
          return;
        }
        setCheckingOnboarding(false);
      } catch (err) {
        console.error("Failed to check onboarding status:", err);
        // On error, allow access (fail open)
        setCheckingOnboarding(false);
      }
    };
    
    checkOnboarding();
  }, [accessToken, user, router, pathname]);

  // Don't render if not authenticated or checking onboarding
  if (!accessToken || !user || checkingOnboarding) {
    return null;
  }

  return <AppShell>{children}</AppShell>;
}





