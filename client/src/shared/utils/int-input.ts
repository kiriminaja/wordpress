export function bindIntegerInput($: JQueryStatic): void {
  $(document).on("input", ".kiriof_int_input", function onIntegerInput(this: HTMLInputElement) {
    this.value = this.value.replace(/\D/g, "");

    if ($(this).hasClass("duplicate_into")) {
      const targetName = String($(this).data("duplicate_into") || "");
      const duplicateTarget = $(`input[name="${targetName}"]`);
      duplicateTarget.val(this.value);
      duplicateTarget.trigger("change");
    }

    if ($(this).hasClass("currency") && typeof window.kiriofFormatRupiah === "function") {
      this.value = window.kiriofFormatRupiah(this.value);
    }
  });
}
