import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const source = readFileSync(
  resolve(process.cwd(), "client/src/admin/entries/admin-script.ts"),
  "utf8",
);

describe("admin QR client source", () => {
  it("uses npm qrcode data URLs rendered with jQuery", () => {
    expect(source).toContain('import QRCode from "qrcode"');
    expect(source).toContain("QRCode.toDataURL");
    expect(source).toContain('jQuery("<img />")');
  });

  it("does not rely on browser global QR libraries", () => {
    expect(source).not.toContain("QRCodeStyling");
    expect(source).not.toContain("jQuery.fn.qrcode");
  });
});
