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
        <div className="bg-white w-full max-w-md rounded-2xl p-8 shadow-lg border border-gray-200 text-center">
          <div className="mb-6 space-y-2">
            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
              <svg className="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <h1 className="text-2xl font-semibold text-gray-900">Registration Successful!</h1>
            <p className="text-sm text-gray-600">{message}</p>
            <p className="mt-4 text-xs text-gray-500">
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
      <div className="bg-white w-full max-w-md rounded-2xl p-8 shadow-lg border border-gray-200">
        <div className="mb-6 space-y-2 text-center">
          <p className="text-sm uppercase tracking-[0.2em] text-gray-500">Get started</p>
          <h1 className="text-2xl font-semibold text-gray-900">Create your workspace</h1>
          <p className="text-sm text-gray-600">Tenant, Business Owner Email, and Password policy enforced.</p>
        </div>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="text-sm text-gray-700 font-medium">Tenant / Company name</label>
            <input
              className="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 placeholder:text-gray-400 outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500"
              type="text"
              placeholder="Acme Corp"
              value={tenantName}
              onChange={(e) => setTenantName(e.target.value)}
              required
            />
          </div>
          <div>
            <label className="text-sm text-gray-700 font-medium">Business Owner Email</label>
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
              placeholder="Strong password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
            <p className="mt-1 text-xs text-gray-500">Min length 12, must include a special character.</p>
          </div>

          {/* Policy Acceptance */}
          <div className="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <p className="text-sm font-medium text-gray-900">Required Acceptances</p>
            <label className="flex items-start gap-2 text-sm text-gray-700">
              <input
                type="checkbox"
                checked={acceptPrivacyPolicy}
                onChange={(e) => setAcceptPrivacyPolicy(e.target.checked)}
                className="mt-1"
                required
              />
              <span>I accept the <Link href="/privacy-policy" className="text-purple-600 hover:underline">Privacy Policy</Link></span>
            </label>
            <label className="flex items-start gap-2 text-sm text-gray-700">
              <input
                type="checkbox"
                checked={acceptTermsOfService}
                onChange={(e) => setAcceptTermsOfService(e.target.checked)}
                className="mt-1"
                required
              />
              <span>I accept the <Link href="/terms-of-service" className="text-purple-600 hover:underline">Terms of Service</Link></span>
            </label>
          </div>

          {/* Communication Preferences */}
          <div className="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <p className="text-sm font-medium text-gray-900">Communication Preferences</p>
            <label className="flex items-center gap-2 text-sm text-gray-700">
              <input
                type="checkbox"
                checked={emailEnabled}
                onChange={(e) => setEmailEnabled(e.target.checked)}
                className=""
              />
              <span>Email notifications</span>
            </label>
            <label className="flex items-center gap-2 text-sm text-gray-700">
              <input
                type="checkbox"
                checked={smsEnabled}
                onChange={(e) => setSmsEnabled(e.target.checked)}
                className=""
              />
              <span>SMS notifications</span>
            </label>
            <label className="flex items-center gap-2 text-sm text-gray-700">
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
            className="w-full rounded-lg bg-gradient-purple-blue px-4 py-2 font-semibold text-white shadow-md transition hover:opacity-90 disabled:opacity-60"
          >
            {loading ? "Creating..." : "Create workspace"}
          </button>
        </form>
        {message && (
          <p className={`mt-4 text-center text-sm ${success ? "text-green-600" : "text-red-600"}`}>
            {message}
          </p>
        )}
        <p className="mt-4 text-center text-xs text-gray-600">
          Already have an account? <Link href="/login" className="text-purple-600 hover:underline">Log in</Link>
        </p>
      </div>
    </div>
  );
}





