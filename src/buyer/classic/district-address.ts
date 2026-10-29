type AddressField = 'city' | 'state' | 'postcode';
const fields: AddressField[] = ['city', 'state', 'postcode'];

/** Refresh hidden hierarchy fields; explicit district choices also replace native defaults. */
export function syncDistrictAddress(
  form: HTMLFormElement,
  states: Record<string, string> = {},
  explicitScope?: string,
) {
  for (const scope of ['billing', 'shipping']) {
    const markerName = `kiriof_${scope}_derived_address`;
    let marker = form.querySelector<HTMLInputElement>(`input[name="${markerName}"]`);
    const country = form.querySelector<HTMLInputElement | HTMLSelectElement>(
      `[name="${scope}_country"], #${scope}_country`,
    );
    const hidden = fields.filter((key) => {
      const field = form.querySelector(`[name="${scope}_${key}"], #${scope}_${key}`);
      return Boolean(field?.closest('.fkcf-hidden'));
    });
    if (country?.value !== 'ID') {
      marker?.remove();
      continue;
    }
    if (hidden.length) {
      if (!marker) {
        marker = form.ownerDocument.createElement('input');
        marker.type = 'hidden';
        marker.name = markerName;
        form.append(marker);
      }
      const markerValue = hidden.join(',');
      if (marker.value !== markerValue) marker.value = markerValue;
    } else {
      marker?.remove();
    }
    const targets = scope === explicitScope ? fields : hidden;
    if (!targets.length) continue;
    const districtKey =
      scope === 'shipping' ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area';
    const district = form.querySelector<HTMLSelectElement>(`select[name="${districtKey}"]`);
    if (!district || !/^[1-9][0-9]*$/.test(district.value)) continue;
    const label =
      Array.from(district.options).find((option) => option.value === district.value)?.text || '';
    const parts = label.split(',').map((part) => part.trim());
    if (
      label.length > 1000 ||
      parts.length !== 5 ||
      // Control characters are invalid in address hierarchy labels.
      // oxlint-disable-next-line no-control-regex
      parts.some((part) => !part || /[<>\x00-\x1f\x7f]/.test(part)) ||
      !/^[0-9]{5}$/.test(parts[4])
    )
      continue;
    const stateField = form.querySelector<HTMLSelectElement | HTMLInputElement>(
      `[name="${scope}_state"], #${scope}_state`,
    );
    const decode = (text: string) => {
      const node = form.ownerDocument.createElement('textarea');
      node.innerHTML = text;
      const name = node.value.trim().toLowerCase();
      const aliases: Record<string, string> = {
        'di yogyakarta': 'daerah istimewa yogyakarta',
        'd.i. yogyakarta': 'daerah istimewa yogyakarta',
        'dki jakarta': 'daerah khusus ibukota jakarta',
      };
      return aliases[name] || name;
    };
    const options =
      stateField?.tagName === 'SELECT'
        ? Object.fromEntries(
            Array.from((stateField as HTMLSelectElement).options, (option) => [
              option.value,
              option.text,
            ]),
          )
        : {};
    const matches = Object.entries({ ...states, ...options }).filter(
      ([code, name]) => code && decode(name) === decode(parts[3]),
    );
    const values = {
      city: parts[2],
      postcode: parts[4],
      state: matches.length === 1 ? matches[0][0] : '',
    };
    for (const key of targets) {
      const field = form.querySelector<HTMLInputElement | HTMLSelectElement>(
        `[name="${scope}_${key}"], #${scope}_${key}`,
      );
      // Unknown province mappings remain invalid; never invent a WooCommerce state code.
      if (field && field.value !== values[key]) field.value = values[key];
    }
  }
}
