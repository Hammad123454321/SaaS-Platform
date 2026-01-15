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
      <body className="bg-radial text-gray-50 antialiased">
        <QueryProvider>
          <main className="min-h-screen px-4 py-6 sm:px-8">{children}</main>
          <ToastProvider />
        </QueryProvider>
      </body>
    </html>
  );
}

