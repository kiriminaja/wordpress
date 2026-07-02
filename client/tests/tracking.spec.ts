import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const source = readFileSync(
  resolve(process.cwd(), "client/src/storefront-classic/entries/tracking.ts"),
  "utf8",
);

describe("tracking client source", () => {
  it("exposes legacy trackOrder global", () => {
    expect(source).toContain("window.trackOrder = trackOrder");
  });

  it("prefills from the order_id query parameter", () => {
    expect(source).toContain('urlParams.get("order_id")');
  });
});
