"use client";

import { useState, useEffect, Suspense } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { api } from "@/lib/api";
import { useHRPoliciesForInvitation } from "@/hooks/compliance/useHRPolicies";
import Link from "next/link";
import { Loader2 } from "lucide-react";

export const dynamic = 'force-dynamic';

function AcceptInviteForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  
  // This page is no longer used - users are created directly and receive credentials via email
  // Redirect to login page
  useEffect(() => {
    router.push("/login");
  }, [router]);

  return (
    <div className="flex min-h-[80vh] items-center justify-center">
      <div className="bg-white w-full max-w-md rounded-2xl p-8 shadow-lg border border-gray-100 text-center">
        <div className="flex justify-center mb-4">
          <Loader2 className="h-8 w-8 animate-spin text-purple-600" />
        </div>
        <h1 className="text-2xl font-semibold text-gray-900">Redirecting...</h1>
        <p className="mt-2 text-sm text-gray-500">Please wait while we redirect you to the login page.</p>
      </div>
    </div>
  );
}

export default function AcceptInvitePage() {
  return (
    <Suspense fallback={
      <div className="flex min-h-[80vh] items-center justify-center">
        <div className="text-center">
          <Loader2 className="h-8 w-8 animate-spin text-purple-600 mx-auto mb-4" />
          <p className="text-gray-600">Loading...</p>
        </div>
      </div>
    }>
      <AcceptInviteForm />
    </Suspense>
  );
}

