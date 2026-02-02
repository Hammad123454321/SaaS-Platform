"use client";

import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

export default function POSKitchenPage() {
  const { data, isLoading, refetch } = useQuery({
    queryKey: ["pos", "kitchen"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/kitchen");
      return res.data as any[];
    },
  });

  const updateStatus = async (itemId: string, status: string) => {
    await api.patch(`/modules/pos/kitchen/${itemId}`, null, {
      params: { status },
    });
    await refetch();
  };

  return (
    <div className="space-y-4">
      <Card className="p-4">
        <h2 className="text-lg font-semibold text-gray-900">Kitchen Display</h2>
        <p className="text-sm text-gray-500">Track kitchen items and update preparation status.</p>
      </Card>

      {isLoading && <Card className="p-4 text-sm text-gray-500">Loading tickets...</Card>}
      {!isLoading && (data || []).length === 0 && (
        <Card className="p-4 text-sm text-gray-500">No active kitchen items.</Card>
      )}

      {(data || []).map((item: any) => (
        <Card key={item.id} className="p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm text-gray-500">Order #{item.sale_id}</p>
            <h3 className="text-base font-semibold text-gray-900">
              {item.product_name} {item.variant_name ? `- ${item.variant_name}` : ""}
            </h3>
            <p className="text-sm text-gray-600">Qty {item.quantity}</p>
          </div>
          <div className="flex items-center gap-2">
            <Select
              value={item.status || "queued"}
              onValueChange={(value) => updateStatus(item.id, value)}
            >
              <SelectTrigger className="w-40">
                <SelectValue placeholder="Status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="queued">Queued</SelectItem>
                <SelectItem value="in_progress">In progress</SelectItem>
                <SelectItem value="ready">Ready</SelectItem>
                <SelectItem value="served">Served</SelectItem>
              </SelectContent>
            </Select>
            <Button variant="outline" onClick={() => updateStatus(item.id, "ready")}>Mark Ready</Button>
          </div>
        </Card>
      ))}
    </div>
  );
}
