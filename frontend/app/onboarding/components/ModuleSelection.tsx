"use client";

import { useState, useEffect } from "react";
import { api } from "@/lib/api";
import { Check, LayoutDashboard, Wand2 } from "lucide-react";

const moduleLabels: Record<string, string> = {
  crm: "CRM",
  hrm: "HRM",
  pos: "POS",
  tasks: "Tasks",
  booking: "Booking",
  landing: "Landing Builder",
  ai: "AI",
};

const moduleDescriptions: Record<string, string> = {
  crm: "Customer relationship management",
  hrm: "Human resources management",
  pos: "Point of sale system",
  tasks: "Task and project management",
  booking: "Appointment booking system",
  landing: "Landing page builder",
  ai: "AI-powered features",
};

// Module pricing (in cents per month)
const modulePricing: Record<string, number> = {
  crm: 2900, // $29/month
  hrm: 3900, // $39/month
  pos: 4900, // $49/month
  tasks: 1900, // $19/month
  booking: 2900, // $29/month
  landing: 1900, // $19/month
  ai: 990, // $9.90/month (add-on)
};

interface ModuleSelectionProps {
  selectedModules: string[];
  onSelectionChange: (modules: string[]) => void;
  isDevelopmentMode?: boolean;
}

export function ModuleSelection({
  selectedModules,
  onSelectionChange,
  isDevelopmentMode = false,
}: ModuleSelectionProps) {
  const [availableModules, setAvailableModules] = useState<string[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    // Get available modules from backend or use default list
    const modules = Object.keys(moduleLabels);
    setAvailableModules(modules);
  }, []);

  const toggleModule = (moduleCode: string) => {
    if (selectedModules.includes(moduleCode)) {
      onSelectionChange(selectedModules.filter((m) => m !== moduleCode));
    } else {
      onSelectionChange([...selectedModules, moduleCode]);
    }
  };

  const calculateTotal = () => {
    return selectedModules.reduce((total, module) => {
      return total + (modulePricing[module] || 0);
    }, 0);
  };

  const formatPrice = (cents: number) => {
    return `$${(cents / 100).toFixed(2)}`;
  };

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-semibold text-gray-900">Select Modules</h2>
        <p className="text-sm text-gray-600 mt-1">
          Choose the modules you want to enable for your workspace.
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {availableModules.map((moduleCode) => {
          const isSelected = selectedModules.includes(moduleCode);
          const price = modulePricing[moduleCode] || 0;
          const isAI = moduleCode === "ai";

          return (
            <div
              key={moduleCode}
              onClick={() => toggleModule(moduleCode)}
              className={`bg-white rounded-xl p-4 cursor-pointer transition-all border-2 ${
                isSelected
                  ? "border-purple-400 bg-purple-50"
                  : "border-gray-200 hover:border-purple-300"
              }`}
            >
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    {isAI ? (
                      <Wand2 className="h-5 w-5 text-purple-600" />
                    ) : (
                      <LayoutDashboard className="h-5 w-5 text-purple-600" />
                    )}
                    <h3 className="font-semibold text-gray-900">
                      {moduleLabels[moduleCode]}
                    </h3>
                  </div>
                  <p className="text-xs text-gray-600 mt-1">
                    {moduleDescriptions[moduleCode]}
                  </p>
                  {!isDevelopmentMode && (
                    <p className="text-sm font-medium text-purple-600 mt-2">
                      {formatPrice(price)}/month
                    </p>
                  )}
                </div>
                <div
                  className={`flex h-6 w-6 items-center justify-center rounded-full border-2 ${
                    isSelected
                      ? "border-purple-400 bg-purple-500"
                      : "border-gray-300"
                  }`}
                >
                  {isSelected && (
                    <Check className="h-4 w-4 text-white" />
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {!isDevelopmentMode && selectedModules.length > 0 && (
        <div className="bg-white rounded-xl border border-purple-200 bg-purple-50 p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-900">Total Monthly Cost</p>
              <p className="text-xs text-gray-600 mt-1">
                {selectedModules.length} module{selectedModules.length !== 1 ? "s" : ""} selected
              </p>
            </div>
            <div className="text-right">
              <p className="text-2xl font-bold text-purple-600">
                {formatPrice(calculateTotal())}
              </p>
              <p className="text-xs text-gray-600">per month</p>
            </div>
          </div>
        </div>
      )}

      {isDevelopmentMode && selectedModules.length > 0 && (
        <div className="bg-white rounded-xl border border-green-200 bg-green-50 p-4">
          <p className="text-sm text-green-700">
            ✓ Development mode: Billing is disabled. Selected modules will be enabled for free.
          </p>
        </div>
      )}
    </div>
  );
}



