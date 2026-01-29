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
  const [isHydrated, setIsHydrated] = useState(false);
  const [checkingOnboarding, setCheckingOnboarding] = useState(true);

  // Wait for Zustand to hydrate from sessionStorage
  useEffect(() => {
    setIsHydrated(true);
  }, []);

  useEffect(() => {
    // Don't do anything until Zustand has hydrated
    if (!isHydrated) return;

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
      } catch (err: unknown) {
        console.error("Failed to check onboarding status:", err);
        // On error, redirect to login with error message
        const errorMessage = (err as any)?.response?.data?.detail || "Failed to verify onboarding status. Please try again.";
        router.push(`/login?error=${encodeURIComponent(errorMessage)}`);
        return;
      }
    };
    
    checkOnboarding();
  }, [isHydrated, accessToken, user, router, pathname]);

  // Show loading while hydrating or checking onboarding
  if (!isHydrated || (!accessToken || !user || checkingOnboarding)) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <div className="w-8 h-8 border-2 border-purple-500 border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  return <AppShell>{children}</AppShell>;
}





