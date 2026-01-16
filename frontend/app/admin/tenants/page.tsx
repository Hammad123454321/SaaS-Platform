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
      <header className="bg-white rounded-2xl px-6 py-5 shadow-sm border border-gray-100">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm uppercase tracking-[0.2em] text-purple-600 font-medium">Super Admin</p>
            <h1 className="text-3xl font-semibold text-gray-900">Tenant Management</h1>
            <p className="text-sm text-gray-500">View and manage all tenant organizations.</p>
          </div>
          <div className="h-12 w-12 rounded-xl bg-purple-100 flex items-center justify-center">
            <Building2 className="h-6 w-6 text-purple-600" />
          </div>
        </div>
      </header>

      <div className="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        {loading ? (
          <div className="text-center py-8 text-gray-500">Loading tenants...</div>
        ) : (
          <div className="space-y-3">
            {tenants.map((tenant) => (
              <div
                key={tenant.id}
                className="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3"
              >
                <div className="flex items-center gap-3">
                  <div className="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                    <Building2 className="h-5 w-5 text-blue-600" />
                  </div>
                  <div>
                    <div className="font-semibold text-gray-900">{tenant.name}</div>
                    <div className="text-xs text-gray-500">
                      Created: {new Date(tenant.created_at).toLocaleDateString()}
                    </div>
                  </div>
                </div>
                <div className="flex items-center gap-3 text-sm text-gray-500">
                  <div className="flex items-center gap-1">
                    <Users className="h-4 w-4" />
                  </div>
                  <div className="flex items-center gap-1">
                    <CreditCard className="h-4 w-4" />
                  </div>
                </div>
              </div>
            ))}
            {tenants.length === 0 && (
              <div className="text-center py-8 text-gray-500">No tenants found.</div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

