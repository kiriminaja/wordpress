import type { SelectorOption } from '../types/selector';

export function districtRows(response: unknown, term: string): SelectorOption[] {
  let data = response;
  for (let depth = 0; depth < 5 && !Array.isArray(data); depth++) {
    if (!data || typeof data !== 'object') throw new Error('Invalid district response');
    const envelope = data as Record<string, unknown>;
    if (
      envelope.success === false ||
      (envelope.term !== undefined && String(envelope.term).trim() !== term)
    ) {
      throw new Error('Invalid district response');
    }
    data = envelope.data !== undefined ? envelope.data : envelope.results;
  }
  if (!Array.isArray(data)) throw new Error('Invalid district response');
  return data
    .filter(
      (row) =>
        row &&
        /^[1-9][0-9]*$/.test(String(row.id)) &&
        typeof row.text === 'string' &&
        row.text.trim(),
    )
    .map((row) => ({ value: String(row.id), label: row.text }));
}

/** This is exclusively a read lookup, never a checkout/session write. */
export async function searchSubdistrict(
  endpoint: string,
  nonce: string,
  term: string,
  signal: AbortSignal,
): Promise<SelectorOption[]> {
  const body = new URLSearchParams({
    action: 'kiriminaja_subdistrict_search',
    nonce,
    term,
    'data[term]': term,
    'data[search]': term,
  });
  const response = await window.fetch(endpoint, {
    method: 'POST',
    credentials: 'same-origin',
    body,
    signal,
  });
  if (!response.ok) throw new Error('District lookup failed');
  return districtRows(await response.json(), term);
}
