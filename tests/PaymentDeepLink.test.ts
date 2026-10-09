import { expect, test } from 'bun:test';
import { paymentDeepLink } from '../src/lib/payments/payment-deep-link';
import type { PaymentRow } from '../src/lib/payments/types';
const instant = { rowKey: 'instant:PAY-1', deliveryType: 'instant', identity: 'PAY-1', pickupNumber: '', orderIds: ['KA-1'], method: 'QRIS', status: 'unpaid', actions: [{ type: 'pay' }] } as PaymentRow;
const regular = { ...instant, rowKey: 'express:PAY-1', deliveryType: 'express', pickupNumber: 'PAY-1', orderIds: [] } as PaymentRow;
test('Instant and Regular deep links select only eligible server rows in their own partition', () => {
  const rows = [regular, instant];
  expect(paymentDeepLink(new URLSearchParams('instant_payment_id=PAY-1&open_payment=1'), rows)).toBe(instant);
  expect(paymentDeepLink(new URLSearchParams('pickup_number=PAY-1&open_payment=true'), rows)).toBe(regular);
});
test('URL intent cannot bypass eligibility or open another row', () => {
  for (const row of [{ ...instant, status: 'paid' }, { ...instant, method: 'CREDIT' }, { ...instant, actions: [] }, { ...instant, orderIds: [] }]) {
    expect(paymentDeepLink(new URLSearchParams('instant_payment_id=PAY-1&open_payment=1'), [row] as PaymentRow[])).toBeUndefined();
  }
  for (const query of ['instant_payment_id=UNKNOWN&open_payment=1', 'instant_payment_id=PAY-1', 'instant_payment_id=PAY-1&open_payment=0', 'instant_payment_id=PAY-1&pickup_number=PAY-1&open_payment=1']) {
    expect(paymentDeepLink(new URLSearchParams(query), [instant, regular])).toBeUndefined();
  }
});
