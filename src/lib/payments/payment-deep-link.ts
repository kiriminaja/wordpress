import type { PaymentRow } from './types';

/** URL intent is not authorization: only the server-provided eligible row opens. */
export function paymentDeepLink(
  params: URLSearchParams,
  rows: PaymentRow[],
): PaymentRow | undefined {
  if (!['1', 'true'].includes(params.get('open_payment') ?? '')) return;
  const instantId = params.get('instant_payment_id');
  const pickup = params.get('pickup_number');
  if (instantId && pickup) return;
  return rows.find(
    (row) =>
      row.actions.some((action) => action.type === 'pay') &&
      (instantId
        ? row.deliveryType === 'instant' &&
          row.identity === instantId &&
          row.method === 'QRIS' &&
          ['pending', 'unpaid'].includes(row.status) &&
          row.orderIds.length > 0
        : Boolean(pickup) && row.deliveryType === 'express' && row.pickupNumber === pickup),
  );
}
