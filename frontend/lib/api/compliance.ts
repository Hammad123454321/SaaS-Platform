import apiClient from "./client";

// ========== Stage 4: Privacy & CASL Wording ==========

export interface PrivacyWordingResponse {
  wording_type: string;
  version: string;
  content: string;
}

export const getPrivacyWording = async (wordingType: "privacy_policy" | "casl"): Promise<PrivacyWordingResponse> => {
  const response = await apiClient.get(`/compliance/privacy-wording?wording_type=${wordingType}`);
  return response.data;
};

export const confirmPrivacyWording = async (wordingType: "privacy_policy" | "casl"): Promise<void> => {
  await apiClient.post("/compliance/privacy-wording/confirm", { wording_type: wordingType });
};

// ========== Stage 4: Financial Setup ==========

export interface FinancialSetup {
  id: number;
  tenant_id: number;
  payroll_type: string | null;
  pay_schedule: string | null;
  wsib_class: string | null;
  is_confirmed: boolean;
  confirmed_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface FinancialSetupCreate {
  payroll_type?: "weekly" | "bi_weekly" | "monthly" | "semi_monthly" | null;
  pay_schedule?: "monday" | "tuesday" | "wednesday" | "thursday" | "friday" | "last_day_of_month" | "first_day_of_month" | "fifteenth" | "last_friday" | null;
  wsib_class?: "retail" | "office" | "construction" | "manufacturing" | "healthcare" | "food_service" | "grooming" | "daycare" | "other" | null;
}

export const createFinancialSetup = async (data: FinancialSetupCreate): Promise<FinancialSetup> => {
  const response = await apiClient.post("/compliance/financial-setup", data);
  return response.data;
};

export const getFinancialSetup = async (): Promise<FinancialSetup> => {
  const response = await apiClient.get("/compliance/financial-setup");
  return response.data;
};

export const confirmFinancialSetup = async (): Promise<FinancialSetup> => {
  const response = await apiClient.post("/compliance/financial-setup/confirm", { confirm: true });
  return response.data;
};

// ========== Stage 4: HR Policies ==========

export interface HRPolicy {
  id: number;
  policy_type: string;
  title: string;
  content: string;
  is_required: boolean;
  version: string;
}

export const getHRPolicies = async (): Promise<HRPolicy[]> => {
  const response = await apiClient.get("/compliance/hr-policies");
  return response.data;
};

export const acknowledgeHRPolicies = async (policyIds: number[]): Promise<void> => {
  await apiClient.post("/compliance/hr-policies/acknowledge", { policy_ids: policyIds });
};

export const getAcknowledgementStatus = async (): Promise<{ has_acknowledged_all: boolean; user_id: number }> => {
  const response = await apiClient.get("/compliance/hr-policies/acknowledgement-status");
  return response.data;
};

// ========== Public endpoint for invitation ==========

export const getHRPoliciesForInvitation = async (token: string): Promise<{ policies: HRPolicy[] }> => {
  const response = await apiClient.get(`/onboarding/invitations/${token}/hr-policies`);
  return response.data;
};

