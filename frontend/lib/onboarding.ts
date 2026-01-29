/**
 * Utility functions for checking onboarding status.
 */
import { api } from "./api";

export interface OnboardingStatus {
  subscription: {
    id: number | null;
    status: string;
    plan_name: string | null;
    amount: number;
    currency: string;
    interval: string | null;
    current_period_end: string | null;
  } | null;
  modules: Record<string, { enabled: boolean; seats: number; ai_access: boolean }>;
  taskify_configured: boolean;
  onboarding_complete: boolean;
}

/**
 * Check if user has completed onboarding.
 * Onboarding is complete if subscription exists and is active.
 */
export async function checkOnboardingStatus(): Promise<OnboardingStatus> {
  try {
    const res = await api.get("/onboarding/status");
    return res.data;
  } catch (error: any) {
    // If endpoint fails, assume onboarding not complete
    console.error("Failed to check onboarding status:", error);
    return {
      subscription: null,
      modules: {},
      taskify_configured: false,
      onboarding_complete: false,
    };
  }
}

/**
 * Check if onboarding is complete.
 */
export async function isOnboardingComplete(): Promise<boolean> {
  const status = await checkOnboardingStatus();
  return status.onboarding_complete;
}





























