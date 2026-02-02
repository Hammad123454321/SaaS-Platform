"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { ShoppingCart, Plus, Minus } from "lucide-react";
import { usePosCategories, usePosProducts } from "@/hooks/usePos";
import { formatCurrency, parseCurrency } from "@/lib/pos-utils";
import { api } from "@/lib/api";
import { usePosSessionStore } from "@/lib/pos-store";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";


type CartItem = {
  product_id: string;
  variant_id?: string | null;
  name: string;
  price_cents: number;
  quantity: number;
  discount_type?: "percent" | "fixed";
  discount_value?: number;
};

export default function POSNewSalePage() {
  const router = useRouter();
  const { registerId, locationId, registerSessionId } = usePosSessionStore();
  const [search, setSearch] = useState("");
  const [categoryId, setCategoryId] = useState<string | null>(null);
  const [cart, setCart] = useState<CartItem[]>([]);
  const [orderDiscountType, setOrderDiscountType] = useState<"percent" | "fixed">("fixed");
  const [orderDiscountValue, setOrderDiscountValue] = useState(0);
  const [submitting, setSubmitting] = useState(false);

  const { data: categories } = usePosCategories();
  const { data: products, isLoading } = usePosProducts(search, categoryId);

  const totals = useMemo(() => {
    const subtotal = cart.reduce((sum, item) => sum + item.price_cents * item.quantity, 0);
    const lineDiscounts = cart.reduce((sum, item) => {
      if (!item.discount_value) return sum;
      if (item.discount_type === "percent") {
        return sum + Math.round((item.price_cents * item.quantity * item.discount_value) / 100);
      }
      return sum + item.discount_value;
    }, 0);

    let orderDiscount = 0;
    if (orderDiscountValue > 0) {
      if (orderDiscountType === "percent") {
        orderDiscount = Math.round((subtotal * orderDiscountValue) / 100);
      } else {
        orderDiscount = orderDiscountValue;
      }
    }

    const total = Math.max(subtotal - lineDiscounts - orderDiscount, 0);
    return { subtotal, lineDiscounts, orderDiscount, total };
  }, [cart, orderDiscountType, orderDiscountValue]);

  const addToCart = (item: any) => {
    setCart((prev) => {
      const existing = prev.find(
        (line) => line.product_id === item.product_id && line.variant_id === item.variant_id
      );
      if (existing) {
        return prev.map((line) =>
          line === existing ? { ...line, quantity: line.quantity + 1 } : line
        );
      }
      return [
        ...prev,
        {
          product_id: item.product_id,
          variant_id: item.variant_id,
          name: item.name,
          price_cents: item.price_cents,
          quantity: 1,
          discount_type: "fixed",
          discount_value: 0,
        },
      ];
    });
  };

  const updateQuantity = (index: number, delta: number) => {
    setCart((prev) =>
      prev
        .map((item, idx) =>
          idx === index ? { ...item, quantity: Math.max(1, item.quantity + delta) } : item
        )
        .filter((item) => item.quantity > 0)
    );
  };

  const updateDiscount = (index: number, type: "percent" | "fixed", value: number) => {
    setCart((prev) =>
      prev.map((item, idx) =>
        idx === index ? { ...item, discount_type: type, discount_value: value } : item
      )
    );
  };

  const createSaleDraft = async () => {
    if (cart.length === 0 || submitting) return;
    if (!registerId || !registerSessionId || !locationId) {
      alert("Open a register session before starting a sale.");
      return;
    }
    setSubmitting(true);
    try {
      const payload = {
        location_id: locationId,
        register_id: registerId,
        items: cart.map((item) => ({
          product_id: item.product_id,
          variant_id: item.variant_id,
          quantity: item.quantity,
          discount_type: item.discount_type,
          discount_bps: item.discount_type === "percent" ? (item.discount_value ?? 0) * 100 : undefined,
          discount_cents: item.discount_type === "fixed" ? item.discount_value ?? 0 : undefined,
        })),
        order_discount: orderDiscountValue
          ? {
              discount_type: orderDiscountType,
              discount_bps: orderDiscountType === "percent" ? orderDiscountValue * 100 : undefined,
              discount_cents: orderDiscountType === "fixed" ? orderDiscountValue : undefined,
            }
          : undefined,
      };

      const res = await api.post("/modules/pos/sales", payload);
      router.push(`/modules/pos/checkout?saleId=${res.data.id}`);
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to create sale draft");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="grid gap-6 lg:grid-cols-3">
      <div className="lg:col-span-2 space-y-4">
        <Card className="p-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <Input
              placeholder="Search products, SKU, or barcode"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
            <Select value={categoryId || "all"} onValueChange={(value) => setCategoryId(value === "all" ? null : value)}>
              <SelectTrigger className="w-full sm:w-56">
                <SelectValue placeholder="All Categories" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Categories</SelectItem>
                {(categories || []).map((category) => (
                  <SelectItem key={category.id} value={category.id}>
                    {category.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </Card>

        <div className="grid gap-3 sm:grid-cols-2">
          {(products || []).map((product) => (
            <Card
              key={`${product.product_id}-${product.variant_id || "base"}`}
              className="p-4 flex flex-col gap-2"
            >
              {product.image_url && (
                <div className="relative h-24 w-full overflow-hidden rounded-md bg-gray-100">
                  <Image
                    src={product.image_url}
                    alt={product.name}
                    fill
                    sizes="(max-width: 640px) 100vw, 50vw"
                    className="object-cover"
                    unoptimized
                  />
                </div>
              )}
              <div className="flex-1">
                <p className="text-sm text-gray-500">{product.sku || ""}</p>
                <h3 className="text-base font-semibold text-gray-900">{product.name}</h3>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-lg font-semibold text-purple-700">
                  {formatCurrency(product.price_cents)}
                </span>
                <Button onClick={() => addToCart(product)} size="sm" className="gap-1">
                  <Plus className="h-4 w-4" /> Add
                </Button>
              </div>
            </Card>
          ))}
          {isLoading && (
            <Card className="p-4 text-sm text-gray-500">Loading products...</Card>
          )}
          {!isLoading && (products || []).length === 0 && (
            <Card className="p-4 text-sm text-gray-500">No products found.</Card>
          )}
        </div>
      </div>

      <div className="space-y-4">
        <Card className="p-4">
          <div className="flex items-center justify-between mb-3">
            <h2 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
              <ShoppingCart className="h-4 w-4" /> Cart
            </h2>
            <Link href="/modules/pos/register" className="text-xs text-purple-600 hover:text-purple-700">
              Manage Register
            </Link>
          </div>

          {cart.length === 0 ? (
            <p className="text-sm text-gray-500">No items in cart.</p>
          ) : (
            <div className="space-y-3">
              {cart.map((item, index) => (
                <div key={`${item.product_id}-${index}`} className="border border-gray-200 rounded-lg p-3">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-gray-900">{item.name}</p>
                      <p className="text-xs text-gray-500">{formatCurrency(item.price_cents)} each</p>
                    </div>
                    <div className="flex items-center gap-2">
                      <Button size="icon" variant="outline" onClick={() => updateQuantity(index, -1)}>
                        <Minus className="h-4 w-4" />
                      </Button>
                      <span className="text-sm font-medium">{item.quantity}</span>
                      <Button size="icon" variant="outline" onClick={() => updateQuantity(index, 1)}>
                        <Plus className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                  <div className="mt-2 flex items-center gap-2">
                    <Select
                      value={item.discount_type}
                      onValueChange={(value) => updateDiscount(index, value as "percent" | "fixed", item.discount_value || 0)}
                    >
                      <SelectTrigger className="w-28">
                        <SelectValue placeholder="Discount" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="fixed">$</SelectItem>
                        <SelectItem value="percent">%</SelectItem>
                      </SelectContent>
                    </Select>
                    <Input
                      className="w-24"
                      placeholder="0"
                      value={
                        item.discount_type === "percent"
                          ? item.discount_value || ""
                          : item.discount_value
                          ? (item.discount_value / 100).toString()
                          : ""
                      }
                      onChange={(e) => {
                        const value = item.discount_type === "percent"
                          ? Number(e.target.value)
                          : parseCurrency(e.target.value);
                        updateDiscount(index, item.discount_type || "fixed", value || 0);
                      }}
                    />
                  </div>
                </div>
              ))}
            </div>
          )}
        </Card>

        <Card className="p-4 space-y-3">
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-600">Subtotal</span>
            <span className="font-medium text-gray-900">{formatCurrency(totals.subtotal)}</span>
          </div>
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-600">Line Discounts</span>
            <span className="font-medium text-gray-900">-{formatCurrency(totals.lineDiscounts)}</span>
          </div>
          <div className="flex items-center gap-2">
            <Select value={orderDiscountType} onValueChange={(value) => setOrderDiscountType(value as "percent" | "fixed")}
            >
              <SelectTrigger className="w-28">
                <SelectValue placeholder="Order Discount" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="fixed">$</SelectItem>
                <SelectItem value="percent">%</SelectItem>
              </SelectContent>
            </Select>
            <Input
              placeholder="Order discount"
              value={
                orderDiscountType === "percent"
                  ? orderDiscountValue || ""
                  : orderDiscountValue
                  ? (orderDiscountValue / 100).toString()
                  : ""
              }
              onChange={(e) => {
                const value = orderDiscountType === "percent"
                  ? Number(e.target.value)
                  : parseCurrency(e.target.value);
                setOrderDiscountValue(value || 0);
              }}
            />
          </div>
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-600">Order Discount</span>
            <span className="font-medium text-gray-900">-{formatCurrency(totals.orderDiscount)}</span>
          </div>
          <div className="flex items-center justify-between text-base font-semibold">
            <span>Total (taxes calculated at checkout)</span>
            <span>{formatCurrency(totals.total)}</span>
          </div>
          <Button className="w-full" onClick={createSaleDraft} disabled={cart.length === 0 || submitting}>
            {submitting ? "Creating..." : "Proceed to Checkout"}
          </Button>
        </Card>
      </div>
    </div>
  );
}
