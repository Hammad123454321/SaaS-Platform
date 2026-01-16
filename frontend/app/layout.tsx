import "./globals.css";
import { ReactNode } from "react";
import { QueryProvider } from "@/lib/providers/query-provider";
import { ToastProvider } from "@/lib/providers/toast-provider";

export const metadata = {
  title: process.env.NEXT_PUBLIC_APP_NAME || "SaaS Frontend",
  description: "Multi-tenant business management SaaS",
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="en">
      <body className="bg-cosmic min-h-screen text-gray-900 antialiased">
        <QueryProvider>
          <main className="relative z-10 min-h-screen px-4 py-6 sm:px-8">{children}</main>
          <ToastProvider />
        </QueryProvider>
      </body>
    </html>
  );
}

