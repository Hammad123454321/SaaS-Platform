"use client";

import { useState } from "react";
import { api } from "@/lib/api";
import { formatCurrency } from "@/lib/pos-utils";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

export default function POSReturnsPage() {
  const [saleId, setSaleId] = useState("");
  const [saleDetail, setSaleDetail] = useState<any>(null);
  const [loading, setLoading] = useState(false);
  const [refundItems, setRefundItems] = useState<Record<string, { quantity: number; restock: boolean }>>({});
  const [paymentMethod, setPaymentMethod] = useState<"cash" | "card" | "other">("cash");

  const lookupSale = async () => {
    if (!saleId) return;
    setLoading(true);
    try {
      const res = await api.get(`/modules/pos/sales/${saleId}`);
      setSaleDetail(res.data);
      const initial: Record<string, { quantity: number; restock: boolean }> = {};
      res.data.items.forEach((item: any) => {
        initial[item.id] = { quantity: 0, restock: true };
      });
      setRefundItems(initial);
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Sale not found");
      setSaleDetail(null);
    } finally {
      setLoading(false);
    }
  };

  const updateRefundItem = (itemId: string, updates: Partial<{ quantity: number; restock: boolean }>) => {
    setRefundItems((prev) => ({
      ...prev,
      [itemId]: { ...prev[itemId], ...updates },
    }));
  };

  const submitRefund = async () => {
    if (!saleDetail) return;
    const items = Object.entries(refundItems)
      .filter(([, value]) => value.quantity > 0)
      .map(([sale_item_id, value]) => ({
        sale_item_id,
        quantity: value.quantity,
        restock: value.restock,
      }));

    if (items.length === 0) {
      alert("Select at least one item to refund.");
      return;
    }

    try {
      await api.post("/modules/pos/refunds", {
        sale_id: saleDetail.sale.id,
        items,
        payment_method: paymentMethod,
      });
      alert("Refund processed.");
      setSaleDetail(null);
      setSaleId("");
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Refund failed");
    }
  };

  return (
    <div className="space-y-4">
      <Card className="p-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
          <Input
            placeholder="Enter Sale ID"
            value={saleId}
            onChange={(e) => setSaleId(e.target.value)}
          />
          <Button onClick={lookupSale} disabled={loading}>
            {loading ? "Searching..." : "Find Sale"}
          </Button>
        </div>
      </Card>

      {saleDetail && (
        <Card className="p-4 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold text-gray-900">Refund Items</h2>
            <Select value={paymentMethod} onValueChange={(value) => setPaymentMethod(value as any)}>
              <SelectTrigger className="w-40">
                <SelectValue placeholder="Refund Method" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="cash">Cash</SelectItem>
                <SelectItem value="card">Card</SelectItem>
                <SelectItem value="other">Other</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-3">
            {saleDetail.items.map((item: any) => (
              <div key={item.id} className="flex flex-col gap-2 border border-gray-200 rounded-lg p-3">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-900">{item.product_name}</p>
                    <p className="text-xs text-gray-500">{formatCurrency(item.line_total_cents)}</p>
                  </div>
                  <Input
                    className="w-20"
                    type="number"
                    min={0}
                    max={item.quantity}
                    value={refundItems[item.id]?.quantity || 0}
                    onChange={(e) => updateRefundItem(item.id, { quantity: Number(e.target.value) })}
                  />
                </div>
                <label className="flex items-center gap-2 text-xs text-gray-600">
                  <input
                    type="checkbox"
                    checked={refundItems[item.id]?.restock ?? true}
                    onChange={(e) => updateRefundItem(item.id, { restock: e.target.checked })}
                  />
                  Restock inventory
                </label>
              </div>
            ))}
          </div>

          <Button onClick={submitRefund}>Process Refund</Button>
        </Card>
      )}
    </div>
  );
}
