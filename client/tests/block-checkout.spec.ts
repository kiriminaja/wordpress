import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const blockCheckout = readFileSync(
  resolve(process.cwd(), "client/src/storefront-block/entries/block-checkout.ts"),
  "utf8",
);
const billingAddress = readFileSync(
  resolve(process.cwd(), "client/src/storefront-classic/entries/form-billing-address.ts"),
  "utf8",
);
const classicDistrict = readFileSync(
  resolve(process.cwd(), "client/src/storefront-classic/lib/classic-district.ts"),
  "utf8",
);

describe("block checkout client source", () => {
  it("fetches current shipping discount for order summary totals", () => {
    expect(blockCheckout).toContain("kiriof_get_current_shipping_discount");
  });

  it("keeps removed shipping decoration code out of block checkout", () => {
    expect(blockCheckout).not.toContain("kiriof_get_shipping_rate_meta");
    expect(blockCheckout).not.toContain("scheduleShippingDecorationRefresh");
    expect(blockCheckout).not.toContain("decorateShippingOptions");
  });

  it("preserves classic district label and parseable AJAX response handling", () => {
    expect(classicDistrict).toContain("kiriofGetClassicDistrictLabel");
    expect(classicDistrict).toContain("kiriofSetClassicDistrictLabel");
    expect(classicDistrict).toContain("kiriofExtractJsonResponseText");
  });

  it("keeps block checkout compatibility hooks in TypeScript source", () => {
    expect(billingAddress).toContain("kiriofInitBlockCheckoutCompatibility");
    expect(billingAddress).toContain("kiriofBlockExtensionCartUpdate");
    expect(billingAddress).toContain("kiriofSyncBlockDistrictWarningState");
  });
});
