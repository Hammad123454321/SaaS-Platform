"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api } from "@/lib/api";
import Link from "next/link";

export default function SignupPage() {
  const router = useRouter();
  const [tenantName, setTenantName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [acceptPrivacyPolicy, setAcceptPrivacyPolicy] = useState(false);
  const [acceptTermsOfService, setAcceptTermsOfService] = useState(false);
  const [emailEnabled, setEmailEnabled] = useState(true);
  const [smsEnabled, setSmsEnabled] = useState(false);
  const [marketingEmailConsent, setMarketingEmailConsent] = useState(false);
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setMessage("");
    
    if (!acceptPrivacyPolicy || !acceptTermsOfService) {
      setMessage("You must accept Privacy Policy and Terms of Service to register.");
      setLoading(false);
      return;
    }
    
    try {
      const res = await api.post("/auth/signup", {
        tenant_name: tenantName,
        email,
        password,
        accept_privacy_policy: acceptPrivacyPolicy,
        accept_terms_of_service: acceptTermsOfService,
        email_enabled: emailEnabled,
        sms_enabled: smsEnabled,
        marketing_email_consent: marketingEmailConsent,
      });
      
      setSuccess(true);
      const responseMessage = res.data.message || "Registration successful! Please check your email to verify your account.";
      setMessage(responseMessage);
      
      // Check if in development mode (auto-verified)
      const isDevelopmentMode = responseMessage.toLowerCase().includes("development mode") || 
                                responseMessage.toLowerCase().includes("auto-verified");
      
      // Redirect based on mode
      setTimeout(() => {
        if (isDevelopmentMode) {
          // Development mode: skip verification, redirect to login (user needs to login to access onboarding)
          // Account is already verified, so login will work immediately
          router.push(`/login?email=${encodeURIComponent(email)}&verified=true`);
        } else {
          // Production mode: redirect to verification page
          router.push(`/auth/verify-email?email=${encodeURIComponent(email)}`);
        }
      }, 3000);
    } catch (err: any) {
      setMessage(err?.response?.data?.detail ?? "Signup failed");
    } finally {
      setLoading(false);
    }
  };

  if (success) {
    return (
      <div className="flex min-h-[80vh] items-center justify-center">
        <div className="glass w-full max-w-md rounded-2xl p-8 shadow-2xl text-center">
          <div className="mb-6 space-y-2">
            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-500/20">
              <svg className="h-8 w-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <h1 className="text-2xl font-semibold text-white">Registration Successful!</h1>
            <p className="text-sm text-gray-200/80">{message}</p>
            <p className="mt-4 text-xs text-gray-300/80">
              {message.toLowerCase().includes("development mode") || message.toLowerCase().includes("auto-verified")
                ? "Redirecting to onboarding..."
                : "Redirecting to verification page..."}
            </p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-[80vh] items-center justify-center p-4">
      <div className="glass w-full max-w-md rounded-2xl p-8 shadow-2xl">
        <div className="mb-6 space-y-2 text-center">
          <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Get started</p>
          <h1 className="text-2xl font-semibold text-white">Create your workspace</h1>
          <p className="text-sm text-gray-200/80">Tenant, Business Owner Email, and Password policy enforced.</p>
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
            <label className="text-sm text-gray-200/80">Business Owner Email</label>
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

          {/* Policy Acceptance */}
          <div className="space-y-3 rounded-lg border border-white/10 bg-white/5 p-4">
            <p className="text-sm font-medium text-white">Required Acceptances</p>
            <label className="flex items-start gap-2 text-sm text-gray-200/80">
              <input
                type="checkbox"
                checked={acceptPrivacyPolicy}
                onChange={(e) => setAcceptPrivacyPolicy(e.target.checked)}
                className="mt-1"
                required
              />
              <span>I accept the <Link href="/privacy-policy" className="text-cyan-400 hover:underline">Privacy Policy</Link></span>
            </label>
            <label className="flex items-start gap-2 text-sm text-gray-200/80">
              <input
                type="checkbox"
                checked={acceptTermsOfService}
                onChange={(e) => setAcceptTermsOfService(e.target.checked)}
                className="mt-1"
                required
              />
              <span>I accept the <Link href="/terms-of-service" className="text-cyan-400 hover:underline">Terms of Service</Link></span>
            </label>
          </div>

          {/* Communication Preferences */}
          <div className="space-y-3 rounded-lg border border-white/10 bg-white/5 p-4">
            <p className="text-sm font-medium text-white">Communication Preferences</p>
            <label className="flex items-center gap-2 text-sm text-gray-200/80">
              <input
                type="checkbox"
                checked={emailEnabled}
                onChange={(e) => setEmailEnabled(e.target.checked)}
                className=""
              />
              <span>Email notifications</span>
            </label>
            <label className="flex items-center gap-2 text-sm text-gray-200/80">
              <input
                type="checkbox"
                checked={smsEnabled}
                onChange={(e) => setSmsEnabled(e.target.checked)}
                className=""
              />
              <span>SMS notifications</span>
            </label>
            <label className="flex items-center gap-2 text-sm text-gray-200/80">
              <input
                type="checkbox"
                checked={marketingEmailConsent}
                onChange={(e) => setMarketingEmailConsent(e.target.checked)}
                className=""
              />
              <span>I consent to receive marketing emails (CASL)</span>
            </label>
          </div>

          <button
            type="submit"
            disabled={loading || !acceptPrivacyPolicy || !acceptTermsOfService}
            className="w-full rounded-lg bg-gradient-to-r from-cyan-400 to-blue-600 px-4 py-2 font-semibold text-gray-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl disabled:opacity-60"
          >
            {loading ? "Creating..." : "Create workspace"}
          </button>
        </form>
        {message && (
          <p className={`mt-4 text-center text-sm ${success ? "text-green-400" : "text-red-400"}`}>
            {message}
          </p>
        )}
        <p className="mt-4 text-center text-xs text-gray-300/80">
          Already have an account? <Link href="/login" className="text-cyan-400 hover:underline">Log in</Link>
        </p>
      </div>
    </div>
  );
}





