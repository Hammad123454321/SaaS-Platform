import Link from "next/link";

const features = [
  "Tenant-aware dashboards",
  "Billing & entitlements",
  "Module integrations",
  "AI assistant ready",
];

export default function Home() {
  return (
    <div className="relative mx-auto flex max-w-6xl flex-col items-center gap-10 py-16">
      <div className="absolute inset-0 parallax opacity-60" />
      <div className="z-10 grid gap-8 text-center">
        <div className="mx-auto max-w-3xl space-y-4">
          <span className="rounded-full bg-white/10 px-4 py-1 text-sm font-semibold text-cyan-200">
            Modern SaaS Shell
          </span>
          <h1 className="text-4xl font-bold leading-tight sm:text-5xl">
            A unified workspace for CRM, HRM, POS, Tasks, Booking, and more.
          </h1>
          <p className="text-lg text-gray-200/90">
            Secure multi-tenant foundations with billing, entitlements, and AI-ready module wrappers.
          </p>
          <div className="flex items-center justify-center gap-3">
            <Link
              href="/login"
              className="rounded-full bg-white px-5 py-2 font-semibold text-gray-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl"
            >
              Log in
            </Link>
            <Link
              href="/onboarding"
              className="rounded-full border border-white/30 px-5 py-2 font-semibold text-white transition hover:bg-white/10"
            >
              Onboarding
            </Link>
            <Link
              href="/signup"
              className="rounded-full bg-gradient-to-r from-cyan-400 to-blue-600 px-5 py-2 font-semibold text-gray-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl"
            >
              Get started
            </Link>
          </div>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          {features.map((f) => (
            <div key={f} className="glass rounded-xl p-4 text-left">
              <div className="text-lg font-semibold text-white">{f}</div>
              <p className="text-sm text-gray-200/80">Production-ready scaffolding with modern UX.</p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

