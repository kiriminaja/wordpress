import { beforeEach, describe, expect, it, vi } from "vitest";
import { kiriofHasWpBlockCheckoutContext } from "../src/storefront-classic/lib/block-context";
import {
  kiriofGetClassicDistrictLabel,
  kiriofIsPlaceholderDistrictText,
  kiriofSetClassicDistrictLabel,
} from "../src/storefront-classic/lib/district-label";
import { kiriofExtractJsonResponseText } from "../src/storefront-classic/lib/json";
import { kiriofNormalizePaymentMethod } from "../src/storefront-classic/lib/payment";

function jqueryObject(overrides: Record<string, unknown> = {}): JQuery {
  return {
    data: vi.fn(() => ""),
    selectWoo: vi.fn(() => []),
    select2: vi.fn(() => []),
    find: vi.fn(() => ({ text: vi.fn(() => "") })),
    attr: vi.fn(() => ""),
    val: vi.fn(),
    ...overrides,
  } as unknown as JQuery;
}

describe("JSON helper", () => {
  it("returns valid JSON unchanged", () => {
    expect(kiriofExtractJsonResponseText('{"ok":true}')).toBe('{"ok":true}');
  });

  it("extracts JSON object from wrapped response text", () => {
    expect(kiriofExtractJsonResponseText('notice {"ok":true} trailing')).toBe(
      '{"ok":true}',
    );
  });

  it("returns original text when JSON cannot be parsed", () => {
    expect(kiriofExtractJsonResponseText("not-json")).toBe("not-json");
  });
});

describe("payment helper", () => {
  it("normalizes empty payment method", () => {
    expect(kiriofNormalizePaymentMethod(null)).toBe("");
  });

  it("returns string payment method", () => {
    expect(kiriofNormalizePaymentMethod("cod")).toBe("cod");
  });

  it("reads known object payment method keys by priority", () => {
    expect(kiriofNormalizePaymentMethod({ paymentMethodSlug: "bacs" })).toBe(
      "bacs",
    );
    expect(kiriofNormalizePaymentMethod({ name: "cod" })).toBe("cod");
    expect(kiriofNormalizePaymentMethod({ id: "cheque" })).toBe("cheque");
    expect(kiriofNormalizePaymentMethod({ value: "bank" })).toBe("bank");
  });
});

describe("block checkout context helper", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    delete window.wp;
  });

  it("returns false when block checkout marker is absent", () => {
    window.wp = { data: {} } as never;
    vi.stubGlobal("jQuery", vi.fn(() => ({ length: 0 })));

    expect(kiriofHasWpBlockCheckoutContext()).toBe(false);
  });

  it("returns true when marker and wp.data exist", () => {
    window.wp = { data: {} } as never;
    vi.stubGlobal("jQuery", vi.fn(() => ({ length: 1 })));

    expect(kiriofHasWpBlockCheckoutContext()).toBe(true);
  });
});

describe("classic district label helpers", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it("detects placeholder district text", () => {
    expect(kiriofIsPlaceholderDistrictText("", { i18n: {} })).toBe(true);
    expect(
      kiriofIsPlaceholderDistrictText("Select Option", { i18n: {} }),
    ).toBe(true);
    expect(kiriofIsPlaceholderDistrictText("Jakarta", { i18n: {} })).toBe(false);
  });

  it("uses cached selected district text when present", () => {
    const $select = jqueryObject({ data: vi.fn(() => "Bandung") });

    expect(kiriofGetClassicDistrictLabel($select, { i18n: {} })).toBe("Bandung");
  });

  it("falls back to selected option text", () => {
    const $select = jqueryObject({
      find: vi.fn(() => ({ text: vi.fn(() => "Bekasi") })),
    });

    expect(kiriofGetClassicDistrictLabel($select, { i18n: {} })).toBe("Bekasi");
  });

  it("writes billing district label and clears shipping when same address", () => {
    const billingVal = vi.fn();
    const shippingVal = vi.fn();
    vi.stubGlobal("jQuery", vi.fn((selector: string) => ({
      val: selector.includes("shipping") ? shippingVal : billingVal,
    })));
    const $select = jqueryObject({ attr: vi.fn(() => "kiriof_destination_area") });

    kiriofSetClassicDistrictLabel($select, "Bogor", 0, { i18n: {} });

    expect(billingVal).toHaveBeenCalledWith("Bogor");
    expect(shippingVal).toHaveBeenCalledWith("");
  });
});
