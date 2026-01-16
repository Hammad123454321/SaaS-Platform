"use client";

import { Building2, ArrowRight, CheckCircle2, Clock } from "lucide-react";
import Link from "next/link";

interface Tenant {
  id: number;
  name: string;
  slug: string;
  createdAt: string;
  userCount: number;
  status: "active" | "trial" | "inactive";
  plan?: string;
}

interface RecentTenantsWidgetProps {
  tenants: Tenant[];
  className?: string;
}

const statusConfig: Record<string, { color: string; bg: string; label: string }> = {
  active: { color: "text-emerald-600", bg: "bg-emerald-100", label: "Active" },
  trial: { color: "text-amber-600", bg: "bg-amber-100", label: "Trial" },
  inactive: { color: "text-gray-500", bg: "bg-gray-100", label: "Inactive" },
};

export function RecentTenantsWidget({ tenants, className }: RecentTenantsWidgetProps) {
  return (
    <div className={`glass rounded-2xl p-5 ${className}`}>
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <Building2 className="h-5 w-5 text-purple-600" />
          <h3 className="text-lg font-semibold text-gray-900">Recent Tenants</h3>
        </div>
        <Link 
          href="/admin/tenants" 
          className="text-sm text-purple-600 hover:text-purple-700 font-medium"
        >
          View All →
        </Link>
      </div>

      {tenants.length === 0 ? (
        <div className="text-center py-8">
          <Building2 className="h-10 w-10 mx-auto mb-2 text-gray-300" />
          <p className="text-gray-500">No tenants found</p>
        </div>
      ) : (
        <div className="space-y-2">
          {tenants.map((tenant) => {
            const status = statusConfig[tenant.status];
            return (
              <Link
                key={tenant.id}
                href={`/admin/tenants`}
                className="block p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition group"
              >
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-lg bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center text-white font-medium">
                    {tenant.name.charAt(0)}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <h4 className="text-gray-900 font-medium truncate">{tenant.name}</h4>
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${status.bg} ${status.color}`}>
                        {status.label}
                      </span>
                    </div>
                    <div className="flex items-center gap-2 mt-0.5 text-xs text-gray-500">
                      <span>{tenant.userCount} users</span>
                      <span>•</span>
                      <span>{tenant.createdAt}</span>
                    </div>
                  </div>
                  <ArrowRight className="h-4 w-4 text-gray-300 group-hover:text-gray-500 transition" />
                </div>
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
}

