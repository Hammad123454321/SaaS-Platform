"use client";

import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { useSessionStore } from "@/lib/store";

export default function ModulePage() {
  const params = useParams();
  const moduleCode = params.module as string;
  const { entitlements, setEntitlements } = useSessionStore();
  const [records, setRecords] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const ensureEntitlements = async () => {
      if (entitlements.length === 0) {
        try {
          const res = await api.get("/entitlements");
          setEntitlements(res.data);
        } catch (err) {
          console.error(err);
        }
      }
    };
    ensureEntitlements();
  }, [entitlements.length, setEntitlements]);

  useEffect(() => {
    const fetchRecords = async () => {
      setLoading(true);
      try {
        const res = await api.get(`/modules/${moduleCode}/records`, { params: { resource: "items" } });
        setRecords(res.data.data || []);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchRecords();
  }, [moduleCode]);

  const moduleLabels: Record<string, string> = {
    crm: "CRM",
    hrm: "HRM",
    pos: "POS",
    tasks: "Tasks",
    booking: "Booking",
    landing: "Landing Builder",
  };

  const isEnabled = entitlements.some((e) => e.module_code === moduleCode && e.enabled);

  if (!isEnabled) {
    return (
      <div className="space-y-4">
        <div className="glass rounded-2xl px-5 py-4 shadow-xl">
          <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Module Not Enabled</p>
          <h1 className="text-2xl font-semibold text-white">{moduleLabels[moduleCode] || moduleCode?.toUpperCase()}</h1>
          <p className="text-sm text-gray-200/80">
            This module is not enabled for your organization. Contact your administrator to enable it.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="glass rounded-2xl px-5 py-4 shadow-xl">
        <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Module</p>
        <h1 className="text-2xl font-semibold text-white">{moduleLabels[moduleCode] || moduleCode?.toUpperCase()}</h1>
        <p className="text-sm text-gray-200/80">
          {moduleCode === "tasks" 
            ? "Integrated with Taskify. View and manage your tasks."
            : "Module data and actions."}
        </p>
      </div>

      <div className="glass rounded-2xl p-4 shadow-xl">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold text-white">Records</h2>
          {loading && <span className="text-xs text-gray-200/80 animate-pulse">Loading...</span>}
        </div>
        {loading && records.length === 0 ? (
          <div className="text-center py-8 text-gray-200/80">Loading records...</div>
        ) : (
          <div className="mt-3 grid gap-2">
            {records.map((rec, idx) => (
              <div key={idx} className="rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-white">
                {typeof rec === "object" ? (
                  <div className="space-y-1">
                    {Object.entries(rec).map(([key, value]) => (
                      <div key={key} className="flex justify-between">
                        <span className="text-gray-300/80 capitalize">{key.replace(/_/g, " ")}:</span>
                        <span className="text-white">{String(value)}</span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div>{String(rec)}</div>
                )}
              </div>
            ))}
            {records.length === 0 && !loading && (
              <div className="rounded-lg border border-dashed border-white/20 px-3 py-6 text-center text-sm text-gray-200/80">
                No records found for this module. Start by creating your first record.
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

