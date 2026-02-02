"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useState, useEffect, Suspense } from "react";
import { api } from "@/lib/api";
import { useSessionStore, UserInfo } from "@/lib/store";

export const dynamic = 'force-dynamic';

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { setSession, clearSession, accessToken } = useSessionStore();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);

  // Pre-fill email from query params (e.g., from signup redirect)
  // Also check for error message from redirects
  useEffect(() => {
    const emailParam = searchParams.get("email");
    if (emailParam) {
      setEmail(emailParam);
    }
    
    const errorParam = searchParams.get("error");
    if (errorParam) {
      setMessage(decodeURIComponent(errorParam));
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
      
      // Clear old session data first to avoid conflicts
      clearSession();
      
      // Wait a moment for session to clear
      await new Promise(resolve => setTimeout(resolve, 50));
      
      // Set session with tokens only (user will be added after /auth/me)
      setSession({
        accessToken,
        refreshToken,
        user: null, // Will be updated after /auth/me call
      });
      
      // Wait for session to persist
      await new Promise(resolve => setTimeout(resolve, 50));
      
      // Call /auth/me with explicit Authorization header
      let userData: UserInfo | null = null;
      try {
        const apiBaseURL = process.env.NEXT_PUBLIC_API_BASE_URL || "";
        const baseURL = apiBaseURL.endsWith('/api/v1') 
          ? apiBaseURL 
          : `${apiBaseURL.replace(/\/$/, '')}/api/v1`;
        
        const fetchResponse = await fetch(`${baseURL}/auth/me`, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${accessToken}`,
            'Content-Type': 'application/json',
          },
          credentials: 'include',
          cache: 'no-store', // Prevent caching
        });
        
        if (!fetchResponse.ok) {
          const errorText = await fetchResponse.text();
          throw new Error(`HTTP ${fetchResponse.status}: ${errorText}`);
        }
        
        const meData = await fetchResponse.json();
        
        if (meData) {
          userData = { 
            id: meData.id,
            email: meData.email, 
            is_super_admin: meData.is_super_admin, 
            is_owner: meData.is_owner,
            roles: meData.roles || [] 
          };
        }
      } catch (meError: any) {
        console.error("[Login] /auth/me failed:", meError?.message);
        // Continue without user data - it can be fetched later on dashboard
      }
      
      // Update session with user data and wait for persistence
      setSession({
        accessToken,
        refreshToken,
        user: userData,
      });
      
      // Give Zustand persist time to write to sessionStorage
      await new Promise(resolve => setTimeout(resolve, 100));
      
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
      } catch (err: unknown) {
        // If onboarding check fails, redirect to login with error message
        console.error("Failed to check onboarding status:", err);
        const errorMessage = (err as any)?.response?.data?.detail || "Failed to verify onboarding status. Please try again.";
        const loginUrl = new URL("/login", window.location.origin);
        loginUrl.searchParams.set("error", encodeURIComponent(errorMessage));
        setTimeout(() => {
          window.location.href = loginUrl.toString();
        }, 500);
        return;
      }
    } catch (err: any) {
      setMessage(err?.response?.data?.detail ?? "Login failed. Please check your credentials.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-[80vh] items-center justify-center">
      <div className="bg-white w-full max-w-md rounded-2xl p-8 shadow-lg border border-gray-200">
        <div className="mb-6 space-y-2 text-center">
          <p className="text-sm uppercase tracking-[0.2em] text-gray-500">Welcome back</p>
          <h1 className="text-2xl font-semibold text-gray-900">Sign in</h1>
          <p className="text-sm text-gray-600">
            Access your tenant workspace with secure JWT.
          </p>
          {searchParams.get("verified") === "true" && (
            <p className="mt-2 text-sm text-green-600">
              ✓ Account verified (development mode). Please sign in to continue.
            </p>
          )}
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
          <div>
            <label className="text-sm text-gray-700 font-medium">Password</label>
            <input
              className="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 placeholder:text-gray-400 outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500"
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
            className="w-full rounded-lg bg-gradient-purple-blue px-4 py-2 font-semibold text-white shadow-md transition hover:opacity-90 disabled:opacity-60"
          >
            {loading ? "Signing in..." : "Sign in"}
          </button>
        </form>
        <div className="mt-4 space-y-2 text-center text-sm">
          {message && <p className={message.includes("Success") ? "text-green-600" : "text-red-600"}>{message}</p>}
          <p>
            <a className="text-purple-600 hover:underline" href="/reset/request">
              Forgot password?
            </a>
          </p>
        </div>
      </div>
    </div>
  );
}

export default function LoginPage() {
  return (
    <Suspense fallback={
      <div className="flex min-h-[80vh] items-center justify-center">
        <div className="text-center">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-purple-500 border-t-transparent mx-auto mb-4"></div>
          <p className="text-gray-600">Loading...</p>
        </div>
      </div>
    }>
      <LoginForm />
    </Suspense>
  );
}
