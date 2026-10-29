import type { PinSnapshot } from '../state/classic-pin.svelte';

export interface ClassicPinTransportConfig {
  ajaxUrl: string;
  nonce: string;
  pinErrors?: Record<string, string>;
}
/** Only explicit coordinates_saved acknowledgment succeeds; never display server-provided text. */
export async function saveClassicPin(
  snapshot: PinSnapshot,
  config: ClassicPinTransportConfig,
  fallback: string,
  fetcher: typeof fetch = fetch,
): Promise<void> {
  const response = await fetcher(config.ajaxUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
    body: new URLSearchParams({
      action: 'kiriof-session-save',
      nonce: config.nonce,
      data: JSON.stringify({
        action: 'save_classic_coordinates',
        address_scope: snapshot.address_scope,
        country: snapshot.country,
        coordinates: snapshot.coordinates,
      }),
    }).toString(),
  });
  const payload = (await response.json()) as {
    success?: boolean;
    data?: { coordinates_saved?: boolean; code?: string };
  };
  if (!response.ok || !payload.success || payload.data?.coordinates_saved !== true) {
    const code = payload.data?.code;
    throw new Error((code && config.pinErrors?.[code]) || fallback);
  }
}
