import { ReactNode } from "react";
import { AppShell } from "@/components/AppShell";
import "../globals.css";

export default function AppLayout({ children }: { children: ReactNode }) {
  return <AppShell>{children}</AppShell>;
}





