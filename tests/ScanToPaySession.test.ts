import { afterEach, describe, expect, test } from 'bun:test';
import {
  createPaymentSession,
  isPaymentPaid,
  type PaymentData,
  type PaymentState,
} from '../src/lib/payments/scan-to-pay';
import { postWordPressAction } from '../src/lib/wordpress/ajax';

const qrPayment: PaymentData = {
  payment_data: { qr_content: 'test-qr', status_code: 0, pay_time: '2026-07-01 12:00:00' },
  payment_in_wc_data: { method: 'qris', status: 'unpaid' },
  sum_fee_cod: 0,
  sum_fee_non_cod: 25000,
};

function harness(request: (signal: AbortSignal) => Promise<PaymentData>) {
  let state: PaymentState | undefined;
  let paid = 0;
  let queued: { callback: () => void; delay: number } | undefined;
  const session = createPaymentSession({
    request,
    onUpdate: (next) => {
      state = next;
    },
    onPaid: () => {
      paid += 1;
    },
    errorMessage: 'QR unavailable',
    expiredMessage: 'QR expired',
    schedule: (callback, delay) => {
      queued = { callback, delay };
      return 1 as unknown as ReturnType<typeof setTimeout>;
    },
    cancel: () => {
      queued = undefined;
    },
  });
  return {
    session,
    get state() {
      return state;
    },
    get paid() {
      return paid;
    },
    get delay() {
      return queued?.delay;
    },
    async tick() {
      const next = queued;
      queued = undefined;
      next?.callback();
      await new Promise<void>((resolve) => queueMicrotask(resolve));
    },
  };
}

describe('Scan to Pay session', () => {
  test('QRIS creation fields are not payment confirmation', () => {
    expect(isPaymentPaid(qrPayment)).toBe(false);
    for (const status of ['paid', 'settlement', 'settled', 'success']) {
      expect(isPaymentPaid({ ...qrPayment, payment_data: { status } })).toBe(true);
    }
    expect(isPaymentPaid({ ...qrPayment, payment_data: { paid_at: '2026-07-01' } })).toBe(true);
    expect(isPaymentPaid({ payment_in_wc_data: { status: 'paid' } })).toBe(true);
    expect(
      isPaymentPaid({ payment_data: { status_code: 0 }, payment_in_wc_data: { method: 'credit' } }),
    ).toBe(true);
  });

  test('continues polling once a QR is ready and completes only once', async () => {
    let calls = 0;
    const flow = harness(async () =>
      ++calls === 1 ? qrPayment : { ...qrPayment, payment_data: { paid_at: '2026-07-01' } },
    );
    await flow.session.start();
    expect(flow.state?.phase).toBe('ready');
    expect(flow.delay).toBe(5000);
    await flow.tick();
    expect(flow.paid).toBe(1);
    expect(flow.delay).toBeUndefined();
    await flow.session.refresh();
    expect(calls).toBe(2);
    expect(flow.paid).toBe(1);
  });

  test('retries pending QR generation with a limit and permits manual retry', async () => {
    let calls = 0;
    const flow = harness(async () => {
      calls += 1;
      return calls > 20 ? qrPayment : {};
    });
    await flow.session.start();
    expect(flow.delay).toBe(1000);
    for (let attempt = 1; attempt < 20; attempt += 1) await flow.tick();
    expect(calls).toBe(20);
    expect(flow.state?.phase).toBe('error');
    expect(flow.delay).toBeUndefined();
    await flow.session.refresh();
    expect(flow.state?.phase).toBe('ready');
    flow.session.stop();
  });

  test('keeps QR visible through a transient polling error', async () => {
    let calls = 0;
    const flow = harness(async () => {
      if (++calls === 2) throw new Error('Network error');
      return qrPayment;
    });
    await flow.session.start();
    await flow.tick();
    expect(flow.state?.phase).toBe('ready');
    expect(flow.state?.payment?.payment_data?.qr_content).toBe('test-qr');
    expect(flow.state?.error).toBe('Network error');
    expect(flow.delay).toBe(5000);
    await flow.tick();
    expect(flow.state?.error).toBe('');
    flow.session.stop();
  });

  test('stops on expired payment and clears the old QR', async () => {
    let calls = 0;
    const flow = harness(async () =>
      ++calls === 1 ? qrPayment : { payment_data: { status: 'expired', qr_content: 'old-qr' } },
    );
    await flow.session.start();
    await flow.tick();
    expect(flow.state?.phase).toBe('expired');
    expect(flow.state?.payment).toBeNull();
    expect(flow.delay).toBeUndefined();
    expect(flow.paid).toBe(0);
  });

  test('does not display a stale QR when the remote QR disappears', async () => {
    let calls = 0;
    const flow = harness(async () => (++calls === 1 ? qrPayment : {}));
    await flow.session.start();
    await flow.tick();
    expect(flow.state?.payment).toBeNull();
    expect(flow.state?.phase).toBe('loading');
    expect(flow.delay).toBe(1000);
    flow.session.stop();
  });

  test('close aborts an in-flight request and ignores a late paid response', async () => {
    let signal: AbortSignal | undefined;
    let resolve!: (data: PaymentData) => void;
    const flow = harness((current) => {
      signal = current;
      return new Promise((done) => {
        resolve = done;
      });
    });
    const loading = flow.session.start();
    flow.session.stop();
    expect(signal?.aborted).toBe(true);
    resolve({ payment_in_wc_data: { status: 'paid' } });
    await loading;
    expect(flow.paid).toBe(0);
    expect(flow.delay).toBeUndefined();
  });

  test('manual refresh cannot overlap an active request', async () => {
    let calls = 0;
    let resolve!: (data: PaymentData) => void;
    const flow = harness(() => {
      calls += 1;
      return new Promise((done) => {
        resolve = done;
      });
    });
    const loading = flow.session.start();
    await flow.session.refresh();
    expect(calls).toBe(1);
    resolve(qrPayment);
    await loading;
    flow.session.stop();
    expect(flow.delay).toBeUndefined();
  });
});

const originalFetch = globalThis.fetch;
afterEach(() => {
  globalThis.fetch = originalFetch;
});

test('payment AJAX sends explicit route, nested nonce, pickup ID, and abort signal', async () => {
  const controller = new AbortController();
  globalThis.fetch = (async (url: string | URL | Request, init?: RequestInit) => {
    expect(url).toBe('/wp-admin/admin-ajax.php');
    expect(init?.signal).toBe(controller.signal);
    const body = init?.body as URLSearchParams;
    expect(body.get('action')).toBe('kiriof_get_payment_form');
    expect(body.get('data[nonce]')).toBe('payment-nonce');
    expect(body.get('data[payment_id]')).toBe('pickup-123');
    return Response.json({ success: true, data: { status: 200, data: qrPayment } });
  }) as typeof fetch;
  const result = await postWordPressAction<PaymentData>(
    'kiriof_get_payment_form',
    { payment_id: 'pickup-123' },
    {
      ajaxUrl: '/wp-admin/admin-ajax.php',
      nonce: 'payment-nonce',
      signal: controller.signal,
    },
  );
  expect(result.data).toEqual(qrPayment);
});

test('payment AJAX exposes server error instead of treating it as missing QR', async () => {
  globalThis.fetch = (async () =>
    Response.json(
      { success: false, data: { message: 'Invalid nonce' } },
      { status: 403 },
    )) as typeof fetch;
  await expect(
    postWordPressAction('kiriof_get_payment_form', {}, { ajaxUrl: '/ajax', nonce: 'bad' }),
  ).rejects.toThrow('Invalid nonce');
});
