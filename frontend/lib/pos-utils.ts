export function formatCurrency(cents: number, currency: string = "USD"): string {
  const amount = (cents || 0) / 100;
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency,
  }).format(amount);
}

export function parseCurrency(input: string): number {
  const cleaned = input.replace(/[^0-9.]/g, "");
  const value = Number.parseFloat(cleaned);
  if (Number.isNaN(value)) return 0;
  return Math.round(value * 100);
}
