"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { useSessionStore } from "@/lib/store";
import { useRouter } from "next/navigation";
import { Building2, Users, CreditCard } from "lucide-react";

type Tenant = {
  id: number;
  name: string;
  created_at: string;
};

export default function TenantsPage() {
  const router = useRouter();
  const { user } = useSessionStore();
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!user?.is_super_admin) {
      router.push("/dashboard");
      return;
    }
    loadTenants();
  }, [user, router]);

  const loadTenants = async () => {
    setLoading(true);
    try {
      const res = await api.get<{ tenants: Tenant[] }>("/admin/tenants");
      setTenants(res.data.tenants);
    } catch (err) {
      console.error("Failed to load tenants:", err);
    } finally {
      setLoading(false);
    }
  };

  if (!user?.is_super_admin) return null;

  return (
    <div className="space-y-6">
      <header className="glass rounded-2xl px-6 py-5 shadow-xl">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm uppercase tracking-[0.2em] text-cyan-200">Super Admin</p>
            <h1 className="text-3xl font-semibold text-white">Tenant Management</h1>
            <p className="text-sm text-gray-200/80">View and manage all tenant organizations.</p>
          </div>
          <Building2 className="h-8 w-8 text-cyan-300" />
        </div>
      </header>

      <div className="glass rounded-2xl p-5 shadow-xl">
        {loading ? (
          <div className="text-center py-8 text-gray-200">Loading tenants...</div>
        ) : (
          <div className="space-y-3">
            {tenants.map((tenant) => (
              <div
                key={tenant.id}
                className="flex items-center justify-between rounded-lg border border-white/10 bg-white/5 px-4 py-3"
              >
                <div className="flex items-center gap-3">
                  <Building2 className="h-5 w-5 text-cyan-300" />
                  <div>
                    <div className="font-semibold text-white">{tenant.name}</div>
                    <div className="text-xs text-gray-200/80">
                      Created: {new Date(tenant.created_at).toLocaleDateString()}
                    </div>
                  </div>
                </div>
                <div className="flex items-center gap-2 text-sm text-gray-200/80">
                  <Users className="h-4 w-4" />
                  <CreditCard className="h-4 w-4" />
                </div>
              </div>
            ))}
            {tenants.length === 0 && (
              <div className="text-center py-8 text-gray-200/80">No tenants found.</div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

