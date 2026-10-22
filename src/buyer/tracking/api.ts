import type { TrackingData, TrackingRoot } from './types';

function record(value: unknown): Record<string, unknown> {
  return value !== null && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}
/** Only scalar display values cross the API boundary; Svelte renders them as text. */
export function displayValue(value: unknown, fallback = '-'): string {
  return (typeof value === 'string' && value !== '') || typeof value === 'number'
    ? String(value)
    : fallback;
}
export function trackingData(value: unknown): TrackingData {
  const data = record(value);
  const details = record(data.details);
  const destination = record(details.destination);
  return {
    number_order: displayValue(data.number_order),
    details: {
      awb: displayValue(details.awb),
      service: displayValue(details.service),
      destination: {
        name: displayValue(destination.name),
        city: displayValue(destination.city),
        province: displayValue(destination.province),
      },
    },
    histories: Array.isArray(data.histories)
      ? data.histories.map((value) => {
          const row = record(value);
          return { created_at: displayValue(row.created_at), status: displayValue(row.status) };
        })
      : [],
  };
}
export async function lookupTracking(root: TrackingRoot, orderNumber: string, signal: AbortSignal) {
  const route = root.kiriofAjaxRoute?.() || root.kiriofAjax?.ajaxurl || '';
  const response = await root.fetch(route, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
    body: new URLSearchParams({
      action: 'kiriof-tracking-ajax',
      order_number: orderNumber,
    }).toString(),
    signal,
  });
  if (!response.ok) throw new Error('Tracking request failed');
  const envelope = record(await response.json());
  const result = envelope.success ? record(envelope.data) : {};
  return result.status === 200
    ? { ok: true as const, data: trackingData(result.data) }
    : { ok: false as const, message: displayValue(result.message, '') };
}
