type KiriofDistrictLabelConfig = {
  i18n?: {
    selectOption?: string;
  };
};

export function kiriofIsPlaceholderDistrictText(
  text: unknown,
  config: KiriofDistrictLabelConfig,
): boolean {
  const districtText = String(text || "").trim();
  return (
    !districtText ||
    districtText === (config.i18n?.selectOption || "Select Option")
  );
}

export function kiriofGetClassicDistrictLabel(
  $select: JQuery,
  config: KiriofDistrictLabelConfig,
): string {
  let label = String($select.data("kiriofSelectedDistrictText") || "").trim();
  if (!kiriofIsPlaceholderDistrictText(label, config)) {
    return label;
  }

  try {
    const selectData =
      $select.selectWoo ? $select.selectWoo("data")
      : $select.select2 ? $select.select2("data")
      : [];
    if (selectData && selectData.length && selectData[0].text) {
      label = String(selectData[0].text || "").trim();
      if (!kiriofIsPlaceholderDistrictText(label, config)) {
        return label;
      }
    }
  } catch (e) {}

  label = String($select.find("option:selected").text() || "").trim();
  if (!kiriofIsPlaceholderDistrictText(label, config)) {
    return label;
  }

  if (
    String($select.attr("name") || "") === "kiriof_shipping_destination_area"
  ) {
    return String(
      jQuery('[name="kiriof_shipping_destination_area_name"]').val() || "",
    ).trim();
  }

  return String(
    jQuery('[name="kiriof_destination_area_name"]').val() || "",
  ).trim();
}

export function kiriofSetClassicDistrictLabel(
  $select: JQuery,
  label: unknown,
  differentAddress: boolean | number | string,
  config: KiriofDistrictLabelConfig,
): void {
  let districtLabel = String(label || "").trim();
  if (kiriofIsPlaceholderDistrictText(districtLabel, config)) {
    districtLabel = "";
  }

  if (
    String($select.attr("name") || "") === "kiriof_shipping_destination_area"
  ) {
    jQuery('[name="kiriof_shipping_destination_area_name"]').val(districtLabel);
    return;
  }

  jQuery('[name="kiriof_destination_area_name"]').val(districtLabel);
  if (!differentAddress) {
    jQuery('[name="kiriof_shipping_destination_area_name"]').val("");
  }
}
