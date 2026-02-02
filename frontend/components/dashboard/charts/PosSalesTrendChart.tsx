"use client";

import { AreaChart, Area, XAxis, Tooltip, ResponsiveContainer } from "recharts";
import { formatCurrency } from "@/lib/pos-utils";

interface TrendItem {
  period: string;
  gross_sales_cents: number;
}

const CustomTooltip = ({ active, payload }: any) => {
  if (active && payload && payload.length) {
    return (
      <div className="bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-lg">
        <p className="text-gray-900 font-medium">{payload[0].payload.period}</p>
        <p className="text-purple-600 text-sm">
          {formatCurrency(payload[0].payload.gross_sales_cents)}
        </p>
      </div>
    );
  }
  return null;
};

export function PosSalesTrendChart({ data }: { data: TrendItem[] }) {
  return (
    <ResponsiveContainer width="100%" height={220}>
      <AreaChart data={data} margin={{ left: 0, right: 12 }}>
        <XAxis dataKey="period" axisLine={false} tickLine={false} tick={{ fill: "#6b7280", fontSize: 12 }} />
        <Tooltip content={<CustomTooltip />} />
        <Area type="monotone" dataKey="gross_sales_cents" stroke="#9333ea" fill="rgba(147, 51, 234, 0.2)" />
      </AreaChart>
    </ResponsiveContainer>
  );
}
