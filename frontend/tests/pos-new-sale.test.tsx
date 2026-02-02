import { render, screen, fireEvent } from "@testing-library/react";
import { vi } from "vitest";
import POSNewSalePage from "@/app/modules/pos/page";

vi.mock("@/hooks/usePos", () => ({
  usePosProducts: () => ({
    data: [
      {
        product_id: "prod-1",
        variant_id: null,
        name: "Espresso",
        sku: "ESP-1",
        price_cents: 500,
        tax_ids: [],
        category_id: null,
      },
    ],
    isLoading: false,
  }),
  usePosCategories: () => ({ data: [] }),
}));

vi.mock("@/lib/pos-store", () => ({
  usePosSessionStore: () => ({
    registerId: "reg-1",
    registerSessionId: "sess-1",
    locationId: "loc-1",
  }),
}));

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn() }),
}));

vi.mock("@/lib/api", () => ({
  api: { post: vi.fn() },
}));


describe("POS New Sale", () => {
  it("adds items to the cart", () => {
    render(<POSNewSalePage />);

    expect(screen.getByText("Espresso")).toBeInTheDocument();

    fireEvent.click(screen.getByText("Add"));

    expect(screen.getByText("Cart")).toBeInTheDocument();
    expect(screen.getAllByText("Espresso").length).toBeGreaterThan(0);
  });
});
