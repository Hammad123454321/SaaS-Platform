"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api } from "@/lib/api";
import { useSessionStore } from "@/lib/store";

export default function SignupPage() {
  const router = useRouter();
  const setSession = useSessionStore((s) => s.setSession);
  const [tenantName, setTenantName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setMessage("");
    try {
      const res = await api.post("/auth/signup", { tenant_name: tenantName, email, password });
      const me = await api.get("/auth/me", {
        headers: { Authorization: `Bearer ${res.data.access_token}` },
      });
      setSession({
        accessToken: res.data.access_token,
        refreshToken: res.data.refresh_token,
        user: { email: me.data.email, is_super_admin: me.data.is_super_admin, roles: me.data.roles },
      });
      setMessage("Workspace created! Setting up your account...");
      // Redirect to onboarding for new tenants after a brief delay
      setTimeout(() => {
        router.push("/onboarding");
      }, 1000);
    } catch (err: any) {
      setMessage(err?.response?.data?.detail ?? "Signup failed");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-[80vh] items-center justify-center">
      <div className="glass w-full max-w-md rounded-2xl p-8 shadow-2xl">
        <div className="mb-6 space-y-2 text-center">
          <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Get started</p>
          <h1 className="text-2xl font-semibold text-white">Create your workspace</h1>
          <p className="text-sm text-gray-200/80">Tenant, admin email, and password policy enforced.</p>
        </div>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="text-sm text-gray-200/80">Tenant / Company name</label>
            <input
              className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white placeholder:text-gray-400 outline-none focus:border-cyan-400"
              type="text"
              placeholder="Acme Corp"
              value={tenantName}
              onChange={(e) => setTenantName(e.target.value)}
              required
            />
          </div>
          <div>
            <label className="text-sm text-gray-200/80">Admin email</label>
            <input
              className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white placeholder:text-gray-400 outline-none focus:border-cyan-400"
              type="email"
              placeholder="you@example.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          </div>
          <div>
            <label className="text-sm text-gray-200/80">Password</label>
            <input
              className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white placeholder:text-gray-400 outline-none focus:border-cyan-400"
              type="password"
              placeholder="Strong password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
            <p className="mt-1 text-xs text-gray-300/80">Min length 12, must include a special character.</p>
          </div>
          <button
            type="submit"
            disabled={loading}
            className="w-full rounded-lg bg-gradient-to-r from-cyan-400 to-blue-600 px-4 py-2 font-semibold text-gray-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl disabled:opacity-60"
          >
            {loading ? "Creating..." : "Create workspace"}
          </button>
        </form>
        {message && <p className="mt-4 text-center text-sm text-gray-100">{message}</p>}
      </div>
    </div>
  );
}





