import "./globals.css";
import { ReactNode } from "react";

export const metadata = {
  title: process.env.NEXT_PUBLIC_APP_NAME || "SaaS Frontend",
  description: "Multi-tenant business management SaaS",
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="en">
      <body className="bg-radial text-gray-50 antialiased">
        <main className="min-h-screen px-4 py-6 sm:px-8">{children}</main>
      </body>
    </html>
  );
}

