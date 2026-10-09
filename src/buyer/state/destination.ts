export interface DestinationInput {
  [field: string]: unknown;
  district_id?: unknown;
  district_label?: unknown;
  postcode?: unknown;
  country?: unknown;
  address_type?: unknown;
}
export interface Destination {
  readonly version: 1;
  readonly district_id: string;
  readonly district_label: string;
  readonly postcode: string;
  readonly country: string;
  readonly address_type: string;
}
function text(value: unknown): string {
  return null === value || undefined === value ? '' : String(value).trim().replace(/\s+/g, ' ');
}

export function normalizeDestination(destination?: DestinationInput | null): Destination {
  const input = destination || {};
  var district = text(input.district_id);
  var validDistrict =
    /^\d+$/.test(district) && Number.isSafeInteger(Number(district)) && Number(district) > 0;

  return Object.freeze({
    version: 1,
    district_id: validDistrict ? String(Number(district)) : '',
    district_label: validDistrict ? text(input.district_label) : '',
    postcode: text(input.postcode).replace(/\s+/g, '').toUpperCase(),
    country: text(input.country).toUpperCase(),
    address_type: text(input.address_type).toLowerCase(),
  });
}
