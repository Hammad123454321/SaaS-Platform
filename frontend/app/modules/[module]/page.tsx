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

  return (
    <div className="space-y-4">
      <div className="glass rounded-2xl px-5 py-4 shadow-xl">
        <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Module</p>
        <h1 className="text-2xl font-semibold text-white">{moduleCode?.toUpperCase()}</h1>
        <p className="text-sm text-gray-200/80">Stubbed data until vendor integrations are added.</p>
      </div>

      <div className="glass rounded-2xl p-4 shadow-xl">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold text-white">Records</h2>
          {loading && <span className="text-xs text-gray-200/80">Loading...</span>}
        </div>
        <div className="mt-3 grid gap-2">
          {records.map((rec, idx) => (
            <div key={idx} className="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white">
              <pre className="whitespace-pre-wrap text-gray-100">{JSON.stringify(rec, null, 2)}</pre>
            </div>
          ))}
          {records.length === 0 && !loading && (
            <div className="rounded-lg border border-dashed border-white/20 px-3 py-6 text-center text-sm text-gray-200/80">
              No records yet for this module.
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

