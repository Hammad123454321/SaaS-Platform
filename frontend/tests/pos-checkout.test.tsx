import { render, screen } from "@testing-library/react";
import { vi } from "vitest";
import POSCheckoutPage from "@/app/modules/pos/checkout/page";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn() }),
  useSearchParams: () => ({ get: () => "sale-1" }),
}));

vi.mock("@/hooks/usePos", () => ({
  usePosSale: () => ({
    data: {
      sale: {
        total_cents: 1200,
        subtotal_cents: 1000,
        tax_cents: 200,
        discount_cents: 0,
      },
      items: [
        { id: "item-1", product_name: "Latte", quantity: 1, line_total_cents: 1200 },
      ],
    },
    isLoading: false,
  }),
}));

vi.mock("@/lib/api", () => ({
  api: { post: vi.fn() },
}));


describe("POS Checkout", () => {
  it("renders sale totals", () => {
    render(<POSCheckoutPage />);
    expect(screen.getByText("Sale Items")).toBeInTheDocument();
    expect(screen.getByText("Latte")).toBeInTheDocument();
    expect(screen.getByText("Total")).toBeInTheDocument();
  });
});
