import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const source = readFileSync(
  resolve(process.cwd(), "client/src/admin/entries/coupon-admin.ts"),
  "utf8",
);

describe("coupon admin client source", () => {
  it("surfaces AJAX error messages", () => {
    expect(source).toContain("xhr.responseJSON.data.message");
    expect(source).toContain("window.alert(message)");
  });

  it("normalizes leading zero amount values", () => {
    expect(source).toContain("normalizeCouponAmountValue");
    expect(source).toContain("normalizeCouponAmountField");
    expect(source).toContain('replace(/^0+(?=\\d)/, "")');
    expect(source).toContain("parseFloat(normalizeCouponAmountField())");
  });

  it("disables combinations when individual use is checked", () => {
    expect(source).toContain("syncCombinationsAvailability");
    expect(source).toContain("individual_use");
    expect(source).toContain("kiriof-combination-options");
  });
});
