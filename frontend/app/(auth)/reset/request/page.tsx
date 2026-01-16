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
      <div className="bg-white w-full max-w-md rounded-2xl p-8 shadow-lg border border-gray-200">
        <div className="mb-6 space-y-2 text-center">
          <p className="text-sm uppercase tracking-[0.2em] text-gray-500">Password reset</p>
          <h1 className="text-2xl font-semibold text-gray-900">Request reset link</h1>
        </div>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="text-sm text-gray-700 font-medium">Email</label>
            <input
              className="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 placeholder:text-gray-400 outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500"
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
            className="w-full rounded-lg bg-gradient-purple-blue px-4 py-2 font-semibold text-white shadow-md transition hover:opacity-90 disabled:opacity-60"
          >
            {loading ? "Sending..." : "Send reset link"}
          </button>
        </form>
        {message && <p className="mt-4 text-center text-sm text-gray-600">{message}</p>}
      </div>
    </div>
  );
}





