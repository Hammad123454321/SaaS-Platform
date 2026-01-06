"use client";

import Link from "next/link";
import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useSessionStore } from "@/lib/store";

const features = [
  "Multi-tenant architecture",
  "Role-based access control",
  "Module integrations (CRM, HRM, POS, Tasks, Booking)",
  "AI-powered business assistant",
  "Stripe billing integration",
  "Real-time dashboards",
];

export default function Home() {
  const router = useRouter();
  const { accessToken, user } = useSessionStore();

  useEffect(() => {
    // Redirect authenticated users to dashboard
    if (accessToken && user) {
      router.push("/dashboard");
    }
  }, [accessToken, user, router]);

  return (
    <div className="relative mx-auto flex max-w-6xl flex-col items-center gap-10 py-16">
      <div className="absolute inset-0 parallax opacity-60" />
      <div className="z-10 grid gap-8 text-center">
        <div className="mx-auto max-w-3xl space-y-4">
          <span className="rounded-full bg-white/10 px-4 py-1 text-sm font-semibold text-cyan-200">
            Business Management Platform
          </span>
          <h1 className="text-4xl font-bold leading-tight sm:text-5xl">
            All-in-One SaaS Platform for Modern Businesses
          </h1>
          <p className="text-lg text-gray-200/90">
            Manage your CRM, HRM, POS, Tasks, Booking, and more from a single unified platform with AI-powered assistance.
          </p>
          <div className="flex items-center justify-center gap-3 flex-wrap">
            <Link
              href="/signup"
              className="rounded-full bg-gradient-to-r from-cyan-400 to-blue-600 px-6 py-3 font-semibold text-gray-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl"
            >
              Get Started Free
            </Link>
            <Link
              href="/login"
              className="rounded-full border border-white/30 bg-white/5 px-6 py-3 font-semibold text-white transition hover:bg-white/10"
            >
              Sign In
            </Link>
          </div>
        </div>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mt-8">
          {features.map((f) => (
            <div key={f} className="glass rounded-xl p-5 text-left transition hover:-translate-y-1 hover:shadow-xl">
              <div className="text-lg font-semibold text-white mb-2">{f}</div>
              <p className="text-sm text-gray-200/80">Enterprise-grade features built for scale.</p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

