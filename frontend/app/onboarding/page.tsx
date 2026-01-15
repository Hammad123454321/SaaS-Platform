"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { api } from "@/lib/api";
import { useFinancialSetup, useCreateFinancialSetup, useConfirmFinancialSetup } from "@/hooks/compliance/useFinancialSetup";
import { confirmFinancialSetup as confirmFinancialSetupAPI } from "@/lib/api/compliance";
import { ModuleSelection } from "./components/ModuleSelection";

type Step = "business-profile" | "owner-confirmation" | "roles" | "invitations" | "module-selection" | "compliance";

const provinces = [
  { code: "ON", label: "Ontario" },
  { code: "QC", label: "Quebec" },
  { code: "BC", label: "British Columbia" },
  { code: "AB", label: "Alberta" },
  { code: "MB", label: "Manitoba" },
  { code: "SK", label: "Saskatchewan" },
  { code: "NS", label: "Nova Scotia" },
  { code: "NB", label: "New Brunswick" },
  { code: "NL", label: "Newfoundland and Labrador" },
  { code: "PE", label: "Prince Edward Island" },
  { code: "NT", label: "Northwest Territories" },
  { code: "YT", label: "Yukon" },
  { code: "NU", label: "Nunavut" },
  { code: "OTHER", label: "Other" },
];

export default function OnboardingPage() {
  const router = useRouter();
  const [step, setStep] = useState<Step>("business-profile");
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState("");
  
  // Stage 1: Business Profile
  const [businessProfile, setBusinessProfile] = useState({
    legal_business_name: "",
    operating_name: "",
    province: "ON" as string,
    country: "Canada",
    timezone: "America/Toronto",
    primary_location: "",
    business_email: "",
    business_phone: "",
  });
  const [complianceRules, setComplianceRules] = useState<string[]>([]);
  const [businessProfileExists, setBusinessProfileExists] = useState(false);
  
  // Stage 2: Owner Confirmation
  const [ownerConfirmed, setOwnerConfirmed] = useState(false);
  const [ownerStatus, setOwnerStatus] = useState<any>(null);
  
  // Stage 2: Roles
  const [roles, setRoles] = useState<any[]>([]);
  const [roleTemplatesSeeded, setRoleTemplatesSeeded] = useState(false);
  
  // Stage 2: Invitations
  const [invitations, setInvitations] = useState<any[]>([]);
  const [newInvitation, setNewInvitation] = useState({ email: "", role_id: null as number | null });
  
  // Stage 4: Module Selection
  const [selectedModules, setSelectedModules] = useState<string[]>([]);
  const [modulesEnabled, setModulesEnabled] = useState(false);
  const [isDevelopmentMode, setIsDevelopmentMode] = useState(false);
  
  // Stage 5: Compliance
  const [financialSetup, setFinancialSetup] = useState({
    payroll_type: null as "weekly" | "bi_weekly" | "monthly" | "semi_monthly" | null,
    pay_schedule: null as "monday" | "tuesday" | "wednesday" | "thursday" | "friday" | "last_day_of_month" | "first_day_of_month" | "fifteenth" | "last_friday" | null,
    wsib_class: null as "retail" | "office" | "construction" | "manufacturing" | "healthcare" | "food_service" | "grooming" | "daycare" | "other" | null,
  });
  const [financialSetupConfirmed, setFinancialSetupConfirmed] = useState(false);
  
  const { mutate: createFinancialSetupMutate } = useCreateFinancialSetup();
  const { mutate: confirmFinancialSetupMutate } = useConfirmFinancialSetup();

  // Check verification status and owner status on mount
  useEffect(() => {
    checkVerificationStatus();
    checkOwnerStatus();
    loadRoles();
    loadInvitations();
    loadFinancialSetup();
    checkDevelopmentMode();
  }, []);

  const checkDevelopmentMode = () => {
    // Check if we're in development mode (check environment or API)
    const isDev = process.env.NODE_ENV === "development" || 
                  window.location.hostname === "localhost" ||
                  window.location.hostname === "127.0.0.1";
    setIsDevelopmentMode(isDev);
  };

  const checkVerificationStatus = async () => {
    try {
      const res = await api.get("/auth/verification-status");
      if (!res.data.email_verified) {
        // Only redirect to verification if not in development mode
        // In development mode, email is auto-verified, so skip this check
        router.push("/auth/verify-email");
      }
    } catch (err: any) {
      // If verification status check fails (e.g., not logged in, 401), 
      // redirect to login instead of blocking
      if (err?.response?.status === 401) {
        router.push("/login?redirect=/onboarding");
      } else {
        console.error("Failed to check verification status:", err);
      }
    }
  };

  const checkOwnerStatus = async () => {
    try {
      const res = await api.get("/onboarding/owner/status");
      setOwnerStatus(res.data);
      if (res.data.is_owner) {
        setOwnerConfirmed(true);
      }
    } catch (err) {
      console.error("Failed to check owner status:", err);
    }
  };

  const loadBusinessProfile = async () => {
    try {
      const res = await api.get("/onboarding/business-profile");
      setBusinessProfile({
        legal_business_name: res.data.legal_business_name || "",
        operating_name: res.data.operating_name || "",
        province: res.data.province || "ON",
        country: res.data.country || "Canada",
        timezone: res.data.timezone || "America/Toronto",
        primary_location: res.data.primary_location || "",
        business_email: res.data.business_email || "",
        business_phone: res.data.business_phone || "",
      });
      setComplianceRules(res.data.activated_compliance_rules || []);
      setBusinessProfileExists(true); // Profile exists, so we can update it
    } catch (err: any) {
      if (err.response?.status === 404) {
        setBusinessProfileExists(false); // Profile doesn't exist yet
      } else {
        console.error("Failed to load business profile:", err);
      }
    }
  };

  const loadRoles = async () => {
    try {
      const res = await api.get("/onboarding/roles");
      setRoles(res.data);
    } catch (err) {
      console.error("Failed to load roles:", err);
    }
  };

  const loadInvitations = async () => {
    try {
      const res = await api.get("/onboarding/invitations");
      setInvitations(res.data);
    } catch (err) {
      console.error("Failed to load invitations:", err);
    }
  };
  
  const loadFinancialSetup = async () => {
    try {
      const res = await api.get("/compliance/financial-setup");
      setFinancialSetup({
        payroll_type: res.data.payroll_type,
        pay_schedule: res.data.pay_schedule,
        wsib_class: res.data.wsib_class,
      });
      setFinancialSetupConfirmed(res.data.is_confirmed);
    } catch (err: any) {
      if (err.response?.status !== 404) {
        console.error("Failed to load financial setup:", err);
      }
    }
  };

  const handleEnableModules = async () => {
    if (selectedModules.length === 0) {
      setMessage("Please select at least one module.");
      return;
    }

    setLoading(true);
    setMessage("");

    try {
      // Enable each selected module via entitlements API
      for (const moduleCode of selectedModules) {
        await api.post(`/entitlements/${moduleCode}`, {
          enabled: true,
          seats: 1, // Default seats
          ai_access: moduleCode === "ai", // Enable AI access if AI module is selected
        });
      }

      setModulesEnabled(true);
      setMessage("Modules enabled successfully!");
      setStep("compliance");
    } catch (err: any) {
      setMessage(err?.response?.data?.detail || "Failed to enable modules.");
    } finally {
      setLoading(false);
    }
  };

  const handleFinancialSetupSubmit = async () => {
    setLoading(true);
    setMessage("");
    try {
      createFinancialSetupMutate(financialSetup, {
        onSuccess: async () => {
          setMessage("Financial setup saved successfully!");
          // Reload financial setup to get updated confirmation status
          await loadFinancialSetup();
          // Auto-confirm after saving to allow user to proceed
          try {
            const confirmedSetup = await confirmFinancialSetupAPI();
            setFinancialSetupConfirmed(confirmedSetup.is_confirmed);
            setMessage("Financial setup saved and confirmed successfully!");
          } catch (confirmErr: any) {
            // If confirmation fails, still allow user to manually confirm
            console.error("Auto-confirmation failed:", confirmErr);
            // Don't show error to user, they can manually confirm
          } finally {
            setLoading(false);
          }
        },
        onError: (err: any) => {
          setMessage(err?.response?.data?.detail || "Failed to save financial setup.");
          setLoading(false);
        },
        onSettled: () => {
          // Only set loading to false if confirmation didn't handle it
          if (!financialSetupConfirmed) {
            setLoading(false);
          }
        },
      });
    } catch (err: any) {
      setMessage(err?.response?.data?.detail || "Failed to save financial setup.");
      setLoading(false);
    }
  };
  
  const handleFinancialSetupConfirm = async () => {
    setLoading(true);
    setMessage("");
    try {
      confirmFinancialSetupMutate(undefined, {
        onSuccess: () => {
          setFinancialSetupConfirmed(true);
          setMessage("Financial setup confirmed successfully! Redirecting to dashboard...");
          // Redirect to dashboard - onboarding completion is checked automatically by the backend
          setTimeout(() => {
            router.push("/dashboard");
          }, 1000);
        },
        onError: (err: any) => {
          setMessage(err?.response?.data?.detail || "Failed to confirm financial setup.");
        },
        onSettled: () => {
          setLoading(false);
        },
      });
    } catch (err: any) {
      setMessage(err?.response?.data?.detail || "Failed to confirm financial setup.");
      setLoading(false);
    }
  };

  useEffect(() => {
    loadBusinessProfile();
  }, []);

  const handleBusinessProfileSubmit = async () => {
    if (!businessProfile.legal_business_name || !businessProfile.province) {
      setMessage("Please fill in all required fields.");
      return;
    }
    
    setLoading(true);
    setMessage("");
    try {
      // Use PUT if profile exists, POST if it doesn't
      const res = businessProfileExists
        ? await api.put("/onboarding/business-profile", businessProfile)
        : await api.post("/onboarding/business-profile", businessProfile);
      setComplianceRules(res.data.activated_compliance_rules || []);
      setBusinessProfileExists(true); // Mark as saved
      setStep("owner-confirmation");
    } catch (err: any) {
      setMessage(err?.response?.data?.detail || "Failed to save business profile.");
    } finally {
      setLoading(false);
    }
  };

  const handleBusinessProfileContinue = () => {
    // If profile already exists, just continue without saving
    if (businessProfileExists) {
      setStep("owner-confirmation");
    } else {
      // If profile doesn't exist, save it first
      handleBusinessProfileSubmit();
    }
  };

  const handleOwnerConfirmation = async () => {
    if (!ownerConfirmed) {
      setMessage("You must accept the responsibility disclaimer to confirm Owner role.");
      return;
    }
    
    setLoading(true);
    setMessage("");
    try {
      await api.post("/onboarding/owner/confirm", {
        responsibility_disclaimer_accepted: true
      });
      setOwnerConfirmed(true);
      await checkOwnerStatus();
      setStep("roles");
    } catch (err: any) {
      setMessage(err?.response?.data?.detail || "Failed to confirm owner role.");
    } finally {
      setLoading(false);
    }
  };

  const handleSeedRoleTemplates = async () => {
    setLoading(true);
    setMessage("");
    try {
      await api.post("/onboarding/roles/seed-templates");
      await loadRoles();
      setRoleTemplatesSeeded(true);
      setMessage("Role templates seeded successfully!");
    } catch (err: any) {
      setMessage(err?.response?.data?.detail || "Failed to seed role templates.");
    } finally {
      setLoading(false);
    }
  };

  const handleCreateInvitation = async () => {
    if (!newInvitation.email) {
      setMessage("Please enter an email address.");
      return;
    }
    
    setLoading(true);
    setMessage("");
    try {
      await api.post("/onboarding/invitations", {
        email: newInvitation.email,
        role_id: newInvitation.role_id || null
      });
      setNewInvitation({ email: "", role_id: null });
      await loadInvitations();
      setMessage("Invitation sent successfully!");
    } catch (err: any) {
      setMessage(err?.response?.data?.detail || "Failed to send invitation.");
    } finally {
      setLoading(false);
    }
  };

  const next = () => {
    if (step === "business-profile") {
      setStep("owner-confirmation");
    } else if (step === "owner-confirmation") {
      setStep("roles");
    } else if (step === "roles") {
      setStep("invitations");
    } else if (step === "invitations") {
      setStep("module-selection");
    } else if (step === "module-selection") {
      setStep("compliance");
    }
  };

  const prev = () => {
    if (step === "compliance") {
      setStep("module-selection");
    } else if (step === "module-selection") {
      setStep("invitations");
    } else if (step === "invitations") {
      setStep("roles");
    } else if (step === "roles") {
      setStep("owner-confirmation");
    } else if (step === "owner-confirmation") {
      setStep("business-profile");
    }
  };

  const canProceed = () => {
    if (step === "business-profile") {
      return businessProfile.legal_business_name && businessProfile.province;
    } else if (step === "owner-confirmation") {
      return ownerConfirmed;
    } else if (step === "module-selection") {
      return selectedModules.length > 0;
    } else if (step === "compliance") {
      // Allow proceeding if financial setup has been saved (has at least one value)
      return financialSetup.payroll_type !== null || financialSetup.pay_schedule !== null || financialSetup.wsib_class !== null;
    }
    return true;
  };

  return (
    <div className="mx-auto max-w-4xl space-y-6 p-6">
      <header className="glass rounded-2xl px-6 py-5 shadow-xl">
        <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Onboarding</p>
        <h1 className="text-3xl font-semibold text-white">Set up your workspace</h1>
        <p className="text-sm text-gray-200/80">Complete your business profile and configure access.</p>
      </header>

      <div className="glass rounded-2xl p-6 shadow-xl">
        {/* Step Indicators */}
        <div className="mb-6 flex items-center gap-3 text-sm font-semibold text-white">
          <span className={`rounded-full px-3 py-1 ${step === "business-profile" ? "bg-cyan-400/20 text-cyan-400" : "bg-white/5"}`}>
            1. Business Profile
          </span>
          <span className={`rounded-full px-3 py-1 ${step === "owner-confirmation" ? "bg-cyan-400/20 text-cyan-400" : "bg-white/5"}`}>
            2. Owner Confirmation
          </span>
          <span className={`rounded-full px-3 py-1 ${step === "roles" ? "bg-cyan-400/20 text-cyan-400" : "bg-white/5"}`}>
            3. Roles
          </span>
          <span className={`rounded-full px-3 py-1 ${step === "invitations" ? "bg-cyan-400/20 text-cyan-400" : "bg-white/5"}`}>
            4. Team Invitations
          </span>
          <span className={`rounded-full px-3 py-1 ${step === "module-selection" ? "bg-cyan-400/20 text-cyan-400" : "bg-white/5"}`}>
            5. Module Selection
          </span>
          <span className={`rounded-full px-3 py-1 ${step === "compliance" ? "bg-cyan-400/20 text-cyan-400" : "bg-white/5"}`}>
            6. Compliance
          </span>
        </div>

        {message && (
          <div className={`mb-4 rounded-lg p-3 text-sm ${
            message.includes("success") ? "bg-green-500/20 text-green-400" : "bg-red-500/20 text-red-400"
          }`}>
            {message}
          </div>
        )}

        {/* Step 1: Business Profile */}
        {step === "business-profile" && (
          <div className="space-y-4">
            <h2 className="text-xl font-semibold text-white">Business Profile</h2>
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="text-sm text-gray-200/80">Legal Business Name *</label>
                <input
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={businessProfile.legal_business_name}
                  onChange={(e) => setBusinessProfile({ ...businessProfile, legal_business_name: e.target.value })}
                  placeholder="Acme Corp Inc."
                  required
                />
              </div>
              <div>
                <label className="text-sm text-gray-200/80">Operating Name (if different)</label>
                <input
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={businessProfile.operating_name}
                  onChange={(e) => setBusinessProfile({ ...businessProfile, operating_name: e.target.value })}
                  placeholder="Acme"
                />
              </div>
              <div>
                <label className="text-sm text-gray-200/80">Province *</label>
                <select
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={businessProfile.province}
                  onChange={(e) => setBusinessProfile({ ...businessProfile, province: e.target.value })}
                  required
                >
                  {provinces.map((p) => (
                    <option key={p.code} value={p.code} className="bg-gray-800">
                      {p.label}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="text-sm text-gray-200/80">Country</label>
                <input
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={businessProfile.country}
                  onChange={(e) => setBusinessProfile({ ...businessProfile, country: e.target.value })}
                  placeholder="Canada"
                />
              </div>
              <div>
                <label className="text-sm text-gray-200/80">Timezone</label>
                <input
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={businessProfile.timezone}
                  onChange={(e) => setBusinessProfile({ ...businessProfile, timezone: e.target.value })}
                  placeholder="America/Toronto"
                />
              </div>
              <div>
                <label className="text-sm text-gray-200/80">Primary Location</label>
                <input
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={businessProfile.primary_location}
                  onChange={(e) => setBusinessProfile({ ...businessProfile, primary_location: e.target.value })}
                  placeholder="123 Main St, Toronto, ON"
                />
              </div>
              <div>
                <label className="text-sm text-gray-200/80">Business Email</label>
                <input
                  type="email"
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={businessProfile.business_email}
                  onChange={(e) => setBusinessProfile({ ...businessProfile, business_email: e.target.value })}
                  placeholder="info@acme.com"
                />
              </div>
              <div>
                <label className="text-sm text-gray-200/80">Business Phone</label>
                <input
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={businessProfile.business_phone}
                  onChange={(e) => setBusinessProfile({ ...businessProfile, business_phone: e.target.value })}
                  placeholder="+1 (555) 123-4567"
                />
              </div>
            </div>
            
            {complianceRules.length > 0 && (
              <div className="mt-4 rounded-lg border border-cyan-400/20 bg-cyan-400/10 p-4">
                <p className="text-sm font-medium text-cyan-400">Activated Compliance Rules:</p>
                <div className="mt-2 flex flex-wrap gap-2">
                  {complianceRules.map((rule) => (
                    <span key={rule} className="rounded-full bg-cyan-400/20 px-3 py-1 text-xs text-cyan-300">
                      {rule}
                    </span>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}

        {/* Step 2: Owner Confirmation */}
        {step === "owner-confirmation" && (
          <div className="space-y-4">
            <h2 className="text-xl font-semibold text-white">Owner Confirmation</h2>
            <div className="rounded-lg border border-white/10 bg-white/5 p-4">
              <p className="mb-4 text-sm text-gray-200/80">
                As the owner of this workspace, you acknowledge that you are responsible for:
              </p>
              <ul className="mb-4 list-disc space-y-2 pl-5 text-sm text-gray-200/80">
                <li>All compliance and legal obligations</li>
                <li>Data accuracy and completeness</li>
                <li>User access and permissions management</li>
                <li>Financial and billing decisions</li>
              </ul>
              <label className="flex items-start gap-2 text-sm text-white">
                <input
                  type="checkbox"
                  checked={ownerConfirmed}
                  onChange={(e) => setOwnerConfirmed(e.target.checked)}
                  className="mt-1"
                />
                <span>I acknowledge and accept the responsibility disclaimer</span>
              </label>
            </div>
            {ownerStatus?.is_owner && (
              <div className="rounded-lg border border-green-400/20 bg-green-400/10 p-3 text-sm text-green-400">
                ✓ Owner role confirmed
              </div>
            )}
          </div>
        )}

        {/* Step 3: Roles */}
        {step === "roles" && (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <h2 className="text-xl font-semibold text-white">Role Templates</h2>
              {!roleTemplatesSeeded && (
                <button
                  onClick={handleSeedRoleTemplates}
                  disabled={loading}
                  className="rounded-lg border border-cyan-400/50 bg-cyan-400/10 px-4 py-2 text-sm font-medium text-cyan-400 transition hover:bg-cyan-400/20 disabled:opacity-60"
                >
                  Seed Templates
                </button>
              )}
            </div>
            <p className="text-sm text-gray-200/80">
              Default role templates (Manager, Staff, Accountant) will be created for your tenant.
            </p>
            <div className="space-y-2">
              {roles.map((role) => (
                <div key={role.id} className="rounded-lg border border-white/10 bg-white/5 p-3">
                  <p className="font-medium text-white">{role.name}</p>
                  <p className="text-xs text-gray-300/80">Created: {new Date(role.created_at).toLocaleDateString()}</p>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Step 4: Team Invitations */}
        {step === "invitations" && (
          <div className="space-y-4">
            <h2 className="text-xl font-semibold text-white">Team Invitations</h2>
            <div className="rounded-lg border border-white/10 bg-white/5 p-4">
              <div className="space-y-3">
                <div>
                  <label className="text-sm text-gray-200/80">Email Address</label>
                  <input
                    type="email"
                    className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                    value={newInvitation.email}
                    onChange={(e) => setNewInvitation({ ...newInvitation, email: e.target.value })}
                    placeholder="colleague@example.com"
                  />
                </div>
                <div>
                  <label className="text-sm text-gray-200/80">Role (Optional)</label>
                  <select
                    className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                    value={newInvitation.role_id || ""}
                    onChange={(e) => setNewInvitation({ ...newInvitation, role_id: e.target.value ? parseInt(e.target.value) : null })}
                  >
                    <option value="" className="bg-gray-800">No specific role</option>
                    {roles.map((role) => (
                      <option key={role.id} value={role.id} className="bg-gray-800">
                        {role.name}
                      </option>
                    ))}
                  </select>
                </div>
                <button
                  onClick={handleCreateInvitation}
                  disabled={loading || !newInvitation.email}
                  className="w-full rounded-lg bg-gradient-to-r from-cyan-400 to-blue-600 px-4 py-2 font-semibold text-gray-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl disabled:opacity-60"
                >
                  {loading ? "Sending..." : "Send Invitation"}
                </button>
              </div>
            </div>
            
            {invitations.length > 0 && (
              <div className="mt-4 space-y-2">
                <p className="text-sm font-medium text-white">Pending Invitations</p>
                {invitations.map((inv) => (
                  <div key={inv.id} className="rounded-lg border border-white/10 bg-white/5 p-3">
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="font-medium text-white">{inv.email}</p>
                        <p className="text-xs text-gray-300/80">
                          {inv.accepted_at ? "Accepted" : "Pending"} • Expires: {new Date(inv.expires_at).toLocaleDateString()}
                        </p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Step 5: Module Selection */}
        {step === "module-selection" && (
          <ModuleSelection
            selectedModules={selectedModules}
            onSelectionChange={setSelectedModules}
            isDevelopmentMode={isDevelopmentMode}
          />
        )}

        {/* Step 6: Compliance */}
        {step === "compliance" && (
          <div className="space-y-4">
            <h2 className="text-xl font-semibold text-white">Financial Setup & Compliance</h2>
            <p className="text-sm text-gray-200/80">
              Configure your financial setup and confirm compliance requirements.
            </p>
            
            <div className="space-y-4">
              <div>
                <label className="text-sm text-gray-200/80">Payroll Type</label>
                <select
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={financialSetup.payroll_type || ""}
                  onChange={(e) => setFinancialSetup({ ...financialSetup, payroll_type: e.target.value as any || null })}
                >
                  <option value="" className="bg-gray-800">Select payroll type</option>
                  <option value="weekly" className="bg-gray-800">Weekly</option>
                  <option value="bi_weekly" className="bg-gray-800">Bi-weekly</option>
                  <option value="monthly" className="bg-gray-800">Monthly</option>
                  <option value="semi_monthly" className="bg-gray-800">Semi-monthly</option>
                </select>
              </div>
              
              <div>
                <label className="text-sm text-gray-200/80">Pay Schedule (Payday)</label>
                <select
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={financialSetup.pay_schedule || ""}
                  onChange={(e) => setFinancialSetup({ ...financialSetup, pay_schedule: e.target.value as any || null })}
                >
                  <option value="" className="bg-gray-800">Select payday</option>
                  <option value="monday" className="bg-gray-800">Monday</option>
                  <option value="tuesday" className="bg-gray-800">Tuesday</option>
                  <option value="wednesday" className="bg-gray-800">Wednesday</option>
                  <option value="thursday" className="bg-gray-800">Thursday</option>
                  <option value="friday" className="bg-gray-800">Friday</option>
                  <option value="last_day_of_month" className="bg-gray-800">Last Day of Month</option>
                  <option value="first_day_of_month" className="bg-gray-800">First Day of Month</option>
                  <option value="fifteenth" className="bg-gray-800">15th</option>
                  <option value="last_friday" className="bg-gray-800">Last Friday</option>
                </select>
              </div>
              
              <div>
                <label className="text-sm text-gray-200/80">WSIB Class</label>
                <select
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={financialSetup.wsib_class || ""}
                  onChange={(e) => setFinancialSetup({ ...financialSetup, wsib_class: e.target.value as any || null })}
                >
                  <option value="" className="bg-gray-800">Select WSIB class</option>
                  <option value="retail" className="bg-gray-800">Retail</option>
                  <option value="office" className="bg-gray-800">Office</option>
                  <option value="construction" className="bg-gray-800">Construction</option>
                  <option value="manufacturing" className="bg-gray-800">Manufacturing</option>
                  <option value="healthcare" className="bg-gray-800">Healthcare</option>
                  <option value="food_service" className="bg-gray-800">Food Service</option>
                  <option value="grooming" className="bg-gray-800">Grooming</option>
                  <option value="daycare" className="bg-gray-800">Daycare</option>
                  <option value="other" className="bg-gray-800">Other</option>
                </select>
              </div>
              
              <button
                onClick={handleFinancialSetupSubmit}
                disabled={loading}
                className="w-full rounded-lg border border-cyan-400/50 bg-cyan-400/10 px-4 py-2 text-sm font-medium text-cyan-400 transition hover:bg-cyan-400/20 disabled:opacity-60"
              >
                {loading ? "Saving..." : "Save Financial Setup"}
              </button>
              
              {financialSetupConfirmed && (
                <div className="rounded-lg border border-green-400/20 bg-green-400/10 p-3 text-sm text-green-400">
                  ✓ Financial setup confirmed
                </div>
              )}
            </div>
          </div>
        )}

        {/* Navigation */}
        <div className="mt-6 flex items-center justify-between">
          <button
            type="button"
            onClick={prev}
            disabled={step === "business-profile"}
            className="rounded-lg border border-white/20 px-4 py-2 text-sm font-semibold text-white transition hover:border-cyan-400 disabled:opacity-40"
          >
            Back
          </button>
          {step === "business-profile" ? (
            <div className="flex gap-2">
              {businessProfileExists && (
                <button
                  type="button"
                  onClick={handleBusinessProfileContinue}
                  disabled={loading}
                  className="rounded-lg border border-cyan-400/50 bg-cyan-400/10 px-4 py-2 text-sm font-semibold text-cyan-400 transition hover:bg-cyan-400/20 disabled:opacity-60"
                >
                  Continue
                </button>
              )}
              <button
                type="button"
                onClick={handleBusinessProfileSubmit}
                disabled={loading || !canProceed()}
                className="rounded-lg bg-gradient-to-r from-cyan-400 to-blue-600 px-4 py-2 text-sm font-semibold text-gray-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl disabled:opacity-60"
              >
                {loading ? "Saving..." : businessProfileExists ? "Update & Continue" : "Save & Continue"}
              </button>
            </div>
          ) : step === "owner-confirmation" ? (
            <button
              type="button"
              onClick={handleOwnerConfirmation}
              disabled={loading || !canProceed()}
              className="rounded-lg bg-gradient-to-r from-cyan-400 to-blue-600 px-4 py-2 text-sm font-semibold text-gray-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl disabled:opacity-60"
            >
              {loading ? "Confirming..." : ownerConfirmed ? "Continue" : "Confirm Owner Role"}
            </button>
          ) : step === "module-selection" ? (
            <button
              type="button"
              onClick={handleEnableModules}
              disabled={loading || selectedModules.length === 0}
              className="rounded-lg bg-gradient-to-r from-cyan-400 to-blue-600 px-4 py-2 text-sm font-semibold text-gray-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl disabled:opacity-60"
            >
              {loading ? "Enabling..." : isDevelopmentMode ? "Enable Modules (Free)" : "Continue to Payment"}
            </button>
          ) : step === "compliance" ? (
            <button
              type="button"
              onClick={handleFinancialSetupConfirm}
              disabled={loading || (!financialSetup.payroll_type && !financialSetup.pay_schedule && !financialSetup.wsib_class)}
              className="rounded-lg bg-gradient-to-r from-cyan-400 to-blue-600 px-4 py-2 text-sm font-semibold text-gray-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl disabled:opacity-60"
            >
              {loading ? "Confirming..." : financialSetupConfirmed ? "Complete Onboarding" : "Confirm & Complete"}
            </button>
          ) : (
            <button
              type="button"
              onClick={next}
              disabled={!canProceed()}
              className="rounded-lg bg-gradient-to-r from-cyan-400 to-blue-600 px-4 py-2 text-sm font-semibold text-gray-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl disabled:opacity-60"
            >
              Next
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
