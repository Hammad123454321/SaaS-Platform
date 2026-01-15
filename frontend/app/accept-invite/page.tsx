"use client";

import { useState, useEffect } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { api } from "@/lib/api";
import { useHRPoliciesForInvitation } from "@/hooks/compliance/useHRPolicies";
import Link from "next/link";

export default function AcceptInvitePage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  
  // This page is no longer used - users are created directly and receive credentials via email
  // Redirect to login page
  useEffect(() => {
    router.push("/login");
  }, [router]);

  return (
    <div className="flex min-h-[80vh] items-center justify-center">
      <div className="glass w-full max-w-md rounded-2xl p-8 shadow-2xl text-center">
        <h1 className="text-2xl font-semibold text-white">Redirecting...</h1>
        <p className="mt-2 text-sm text-gray-200/80">Please wait while we redirect you to the login page.</p>
      </div>
    </div>
  );
}

