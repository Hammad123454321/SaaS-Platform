"use client";

import { useState } from "react";
import { api } from "@/lib/api";

export default function ResetRequestPage() {
  const [email, setEmail] = useState("");
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setMessage("");
    try {
      await api.post("/auth/password-reset/request", { email });
      setMessage("If that email exists, a reset link was sent.");
    } catch (err: any) {
      setMessage(err?.response?.data?.detail ?? "Unable to request reset");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-[80vh] items-center justify-center">
      <div className="glass w-full max-w-md rounded-2xl p-8 shadow-2xl">
        <div className="mb-6 space-y-2 text-center">
          <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Password reset</p>
          <h1 className="text-2xl font-semibold text-white">Request reset link</h1>
        </div>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="text-sm text-gray-200/80">Email</label>
            <input
              className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white placeholder:text-gray-400 outline-none focus:border-cyan-400"
              type="email"
              placeholder="you@example.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          </div>
          <button
            type="submit"
            disabled={loading}
            className="w-full rounded-lg bg-gradient-to-r from-cyan-400 to-blue-600 px-4 py-2 font-semibold text-gray-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl disabled:opacity-60"
          >
            {loading ? "Sending..." : "Send reset link"}
          </button>
        </form>
        {message && <p className="mt-4 text-center text-sm text-gray-100">{message}</p>}
      </div>
    </div>
  );
}





