"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { formatCurrency, parseCurrency } from "@/lib/pos-utils";
import { usePosLocations } from "@/hooks/usePos";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

export default function POSManagementPage() {
  const { data: locations } = usePosLocations();

  const { data: storefront, refetch: refetchStorefront } = useQuery({
    queryKey: ["pos", "storefront"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/storefront/settings");
      return res.data;
    },
  });

  const { data: discounts } = useQuery({
    queryKey: ["pos", "discounts"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/discounts");
      return res.data as any[];
    },
  });

  const { data: campaigns, refetch: refetchCampaigns } = useQuery({
    queryKey: ["pos", "campaigns"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/marketing/campaigns");
      return res.data as any[];
    },
  });

  const { data: coupons, refetch: refetchCoupons } = useQuery({
    queryKey: ["pos", "coupons"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/marketing/coupons");
      return res.data as any[];
    },
  });

  const { data: loyaltyProgram, refetch: refetchLoyalty } = useQuery({
    queryKey: ["pos", "loyalty", "program"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/loyalty/program");
      return res.data;
    },
  });

  const { data: vendors, refetch: refetchVendors } = useQuery({
    queryKey: ["pos", "vendors"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/vendors");
      return res.data as any[];
    },
  });

  const { data: purchaseOrders, refetch: refetchPurchaseOrders } = useQuery({
    queryKey: ["pos", "purchase-orders"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/purchase-orders");
      return res.data as any[];
    },
  });

  const { data: transfers, refetch: refetchTransfers } = useQuery({
    queryKey: ["pos", "inventory", "transfers"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/inventory/transfers");
      return res.data as any[];
    },
  });

  const { data: stockCounts, refetch: refetchStockCounts } = useQuery({
    queryKey: ["pos", "inventory", "counts"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/inventory/counts");
      return res.data as any[];
    },
  });

  const { data: staffProfiles, refetch: refetchStaff } = useQuery({
    queryKey: ["pos", "staff"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/staff");
      return res.data as any[];
    },
  });

  const { data: workOrders, refetch: refetchWorkOrders } = useQuery({
    queryKey: ["pos", "work-orders"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/work-orders");
      return res.data as any[];
    },
  });

  const { data: appointments, refetch: refetchAppointments } = useQuery({
    queryKey: ["pos", "appointments"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/appointments");
      return res.data as any[];
    },
  });

  const { data: subscriptionPlans, refetch: refetchPlans } = useQuery({
    queryKey: ["pos", "subscriptions", "plans"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/subscriptions/plans");
      return res.data as any[];
    },
  });

  const { data: subscriptions, refetch: refetchSubscriptions } = useQuery({
    queryKey: ["pos", "subscriptions"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/subscriptions");
      return res.data as any[];
    },
  });

  const { data: invoices, refetch: refetchInvoices } = useQuery({
    queryKey: ["pos", "subscription-invoices"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/subscriptions/invoices");
      return res.data as any[];
    },
  });

  const { data: feedback, refetch: refetchFeedback } = useQuery({
    queryKey: ["pos", "feedback"],
    queryFn: async () => {
      const res = await api.get("/modules/pos/reputation/feedback");
      return res.data as any[];
    },
  });

  const [storefrontForm, setStorefrontForm] = useState({
    name: "",
    slug: "",
    headline: "",
    description: "",
    primary_color: "#7c3aed",
    accent_color: "#f59e0b",
    logo_url: "",
    hero_image_url: "",
    show_out_of_stock: false,
    is_published: false,
  });

  useEffect(() => {
    if (storefront) {
      setStorefrontForm({
        name: storefront.name || "",
        slug: storefront.slug || "",
        headline: storefront.headline || "",
        description: storefront.description || "",
        primary_color: storefront.primary_color || "#7c3aed",
        accent_color: storefront.accent_color || "#f59e0b",
        logo_url: storefront.logo_url || "",
        hero_image_url: storefront.hero_image_url || "",
        show_out_of_stock: storefront.show_out_of_stock || false,
        is_published: storefront.is_published || false,
      });
    }
  }, [storefront]);

  const [campaignForm, setCampaignForm] = useState({
    name: "",
    description: "",
    discount_id: "",
    status: "draft",
    starts_at: "",
    ends_at: "",
  });

  const [couponForm, setCouponForm] = useState({
    code: "",
    discount_id: "",
    campaign_id: "",
    usage_limit: "",
    starts_at: "",
    ends_at: "",
  });

  const [loyaltyForm, setLoyaltyForm] = useState({
    name: "",
    points_per_currency_unit: 1,
    redeem_rate_cents_per_point: 1,
    is_active: true,
  });

  useEffect(() => {
    if (loyaltyProgram) {
      setLoyaltyForm({
        name: loyaltyProgram.name || "",
        points_per_currency_unit: loyaltyProgram.points_per_currency_unit || 1,
        redeem_rate_cents_per_point: loyaltyProgram.redeem_rate_cents_per_point || 1,
        is_active: loyaltyProgram.is_active ?? true,
      });
    }
  }, [loyaltyProgram]);

  const [loyaltyAdjust, setLoyaltyAdjust] = useState({
    customer_id: "",
    points_delta: 0,
    reason: "adjust",
  });

  const [vendorForm, setVendorForm] = useState({
    name: "",
    contact_name: "",
    email: "",
    phone: "",
    address: "",
    notes: "",
  });

  const [poForm, setPoForm] = useState({
    vendor_id: "",
    location_id: "",
    items: [] as any[],
  });
  const [poItem, setPoItem] = useState({ product_id: "", variant_id: "", quantity: 1, unit_cost_cents: 0 });

  const [transferForm, setTransferForm] = useState({
    from_location_id: "",
    to_location_id: "",
    items: [] as any[],
  });
  const [transferItem, setTransferItem] = useState({ product_id: "", variant_id: "", quantity: 1 });
  const [countEdits, setCountEdits] = useState<Record<string, Record<string, number>>>({});

  const [stockCountLocation, setStockCountLocation] = useState("");

  const [staffForm, setStaffForm] = useState({
    user_id: "",
    job_title: "",
    hourly_rate_cents: 0,
    pos_pin: "",
    location_ids: "",
  });
  const [staffClockLocation, setStaffClockLocation] = useState("");

  const [payrollRange, setPayrollRange] = useState({ start_date: "", end_date: "" });
  const [payrollResults, setPayrollResults] = useState<any[]>([]);

  const [workOrderForm, setWorkOrderForm] = useState({
    title: "",
    customer_id: "",
    assigned_to_user_id: "",
    due_at: "",
    notes: "",
  });

  const [appointmentForm, setAppointmentForm] = useState({
    customer_id: "",
    service_product_id: "",
    assigned_to_user_id: "",
    start_at: "",
    end_at: "",
    notes: "",
  });

  const [planForm, setPlanForm] = useState({
    name: "",
    description: "",
    price_cents: 0,
    interval: "month",
    interval_count: 1,
  });

  const [subscriptionForm, setSubscriptionForm] = useState({
    customer_id: "",
    plan_id: "",
  });

  const [feedbackResponse, setFeedbackResponse] = useState<Record<string, string>>({});

  const saveStorefront = async () => {
    await api.put("/modules/pos/storefront/settings", storefrontForm);
    await refetchStorefront();
  };

  const createCampaign = async () => {
    await api.post("/modules/pos/marketing/campaigns", {
      ...campaignForm,
      discount_id: campaignForm.discount_id || undefined,
      starts_at: campaignForm.starts_at ? new Date(campaignForm.starts_at).toISOString() : undefined,
      ends_at: campaignForm.ends_at ? new Date(campaignForm.ends_at).toISOString() : undefined,
    });
    setCampaignForm({ name: "", description: "", discount_id: "", status: "draft", starts_at: "", ends_at: "" });
    await refetchCampaigns();
  };

  const createCoupon = async () => {
    await api.post("/modules/pos/marketing/coupons", {
      code: couponForm.code,
      discount_id: couponForm.discount_id,
      campaign_id: couponForm.campaign_id || undefined,
      usage_limit: couponForm.usage_limit ? Number(couponForm.usage_limit) : undefined,
      starts_at: couponForm.starts_at ? new Date(couponForm.starts_at).toISOString() : undefined,
      ends_at: couponForm.ends_at ? new Date(couponForm.ends_at).toISOString() : undefined,
      is_active: true,
    });
    setCouponForm({ code: "", discount_id: "", campaign_id: "", usage_limit: "", starts_at: "", ends_at: "" });
    await refetchCoupons();
  };

  const saveLoyaltyProgram = async () => {
    await api.post("/modules/pos/loyalty/program", loyaltyForm);
    await refetchLoyalty();
  };

  const adjustLoyalty = async () => {
    await api.post("/modules/pos/loyalty/adjust", {
      ...loyaltyAdjust,
      points_delta: Number(loyaltyAdjust.points_delta),
    });
    setLoyaltyAdjust({ customer_id: "", points_delta: 0, reason: "adjust" });
  };

  const createVendor = async () => {
    await api.post("/modules/pos/vendors", vendorForm);
    setVendorForm({ name: "", contact_name: "", email: "", phone: "", address: "", notes: "" });
    await refetchVendors();
  };

  const addPoItem = () => {
    if (!poItem.product_id || !poItem.quantity) return;
    setPoForm((prev) => ({
      ...prev,
      items: [...prev.items, { ...poItem, unit_cost_cents: Number(poItem.unit_cost_cents) }],
    }));
    setPoItem({ product_id: "", variant_id: "", quantity: 1, unit_cost_cents: 0 });
  };

  const createPurchaseOrder = async () => {
    await api.post("/modules/pos/purchase-orders", {
      vendor_id: poForm.vendor_id,
      location_id: poForm.location_id,
      items: poForm.items,
      status: "ordered",
    });
    setPoForm({ vendor_id: "", location_id: "", items: [] });
    await refetchPurchaseOrders();
  };

  const receivePurchaseOrder = async (orderId: string) => {
    const order = purchaseOrders?.find((o: any) => o.id === orderId);
    if (!order) return;
    const items = (order.items || []).map((item: any) => ({
      product_id: item.product_id,
      variant_id: item.variant_id,
      received_quantity: Math.max(item.quantity - (item.received_quantity || 0), 0),
    }));
    await api.post(`/modules/pos/purchase-orders/${orderId}/receive`, { items });
    await refetchPurchaseOrders();
  };

  const addTransferItem = () => {
    if (!transferItem.product_id || !transferItem.quantity) return;
    setTransferForm((prev) => ({
      ...prev,
      items: [...prev.items, { ...transferItem, quantity: Number(transferItem.quantity) }],
    }));
    setTransferItem({ product_id: "", variant_id: "", quantity: 1 });
  };

  const createTransfer = async () => {
    await api.post("/modules/pos/inventory/transfers", {
      ...transferForm,
      status: "in_transit",
    });
    setTransferForm({ from_location_id: "", to_location_id: "", items: [] });
    await refetchTransfers();
  };

  const receiveTransfer = async (transferId: string) => {
    await api.post(`/modules/pos/inventory/transfers/${transferId}/receive`);
    await refetchTransfers();
  };

  const createStockCount = async () => {
    if (!stockCountLocation) return;
    await api.post("/modules/pos/inventory/counts", { location_id: stockCountLocation });
    await refetchStockCounts();
  };

  const countItemKey = (item: any) => `${item.product_id || ""}:${item.variant_id || ""}`;

  const getCountedQty = (countId: string, item: any) => {
    const key = countItemKey(item);
    const updated = countEdits[countId]?.[key];
    if (typeof updated === "number") return updated;
    if (typeof item.counted_qty === "number") return item.counted_qty;
    if (typeof item.expected_qty === "number") return item.expected_qty;
    return 0;
  };

  const updateCountedQty = (countId: string, item: any, value: number) => {
    const key = countItemKey(item);
    setCountEdits((prev) => ({
      ...prev,
      [countId]: {
        ...(prev[countId] || {}),
        [key]: value,
      },
    }));
  };

  const completeStockCount = async (countId: string, items: any[]) => {
    const payloadItems = items.map((item) => ({
      product_id: item.product_id,
      variant_id: item.variant_id,
      counted_qty: getCountedQty(countId, item),
    }));
    await api.post(`/modules/pos/inventory/counts/${countId}/complete`, { items: payloadItems });
    setCountEdits((prev) => {
      const next = { ...prev };
      delete next[countId];
      return next;
    });
    await refetchStockCounts();
  };

  const saveStaffProfile = async () => {
    await api.post("/modules/pos/staff", {
      user_id: staffForm.user_id,
      job_title: staffForm.job_title || undefined,
      hourly_rate_cents: staffForm.hourly_rate_cents ? Number(staffForm.hourly_rate_cents) : undefined,
      pos_pin: staffForm.pos_pin || undefined,
      location_ids: staffForm.location_ids ? staffForm.location_ids.split(",").map((id) => id.trim()) : [],
      is_active: true,
    });
    setStaffForm({ user_id: "", job_title: "", hourly_rate_cents: 0, pos_pin: "", location_ids: "" });
    await refetchStaff();
  };

  const clockIn = async () => {
    await api.post("/modules/pos/staff/clock-in", {
      location_id: staffClockLocation || undefined,
      break_minutes: 0,
    });
  };

  const clockOut = async () => {
    await api.post("/modules/pos/staff/clock-out", {
      break_minutes: 0,
    });
  };

  const runPayroll = async () => {
    if (!payrollRange.start_date || !payrollRange.end_date) return;
    const res = await api.get("/modules/pos/payroll/summary", {
      params: { start_date: payrollRange.start_date, end_date: payrollRange.end_date },
    });
    setPayrollResults(res.data || []);
  };

  const createWorkOrder = async () => {
    await api.post("/modules/pos/work-orders", {
      ...workOrderForm,
      due_at: workOrderForm.due_at ? new Date(workOrderForm.due_at).toISOString() : undefined,
    });
    setWorkOrderForm({ title: "", customer_id: "", assigned_to_user_id: "", due_at: "", notes: "" });
    await refetchWorkOrders();
  };

  const createAppointment = async () => {
    if (!appointmentForm.start_at || !appointmentForm.end_at) {
      alert("Start and end time are required.");
      return;
    }
    await api.post("/modules/pos/appointments", {
      ...appointmentForm,
      start_at: new Date(appointmentForm.start_at).toISOString(),
      end_at: new Date(appointmentForm.end_at).toISOString(),
    });
    setAppointmentForm({ customer_id: "", service_product_id: "", assigned_to_user_id: "", start_at: "", end_at: "", notes: "" });
    await refetchAppointments();
  };

  const createPlan = async () => {
    await api.post("/modules/pos/subscriptions/plans", {
      ...planForm,
      price_cents: Number(planForm.price_cents),
      interval_count: Number(planForm.interval_count),
    });
    setPlanForm({ name: "", description: "", price_cents: 0, interval: "month", interval_count: 1 });
    await refetchPlans();
  };

  const createSubscription = async () => {
    await api.post("/modules/pos/subscriptions", subscriptionForm);
    setSubscriptionForm({ customer_id: "", plan_id: "" });
    await refetchSubscriptions();
    await refetchInvoices();
  };

  const payInvoice = async (invoiceId: string) => {
    await api.post(`/modules/pos/subscriptions/invoices/${invoiceId}/pay`, {
      payment_method: "other",
    });
    await refetchInvoices();
  };

  const respondFeedback = async (feedbackId: string) => {
    await api.patch(`/modules/pos/reputation/feedback/${feedbackId}`, {
      status: "responded",
      response: feedbackResponse[feedbackId] || "",
    });
    await refetchFeedback();
  };

  return (
    <div className="space-y-6">
      <Card className="p-6">
        <h1 className="text-2xl font-bold text-gray-900">POS Management</h1>
        <p className="text-sm text-gray-600 mt-1">Manage advanced POS features, staff, and operational settings.</p>
      </Card>

      <Tabs defaultValue="storefront" className="space-y-4">
        <TabsList className="flex flex-wrap gap-2">
          <TabsTrigger value="storefront">Storefront</TabsTrigger>
          <TabsTrigger value="marketing">Marketing</TabsTrigger>
          <TabsTrigger value="loyalty">Loyalty</TabsTrigger>
          <TabsTrigger value="vendors">Vendors</TabsTrigger>
          <TabsTrigger value="inventory">Inventory Ops</TabsTrigger>
          <TabsTrigger value="staff">Staff & Payroll</TabsTrigger>
          <TabsTrigger value="workorders">Work Orders</TabsTrigger>
          <TabsTrigger value="appointments">Appointments</TabsTrigger>
          <TabsTrigger value="subscriptions">Subscriptions</TabsTrigger>
          <TabsTrigger value="reputation">Reputation</TabsTrigger>
        </TabsList>

        <TabsContent value="storefront">
          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Storefront Settings</h2>
            <div className="grid gap-2 sm:grid-cols-2">
              <Input
                placeholder="Store name"
                value={storefrontForm.name}
                onChange={(e) => setStorefrontForm((prev) => ({ ...prev, name: e.target.value }))}
              />
              <Input
                placeholder="Slug (e.g. my-store)"
                value={storefrontForm.slug}
                onChange={(e) => setStorefrontForm((prev) => ({ ...prev, slug: e.target.value }))}
              />
              <Input
                placeholder="Headline"
                value={storefrontForm.headline}
                onChange={(e) => setStorefrontForm((prev) => ({ ...prev, headline: e.target.value }))}
              />
              <Input
                placeholder="Primary color"
                value={storefrontForm.primary_color}
                onChange={(e) => setStorefrontForm((prev) => ({ ...prev, primary_color: e.target.value }))}
              />
              <Input
                placeholder="Accent color"
                value={storefrontForm.accent_color}
                onChange={(e) => setStorefrontForm((prev) => ({ ...prev, accent_color: e.target.value }))}
              />
              <Input
                placeholder="Logo URL"
                value={storefrontForm.logo_url}
                onChange={(e) => setStorefrontForm((prev) => ({ ...prev, logo_url: e.target.value }))}
              />
              <Input
                placeholder="Hero image URL"
                value={storefrontForm.hero_image_url}
                onChange={(e) => setStorefrontForm((prev) => ({ ...prev, hero_image_url: e.target.value }))}
              />
            </div>
            <Textarea
              placeholder="Description"
              value={storefrontForm.description}
              onChange={(e) => setStorefrontForm((prev) => ({ ...prev, description: e.target.value }))}
            />
            <div className="flex items-center gap-3 text-sm text-gray-600">
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={storefrontForm.show_out_of_stock}
                  onChange={(e) => setStorefrontForm((prev) => ({ ...prev, show_out_of_stock: e.target.checked }))}
                />
                Show out of stock
              </label>
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={storefrontForm.is_published}
                  onChange={(e) => setStorefrontForm((prev) => ({ ...prev, is_published: e.target.checked }))}
                />
                Publish storefront
              </label>
            </div>
            <Button onClick={saveStorefront}>Save Settings</Button>
            <Link href="/modules/pos/storefront" className="inline-flex">
              <Button variant="outline">Preview Storefront</Button>
            </Link>
          </Card>
        </TabsContent>

        <TabsContent value="marketing" className="space-y-4">
          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Create Campaign</h2>
            <div className="grid gap-2 sm:grid-cols-2">
              <Input
                placeholder="Campaign name"
                value={campaignForm.name}
                onChange={(e) => setCampaignForm((prev) => ({ ...prev, name: e.target.value }))}
              />
              <Select
                value={campaignForm.discount_id || ""}
                onValueChange={(value) => setCampaignForm((prev) => ({ ...prev, discount_id: value }))}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Discount" />
                </SelectTrigger>
                <SelectContent>
                  {(discounts || []).map((discount) => (
                    <SelectItem key={discount.id} value={discount.id}>
                      {discount.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Input
                type="date"
                value={campaignForm.starts_at}
                onChange={(e) => setCampaignForm((prev) => ({ ...prev, starts_at: e.target.value }))}
              />
              <Input
                type="date"
                value={campaignForm.ends_at}
                onChange={(e) => setCampaignForm((prev) => ({ ...prev, ends_at: e.target.value }))}
              />
            </div>
            <Textarea
              placeholder="Description"
              value={campaignForm.description}
              onChange={(e) => setCampaignForm((prev) => ({ ...prev, description: e.target.value }))}
            />
            <Button onClick={createCampaign}>Create Campaign</Button>
          </Card>

          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Create Coupon</h2>
            <div className="grid gap-2 sm:grid-cols-2">
              <Input
                placeholder="Code"
                value={couponForm.code}
                onChange={(e) => setCouponForm((prev) => ({ ...prev, code: e.target.value }))}
              />
              <Select
                value={couponForm.discount_id || ""}
                onValueChange={(value) => setCouponForm((prev) => ({ ...prev, discount_id: value }))}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Discount" />
                </SelectTrigger>
                <SelectContent>
                  {(discounts || []).map((discount) => (
                    <SelectItem key={discount.id} value={discount.id}>
                      {discount.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Select
                value={couponForm.campaign_id || ""}
                onValueChange={(value) => setCouponForm((prev) => ({ ...prev, campaign_id: value }))}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Campaign (optional)" />
                </SelectTrigger>
                <SelectContent>
                  {(campaigns || []).map((campaign) => (
                    <SelectItem key={campaign.id} value={campaign.id}>
                      {campaign.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Input
                placeholder="Usage limit"
                value={couponForm.usage_limit}
                onChange={(e) => setCouponForm((prev) => ({ ...prev, usage_limit: e.target.value }))}
              />
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
              <Input
                type="date"
                value={couponForm.starts_at}
                onChange={(e) => setCouponForm((prev) => ({ ...prev, starts_at: e.target.value }))}
              />
              <Input
                type="date"
                value={couponForm.ends_at}
                onChange={(e) => setCouponForm((prev) => ({ ...prev, ends_at: e.target.value }))}
              />
            </div>
            <Button onClick={createCoupon}>Create Coupon</Button>
          </Card>

          <Card className="p-4 space-y-2">
            <h3 className="text-base font-semibold text-gray-900">Active Coupons</h3>
            {(coupons || []).map((coupon) => (
              <div key={coupon.id} className="flex items-center justify-between text-sm border-b border-gray-100 pb-2">
                <span>{coupon.code}</span>
                <span className="text-gray-500">
                  {coupon.usage_count}/{coupon.usage_limit ?? "unlimited"}
                </span>
              </div>
            ))}
          </Card>
        </TabsContent>

        <TabsContent value="loyalty" className="space-y-4">
          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Loyalty Program</h2>
            <div className="grid gap-2 sm:grid-cols-2">
              <Input
                placeholder="Program name"
                value={loyaltyForm.name}
                onChange={(e) => setLoyaltyForm((prev) => ({ ...prev, name: e.target.value }))}
              />
              <Input
                placeholder="Points per $1"
                value={loyaltyForm.points_per_currency_unit}
                onChange={(e) => setLoyaltyForm((prev) => ({ ...prev, points_per_currency_unit: Number(e.target.value) || 0 }))}
              />
              <Input
                placeholder="Cents per point"
                value={loyaltyForm.redeem_rate_cents_per_point}
                onChange={(e) => setLoyaltyForm((prev) => ({ ...prev, redeem_rate_cents_per_point: Number(e.target.value) || 0 }))}
              />
            </div>
            <label className="flex items-center gap-2 text-sm text-gray-600">
              <input
                type="checkbox"
                checked={loyaltyForm.is_active}
                onChange={(e) => setLoyaltyForm((prev) => ({ ...prev, is_active: e.target.checked }))}
              />
              Program active
            </label>
            <Button onClick={saveLoyaltyProgram}>Save Program</Button>
          </Card>

          <Card className="p-4 space-y-3">
            <h3 className="text-base font-semibold text-gray-900">Adjust Points</h3>
            <div className="grid gap-2 sm:grid-cols-3">
              <Input
                placeholder="Customer ID"
                value={loyaltyAdjust.customer_id}
                onChange={(e) => setLoyaltyAdjust((prev) => ({ ...prev, customer_id: e.target.value }))}
              />
              <Input
                placeholder="Points +/-"
                value={loyaltyAdjust.points_delta}
                onChange={(e) => setLoyaltyAdjust((prev) => ({ ...prev, points_delta: Number(e.target.value) || 0 }))}
              />
              <Select
                value={loyaltyAdjust.reason}
                onValueChange={(value) => setLoyaltyAdjust((prev) => ({ ...prev, reason: value }))}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Reason" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="adjust">Adjust</SelectItem>
                  <SelectItem value="earn">Earn</SelectItem>
                  <SelectItem value="redeem">Redeem</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <Button onClick={adjustLoyalty}>Apply Adjustment</Button>
          </Card>
        </TabsContent>

        <TabsContent value="vendors" className="space-y-4">
          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Vendors</h2>
            <div className="grid gap-2 sm:grid-cols-2">
              <Input placeholder="Vendor name" value={vendorForm.name} onChange={(e) => setVendorForm((prev) => ({ ...prev, name: e.target.value }))} />
              <Input placeholder="Contact name" value={vendorForm.contact_name} onChange={(e) => setVendorForm((prev) => ({ ...prev, contact_name: e.target.value }))} />
              <Input placeholder="Email" value={vendorForm.email} onChange={(e) => setVendorForm((prev) => ({ ...prev, email: e.target.value }))} />
              <Input placeholder="Phone" value={vendorForm.phone} onChange={(e) => setVendorForm((prev) => ({ ...prev, phone: e.target.value }))} />
            </div>
            <Textarea placeholder="Notes" value={vendorForm.notes} onChange={(e) => setVendorForm((prev) => ({ ...prev, notes: e.target.value }))} />
            <Button onClick={createVendor}>Add Vendor</Button>
            <div className="space-y-2">
              {(vendors || []).map((vendor) => (
                <div key={vendor.id} className="text-sm text-gray-700 border-b border-gray-100 pb-2">
                  {vendor.name} {vendor.email ? `- ${vendor.email}` : ""}
                </div>
              ))}
            </div>
          </Card>

          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Purchase Orders</h2>
            <div className="grid gap-2 sm:grid-cols-2">
              <Select value={poForm.vendor_id} onValueChange={(value) => setPoForm((prev) => ({ ...prev, vendor_id: value }))}>
                <SelectTrigger>
                  <SelectValue placeholder="Vendor" />
                </SelectTrigger>
                <SelectContent>
                  {(vendors || []).map((vendor) => (
                    <SelectItem key={vendor.id} value={vendor.id}>{vendor.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Select value={poForm.location_id} onValueChange={(value) => setPoForm((prev) => ({ ...prev, location_id: value }))}>
                <SelectTrigger>
                  <SelectValue placeholder="Location" />
                </SelectTrigger>
                <SelectContent>
                  {(locations || []).map((loc) => (
                    <SelectItem key={loc.id} value={loc.id}>{loc.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="grid gap-2 sm:grid-cols-4">
              <Input placeholder="Product ID" value={poItem.product_id} onChange={(e) => setPoItem((prev) => ({ ...prev, product_id: e.target.value }))} />
              <Input placeholder="Variant ID" value={poItem.variant_id} onChange={(e) => setPoItem((prev) => ({ ...prev, variant_id: e.target.value }))} />
              <Input placeholder="Qty" value={poItem.quantity} onChange={(e) => setPoItem((prev) => ({ ...prev, quantity: Number(e.target.value) || 0 }))} />
              <Input placeholder="Unit cost" value={poItem.unit_cost_cents ? (poItem.unit_cost_cents / 100).toString() : ""} onChange={(e) => setPoItem((prev) => ({ ...prev, unit_cost_cents: parseCurrency(e.target.value) }))} />
            </div>
            <Button variant="outline" onClick={addPoItem}>Add Item</Button>
            {poForm.items.length > 0 && (
              <div className="space-y-1 text-sm text-gray-600">
                {poForm.items.map((item, idx) => (
                  <div key={idx}>
                    {item.product_id} - Qty {item.quantity} - {formatCurrency(item.unit_cost_cents)}
                  </div>
                ))}
              </div>
            )}
            <Button onClick={createPurchaseOrder}>Create Purchase Order</Button>
            <div className="space-y-2">
              {(purchaseOrders || []).map((order) => (
                <div key={order.id} className="flex items-center justify-between text-sm border-b border-gray-100 pb-2">
                  <span>PO #{order.id} - {order.status}</span>
                  <Button size="sm" variant="outline" onClick={() => receivePurchaseOrder(order.id)}>Receive</Button>
                </div>
              ))}
            </div>
          </Card>
        </TabsContent>

        <TabsContent value="inventory" className="space-y-4">
          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Stock Transfers</h2>
            <div className="grid gap-2 sm:grid-cols-2">
              <Select value={transferForm.from_location_id} onValueChange={(value) => setTransferForm((prev) => ({ ...prev, from_location_id: value }))}>
                <SelectTrigger>
                  <SelectValue placeholder="From location" />
                </SelectTrigger>
                <SelectContent>
                  {(locations || []).map((loc) => (
                    <SelectItem key={loc.id} value={loc.id}>{loc.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Select value={transferForm.to_location_id} onValueChange={(value) => setTransferForm((prev) => ({ ...prev, to_location_id: value }))}>
                <SelectTrigger>
                  <SelectValue placeholder="To location" />
                </SelectTrigger>
                <SelectContent>
                  {(locations || []).map((loc) => (
                    <SelectItem key={loc.id} value={loc.id}>{loc.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="grid gap-2 sm:grid-cols-3">
              <Input placeholder="Product ID" value={transferItem.product_id} onChange={(e) => setTransferItem((prev) => ({ ...prev, product_id: e.target.value }))} />
              <Input placeholder="Variant ID" value={transferItem.variant_id} onChange={(e) => setTransferItem((prev) => ({ ...prev, variant_id: e.target.value }))} />
              <Input placeholder="Qty" value={transferItem.quantity} onChange={(e) => setTransferItem((prev) => ({ ...prev, quantity: Number(e.target.value) || 0 }))} />
            </div>
            <Button variant="outline" onClick={addTransferItem}>Add Item</Button>
            {transferForm.items.length > 0 && (
              <div className="space-y-1 text-sm text-gray-600">
                {transferForm.items.map((item, idx) => (
                  <div key={idx}>
                    {item.product_id} - Qty {item.quantity}
                  </div>
                ))}
              </div>
            )}
            <Button onClick={createTransfer}>Create Transfer</Button>
            <div className="space-y-2 text-sm text-gray-600">
              {(transfers || []).length === 0 && <p>No stock transfers yet.</p>}
              {(transfers || []).map((transfer) => (
                <div key={transfer.id} className="border-b border-gray-100 pb-2">
                  <div className="flex items-center justify-between">
                    <span>
                      Transfer {transfer.id} - {transfer.status}
                    </span>
                    {transfer.status !== "received" && (
                      <Button size="sm" variant="outline" onClick={() => receiveTransfer(transfer.id)}>
                        Receive
                      </Button>
                    )}
                  </div>
                  <div className="text-xs text-gray-500">
                    {transfer.from_location_id} {"->"} {transfer.to_location_id}
                  </div>
                </div>
              ))}
            </div>
          </Card>

          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Stock Counts</h2>
            <Select value={stockCountLocation} onValueChange={setStockCountLocation}>
              <SelectTrigger>
                <SelectValue placeholder="Location" />
              </SelectTrigger>
              <SelectContent>
                {(locations || []).map((loc) => (
                  <SelectItem key={loc.id} value={loc.id}>{loc.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button onClick={createStockCount}>Start Stock Count</Button>
            <div className="space-y-3 text-sm text-gray-600">
              {(stockCounts || []).length === 0 && <p>No stock counts yet.</p>}
              {(stockCounts || []).map((count) => (
                <div key={count.id} className="border border-gray-100 rounded-lg p-3 space-y-2">
                  <div className="flex items-center justify-between">
                    <span>
                      Count {count.id} - {count.status}
                    </span>
                    {count.status === "open" && (
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => completeStockCount(count.id, count.items || [])}
                      >
                        Complete
                      </Button>
                    )}
                  </div>
                  {(count.items || []).length > 0 && (
                    <div className="max-h-64 overflow-auto space-y-2 text-xs">
                      {(count.items || []).map((item: any, idx: number) => (
                        <div key={`${count.id}-${idx}`} className="flex items-center justify-between gap-2">
                          <div className="flex-1">
                            <div>{item.product_id}{item.variant_id ? ` (${item.variant_id})` : ""}</div>
                            <div className="text-gray-400">Expected: {item.expected_qty ?? 0}</div>
                          </div>
                          <Input
                            className="w-20"
                            type="number"
                            value={getCountedQty(count.id, item)}
                            onChange={(e) => updateCountedQty(count.id, item, Number(e.target.value))}
                          />
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </Card>
        </TabsContent>

        <TabsContent value="staff" className="space-y-4">
          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Staff Profiles</h2>
            <div className="grid gap-2 sm:grid-cols-2">
              <Input placeholder="User ID" value={staffForm.user_id} onChange={(e) => setStaffForm((prev) => ({ ...prev, user_id: e.target.value }))} />
              <Input placeholder="Job title" value={staffForm.job_title} onChange={(e) => setStaffForm((prev) => ({ ...prev, job_title: e.target.value }))} />
              <Input placeholder="Hourly rate" value={staffForm.hourly_rate_cents ? (staffForm.hourly_rate_cents / 100).toString() : ""} onChange={(e) => setStaffForm((prev) => ({ ...prev, hourly_rate_cents: parseCurrency(e.target.value) }))} />
              <Input placeholder="POS PIN" value={staffForm.pos_pin} onChange={(e) => setStaffForm((prev) => ({ ...prev, pos_pin: e.target.value }))} />
            </div>
            <Input placeholder="Location IDs (comma separated)" value={staffForm.location_ids} onChange={(e) => setStaffForm((prev) => ({ ...prev, location_ids: e.target.value }))} />
            <Button onClick={saveStaffProfile}>Save Profile</Button>
            <div className="space-y-2 text-sm text-gray-600">
              {(staffProfiles || []).map((profile) => (
                <div key={profile.id} className="border-b border-gray-100 pb-2">
                  {profile.user_id} - {profile.job_title || "Staff"}
                </div>
              ))}
            </div>
          </Card>

          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Time Clock</h2>
            <Select value={staffClockLocation} onValueChange={setStaffClockLocation}>
              <SelectTrigger>
                <SelectValue placeholder="Location" />
              </SelectTrigger>
              <SelectContent>
                {(locations || []).map((loc) => (
                  <SelectItem key={loc.id} value={loc.id}>{loc.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <div className="flex gap-2">
              <Button variant="outline" onClick={clockIn}>Clock In</Button>
              <Button variant="outline" onClick={clockOut}>Clock Out</Button>
            </div>
          </Card>

          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Payroll Summary</h2>
            <div className="grid gap-2 sm:grid-cols-2">
              <Input type="date" value={payrollRange.start_date} onChange={(e) => setPayrollRange((prev) => ({ ...prev, start_date: e.target.value }))} />
              <Input type="date" value={payrollRange.end_date} onChange={(e) => setPayrollRange((prev) => ({ ...prev, end_date: e.target.value }))} />
            </div>
            <Button onClick={runPayroll}>Run Payroll</Button>
            {payrollResults.length > 0 && (
              <div className="space-y-2 text-sm text-gray-700">
                {payrollResults.map((row) => (
                  <div key={row.user_id} className="flex items-center justify-between border-b border-gray-100 pb-2">
                    <span>{row.email || row.user_id}</span>
                    <span>{row.hours} hrs - {formatCurrency(row.gross_pay_cents)}</span>
                  </div>
                ))}
              </div>
            )}
          </Card>
        </TabsContent>

        <TabsContent value="workorders" className="space-y-4">
          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Work Orders</h2>
            <div className="grid gap-2 sm:grid-cols-2">
              <Input placeholder="Title" value={workOrderForm.title} onChange={(e) => setWorkOrderForm((prev) => ({ ...prev, title: e.target.value }))} />
              <Input placeholder="Customer ID" value={workOrderForm.customer_id} onChange={(e) => setWorkOrderForm((prev) => ({ ...prev, customer_id: e.target.value }))} />
              <Input placeholder="Assigned user ID" value={workOrderForm.assigned_to_user_id} onChange={(e) => setWorkOrderForm((prev) => ({ ...prev, assigned_to_user_id: e.target.value }))} />
              <Input type="date" value={workOrderForm.due_at} onChange={(e) => setWorkOrderForm((prev) => ({ ...prev, due_at: e.target.value }))} />
            </div>
            <Textarea placeholder="Notes" value={workOrderForm.notes} onChange={(e) => setWorkOrderForm((prev) => ({ ...prev, notes: e.target.value }))} />
            <Button onClick={createWorkOrder}>Create Work Order</Button>
            <div className="space-y-2 text-sm text-gray-600">
              {(workOrders || []).map((order) => (
                <div key={order.id} className="border-b border-gray-100 pb-2">
                  {order.title} - {order.status}
                </div>
              ))}
            </div>
          </Card>
        </TabsContent>

        <TabsContent value="appointments" className="space-y-4">
          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Appointments</h2>
            <div className="grid gap-2 sm:grid-cols-2">
              <Input placeholder="Customer ID" value={appointmentForm.customer_id} onChange={(e) => setAppointmentForm((prev) => ({ ...prev, customer_id: e.target.value }))} />
              <Input placeholder="Service product ID" value={appointmentForm.service_product_id} onChange={(e) => setAppointmentForm((prev) => ({ ...prev, service_product_id: e.target.value }))} />
              <Input placeholder="Assigned user ID" value={appointmentForm.assigned_to_user_id} onChange={(e) => setAppointmentForm((prev) => ({ ...prev, assigned_to_user_id: e.target.value }))} />
              <Input type="datetime-local" value={appointmentForm.start_at} onChange={(e) => setAppointmentForm((prev) => ({ ...prev, start_at: e.target.value }))} />
              <Input type="datetime-local" value={appointmentForm.end_at} onChange={(e) => setAppointmentForm((prev) => ({ ...prev, end_at: e.target.value }))} />
            </div>
            <Textarea placeholder="Notes" value={appointmentForm.notes} onChange={(e) => setAppointmentForm((prev) => ({ ...prev, notes: e.target.value }))} />
            <Button onClick={createAppointment}>Schedule Appointment</Button>
            <div className="space-y-2 text-sm text-gray-600">
              {(appointments || []).map((appointment) => (
                <div key={appointment.id} className="border-b border-gray-100 pb-2">
                  {appointment.start_at} - {appointment.status}
                </div>
              ))}
            </div>
          </Card>
        </TabsContent>

        <TabsContent value="subscriptions" className="space-y-4">
          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Subscription Plans</h2>
            <div className="grid gap-2 sm:grid-cols-2">
              <Input placeholder="Plan name" value={planForm.name} onChange={(e) => setPlanForm((prev) => ({ ...prev, name: e.target.value }))} />
              <Input placeholder="Price" value={planForm.price_cents ? (planForm.price_cents / 100).toString() : ""} onChange={(e) => setPlanForm((prev) => ({ ...prev, price_cents: parseCurrency(e.target.value) }))} />
              <Select value={planForm.interval} onValueChange={(value) => setPlanForm((prev) => ({ ...prev, interval: value }))}>
                <SelectTrigger>
                  <SelectValue placeholder="Interval" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="month">Monthly</SelectItem>
                  <SelectItem value="year">Yearly</SelectItem>
                  <SelectItem value="week">Weekly</SelectItem>
                </SelectContent>
              </Select>
              <Input placeholder="Interval count" value={planForm.interval_count} onChange={(e) => setPlanForm((prev) => ({ ...prev, interval_count: Number(e.target.value) || 1 }))} />
            </div>
            <Textarea placeholder="Description" value={planForm.description} onChange={(e) => setPlanForm((prev) => ({ ...prev, description: e.target.value }))} />
            <Button onClick={createPlan}>Create Plan</Button>
            <div className="space-y-2 text-sm text-gray-600">
              {(subscriptionPlans || []).map((plan) => (
                <div key={plan.id} className="border-b border-gray-100 pb-2">
                  {plan.name} - {formatCurrency(plan.price_cents)}
                </div>
              ))}
            </div>
          </Card>

          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Customer Subscriptions</h2>
            <div className="grid gap-2 sm:grid-cols-2">
              <Input placeholder="Customer ID" value={subscriptionForm.customer_id} onChange={(e) => setSubscriptionForm((prev) => ({ ...prev, customer_id: e.target.value }))} />
              <Select value={subscriptionForm.plan_id} onValueChange={(value) => setSubscriptionForm((prev) => ({ ...prev, plan_id: value }))}>
                <SelectTrigger>
                  <SelectValue placeholder="Plan" />
                </SelectTrigger>
                <SelectContent>
                  {(subscriptionPlans || []).map((plan) => (
                    <SelectItem key={plan.id} value={plan.id}>{plan.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <Button onClick={createSubscription}>Create Subscription</Button>
            <div className="space-y-2 text-sm text-gray-600">
              {(subscriptions || []).map((sub) => (
                <div key={sub.id} className="border-b border-gray-100 pb-2">
                  {sub.customer_id} - {sub.status}
                </div>
              ))}
            </div>
          </Card>

          <Card className="p-4 space-y-2">
            <h3 className="text-base font-semibold text-gray-900">Invoices</h3>
            {(invoices || []).map((invoice) => (
              <div key={invoice.id} className="flex items-center justify-between text-sm border-b border-gray-100 pb-2">
                <span>{invoice.due_date} - {formatCurrency(invoice.amount_cents)} - {invoice.status}</span>
                {invoice.status !== "paid" && (
                  <Button size="sm" variant="outline" onClick={() => payInvoice(invoice.id)}>Mark Paid</Button>
                )}
              </div>
            ))}
          </Card>
        </TabsContent>

        <TabsContent value="reputation" className="space-y-4">
          <Card className="p-4 space-y-3">
            <h2 className="text-lg font-semibold text-gray-900">Customer Feedback</h2>
            {(feedback || []).length === 0 && <p className="text-sm text-gray-500">No feedback yet.</p>}
            {(feedback || []).map((item) => (
              <div key={item.id} className="border-b border-gray-100 pb-3 space-y-2">
                <div className="text-sm font-medium text-gray-900">Rating {item.rating} - {item.status}</div>
                {item.comment && <p className="text-sm text-gray-600">{item.comment}</p>}
                <Textarea
                  placeholder="Respond to customer"
                  value={feedbackResponse[item.id] || ""}
                  onChange={(e) => setFeedbackResponse((prev) => ({ ...prev, [item.id]: e.target.value }))}
                />
                <Button size="sm" variant="outline" onClick={() => respondFeedback(item.id)}>Send Response</Button>
              </div>
            ))}
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}


