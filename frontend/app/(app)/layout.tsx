"use client";

import { ReactNode, useEffect } from "react";
import { useRouter } from "next/navigation";
import { AppShell } from "@/components/AppShell";
import { useSessionStore } from "@/lib/store";
import "../globals.css";

export default function AppLayout({ children }: { children: ReactNode }) {
  const router = useRouter();
  const { user, accessToken } = useSessionStore();

  useEffect(() => {
    // Redirect to login if not authenticated
    if (!accessToken || !user) {
      router.push("/login");
    }
  }, [accessToken, user, router]);

  // Don't render if not authenticated
  if (!accessToken || !user) {
    return null;
  }

  return <AppShell>{children}</AppShell>;
}





