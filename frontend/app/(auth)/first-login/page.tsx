"use client";

import { useState, useEffect, useMemo } from "react";
import { useRouter } from "next/navigation";
import { api } from "@/lib/api";
import { useSessionStore } from "@/lib/store";
import { useHRPolicies } from "@/hooks/compliance/useHRPolicies";
import Link from "next/link";

export default function FirstLoginPage() {
  const router = useRouter();
  const { accessToken, user } = useSessionStore();
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [acceptPrivacyPolicy, setAcceptPrivacyPolicy] = useState(false);
  const [acceptTermsOfService, setAcceptTermsOfService] = useState(false);
  const [emailEnabled, setEmailEnabled] = useState(true);
  const [smsEnabled, setSmsEnabled] = useState(false);
  const [marketingEmailConsent, setMarketingEmailConsent] = useState(false);
  const [hrPolicyAcknowledgements, setHrPolicyAcknowledgements] = useState<number[]>([]);
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);
  
  const { data: hrPoliciesData, isLoading: loadingPolicies } = useHRPolicies();
  const hrPolicies = useMemo(() => hrPoliciesData || [], [hrPoliciesData]);

  // Redirect if not logged in
  useEffect(() => {
    if (!accessToken) {
      router.push("/login");
    }
  }, [accessToken, router]);

  useEffect(() => {
    if (hrPolicies.length > 0) {
      // Pre-select all required policies
      const requiredPolicyIds = hrPolicies.filter(p => p.is_required).map(p => p.id);
      setHrPolicyAcknowledgements(requiredPolicyIds);
    }
  }, [hrPolicies]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setMessage("");
    
    // Validate passwords match
    if (newPassword !== confirmPassword) {
      setMessage("Passwords do not match.");
      setLoading(false);
      return;
    }
    
    if (!acceptPrivacyPolicy || !acceptTermsOfService) {
      setMessage("You must accept Privacy Policy and Terms of Service.");
      setLoading(false);
      return;
    }
    
    // Check if all required HR policies are acknowledged
    const requiredPolicyIds = hrPolicies.filter(p => p.is_required).map(p => p.id);
    const acknowledgedIds = new Set(hrPolicyAcknowledgements);
    const allRequiredAcknowledged = requiredPolicyIds.every(id => acknowledgedIds.has(id));
    
    if (!allRequiredAcknowledged) {
      setMessage("You must acknowledge all required HR policies.");
      setLoading(false);
      return;
    }
    
    try {
      await api.post("/auth/first-login/change-password", {
        new_password: newPassword,
        accept_privacy_policy: acceptPrivacyPolicy,
        accept_terms_of_service: acceptTermsOfService,
        email_enabled: emailEnabled,
        sms_enabled: smsEnabled,
        marketing_email_consent: marketingEmailConsent,
        hr_policy_acknowledgements: hrPolicyAcknowledgements,
      });
      
      setMessage("Password changed successfully! Redirecting to dashboard...");
      
      // Redirect to dashboard after 2 seconds
      setTimeout(() => {
        router.push("/dashboard");
      }, 2000);
    } catch (err: any) {
      setMessage(err?.response?.data?.detail ?? "Failed to change password");
    } finally {
      setLoading(false);
    }
  };

  const toggleHRPolicy = (policyId: number) => {
    setHrPolicyAcknowledgements(prev => {
      if (prev.includes(policyId)) {
        return prev.filter(id => id !== policyId);
      } else {
        return [...prev, policyId];
      }
    });
  };

  if (!accessToken) {
    return null; // Will redirect
  }

  return (
    <div className="flex min-h-[80vh] items-center justify-center p-4">
      <div className="bg-white w-full max-w-md rounded-2xl p-8 shadow-lg border border-gray-200">
        <div className="mb-6 space-y-2 text-center">
          <p className="text-sm uppercase tracking-[0.2em] text-gray-500">First Login</p>
          <h1 className="text-2xl font-semibold text-gray-900">Change Your Password</h1>
          <p className="text-sm text-gray-600">
            For security reasons, please change your password and accept required policies.
          </p>
        </div>
        
        {loadingPolicies ? (
          <div className="text-center text-gray-600">Loading policies...</div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="text-sm text-gray-700 font-medium">New Password</label>
              <input
                className="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 placeholder:text-gray-400 outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500"
                type="password"
                placeholder="Enter new password"
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                required
              />
              <p className="mt-1 text-xs text-gray-500">Min length 12, must include a special character.</p>
            </div>

            <div>
              <label className="text-sm text-gray-700 font-medium">Confirm Password</label>
              <input
                className="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 placeholder:text-gray-400 outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500"
                type="password"
                placeholder="Confirm new password"
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                required
              />
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

            {/* HR Policy Acknowledgements */}
            {hrPolicies.length > 0 && (
              <div className="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
                <p className="text-sm font-medium text-gray-900">HR Policies</p>
                <p className="text-xs text-gray-500">Please acknowledge the following HR policies:</p>
                {hrPolicies.map((policy) => (
                  <div key={policy.id} className="space-y-2">
                    <label className="flex items-start gap-2 text-sm text-gray-700">
                      <input
                        type="checkbox"
                        checked={hrPolicyAcknowledgements.includes(policy.id)}
                        onChange={() => toggleHRPolicy(policy.id)}
                        className="mt-1"
                        required={policy.is_required}
                      />
                      <div className="flex-1">
                        <span className="font-medium">{policy.title}</span>
                        {policy.is_required && (
                          <span className="ml-2 text-xs text-red-600">Required</span>
                        )}
                      </div>
                    </label>
                    <div className="ml-6 max-h-32 overflow-y-auto rounded border border-gray-200 bg-white p-2 text-xs text-gray-600">
                      {policy.content}
                    </div>
                  </div>
                ))}
              </div>
            )}

            {/* Communication Preferences */}
            <div className="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
              <p className="text-sm font-medium text-gray-900">Communication Preferences</p>
              <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                  type="checkbox"
                  checked={emailEnabled}
                  onChange={(e) => setEmailEnabled(e.target.checked)}
                  className="h-4 w-4 rounded border-gray-300"
                />
                <span>Email notifications</span>
              </label>
              <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                  type="checkbox"
                  checked={smsEnabled}
                  onChange={(e) => setSmsEnabled(e.target.checked)}
                  className="h-4 w-4 rounded border-gray-300"
                />
                <span>SMS notifications</span>
              </label>
              <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                  type="checkbox"
                  checked={marketingEmailConsent}
                  onChange={(e) => setMarketingEmailConsent(e.target.checked)}
                  className="h-4 w-4 rounded border-gray-300"
                />
                <span>Marketing emails (CASL consent)</span>
              </label>
            </div>

            {message && (
              <div className={`rounded-lg p-3 text-sm ${
                message.includes("success") || message.includes("changed")
                  ? "bg-green-100 text-green-700"
                  : "bg-red-100 text-red-700"
              }`}>
                {message}
              </div>
            )}

            <button
              type="submit"
              disabled={loading}
              className="w-full rounded-lg bg-gradient-purple-blue px-4 py-2 font-semibold text-white shadow-md transition hover:opacity-90 disabled:opacity-60"
            >
              {loading ? "Changing Password..." : "Change Password & Continue"}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
