"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useState, useEffect, Suspense, useCallback } from "react";
import { api } from "@/lib/api";
import Link from "next/link";

export const dynamic = 'force-dynamic';

function VerifyEmailForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const token = searchParams.get("token");
  const email = searchParams.get("email");
  
  const [status, setStatus] = useState<"pending" | "verifying" | "success" | "error">("pending");
  const [message, setMessage] = useState("");
  const [resendLoading, setResendLoading] = useState(false);

  // Check if user is already verified (development mode)
  useEffect(() => {
    const checkVerificationStatus = async () => {
      try {
        const res = await api.get("/auth/verification-status");
        if (res.data.email_verified) {
          // Already verified (development mode), redirect to onboarding
          router.push("/onboarding");
          return;
        }
      } catch (err) {
        // If not logged in or error, continue with normal flow
        console.error("Failed to check verification status:", err);
      }
    };
    
    // Only check if no token (user just landed on page)
    if (!token) {
      checkVerificationStatus();
    }
  }, [token, router]);

  const verifyEmail = useCallback(async (verificationToken: string) => {
    setStatus("verifying");
    try {
      const res = await api.post("/auth/verify-email", { token: verificationToken });
      setStatus("success");
      setMessage(res.data.message || "Email verified successfully!");
      
      // Redirect to onboarding (next stage) after 3 seconds
      setTimeout(() => {
        router.push("/onboarding");
      }, 3000);
    } catch (err: any) {
      setStatus("error");
      setMessage(err?.response?.data?.detail || "Verification failed. The link may be invalid or expired.");
    }
  }, [router]);

  useEffect(() => {
    if (token) {
      verifyEmail(token);
    }
  }, [token, verifyEmail]);

  const handleResend = async () => {
    if (!email) {
      setMessage("Email address is required to resend verification.");
      return;
    }
    
    setResendLoading(true);
    setMessage("");
    try {
      const res = await api.post("/auth/resend-verification", { email });
      setMessage(res.data.message || "Verification email sent!");
    } catch (err: any) {
      setMessage(err?.response?.data?.detail || "Failed to resend verification email.");
    } finally {
      setResendLoading(false);
    }
  };

  return (
    <div className="flex min-h-[80vh] items-center justify-center p-4">
      <div className="bg-white w-full max-w-md rounded-2xl p-8 shadow-lg border border-gray-200 text-center">
        {status === "pending" && !token && (
          <>
            <div className="mb-6 space-y-2">
              <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-purple-100">
                <svg className="h-8 w-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
              <h1 className="text-2xl font-semibold text-gray-900">Check Your Email</h1>
              <p className="text-sm text-gray-600">
                We&apos;ve sent a verification link to <strong>{email || "your email"}</strong>
              </p>
              <p className="mt-2 text-xs text-gray-500">
                Please click the link in the email to verify your account. The link will expire in 24 hours.
              </p>
            </div>
            <div className="space-y-3">
              <button
                onClick={handleResend}
                disabled={resendLoading}
                className="w-full rounded-lg border border-purple-300 bg-purple-50 px-4 py-2 text-sm font-medium text-purple-700 transition hover:bg-purple-100 disabled:opacity-60"
              >
                {resendLoading ? "Sending..." : "Resend Verification Email"}
              </button>
              <Link
                href="/login"
                className="block w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
              >
                Back to Login
              </Link>
            </div>
            {message && <p className="mt-4 text-sm text-purple-600">{message}</p>}
          </>
        )}

        {status === "verifying" && (
          <div className="mb-6 space-y-2">
            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-purple-100">
              <div className="h-8 w-8 animate-spin rounded-full border-4 border-purple-500 border-t-transparent"></div>
            </div>
            <h1 className="text-2xl font-semibold text-gray-900">Verifying...</h1>
            <p className="text-sm text-gray-600">Please wait while we verify your email.</p>
          </div>
        )}

        {status === "success" && (
          <div className="mb-6 space-y-2">
            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
              <svg className="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <h1 className="text-2xl font-semibold text-gray-900">Email Verified!</h1>
            <p className="text-sm text-gray-600">{message}</p>
            <p className="mt-2 text-xs text-gray-500">Redirecting to login...</p>
          </div>
        )}

        {status === "error" && (
          <>
            <div className="mb-6 space-y-2">
            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
              <svg className="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </div>
            <h1 className="text-2xl font-semibold text-gray-900">Verification Failed</h1>
            <p className="text-sm text-gray-600">{message}</p>
            </div>
            <div className="space-y-3">
              <button
                onClick={handleResend}
                disabled={resendLoading}
              className="w-full rounded-lg border border-purple-300 bg-purple-50 px-4 py-2 text-sm font-medium text-purple-700 transition hover:bg-purple-100 disabled:opacity-60"
              >
                {resendLoading ? "Sending..." : "Resend Verification Email"}
              </button>
              <Link
                href="/login"
              className="block w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
              >
                Back to Login
              </Link>
            </div>
          </>
        )}
      </div>
    </div>
  );
}

export default function VerifyEmailPage() {
  return (
    <Suspense fallback={
      <div className="flex min-h-[80vh] items-center justify-center">
        <div className="text-center">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-purple-500 border-t-transparent mx-auto mb-4"></div>
          <p className="text-gray-600">Loading...</p>
        </div>
      </div>
    }>
      <VerifyEmailForm />
    </Suspense>
  );
}
