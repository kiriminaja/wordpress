import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  bindClassicDistrictChange,
  bindClassicDistrictSearch,
} from "../src/storefront-classic/lib/classic-district";

describe("classic district bindings", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    window.kiriofAjax = {
      ajaxurl: "/ajax",
      nonce: "nonce",
      destination_nonce: "destination",
    };
  });

  it("binds change handler to billing and shipping district selects", () => {
    const on = vi.fn();
    const off = vi.fn(() => ({ on }));
    vi.stubGlobal("jQuery", vi.fn(() => ({ off })));

    bindClassicDistrictChange({
      config: { fieldKey: "kiriof_destination_area", i18n: {} },
      getClassicInsuranceValue: () => 1,
      refreshCodInsurance: vi.fn(),
    });

    expect(jQuery).toHaveBeenCalledWith(
      "select#kiriof_destination_area,select#kiriof_shipping_destination_area",
    );
    expect(off).toHaveBeenCalledWith("change.kiriofClassicDistrict");
    expect(on).toHaveBeenCalledWith(
      "change.kiriofClassicDistrict",
      expect.any(Function),
    );
  });

  it("does not initialize search when select element is missing", () => {
    const each = vi.fn();
    vi.stubGlobal("jQuery", Object.assign(vi.fn(() => ({ length: 0, each })), {
      fn: { select2: vi.fn() },
    }));

    bindClassicDistrictSearch({ fieldKey: "kiriof_destination_area", i18n: {} });

    expect(each).not.toHaveBeenCalled();
  });
});
