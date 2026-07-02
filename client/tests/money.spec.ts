import { describe, expect, it } from "vitest";
import { formatNumber, moneyFormat } from "../src/shared/utils/money";

describe("formatNumber", () => {
  it("formats Indonesian thousands without currency", () => {
    expect(formatNumber(3000)).toBe("3.000");
  });

  it("formats Indonesian rupiah without extra separator", () => {
    expect(formatNumber(3000, { currency: true })).toBe("Rp3.000");
  });

  it("keeps legacy kiriofMoneyFormat behavior", () => {
    expect(moneyFormat(3000)).toBe("3.000");
    expect(moneyFormat(3000, "Rp")).toBe("Rp. 3.000");
  });
});
