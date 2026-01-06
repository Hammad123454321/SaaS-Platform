"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { api } from "@/lib/api";
import { useSessionStore } from "@/lib/store";

type Step = "company" | "modules" | "branding";

const modules = [
  { code: "crm", label: "CRM" },
  { code: "hrm", label: "HRM" },
  { code: "pos", label: "POS" },
  { code: "tasks", label: "Tasks" },
  { code: "booking", label: "Booking" },
  { code: "landing", label: "Landing Builder" },
];

export default function OnboardingPage() {
  const router = useRouter();
  const setBrandingStore = useSessionStore((s) => s.setBranding);
  const [step, setStep] = useState<Step>("company");
  const [company, setCompany] = useState({ name: "", industry: "" });
  const [selectedModules, setSelectedModules] = useState<string[]>(["crm", "tasks"]);
  const [branding, setBranding] = useState({ color: "#0ea5e9", logoUrl: "" });
  const [submitting, setSubmitting] = useState(false);

  const next = () => {
    if (step === "company") {
      if (!company.name || !company.industry) {
        alert("Please fill in company name and industry before proceeding.");
        return;
      }
      setStep("modules");
    } else if (step === "modules") {
      if (selectedModules.length === 0) {
        alert("Please select at least one module.");
        return;
      }
      setStep("branding");
    }
  };
  
  const prev = () => {
    if (step === "branding") {
      setStep("modules");
    } else if (step === "modules") {
      setStep("company");
    }
  };

  const toggleModule = (code: string) => {
    setSelectedModules((prev) =>
      prev.includes(code) ? prev.filter((m) => m !== code) : [...prev, code]
    );
  };

  const handleSubmit = async () => {
    if (!company.name || !company.industry) {
      alert("Please fill in all company information.");
      return;
    }
    if (selectedModules.length === 0) {
      alert("Please select at least one module.");
      return;
    }
    
    setSubmitting(true);
    try {
      await api.post("/onboarding", {
        company,
        modules: selectedModules,
        branding,
      });
      setBrandingStore(branding);
      router.push("/dashboard");
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to save onboarding. Please try again.");
      console.error(err);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <header className="glass rounded-2xl px-6 py-5 shadow-xl">
        <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Onboarding</p>
        <h1 className="text-3xl font-semibold text-white">Set up your workspace</h1>
        <p className="text-sm text-gray-200/80">Company info, modules, and branding in three steps.</p>
      </header>

      <div className="glass rounded-2xl p-6 shadow-xl">
        <div className="flex items-center gap-3 text-sm font-semibold text-white">
          <span className={`rounded-full px-3 py-1 ${step === "company" ? "bg-white/20" : "bg-white/5"}`}>
            1. Company
          </span>
          <span className={`rounded-full px-3 py-1 ${step === "modules" ? "bg-white/20" : "bg-white/5"}`}>
            2. Modules
          </span>
          <span className={`rounded-full px-3 py-1 ${step === "branding" ? "bg-white/20" : "bg-white/5"}`}>
            3. Branding
          </span>
        </div>

        <div className="mt-6 space-y-4">
          {step === "company" && (
            <div className="space-y-4">
              <div>
                <label className="text-sm text-gray-200/80">Company name</label>
                <input
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={company.name}
                  onChange={(e) => setCompany({ ...company, name: e.target.value })}
                  placeholder="Acme Corp"
                />
              </div>
              <div>
                <label className="text-sm text-gray-200/80">Industry</label>
                <input
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={company.industry}
                  onChange={(e) => setCompany({ ...company, industry: e.target.value })}
                  placeholder="Retail / Services / Hospitality"
                />
              </div>
            </div>
          )}

          {step === "modules" && (
            <div className="grid gap-3 sm:grid-cols-2">
              {modules.map((m) => {
                const active = selectedModules.includes(m.code);
                return (
                  <button
                    key={m.code}
                    type="button"
                    onClick={() => toggleModule(m.code)}
                    className={`rounded-lg border px-4 py-3 text-left text-sm font-semibold transition ${
                      active
                        ? "border-cyan-400 bg-white/10 text-white"
                        : "border-white/10 bg-white/5 text-gray-200"
                    }`}
                  >
                    {m.label}
                  </button>
                );
              })}
            </div>
          )}

          {step === "branding" && (
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="text-sm text-gray-200/80">Primary color</label>
                <input
                  type="color"
                  className="mt-2 h-12 w-full rounded-lg border border-white/10 bg-white/5"
                  value={branding.color}
                  onChange={(e) => setBranding({ ...branding, color: e.target.value })}
                />
              </div>
              <div>
                <label className="text-sm text-gray-200/80">Logo URL</label>
                <input
                  className="mt-1 w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-400"
                  value={branding.logoUrl}
                  onChange={(e) => setBranding({ ...branding, logoUrl: e.target.value })}
                  placeholder="https://..."
                />
              </div>
            </div>
          )}
        </div>

        <div className="mt-6 flex items-center justify-between">
          <button
            type="button"
            onClick={prev}
            disabled={step === "company"}
            className="rounded-lg border border-white/20 px-4 py-2 text-sm font-semibold text-white transition hover:border-cyan-400 disabled:opacity-40"
          >
            Back
          </button>
          {step !== "branding" ? (
            <button
              type="button"
              onClick={next}
              className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow transition hover:-translate-y-0.5"
            >
              Next
            </button>
          ) : (
            <button
              type="button"
              onClick={handleSubmit}
              disabled={submitting}
              className="rounded-lg bg-gradient-to-r from-cyan-400 to-blue-600 px-4 py-2 text-sm font-semibold text-gray-900 shadow transition hover:-translate-y-0.5 disabled:opacity-60"
            >
              {submitting ? "Saving..." : "Finish"}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

