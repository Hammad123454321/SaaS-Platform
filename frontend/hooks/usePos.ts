import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";

export type PosCategory = {
  id: string;
  name: string;
};

export type PosLocation = {
  id: string;
  name: string;
  code: string;
};

export type PosRegister = {
  id: string;
  name: string;
  location_id: string;
};

export type PosProduct = {
  product_id: string;
  variant_id: string | null;
  name: string;
  sku?: string | null;
  barcode?: string | null;
  image_url?: string | null;
  price_cents: number;
  tax_ids: string[];
  category_id?: string | null;
  is_service?: boolean;
  is_subscription?: boolean;
  is_kitchen_item?: boolean;
  requires_id_check?: boolean;
  minimum_age?: number | null;
};

export type PosSaleDetail = {
  sale: any;
  items: any[];
  payments: any[];
  receipt?: any;
};

export function usePosCategories() {
  return useQuery<PosCategory[]>({
    queryKey: ["pos", "categories"],
    queryFn: async () => {
      const res = await api.get<PosCategory[]>("/modules/pos/categories");
      return res.data;
    },
  });
}

export function usePosLocations() {
  return useQuery<PosLocation[]>({
    queryKey: ["pos", "locations"],
    queryFn: async () => {
      const res = await api.get<PosLocation[]>("/modules/pos/locations");
      return res.data;
    },
  });
}

export function usePosRegisters(locationId?: string | null) {
  return useQuery<PosRegister[]>({
    queryKey: ["pos", "registers", locationId],
    queryFn: async () => {
      const res = await api.get<PosRegister[]>("/modules/pos/registers", {
        params: locationId ? { location_id: locationId } : undefined,
      });
      return res.data;
    },
    enabled: true,
  });
}

export function usePosProducts(search: string, categoryId?: string | null) {
  return useQuery<PosProduct[]>({
    queryKey: ["pos", "products", search, categoryId],
    queryFn: async () => {
      const res = await api.get<PosProduct[]>("/modules/pos/products", {
        params: {
          search: search || undefined,
          category_id: categoryId || undefined,
        },
      });
      return res.data;
    },
    enabled: true,
  });
}

export function usePosSale(saleId?: string | null) {
  return useQuery<PosSaleDetail>({
    queryKey: ["pos", "sale", saleId],
    queryFn: async () => {
      const res = await api.get<PosSaleDetail>(`/modules/pos/sales/${saleId}`);
      return res.data;
    },
    enabled: !!saleId,
  });
}

export function usePosSalesHistory(filters: Record<string, any>) {
  return useQuery<any[]>({
    queryKey: ["pos", "sales", filters],
    queryFn: async () => {
      const res = await api.get<any[]>("/modules/pos/sales", { params: filters });
      return res.data;
    },
  });
}

export function usePosLowStock(limit: number = 20) {
  return useQuery<any[]>({
    queryKey: ["pos", "low-stock", limit],
    queryFn: async () => {
      const res = await api.get<any[]>("/modules/pos/inventory/low-stock", {
        params: { limit },
      });
      return res.data;
    },
  });
}

export function usePosAnalyticsKpis(startDate: string, endDate: string) {
  return useQuery<any>({
    queryKey: ["pos", "analytics", "kpis", startDate, endDate],
    queryFn: async () => {
      const res = await api.get("/modules/pos/analytics/kpis", {
        params: { start_date: startDate, end_date: endDate },
      });
      return res.data;
    },
    enabled: !!startDate && !!endDate,
  });
}

export function usePosAnalyticsTrends(startDate: string, endDate: string, granularity: string = "day") {
  return useQuery<any[]>({
    queryKey: ["pos", "analytics", "trends", startDate, endDate, granularity],
    queryFn: async () => {
      const res = await api.get("/modules/pos/analytics/trends", {
        params: { start_date: startDate, end_date: endDate, granularity },
      });
      return res.data;
    },
    enabled: !!startDate && !!endDate,
  });
}

export function usePosAnalyticsTopProducts(startDate: string, endDate: string, limit: number = 5) {
  return useQuery<any[]>({
    queryKey: ["pos", "analytics", "top-products", startDate, endDate, limit],
    queryFn: async () => {
      const res = await api.get("/modules/pos/analytics/top-products", {
        params: { start_date: startDate, end_date: endDate, limit },
      });
      return res.data;
    },
    enabled: !!startDate && !!endDate,
  });
}

export function usePosAnalyticsPayments(startDate: string, endDate: string) {
  return useQuery<any[]>({
    queryKey: ["pos", "analytics", "payments", startDate, endDate],
    queryFn: async () => {
      const res = await api.get("/modules/pos/analytics/payments", {
        params: { start_date: startDate, end_date: endDate },
      });
      return res.data;
    },
    enabled: !!startDate && !!endDate,
  });
}
