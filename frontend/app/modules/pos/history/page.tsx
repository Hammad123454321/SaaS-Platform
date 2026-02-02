"use client";

import { useState } from "react";
import Link from "next/link";
import { usePosSalesHistory } from "@/hooks/usePos";
import { formatCurrency } from "@/lib/pos-utils";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

export default function POSHistoryPage() {
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [status, setStatus] = useState("all");

  const filters: Record<string, any> = {};
  if (startDate) filters.start_date = startDate;
  if (endDate) filters.end_date = endDate;
  if (status !== "all") filters.status = status;

  const { data } = usePosSalesHistory(filters);

  return (
    <div className="space-y-4">
      <Card className="p-4">
        <div className="grid gap-3 sm:grid-cols-3">
          <Input
            type="date"
            value={startDate}
            onChange={(e) => setStartDate(e.target.value)}
          />
          <Input
            type="date"
            value={endDate}
            onChange={(e) => setEndDate(e.target.value)}
          />
          <Select value={status} onValueChange={(value) => setStatus(value)}>
            <SelectTrigger>
              <SelectValue placeholder="Status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Statuses</SelectItem>
              <SelectItem value="draft">Draft</SelectItem>
              <SelectItem value="completed">Completed</SelectItem>
              <SelectItem value="refunded">Refunded</SelectItem>
              <SelectItem value="voided">Voided</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </Card>

      <Card className="p-4">
        <div className="space-y-3">
          {(data || []).map((sale: any) => (
            <div key={sale.id} className="flex items-center justify-between border-b border-gray-100 pb-2">
              <div>
                <p className="text-sm font-medium text-gray-900">Sale {sale.id}</p>
                <p className="text-xs text-gray-500">Status: {sale.status}</p>
                {sale.channel && (
                  <p className="text-xs text-gray-500">Channel: {sale.channel}</p>
                )}
                {sale.fulfillment?.status && (
                  <p className="text-xs text-gray-500">Fulfillment: {sale.fulfillment.status}</p>
                )}
              </div>
              <div className="flex items-center gap-3">
                <span className="text-sm font-semibold text-gray-900">
                  {formatCurrency(sale.total_cents)}
                </span>
                <Link
                  href={`/modules/pos/receipt/${sale.id}`}
                  className="text-xs text-purple-600 hover:text-purple-700"
                >
                  View Receipt
                </Link>
              </div>
            </div>
          ))}
          {data?.length === 0 && (
            <p className="text-sm text-gray-500">No sales found for the selected filters.</p>
          )}
        </div>
      </Card>
    </div>
  );
}
