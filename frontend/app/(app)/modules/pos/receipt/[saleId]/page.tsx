"use client";

import { useParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { formatCurrency } from "@/lib/pos-utils";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

export default function POSReceiptPage() {
  const params = useParams();
  const saleId = params.saleId as string;

  const { data, isLoading } = useQuery({
    queryKey: ["pos", "receipt", saleId],
    queryFn: async () => {
      const res = await api.get(`/modules/pos/receipts/${saleId}`);
      return res.data;
    },
  });

  const receipt = data?.rendered;

  return (
    <div className="space-y-4">
      <Card className="p-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-xl font-semibold text-gray-900">Receipt</h2>
          <Button variant="outline" onClick={() => window.print()}>
            Print
          </Button>
        </div>

        {isLoading && <p className="text-sm text-gray-500">Loading receipt...</p>}
        {!isLoading && receipt ? (
          <div className="space-y-4">
            <div className="text-sm text-gray-500">
              <p>Receipt: {data.receipt_number}</p>
              <p>Completed: {receipt.completed_at || ""}</p>
            </div>

            <div className="space-y-2">
              {receipt.items?.map((item: any, index: number) => (
                <div key={index} className="flex items-center justify-between text-sm">
                  <span>{item.product_name}</span>
                  <span>{formatCurrency(item.line_total_cents)}</span>
                </div>
              ))}
            </div>

            <div className="border-t border-gray-200 pt-3 text-sm space-y-2">
              <div className="flex items-center justify-between">
                <span>Subtotal</span>
                <span>{formatCurrency(receipt.totals.subtotal_cents)}</span>
              </div>
              <div className="flex items-center justify-between">
                <span>Discounts</span>
                <span>-{formatCurrency(receipt.totals.discount_cents)}</span>
              </div>
              <div className="flex items-center justify-between">
                <span>Tax</span>
                <span>{formatCurrency(receipt.totals.tax_cents)}</span>
              </div>
              {receipt.totals.shipping_cents ? (
                <div className="flex items-center justify-between">
                  <span>Shipping</span>
                  <span>{formatCurrency(receipt.totals.shipping_cents)}</span>
                </div>
              ) : null}
              <div className="flex items-center justify-between font-semibold text-base">
                <span>Total</span>
                <span>{formatCurrency(receipt.totals.total_cents)}</span>
              </div>
              {receipt.coupon_code && (
                <div className="flex items-center justify-between">
                  <span>Coupon</span>
                  <span className="text-gray-600">{receipt.coupon_code}</span>
                </div>
              )}
              {receipt.loyalty_points_redeemed ? (
                <div className="flex items-center justify-between">
                  <span>Loyalty Redeemed</span>
                  <span>{receipt.loyalty_points_redeemed} pts</span>
                </div>
              ) : null}
              {receipt.loyalty_points_earned ? (
                <div className="flex items-center justify-between">
                  <span>Loyalty Earned</span>
                  <span>{receipt.loyalty_points_earned} pts</span>
                </div>
              ) : null}
              <div className="flex items-center justify-between">
                <span>Paid</span>
                <span>{formatCurrency(receipt.totals.paid_cents)}</span>
              </div>
              <div className="flex items-center justify-between">
                <span>Change Due</span>
                <span>{formatCurrency(receipt.totals.change_due_cents)}</span>
              </div>
            </div>
          </div>
        ) : (
          !isLoading && <p className="text-sm text-gray-500">Receipt not found.</p>
        )}
      </Card>
    </div>
  );
}
