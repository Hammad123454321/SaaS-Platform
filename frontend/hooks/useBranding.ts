import { useEffect } from "react";
import { useSessionStore } from "@/lib/store";

export function useBranding() {
  const branding = useSessionStore((s) => s.branding);
  useEffect(() => {
    if (typeof document !== "undefined") {
      document.documentElement.style.setProperty("--brand-color", branding.color);
    }
  }, [branding.color]);
  return branding;
}

