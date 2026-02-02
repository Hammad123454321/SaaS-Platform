"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { ReactNode } from "react";
import { ShoppingCart, CreditCard, RotateCcw, History, Package, Settings2, Truck, ChefHat, Sliders } from "lucide-react";
import { usePosSessionStore } from "@/lib/pos-store";

const tabs = [
  { href: "/modules/pos", label: "New Sale", icon: ShoppingCart },
  { href: "/modules/pos/checkout", label: "Checkout", icon: CreditCard },
  { href: "/modules/pos/returns", label: "Returns", icon: RotateCcw },
  { href: "/modules/pos/history", label: "History", icon: History },
  { href: "/modules/pos/inventory", label: "Inventory", icon: Package },
  { href: "/modules/pos/fulfillment", label: "Fulfillment", icon: Truck },
  { href: "/modules/pos/kitchen", label: "Kitchen", icon: ChefHat },
  { href: "/modules/pos/management", label: "Management", icon: Sliders },
  { href: "/modules/pos/register", label: "Register", icon: Settings2 },
];

export default function POSLayout({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const { registerId, registerSessionId } = usePosSessionStore();

  return (
    <div className="space-y-6">
      <div className="bg-white rounded-2xl p-6 shadow-sm border border-gray-200">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm text-gray-500">Axiom9 - Point of Sale</p>
            <h1 className="text-2xl font-bold text-gray-900">POS Operations</h1>
          </div>
          <div className="text-sm text-gray-600">
            {registerSessionId ? (
              <span className="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700">
                Register Active
              </span>
            ) : (
              <span className="px-3 py-1 rounded-full bg-orange-100 text-orange-700">
                No Register Session
              </span>
            )}
          </div>
        </div>
      </div>

      <div className="flex flex-wrap gap-2 border-b border-purple-200 pb-2">
        {tabs.map((tab) => {
          const active = pathname === tab.href;
          const Icon = tab.icon;
          return (
            <Link
              key={tab.href}
              href={tab.href}
              className={`flex items-center gap-2 px-4 py-2 rounded-t-lg text-sm font-medium transition ${
                active
                  ? "text-purple-700 border-b-2 border-purple-500 bg-white/60"
                  : "text-gray-500 hover:text-gray-700 hover:bg-white/40"
              }`}
            >
              <Icon className="h-4 w-4" />
              {tab.label}
            </Link>
          );
        })}
      </div>

      {children}
    </div>
  );
}
