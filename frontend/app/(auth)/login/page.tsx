"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useState, useEffect } from "react";
import { api } from "@/lib/api";
import { useSessionStore } from "@/lib/store";

export default function LoginPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { setSession, accessToken } = useSessionStore();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);

  // Pre-fill email from query params (e.g., from signup redirect)
  useEffect(() => {
    const emailParam = searchParams.get("email");
    if (emailParam) {
      setEmail(emailParam);
    }
  }, [searchParams]);

  // Redirect if already logged in
  useEffect(() => {
    if (accessToken) {
      const redirect = searchParams.get("redirect");
      const verified = searchParams.get("verified");
      
      // Respect the verified param for dev mode signup flow
      const destination = verified === "true" ? "/onboarding" : (redirect || "/dashboard");
      
      // Use window.location for a full redirect to avoid hydration issues
      window.location.href = destination;
    }
  }, [accessToken, router, searchParams]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setMessage("");
    try {
      const res = await api.post("/auth/login", { email, password });
      const accessToken = res.data.access_token;
      const refreshToken = res.data.refresh_token;
      const passwordChangeRequired = res.data.password_change_required || false;
      
      // Store token in cookie for middleware access
      document.cookie = `access_token=${accessToken}; path=/; max-age=86400; SameSite=Lax`;
      if (refreshToken) {
        document.cookie = `refresh_token=${refreshToken}; path=/; max-age=604800; SameSite=Lax`;
      }
      
      // Set session BEFORE calling /auth/me so the interceptor can use it
      setSession({
        accessToken,
        refreshToken,
        user: null, // Will be updated after /auth/me call
      });
      
      // Call /auth/me with explicit Authorization header
      const me = await api.get("/auth/me", {
        headers: {
          Authorization: `Bearer ${accessToken}`,
        },
      });
      
      // Update session with user data
      setSession({
        accessToken,
        refreshToken,
        user: { 
          email: me.data.email, 
          is_super_admin: me.data.is_super_admin, 
          roles: me.data.roles || [] 
        },
      });
      
      // If password change is required, redirect to first-login page
      if (passwordChangeRequired) {
        setMessage("Password change required. Redirecting...");
        setTimeout(() => {
          window.location.href = "/auth/first-login";
        }, 500);
        return;
      }
      
      setMessage("Success! Redirecting...");
      
      // Check onboarding status before redirecting
      try {
        const onboardingStatus = await api.get("/auth/onboarding-status");
        const redirect = searchParams.get("redirect");
        const verified = searchParams.get("verified"); // From dev mode signup
        
        // Determine destination
        let destination: string;
        if (verified === "true" || !onboardingStatus.data.onboarding_complete) {
          // If coming from dev mode signup or onboarding not complete, go to onboarding
          destination = "/onboarding";
        } else {
          // Otherwise use redirect param or default to dashboard
          destination = redirect || "/dashboard";
        }
        
        // Use window.location for a full redirect to ensure cookies are set and avoid redirect loops
        setTimeout(() => {
          window.location.href = destination;
        }, 500);
      } catch (err) {
        // If onboarding check fails, default to dashboard (fail open)
        const redirect = searchParams.get("redirect");
        const destination = redirect || "/dashboard";
        setTimeout(() => {
          window.location.href = destination;
        }, 500);
      }
    } catch (err: any) {
      setMessage(err?.response?.data?.detail ?? "Login failed. Please check your credentials.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-[80vh] items-center justify-center">
      <div className="glass w-full max-w-md rounded-2xl p-8 shadow-2xl">
        <div className="mb-6 space-y-2 text-center">
          <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Welcome back</p>
          <h1 className="text-2xl font-semibold text-white">Sign in</h1>
          <p className="text-sm text-gray-200/80">
            Access your tenant workspace with secure JWT.
          </p>
          {searchParams.get("verified") === "true" && (
            <p className="mt-2 text-sm text-green-400">
              ✓ Account verified (development mode). Please sign in to continue.
            </p>
          )}
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
          <div>
            <label className="text-sm text-gray-200/80">Password</label>
            <input
              className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white placeholder:text-gray-400 outline-none focus:border-cyan-400"
              type="password"
              placeholder="********"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </div>
          <button
            type="submit"
            disabled={loading}
            className="w-full rounded-lg bg-gradient-to-r from-cyan-400 to-blue-600 px-4 py-2 font-semibold text-gray-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl disabled:opacity-60"
          >
            {loading ? "Signing in..." : "Sign in"}
          </button>
        </form>
        <div className="mt-4 space-y-2 text-center text-sm text-gray-100">
          {message && <p>{message}</p>}
          <p>
            <a className="text-cyan-200 hover:underline" href="/reset/request">
              Forgot password?
            </a>
          </p>
        </div>
      </div>
    </div>
  );
}

