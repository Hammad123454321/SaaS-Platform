"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { useSessionStore } from "@/lib/store";

type BillingHistoryItem = {
  event_type: string;
  amount?: number;
  currency?: string;
  created_at: string;
};

export default function BillingPage() {
  const user = useSessionStore((s) => s.user);
  const [history, setHistory] = useState<BillingHistoryItem[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const fetchHistory = async () => {
      setLoading(true);
      try {
        const res = await api.get<BillingHistoryItem[]>("/billing/history");
        setHistory(res.data);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchHistory();
  }, []);

  if (!user?.is_super_admin) {
    return (
      <div className="glass rounded-2xl px-5 py-4 text-white shadow-xl">
        Admin only. Sign in as Super Admin to manage billing.
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <header className="glass rounded-2xl px-6 py-5 shadow-xl">
        <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Billing</p>
        <h1 className="text-3xl font-semibold text-white">Subscription & History</h1>
        <p className="text-sm text-gray-200/80">
          Stripe webhooks processed server-side; this view reflects recorded events.
        </p>
      </header>

      <section className="glass rounded-2xl p-5 shadow-xl">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold text-white">Recent events</h2>
          {loading && <span className="text-xs text-gray-200/80">Loading…</span>}
        </div>
        <div className="mt-3 grid gap-3">
          {history.map((item, idx) => (
            <div
              key={idx}
              className="rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-white"
            >
              <div className="flex items-center justify-between">
                <span className="font-semibold">{item.event_type}</span>
                <span className="text-xs text-gray-200/80">
                  {new Date(item.created_at).toLocaleString()}
                </span>
              </div>
              <div className="text-xs text-gray-200/80">
                {item.amount ? `${item.amount / 100} ${item.currency?.toUpperCase() || ""}` : "—"}
              </div>
            </div>
          ))}
          {history.length === 0 && !loading && (
            <div className="rounded-lg border border-dashed border-white/20 px-4 py-6 text-center text-sm text-gray-200/80">
              No billing events yet.
            </div>
          )}
        </div>
      </section>

      <section className="glass rounded-2xl p-5 shadow-xl">
        <h2 className="text-lg font-semibold text-white">Checkout/Customer Portal</h2>
        <p className="mt-2 text-sm text-gray-200/80">
          Stripe-hosted UI (Checkout/Customer Portal) should be used for plan selection and payment
          updates. Provide a backend endpoint to create session URLs and redirect users client-side.
        </p>
      </section>
    </div>
  );
}

