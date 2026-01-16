"use client";

export const dynamic = "force-dynamic";

import { useSearchParams, useRouter } from "next/navigation";
import { Suspense, useState } from "react";
import { api } from "@/lib/api";

function ResetConfirmPageInner() {
  const search = useSearchParams();
  const router = useRouter();
  const token = search.get("token") || "";
  const [password, setPassword] = useState("");
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setMessage("");
    try {
      await api.post("/auth/password-reset/confirm", { token, new_password: password });
      setMessage("Password updated. Redirecting to login...");
      setTimeout(() => router.push("/login"), 1000);
    } catch (err: any) {
      setMessage(err?.response?.data?.detail ?? "Unable to reset password");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-[80vh] items-center justify-center">
      <div className="bg-white w-full max-w-md rounded-2xl p-8 shadow-lg border border-gray-200">
        <div className="mb-6 space-y-2 text-center">
          <p className="text-sm uppercase tracking-[0.2em] text-gray-500">Password reset</p>
          <h1 className="text-2xl font-semibold text-gray-900">Set a new password</h1>
        </div>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="text-sm text-gray-700 font-medium">New password</label>
            <input
              className="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 placeholder:text-gray-400 outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500"
              type="password"
              placeholder="Strong password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
            <p className="mt-1 text-xs text-gray-500">Min length 12, include a special character.</p>
          </div>
          <button
            type="submit"
            disabled={loading}
            className="w-full rounded-lg bg-gradient-purple-blue px-4 py-2 font-semibold text-white shadow-md transition hover:opacity-90 disabled:opacity-60"
          >
            {loading ? "Updating..." : "Update password"}
          </button>
        </form>
        {message && <p className="mt-4 text-center text-sm text-gray-600">{message}</p>}
      </div>
    </div>
  );
}

export default function ResetConfirmPage() {
  return (
    <Suspense fallback={<div className="flex min-h-[80vh] items-center justify-center text-white">Loading...</div>}>
      <ResetConfirmPageInner />
    </Suspense>
  );
}





