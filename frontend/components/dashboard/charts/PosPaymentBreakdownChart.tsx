"use client";

import { PieChart, Pie, Cell, Tooltip, ResponsiveContainer } from "recharts";
import { formatCurrency } from "@/lib/pos-utils";

const COLORS = ["#9333ea", "#3b82f6", "#22c55e", "#f97316", "#ec4899"]; // purple, blue, green, orange, pink

interface PaymentItem extends Record<string, unknown> {
  method: string;
  amount_cents: number;
}

const CustomTooltip = ({ active, payload }: any) => {
  if (active && payload && payload.length) {
    return (
      <div className="bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-lg">
        <p className="text-gray-900 font-medium">{payload[0].payload.method}</p>
        <p className="text-purple-600 text-sm">
          {formatCurrency(payload[0].payload.amount_cents)}
        </p>
      </div>
    );
  }
  return null;
};

export function PosPaymentBreakdownChart({ data }: { data: PaymentItem[] }) {
  return (
    <ResponsiveContainer width="100%" height={220}>
      <PieChart>
        <Pie data={data} dataKey="amount_cents" nameKey="method" innerRadius={60} outerRadius={90}>
          {data.map((entry, index) => (
            <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
          ))}
        </Pie>
        <Tooltip content={<CustomTooltip />} />
      </PieChart>
    </ResponsiveContainer>
  );
}
