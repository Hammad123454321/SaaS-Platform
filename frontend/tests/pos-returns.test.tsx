import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import { vi } from "vitest";
import POSReturnsPage from "@/app/modules/pos/returns/page";

const getMock = vi.hoisted(() =>
  vi.fn().mockResolvedValue({
    data: {
      sale: { id: "sale-1" },
      items: [
        { id: "item-1", product_name: "Mocha", quantity: 1, line_total_cents: 800 },
      ],
    },
  })
);

vi.mock("@/lib/api", () => ({
  api: { get: getMock, post: vi.fn() },
}));


describe("POS Returns", () => {
  it("loads sale details on lookup", async () => {
    render(<POSReturnsPage />);

    fireEvent.change(screen.getByPlaceholderText("Enter Sale ID"), { target: { value: "sale-1" } });
    fireEvent.click(screen.getByText("Find Sale"));

    await waitFor(() => {
      expect(screen.getByText("Refund Items")).toBeInTheDocument();
      expect(screen.getByText("Mocha")).toBeInTheDocument();
    });
  });
});
