"use client";

import { useEffect, useMemo, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Plus, Trash2 } from "lucide-react";
import { usePosSale } from "@/hooks/usePos";
import { formatCurrency, parseCurrency } from "@/lib/pos-utils";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";


type PaymentLine = {
  method: "cash" | "card" | "other";
  amount_cents: number;
  reference?: string;
};

type Customer = {
  id: string;
  name: string;
  email?: string | null;
  phone?: string | null;
};

type ShippingAddress = {
  line1: string;
  line2?: string;
  city: string;
  state: string;
  postal_code: string;
  country: string;
};

export default function POSCheckoutPage() {
  const router = useRouter();
  const params = useSearchParams();
  const saleId = params.get("saleId");
  const { data, isLoading, refetch: refetchSale } = usePosSale(saleId);
  const [payments, setPayments] = useState<PaymentLine[]>([]);
  const [finalizing, setFinalizing] = useState(false);
  const [customerId, setCustomerId] = useState("");
  const [customerSearch, setCustomerSearch] = useState("");
  const [customerResults, setCustomerResults] = useState<Customer[]>([]);
  const [loadingCustomers, setLoadingCustomers] = useState(false);
  const [customerName, setCustomerName] = useState("");
  const [customerEmail, setCustomerEmail] = useState("");
  const [customerPhone, setCustomerPhone] = useState("");
  const [creatingCustomer, setCreatingCustomer] = useState(false);
  const [couponCode, setCouponCode] = useState("");
  const [loyaltyPointsAvailable, setLoyaltyPointsAvailable] = useState(0);
  const [loyaltyPointsRedeem, setLoyaltyPointsRedeem] = useState(0);
  const [channel, setChannel] = useState("pos");
  const [fulfillmentType, setFulfillmentType] = useState("in_store");
  const [fulfillmentStatus, setFulfillmentStatus] = useState("pending");
  const [shippingCost, setShippingCost] = useState(0);
  const [carrier, setCarrier] = useState("");
  const [trackingNumber, setTrackingNumber] = useState("");
  const [scheduledFor, setScheduledFor] = useState("");
  const [deliveryInstructions, setDeliveryInstructions] = useState("");
  const [shippingAddress, setShippingAddress] = useState<ShippingAddress>({
    line1: "",
    line2: "",
    city: "",
    state: "",
    postal_code: "",
    country: "US",
  });
  const [idType, setIdType] = useState("driver_license");
  const [idLast4, setIdLast4] = useState("");
  const [idBirthDate, setIdBirthDate] = useState("");
  const [updatingDraft, setUpdatingDraft] = useState(false);

  const totals = useMemo(() => {
    const sale = data?.sale;
    if (!sale) return { total: 0, subtotal: 0, tax: 0, discount: 0 };
    return {
      total: sale.total_cents || 0,
      subtotal: sale.subtotal_cents || 0,
      tax: sale.tax_cents || 0,
      discount: sale.discount_cents || 0,
    };
  }, [data]);

  const idCheck = useMemo(() => {
    const items = data?.items || [];
    const restricted = items.filter((item: any) => item.requires_id_check);
    const minAge = restricted.reduce((max: number, item: any) => Math.max(max, item.minimum_age || 0), 0);
    return { required: restricted.length > 0, minAge };
  }, [data]);

  const idCheckInvalid = idCheck.required && (!idLast4 || !idBirthDate);

  useEffect(() => {
    if (data?.sale && payments.length === 0) {
      setPayments([{ method: "cash", amount_cents: data.sale.total_cents || 0 }]);
    }
  }, [data, payments.length]);

  useEffect(() => {
    if (!data?.sale) return;
    if (data.sale.channel && channel === "pos") {
      setChannel(data.sale.channel);
    }
    if (data.sale.fulfillment) {
      const fulfillment = data.sale.fulfillment;
      setFulfillmentType(fulfillment.fulfillment_type || "in_store");
      setFulfillmentStatus(fulfillment.status || "pending");
      setShippingCost(fulfillment.shipping_cost_cents || 0);
      setCarrier(fulfillment.carrier || "");
      setTrackingNumber(fulfillment.tracking_number || "");
      setDeliveryInstructions(fulfillment.delivery_instructions || "");
      setScheduledFor(fulfillment.scheduled_for ? fulfillment.scheduled_for.split("T")[0] : "");
      if (fulfillment.shipping_address) {
        setShippingAddress({
          line1: fulfillment.shipping_address.line1 || "",
          line2: fulfillment.shipping_address.line2 || "",
          city: fulfillment.shipping_address.city || "",
          state: fulfillment.shipping_address.state || "",
          postal_code: fulfillment.shipping_address.postal_code || "",
          country: fulfillment.shipping_address.country || "US",
        });
      }
    }
    if (data.sale.applied_coupon_code && !couponCode) {
      setCouponCode(data.sale.applied_coupon_code);
    }
    if (data.sale.loyalty_points_redeemed && loyaltyPointsRedeem === 0) {
      setLoyaltyPointsRedeem(data.sale.loyalty_points_redeemed);
    }
    if (data.sale.customer_id && !customerId) {
      setCustomerId(data.sale.customer_id);
    }
  }, [data, channel, couponCode, loyaltyPointsRedeem, customerId]);

  useEffect(() => {
    const loadLoyalty = async () => {
      if (!customerId) {
        setLoyaltyPointsAvailable(0);
        return;
      }
      try {
        const res = await api.get(`/modules/pos/loyalty/customers/${customerId}`);
        setLoyaltyPointsAvailable(res.data.points_balance || 0);
      } catch {
        setLoyaltyPointsAvailable(0);
      }
    };
    loadLoyalty();
  }, [customerId]);

  const paidTotal = payments.reduce((sum, p) => sum + (p.amount_cents || 0), 0);
  const changeDue = Math.max(paidTotal - totals.total, 0);
  const balanceDue = Math.max(totals.total - paidTotal, 0);

  const updatePayment = (index: number, updates: Partial<PaymentLine>) => {
    setPayments((prev) => prev.map((line, idx) => (idx === index ? { ...line, ...updates } : line)));
  };

  const addPaymentLine = () => {
    setPayments((prev) => [...prev, { method: "card", amount_cents: 0 }]);
  };

  const removePaymentLine = (index: number) => {
    setPayments((prev) => prev.filter((_, idx) => idx !== index));
  };

  const buildItemsPayload = () => {
    if (!data?.items) return [];
    return data.items.map((item: any) => ({
      product_id: item.product_id,
      variant_id: item.variant_id,
      quantity: item.quantity,
      price_override_cents: item.unit_price_cents,
      discount_cents: item.discount_cents,
      tax_ids: item.tax_ids,
    }));
  };

  const applyDraftUpdate = async (updates: Record<string, any>) => {
    if (!saleId || updatingDraft) return;
    setUpdatingDraft(true);
    try {
      const payload: Record<string, any> = {
        customer_id: customerId || undefined,
        channel,
        fulfillment: {
          fulfillment_type: fulfillmentType,
          status: fulfillmentStatus,
          shipping_cost_cents: shippingCost,
          carrier: carrier || undefined,
          tracking_number: trackingNumber || undefined,
          delivery_instructions: deliveryInstructions || undefined,
          scheduled_for: scheduledFor ? new Date(scheduledFor).toISOString() : undefined,
          shipping_address:
            fulfillmentType === "shipping" || fulfillmentType === "delivery"
              ? shippingAddress
              : undefined,
        },
        items: buildItemsPayload(),
        ...updates,
      };
      await api.patch(`/modules/pos/sales/${saleId}`, payload);
      await refetchSale();
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to update sale");
    } finally {
      setUpdatingDraft(false);
    }
  };

  const searchCustomers = async () => {
    if (!customerSearch.trim()) return;
    setLoadingCustomers(true);
    try {
      const res = await api.get<Customer[]>("/modules/pos/customers", {
        params: { search: customerSearch.trim() },
      });
      setCustomerResults(res.data || []);
    } catch {
      setCustomerResults([]);
    } finally {
      setLoadingCustomers(false);
    }
  };

  const attachCustomer = async (id: string) => {
    setCustomerId(id);
    setCustomerResults([]);
    await applyDraftUpdate({ customer_id: id });
  };

  const applyCoupon = async () => {
    await applyDraftUpdate({ coupon_code: couponCode || "" });
  };

  const applyLoyalty = async () => {
    if (!customerId) {
      alert("Attach a customer to redeem loyalty points.");
      return;
    }
    await applyDraftUpdate({ loyalty_points_redeemed: loyaltyPointsRedeem || 0 });
  };

  const updateFulfillment = async () => {
    await applyDraftUpdate({
      channel,
      fulfillment: {
        fulfillment_type: fulfillmentType,
        status: fulfillmentStatus,
        shipping_cost_cents: shippingCost,
        carrier: carrier || undefined,
        tracking_number: trackingNumber || undefined,
        delivery_instructions: deliveryInstructions || undefined,
        scheduled_for: scheduledFor ? new Date(scheduledFor).toISOString() : undefined,
        shipping_address:
          fulfillmentType === "shipping" || fulfillmentType === "delivery"
            ? shippingAddress
            : undefined,
      },
    });
  };

  const finalizeSale = async () => {
    if (!saleId || finalizing) return;
    setFinalizing(true);
    try {
      const payload = {
        payments: payments.map((p) => ({
          method: p.method,
          amount_cents: p.amount_cents,
          reference: p.reference || undefined,
        })),
        customer_id: customerId || undefined,
        id_verification: idCheck.required
          ? {
              id_type: idType,
              id_last4: idLast4,
              birth_date: idBirthDate || undefined,
              minimum_age: idCheck.minAge || undefined,
            }
          : undefined,
      };
      await api.post(`/modules/pos/sales/${saleId}/finalize`, payload);
      router.push(`/modules/pos/receipt/${saleId}`);
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to finalize sale");
    } finally {
      setFinalizing(false);
    }
  };

  const createCustomer = async () => {
    if (!customerName) return;
    setCreatingCustomer(true);
    try {
      const res = await api.post("/modules/pos/customers", {
        name: customerName,
        email: customerEmail || undefined,
        phone: customerPhone || undefined,
      });
      setCustomerId(res.data.id);
      await applyDraftUpdate({ customer_id: res.data.id });
      alert("Customer created and attached");
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to create customer");
    } finally {
      setCreatingCustomer(false);
    }
  };

  return (
    <div className="grid gap-6 lg:grid-cols-3">
      <div className="lg:col-span-2 space-y-4">
        <Card className="p-4">
          <h2 className="text-lg font-semibold text-gray-900 mb-3">Sale Items</h2>
          {isLoading && <p className="text-sm text-gray-500">Loading sale...</p>}
          {!isLoading && data?.items?.length ? (
            <div className="space-y-3">
              {data.items.map((item: any) => (
                <div key={item.id} className="flex items-center justify-between border-b border-gray-100 pb-2">
                  <div>
                    <p className="text-sm font-medium text-gray-900">{item.product_name}</p>
                    <p className="text-xs text-gray-500">Qty {item.quantity}</p>
                  </div>
                  <p className="text-sm font-semibold">{formatCurrency(item.line_total_cents)}</p>
                </div>
              ))}
            </div>
          ) : (
            !isLoading && <p className="text-sm text-gray-500">No items for this sale.</p>
          )}
        </Card>

        <Card className="p-4 space-y-3">
          <h2 className="text-lg font-semibold text-gray-900">Customer</h2>
          <div className="flex flex-col gap-2 sm:flex-row">
            <Input
              placeholder="Search customers by name, email, or phone"
              value={customerSearch}
              onChange={(e) => setCustomerSearch(e.target.value)}
            />
            <Button variant="outline" onClick={searchCustomers} disabled={loadingCustomers}>
              {loadingCustomers ? "Searching..." : "Search"}
            </Button>
          </div>
          {customerResults.length > 0 && (
            <div className="space-y-2">
              {customerResults.map((customer) => (
                <div key={customer.id} className="flex items-center justify-between rounded-lg border border-gray-200 p-2">
                  <div>
                    <p className="text-sm font-medium text-gray-900">{customer.name}</p>
                    <p className="text-xs text-gray-500">{customer.email || customer.phone || "No contact info"}</p>
                  </div>
                  <Button size="sm" variant="outline" onClick={() => attachCustomer(customer.id)}>
                    Attach
                  </Button>
                </div>
              ))}
            </div>
          )}
          {customerId && (
            <p className="text-sm text-emerald-600">Attached customer ID: {customerId}</p>
          )}

          <div className="border-t border-gray-100 pt-3 space-y-2">
            <p className="text-sm font-medium text-gray-700">Create new customer</p>
            <div className="grid gap-2 sm:grid-cols-3">
              <Input
                placeholder="Name"
                value={customerName}
                onChange={(e) => setCustomerName(e.target.value)}
              />
              <Input
                placeholder="Email"
                value={customerEmail}
                onChange={(e) => setCustomerEmail(e.target.value)}
              />
              <Input
                placeholder="Phone"
                value={customerPhone}
                onChange={(e) => setCustomerPhone(e.target.value)}
              />
            </div>
            <Button variant="outline" onClick={createCustomer} disabled={creatingCustomer}>
              {creatingCustomer ? "Creating..." : "Create Customer"}
            </Button>
          </div>
        </Card>

        <Card className="p-4 space-y-3">
          <h2 className="text-lg font-semibold text-gray-900">Order Details</h2>
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-2">
              <label className="text-xs uppercase tracking-wide text-gray-500">Channel</label>
              <Select value={channel} onValueChange={setChannel}>
                <SelectTrigger>
                  <SelectValue placeholder="Channel" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="pos">POS</SelectItem>
                  <SelectItem value="online">Online</SelectItem>
                  <SelectItem value="phone">Phone</SelectItem>
                  <SelectItem value="wholesale">Wholesale</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <label className="text-xs uppercase tracking-wide text-gray-500">Fulfillment</label>
              <Select value={fulfillmentType} onValueChange={setFulfillmentType}>
                <SelectTrigger>
                  <SelectValue placeholder="Fulfillment type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="in_store">In store</SelectItem>
                  <SelectItem value="pickup">Pickup</SelectItem>
                  <SelectItem value="delivery">Local delivery</SelectItem>
                  <SelectItem value="shipping">Shipping</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          {(fulfillmentType === "delivery" || fulfillmentType === "shipping") && (
            <div className="space-y-2">
              <label className="text-xs uppercase tracking-wide text-gray-500">Shipping Address</label>
              <div className="grid gap-2 sm:grid-cols-2">
                <Input
                  placeholder="Address line 1"
                  value={shippingAddress.line1}
                  onChange={(e) => setShippingAddress({ ...shippingAddress, line1: e.target.value })}
                />
                <Input
                  placeholder="Address line 2"
                  value={shippingAddress.line2 || ""}
                  onChange={(e) => setShippingAddress({ ...shippingAddress, line2: e.target.value })}
                />
                <Input
                  placeholder="City"
                  value={shippingAddress.city}
                  onChange={(e) => setShippingAddress({ ...shippingAddress, city: e.target.value })}
                />
                <Input
                  placeholder="State"
                  value={shippingAddress.state}
                  onChange={(e) => setShippingAddress({ ...shippingAddress, state: e.target.value })}
                />
                <Input
                  placeholder="Postal code"
                  value={shippingAddress.postal_code}
                  onChange={(e) => setShippingAddress({ ...shippingAddress, postal_code: e.target.value })}
                />
                <Input
                  placeholder="Country"
                  value={shippingAddress.country}
                  onChange={(e) => setShippingAddress({ ...shippingAddress, country: e.target.value })}
                />
              </div>
            </div>
          )}

          <div className="grid gap-2 sm:grid-cols-3">
            <Input
              placeholder="Shipping cost"
              value={shippingCost ? (shippingCost / 100).toString() : ""}
              onChange={(e) => setShippingCost(parseCurrency(e.target.value))}
            />
            <Input
              placeholder="Carrier"
              value={carrier}
              onChange={(e) => setCarrier(e.target.value)}
            />
            <Input
              placeholder="Tracking #"
              value={trackingNumber}
              onChange={(e) => setTrackingNumber(e.target.value)}
            />
          </div>
          <Textarea
            placeholder="Delivery instructions"
            value={deliveryInstructions}
            onChange={(e) => setDeliveryInstructions(e.target.value)}
          />
          <div className="grid gap-2 sm:grid-cols-2">
            <Input
              type="date"
              value={scheduledFor}
              onChange={(e) => setScheduledFor(e.target.value)}
            />
            <Select value={fulfillmentStatus} onValueChange={setFulfillmentStatus}>
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
          </div>
          <Button variant="outline" onClick={updateFulfillment} disabled={updatingDraft}>
            {updatingDraft ? "Updating..." : "Update Order Details"}
          </Button>
        </Card>

        <Card className="p-4 space-y-3">
          <h2 className="text-lg font-semibold text-gray-900">Promotions & Loyalty</h2>
          <div className="flex flex-col gap-2 sm:flex-row">
            <Input
              placeholder="Coupon code"
              value={couponCode}
              onChange={(e) => setCouponCode(e.target.value)}
            />
            <Button variant="outline" onClick={applyCoupon} disabled={updatingDraft}>
              Apply Coupon
            </Button>
          </div>
          <div className="rounded-lg border border-gray-100 p-3 text-sm text-gray-600">
            Available points: <span className="font-semibold text-gray-900">{loyaltyPointsAvailable}</span>
          </div>
          <div className="flex flex-col gap-2 sm:flex-row">
            <Input
              placeholder="Redeem points"
              value={loyaltyPointsRedeem ? loyaltyPointsRedeem.toString() : ""}
              onChange={(e) => setLoyaltyPointsRedeem(Number(e.target.value) || 0)}
            />
            <Button variant="outline" onClick={applyLoyalty} disabled={updatingDraft}>
              Apply Points
            </Button>
          </div>
        </Card>

        {idCheck.required && (
          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">ID Verification</h2>
            <p className="text-sm text-gray-600">
              Age verification required. Minimum age: <span className="font-semibold">{idCheck.minAge}</span>
            </p>
            <div className="grid gap-2 sm:grid-cols-3">
              <Select value={idType} onValueChange={setIdType}>
                <SelectTrigger>
                  <SelectValue placeholder="ID Type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="driver_license">Driver License</SelectItem>
                  <SelectItem value="passport">Passport</SelectItem>
                  <SelectItem value="state_id">State ID</SelectItem>
                </SelectContent>
              </Select>
              <Input
                placeholder="ID last 4"
                value={idLast4}
                onChange={(e) => setIdLast4(e.target.value)}
              />
              <Input
                type="date"
                value={idBirthDate}
                onChange={(e) => setIdBirthDate(e.target.value)}
              />
            </div>
          </Card>
        )}

        <Card className="p-4">
          <h2 className="text-lg font-semibold text-gray-900 mb-3">Payments</h2>
          <div className="space-y-3">
            {payments.map((payment, index) => (
              <div key={index} className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <Select
                  value={payment.method}
                  onValueChange={(value) => updatePayment(index, { method: value as PaymentLine["method"] })}
                >
                  <SelectTrigger className="w-full sm:w-40">
                    <SelectValue placeholder="Method" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="cash">Cash</SelectItem>
                    <SelectItem value="card">Card</SelectItem>
                    <SelectItem value="other">Other</SelectItem>
                  </SelectContent>
                </Select>
                <Input
                  className="w-full sm:w-40"
                  placeholder="Amount"
                  value={payment.amount_cents ? (payment.amount_cents / 100).toString() : ""}
                  onChange={(e) => updatePayment(index, { amount_cents: parseCurrency(e.target.value) })}
                />
                <Input
                  className="w-full sm:flex-1"
                  placeholder="Reference (optional)"
                  value={payment.reference || ""}
                  onChange={(e) => updatePayment(index, { reference: e.target.value })}
                />
                {payments.length > 1 && (
                  <Button variant="outline" size="icon" onClick={() => removePaymentLine(index)}>
                    <Trash2 className="h-4 w-4" />
                  </Button>
                )}
              </div>
            ))}
            <Button variant="outline" onClick={addPaymentLine} className="gap-2">
              <Plus className="h-4 w-4" /> Add Payment
            </Button>
          </div>
        </Card>
      </div>

      <div className="space-y-4">
        <Card className="p-4 space-y-3">
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-600">Subtotal</span>
            <span className="font-medium text-gray-900">{formatCurrency(totals.subtotal)}</span>
          </div>
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-600">Discounts</span>
            <span className="font-medium text-gray-900">-{formatCurrency(totals.discount)}</span>
          </div>
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-600">Tax</span>
            <span className="font-medium text-gray-900">{formatCurrency(totals.tax)}</span>
          </div>
          {data?.sale?.shipping_cents ? (
            <div className="flex items-center justify-between text-sm">
              <span className="text-gray-600">Shipping/Delivery</span>
              <span className="font-medium text-gray-900">
                {formatCurrency(data.sale.shipping_cents || 0)}
              </span>
            </div>
          ) : null}
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-600">Total</span>
            <span className="font-semibold text-gray-900">{formatCurrency(totals.total)}</span>
          </div>
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-600">Paid</span>
            <span className="font-medium text-gray-900">{formatCurrency(paidTotal)}</span>
          </div>
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-600">Balance Due</span>
            <span className="font-medium text-rose-600">{formatCurrency(balanceDue)}</span>
          </div>
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-600">Change Due</span>
            <span className="font-medium text-emerald-600">{formatCurrency(changeDue)}</span>
          </div>
          <Button className="w-full" onClick={finalizeSale} disabled={finalizing || idCheckInvalid}>
            {finalizing ? "Finalizing..." : idCheckInvalid ? "ID Verification Required" : "Finalize Sale"}
          </Button>
        </Card>
      </div>
    </div>
  );
}
