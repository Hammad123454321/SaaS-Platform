"use client";

import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { formatCurrency, parseCurrency } from "@/lib/pos-utils";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

export default function POSFulfillmentPage() {
  const [statusFilter, setStatusFilter] = useState<string>("pending");
  const [updates, setUpdates] = useState<Record<string, any>>({});

  const { data, isLoading, refetch } = useQuery({
    queryKey: ["pos", "fulfillment", statusFilter],
    queryFn: async () => {
      const res = await api.get("/modules/pos/fulfillment", {
        params: { status: statusFilter || undefined },
      });
      return res.data as any[];
    },
  });

  const updateFulfillment = async (sale: any) => {
    const update = updates[sale.id] || {};
    const fulfillment = sale.fulfillment || {};
    const payload = {
      fulfillment_type: fulfillment.fulfillment_type || "shipping",
      status: update.status || fulfillment.status || "pending",
      shipping_cost_cents: update.shipping_cost_cents ?? fulfillment.shipping_cost_cents ?? 0,
      carrier: update.carrier || fulfillment.carrier || undefined,
      tracking_number: update.tracking_number || fulfillment.tracking_number || undefined,
      delivery_instructions: fulfillment.delivery_instructions || undefined,
      scheduled_for: fulfillment.scheduled_for || undefined,
      shipping_address: fulfillment.shipping_address || undefined,
    };
    await api.patch(`/modules/pos/fulfillment/${sale.id}`, payload);
    await refetch();
  };

  return (
    <div className="space-y-4">
      <Card className="p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-lg font-semibold text-gray-900">Fulfillment Queue</h2>
          <p className="text-sm text-gray-500">Manage delivery, pickup, and shipping orders.</p>
        </div>
        <Select value={statusFilter} onValueChange={setStatusFilter}>
          <SelectTrigger className="w-full sm:w-48">
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="pending">Pending</SelectItem>
            <SelectItem value="in_progress">In progress</SelectItem>
            <SelectItem value="ready">Ready</SelectItem>
            <SelectItem value="shipped">Shipped</SelectItem>
            <SelectItem value="delivered">Delivered</SelectItem>
            <SelectItem value="picked_up">Picked up</SelectItem>
            <SelectItem value="cancelled">Cancelled</SelectItem>
          </SelectContent>
        </Select>
      </Card>

      {isLoading && <Card className="p-4 text-sm text-gray-500">Loading orders...</Card>}
      {!isLoading && (data || []).length === 0 && (
        <Card className="p-4 text-sm text-gray-500">No orders for this status.</Card>
      )}

      {(data || []).map((sale: any) => {
        const fulfillment = sale.fulfillment || {};
        return (
          <Card key={sale.id} className="p-4 space-y-3">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p className="text-sm text-gray-500">Order #{sale.id}</p>
                <h3 className="text-base font-semibold text-gray-900">
                  {fulfillment.fulfillment_type || "in_store"} - {fulfillment.status || "pending"}
                </h3>
              </div>
              <div className="text-sm font-semibold text-purple-700">{formatCurrency(sale.total_cents)}</div>
            </div>

            <div className="grid gap-2 sm:grid-cols-3">
              <Select
                value={updates[sale.id]?.status || fulfillment.status || "pending"}
                onValueChange={(value) => setUpdates((prev) => ({ ...prev, [sale.id]: { ...prev[sale.id], status: value } }))}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="pending">Pending</SelectItem>
                  <SelectItem value="in_progress">In progress</SelectItem>
                  <SelectItem value="ready">Ready</SelectItem>
                  <SelectItem value="shipped">Shipped</SelectItem>
                  <SelectItem value="delivered">Delivered</SelectItem>
                  <SelectItem value="picked_up">Picked up</SelectItem>
                  <SelectItem value="cancelled">Cancelled</SelectItem>
                </SelectContent>
              </Select>
              <Input
                placeholder="Carrier"
                value={updates[sale.id]?.carrier || fulfillment.carrier || ""}
                onChange={(e) =>
                  setUpdates((prev) => ({ ...prev, [sale.id]: { ...prev[sale.id], carrier: e.target.value } }))
                }
              />
              <Input
                placeholder="Tracking #"
                value={updates[sale.id]?.tracking_number || fulfillment.tracking_number || ""}
                onChange={(e) =>
                  setUpdates((prev) => ({ ...prev, [sale.id]: { ...prev[sale.id], tracking_number: e.target.value } }))
                }
              />
            </div>
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
              <Input
                placeholder="Shipping cost"
                value={
                  updates[sale.id]?.shipping_cost_cents
                    ? (updates[sale.id].shipping_cost_cents / 100).toString()
                    : fulfillment.shipping_cost_cents
                    ? (fulfillment.shipping_cost_cents / 100).toString()
                    : ""
                }
                onChange={(e) =>
                  setUpdates((prev) => ({
                    ...prev,
                    [sale.id]: { ...prev[sale.id], shipping_cost_cents: parseCurrency(e.target.value) },
                  }))
                }
              />
              <Button variant="outline" onClick={() => updateFulfillment(sale)}>
                Update
              </Button>
            </div>
          </Card>
        );
      })}
    </div>
  );
}
