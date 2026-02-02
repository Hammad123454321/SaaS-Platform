"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { formatCurrency } from "@/lib/pos-utils";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

type CartItem = {
  product_id: string;
  variant_id?: string | null;
  name: string;
  price_cents: number;
  quantity: number;
};

export default function POSStorefrontPreview() {
  const router = useRouter();
  const [cart, setCart] = useState<CartItem[]>([]);
  const [creating, setCreating] = useState(false);

  const { data: settings } = useQuery({
    queryKey: ["pos", "storefront", "preview"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/storefront/settings");
      return res.data;
    },
  });

  const { data: products } = useQuery({
    queryKey: ["pos", "storefront", "products"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/products");
      return res.data as any[];
    },
  });

  const addToCart = (product: any) => {
    setCart((prev) => {
      const existing = prev.find(
        (item) => item.product_id === product.product_id && item.variant_id === product.variant_id
      );
      if (existing) {
        return prev.map((item) =>
          item === existing ? { ...item, quantity: item.quantity + 1 } : item
        );
      }
      return [
        ...prev,
        {
          product_id: product.product_id,
          variant_id: product.variant_id,
          name: product.name,
          price_cents: product.price_cents,
          quantity: 1,
        },
      ];
    });
  };

  const createOnlineOrder = async () => {
    if (cart.length === 0 || creating) return;
    setCreating(true);
    try {
      const res = await api.post("/modules/pos/sales", {
        channel: "online",
        fulfillment: {
          fulfillment_type: "shipping",
          status: "pending",
          shipping_cost_cents: 0,
        },
        items: cart.map((item) => ({
          product_id: item.product_id,
          variant_id: item.variant_id,
          quantity: item.quantity,
        })),
      });
      router.push(`/modules/pos/checkout?saleId=${res.data.id}`);
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to create online order");
    } finally {
      setCreating(false);
    }
  };

  return (
    <div className="space-y-6">
      <Card className="p-6">
        <div className="flex flex-col gap-2">
          <p className="text-sm text-gray-500">Storefront Preview</p>
          <h1 className="text-2xl font-bold text-gray-900">{settings?.name || "Your Storefront"}</h1>
          {settings?.headline && <p className="text-gray-600">{settings.headline}</p>}
        </div>
      </Card>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {(products || []).map((product) => (
          <Card key={`${product.product_id}-${product.variant_id || "base"}`} className="p-4 space-y-2">
            {product.image_url && (
              <div className="h-32 w-full overflow-hidden rounded-md bg-gray-100">
                <img
                  src={product.image_url}
                  alt={product.name}
                  className="h-full w-full object-cover"
                  loading="lazy"
                />
              </div>
            )}
            <h3 className="text-base font-semibold text-gray-900">{product.name}</h3>
            <p className="text-sm text-gray-500">{product.sku || ""}</p>
            <span className="text-lg font-semibold text-purple-700">
              {formatCurrency(product.price_cents)}
            </span>
            <Button variant="outline" onClick={() => addToCart(product)}>
              Add to Cart
            </Button>
          </Card>
        ))}
      </div>

      <Card className="p-4 space-y-3">
        <h2 className="text-lg font-semibold text-gray-900">Online Order Cart</h2>
        {cart.length === 0 && <p className="text-sm text-gray-500">Cart is empty.</p>}
        {cart.map((item) => (
          <div key={`${item.product_id}-${item.variant_id || "base"}`} className="flex items-center justify-between text-sm">
            <span>{item.name}</span>
            <span>{item.quantity} x {formatCurrency(item.price_cents)}</span>
          </div>
        ))}
        <Button onClick={createOnlineOrder} disabled={creating}>
          {creating ? "Creating..." : "Create Online Order"}
        </Button>
      </Card>
    </div>
  );
}
