import { beforeEach, describe, expect, it, vi } from "vitest";
import { exposeAjaxRoute, getAjaxUrl } from "../src/shared/utils/ajax";
import { bindIntegerInput, integerOnly } from "../src/shared/utils/int-input";
import { exposePrintAsString, printAsString } from "../src/shared/utils/print";

describe("ajax utils", () => {
  it("returns configured AJAX URL", () => {
    expect(getAjaxUrl({ ajaxurl: "/wp-admin/admin-ajax.php" })).toBe(
      "/wp-admin/admin-ajax.php",
    );
  });

  it("returns an empty string when config is missing", () => {
    expect(getAjaxUrl()).toBe("");
  });

  it("exposes kiriofAjaxRoute on window", () => {
    window.kiriofAjax = { ajaxurl: "/ajax" };
    exposeAjaxRoute();

    expect(window.kiriofAjaxRoute?.()).toBe("/ajax");
  });
});

describe("print utils", () => {
  it("returns placeholder for nullish values", () => {
    expect(printAsString(null, "-")).toBe("-");
    expect(printAsString(undefined, "-")).toBe("-");
  });

  it("returns original non-null value", () => {
    expect(printAsString(123, "-")).toBe(123);
  });

  it("exposes kiriofPrintAsString on window", () => {
    exposePrintAsString();

    expect(window.kiriofPrintAsString?.(null, "-")).toBe("-");
  });
});

describe("integer input utils", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it("removes non-digit characters", () => {
    expect(integerOnly("Rp 12.345 abc")).toBe("12345");
  });

  it("binds delegated integer input handler", () => {
    const on = vi.fn();
    const $ = vi.fn(() => ({ on })) as unknown as JQueryStatic;

    bindIntegerInput($);

    expect(on).toHaveBeenCalledWith(
      "input",
      ".kiriof_int_input",
      expect.any(Function),
    );
  });
});
