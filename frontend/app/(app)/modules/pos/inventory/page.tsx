"use client";

import { useState } from "react";
import { usePosLowStock, usePosProducts, usePosCategories } from "@/hooks/usePos";
import { usePosSessionStore } from "@/lib/pos-store";
import { api } from "@/lib/api";
import { formatCurrency, parseCurrency } from "@/lib/pos-utils";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";

export default function POSInventoryPage() {
  const [search, setSearch] = useState("");
  const [selectedProduct, setSelectedProduct] = useState<any>(null);
  const [qtyDelta, setQtyDelta] = useState(0);
  const [reorderPoint, setReorderPoint] = useState<number | undefined>(undefined);
  const [adjusting, setAdjusting] = useState(false);

  const [newProduct, setNewProduct] = useState({
    name: "",
    sku: "",
    barcode: "",
    image_url: "",
    price_cents: 0,
    category_id: "",
    is_service: false,
    is_subscription: false,
    is_kitchen_item: false,
    requires_id_check: false,
    minimum_age: 0,
  });
  const [creatingProduct, setCreatingProduct] = useState(false);
  const [variantForm, setVariantForm] = useState({
    product_id: "",
    name: "",
    sku: "",
    barcode: "",
    image_url: "",
    price_cents: 0,
    is_service: false,
    is_subscription: false,
    is_kitchen_item: false,
    requires_id_check: false,
    minimum_age: 0,
  });
  const [creatingVariant, setCreatingVariant] = useState(false);
  const [bulkCsv, setBulkCsv] = useState("");
  const [bulkSubmitting, setBulkSubmitting] = useState(false);

  const { data: products } = usePosProducts(search, null);
  const { data: lowStock } = usePosLowStock();
  const { data: categories } = usePosCategories();
  const { locationId } = usePosSessionStore();

  const adjustInventory = async () => {
    if (!selectedProduct) return;
    if (!locationId) {
      alert("Select an active register session to choose a location.");
      return;
    }
    setAdjusting(true);
    try {
      await api.post("/modules/pos/inventory/adjustments", {
        location_id: locationId,
        product_id: selectedProduct.product_id,
        variant_id: selectedProduct.variant_id,
        qty_delta: Number(qtyDelta),
        reorder_point: reorderPoint,
        reason: "manual_adjustment",
      });
      alert("Inventory updated");
      setQtyDelta(0);
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to update inventory");
    } finally {
      setAdjusting(false);
    }
  };

  const createProduct = async () => {
    if (!newProduct.name || !newProduct.price_cents) return;
    setCreatingProduct(true);
    try {
      await api.post("/modules/pos/products", {
        name: newProduct.name,
        sku: newProduct.sku || undefined,
        barcode: newProduct.barcode || undefined,
        image_url: newProduct.image_url || undefined,
        base_price_cents: newProduct.price_cents,
        category_id: newProduct.category_id || undefined,
        is_service: newProduct.is_service,
        is_subscription: newProduct.is_subscription,
        is_kitchen_item: newProduct.is_kitchen_item,
        requires_id_check: newProduct.requires_id_check,
        minimum_age: newProduct.requires_id_check ? newProduct.minimum_age || undefined : undefined,
      });
      alert("Product created");
      setNewProduct({
        name: "",
        sku: "",
        barcode: "",
        image_url: "",
        price_cents: 0,
        category_id: "",
        is_service: false,
        is_subscription: false,
        is_kitchen_item: false,
        requires_id_check: false,
        minimum_age: 0,
      });
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to create product");
    } finally {
      setCreatingProduct(false);
    }
  };

  const createVariant = async () => {
    if (!variantForm.product_id || !variantForm.name || !variantForm.price_cents) return;
    setCreatingVariant(true);
    try {
      await api.post("/modules/pos/variants", {
        product_id: variantForm.product_id,
        name: variantForm.name,
        sku: variantForm.sku || undefined,
        barcode: variantForm.barcode || undefined,
        image_url: variantForm.image_url || undefined,
        price_cents: variantForm.price_cents,
        is_service: variantForm.is_service,
        is_subscription: variantForm.is_subscription,
        is_kitchen_item: variantForm.is_kitchen_item,
        requires_id_check: variantForm.requires_id_check,
        minimum_age: variantForm.requires_id_check ? variantForm.minimum_age || undefined : undefined,
      });
      alert("Variant created");
      setVariantForm({
        product_id: "",
        name: "",
        sku: "",
        barcode: "",
        image_url: "",
        price_cents: 0,
        is_service: false,
        is_subscription: false,
        is_kitchen_item: false,
        requires_id_check: false,
        minimum_age: 0,
      });
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to create variant");
    } finally {
      setCreatingVariant(false);
    }
  };

  const parseCsvRow = (row: string) => {
    const values: string[] = [];
    let current = "";
    let inQuotes = false;
    for (let i = 0; i < row.length; i += 1) {
      const char = row[i];
      if (char === "\"") {
        if (inQuotes && row[i + 1] === "\"") {
          current += "\"";
          i += 1;
        } else {
          inQuotes = !inQuotes;
        }
      } else if (char === "," && !inQuotes) {
        values.push(current);
        current = "";
      } else {
        current += char;
      }
    }
    values.push(current);
    return values.map((value) => value.trim());
  };

  const parseCsv = (text: string) => {
    const lines = text
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter((line) => line.length > 0);
    if (lines.length === 0) return [];
    const headers = parseCsvRow(lines[0]).map((header) => header.toLowerCase());
    return lines.slice(1).map((line) => {
      const values = parseCsvRow(line);
      const row: Record<string, string> = {};
      headers.forEach((header, index) => {
        row[header] = values[index] ?? "";
      });
      return row;
    });
  };

  const parseBool = (value: string) => {
    const normalized = value.trim().toLowerCase();
    return ["true", "1", "yes", "y"].includes(normalized);
  };

  const buildBulkItems = (rows: Record<string, string>[]) => {
    return rows
      .map((row) => {
        const name = (row.name || "").trim();
        if (!name) return null;
        const basePriceRaw = (row.base_price_cents || row.base_price || row.price || "").trim();
        if (!basePriceRaw) return null;
        const costValue = row.cost_cents || row.cost || "";
        const basePriceCents = row.base_price_cents
          ? Number(row.base_price_cents)
          : parseCurrency(basePriceRaw);
        const costCents = row.cost_cents ? Number(row.cost_cents) : costValue ? parseCurrency(costValue) : undefined;
        if (!Number.isFinite(basePriceCents)) return null;
        const minimumAge = row.minimum_age ? Number(row.minimum_age) : undefined;
        const taxIds = row.tax_ids
          ? row.tax_ids.split("|").map((id) => id.trim()).filter(Boolean)
          : undefined;
        return {
          product_id: row.product_id || undefined,
          name,
          description: row.description || "",
          category_id: row.category_id || undefined,
          sku: row.sku || undefined,
          barcode: row.barcode || undefined,
          image_url: row.image_url || undefined,
          base_price_cents: basePriceCents,
          cost_cents: costCents,
          tax_ids: taxIds,
          is_service: parseBool(row.is_service || ""),
          is_subscription: parseBool(row.is_subscription || ""),
          is_kitchen_item: parseBool(row.is_kitchen_item || ""),
          requires_id_check: parseBool(row.requires_id_check || ""),
          minimum_age: minimumAge,
          is_active: row.is_active ? parseBool(row.is_active) : true,
        };
      })
      .filter((item) => item !== null) as any[];
  };

  const submitBulkImport = async () => {
    if (!bulkCsv.trim() || bulkSubmitting) return;
    const rows = parseCsv(bulkCsv);
    const items = buildBulkItems(rows);
    if (items.length === 0) {
      alert("No valid rows found.");
      return;
    }
    setBulkSubmitting(true);
    try {
      await api.post("/modules/pos/products/bulk", { items });
      alert(`Imported ${items.length} products.`);
      setBulkCsv("");
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Bulk import failed");
    } finally {
      setBulkSubmitting(false);
    }
  };

  return (
    <div className="grid gap-6 lg:grid-cols-3">
      <div className="lg:col-span-2 space-y-4">
        <Card className="p-4">
          <h2 className="text-lg font-semibold text-gray-900 mb-3">Inventory Adjustment</h2>
          <Input
            placeholder="Search product"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <div className="mt-3 grid gap-2">
            {(products || []).map((product) => (
              <button
                key={`${product.product_id}-${product.variant_id || "base"}`}
                onClick={() => setSelectedProduct(product)}
                className={`text-left p-3 rounded-lg border ${
                  selectedProduct?.product_id === product.product_id &&
                  selectedProduct?.variant_id === product.variant_id
                    ? "border-purple-400 bg-purple-50"
                    : "border-gray-200"
                }`}
              >
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-900">{product.name}</p>
                    <p className="text-xs text-gray-500">{product.sku || ""}</p>
                  </div>
                  <span className="text-sm font-semibold text-purple-700">
                    {formatCurrency(product.price_cents)}
                  </span>
                </div>
              </button>
            ))}
          </div>

          {selectedProduct && (
            <div className="mt-4 grid gap-3 sm:grid-cols-3">
              <Input
                type="number"
                placeholder="Qty delta"
                value={qtyDelta}
                onChange={(e) => setQtyDelta(Number(e.target.value))}
              />
              <Input
                type="number"
                placeholder="Reorder point"
                value={reorderPoint ?? ""}
                onChange={(e) => setReorderPoint(Number(e.target.value))}
              />
              <Button onClick={adjustInventory} disabled={adjusting}>
                {adjusting ? "Updating..." : "Apply"}
              </Button>
            </div>
          )}
        </Card>

        <Card className="p-4">
          <h2 className="text-lg font-semibold text-gray-900 mb-3">Low Stock</h2>
          <div className="space-y-2">
            {(lowStock || []).map((item: any, index: number) => (
              <div key={index} className="flex items-center justify-between border-b border-gray-100 pb-2 text-sm">
                <div>
                  <p className="text-gray-900">{item.product_name || `Product ${item.product_id}`}</p>
                  {item.variant_name && <p className="text-xs text-gray-500">{item.variant_name}</p>}
                </div>
                <span className="font-medium text-rose-600">
                  {item.qty_on_hand} / {item.reorder_point}
                </span>
              </div>
            ))}
            {lowStock?.length === 0 && (
              <p className="text-sm text-gray-500">No low stock alerts.</p>
            )}
          </div>
        </Card>
      </div>

      <div className="space-y-4">
        <Card className="p-4 space-y-3">
          <h2 className="text-lg font-semibold text-gray-900">Create Product</h2>
          <Input
            placeholder="Name"
            value={newProduct.name}
            onChange={(e) => setNewProduct((prev) => ({ ...prev, name: e.target.value }))}
          />
          <Input
            placeholder="SKU"
            value={newProduct.sku}
            onChange={(e) => setNewProduct((prev) => ({ ...prev, sku: e.target.value }))}
          />
          <Input
            placeholder="Barcode"
            value={newProduct.barcode}
            onChange={(e) => setNewProduct((prev) => ({ ...prev, barcode: e.target.value }))}
          />
          <Input
            placeholder="Image URL"
            value={newProduct.image_url}
            onChange={(e) => setNewProduct((prev) => ({ ...prev, image_url: e.target.value }))}
          />
          <Input
            placeholder="Price"
            value={newProduct.price_cents ? (newProduct.price_cents / 100).toString() : ""}
            onChange={(e) => setNewProduct((prev) => ({ ...prev, price_cents: parseCurrency(e.target.value) }))}
          />
          <Select
            value={newProduct.category_id || ""}
            onValueChange={(value) => setNewProduct((prev) => ({ ...prev, category_id: value }))}
          >
            <SelectTrigger>
              <SelectValue placeholder="Category" />
            </SelectTrigger>
            <SelectContent>
              {(categories || []).map((category) => (
                <SelectItem key={category.id} value={category.id}>
                  {category.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <div className="space-y-2 text-sm text-gray-600">
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={newProduct.is_service}
                onChange={(e) => setNewProduct((prev) => ({ ...prev, is_service: e.target.checked }))}
              />
              Service item
            </label>
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={newProduct.is_subscription}
                onChange={(e) => setNewProduct((prev) => ({ ...prev, is_subscription: e.target.checked }))}
              />
              Subscription item
            </label>
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={newProduct.is_kitchen_item}
                onChange={(e) => setNewProduct((prev) => ({ ...prev, is_kitchen_item: e.target.checked }))}
              />
              Kitchen prep required
            </label>
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={newProduct.requires_id_check}
                onChange={(e) => setNewProduct((prev) => ({ ...prev, requires_id_check: e.target.checked }))}
              />
              Requires ID check
            </label>
            {newProduct.requires_id_check && (
              <Input
                placeholder="Minimum age"
                value={newProduct.minimum_age || ""}
                onChange={(e) => setNewProduct((prev) => ({ ...prev, minimum_age: Number(e.target.value) || 0 }))}
              />
            )}
          </div>
          <Button onClick={createProduct} disabled={creatingProduct}>
            {creatingProduct ? "Creating..." : "Add Product"}
          </Button>
        </Card>

        <Card className="p-4 space-y-3">
          <h2 className="text-lg font-semibold text-gray-900">Create Variant</h2>
          <Input
            placeholder="Product ID"
            value={variantForm.product_id}
            onChange={(e) => setVariantForm((prev) => ({ ...prev, product_id: e.target.value }))}
          />
          <Input
            placeholder="Variant name"
            value={variantForm.name}
            onChange={(e) => setVariantForm((prev) => ({ ...prev, name: e.target.value }))}
          />
          <Input
            placeholder="SKU"
            value={variantForm.sku}
            onChange={(e) => setVariantForm((prev) => ({ ...prev, sku: e.target.value }))}
          />
          <Input
            placeholder="Barcode"
            value={variantForm.barcode}
            onChange={(e) => setVariantForm((prev) => ({ ...prev, barcode: e.target.value }))}
          />
          <Input
            placeholder="Image URL"
            value={variantForm.image_url}
            onChange={(e) => setVariantForm((prev) => ({ ...prev, image_url: e.target.value }))}
          />
          <Input
            placeholder="Price"
            value={variantForm.price_cents ? (variantForm.price_cents / 100).toString() : ""}
            onChange={(e) => setVariantForm((prev) => ({ ...prev, price_cents: parseCurrency(e.target.value) }))}
          />
          <div className="space-y-2 text-sm text-gray-600">
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={variantForm.is_service}
                onChange={(e) => setVariantForm((prev) => ({ ...prev, is_service: e.target.checked }))}
              />
              Service item
            </label>
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={variantForm.is_subscription}
                onChange={(e) => setVariantForm((prev) => ({ ...prev, is_subscription: e.target.checked }))}
              />
              Subscription item
            </label>
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={variantForm.is_kitchen_item}
                onChange={(e) => setVariantForm((prev) => ({ ...prev, is_kitchen_item: e.target.checked }))}
              />
              Kitchen prep required
            </label>
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={variantForm.requires_id_check}
                onChange={(e) => setVariantForm((prev) => ({ ...prev, requires_id_check: e.target.checked }))}
              />
              Requires ID check
            </label>
            {variantForm.requires_id_check && (
              <Input
                placeholder="Minimum age"
                value={variantForm.minimum_age || ""}
                onChange={(e) => setVariantForm((prev) => ({ ...prev, minimum_age: Number(e.target.value) || 0 }))}
              />
            )}
          </div>
          <Button onClick={createVariant} disabled={creatingVariant}>
            {creatingVariant ? "Creating..." : "Add Variant"}
          </Button>
        </Card>

        <Card className="p-4 space-y-3">
          <h2 className="text-lg font-semibold text-gray-900">Bulk Import Products</h2>
          <p className="text-xs text-gray-500">
            CSV headers: name, sku, barcode, price, base_price_cents, category_id, image_url, is_service, is_subscription,
            is_kitchen_item, requires_id_check, minimum_age, is_active, product_id
          </p>
          <Textarea
            placeholder="Paste CSV rows here"
            value={bulkCsv}
            onChange={(e) => setBulkCsv(e.target.value)}
            className="min-h-[140px]"
          />
          <Button onClick={submitBulkImport} disabled={bulkSubmitting}>
            {bulkSubmitting ? "Importing..." : "Import Products"}
          </Button>
        </Card>
      </div>
    </div>
  );
}
