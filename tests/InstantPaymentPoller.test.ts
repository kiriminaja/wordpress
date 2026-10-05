import { describe, expect, test } from 'bun:test';
import {
  createInstantPaymentPoller,
  paymentTerminal,
  PAYMENT_REQUEST_TIMEOUT,
  type PollPayment,
  type PaymentPollPhase,
} from '../src/lib/transactions/instant-payment-poller';

const payment = (id = 'a'): PollPayment => ({
  id,
  status: 'unpaid',
  amount: 100,
  qr_content: 'qr',
  order_ids: ['order'],
});
function harness(request?: (payment: PollPayment, signal: AbortSignal) => Promise<PollPayment>) {
  let now = 1_700_000_000_000;
  let counter = 0;
  const timers = new Map<number, { at: number; fn: () => void }>();
  const calls: { id: string; at: number; signal: AbortSignal }[] = [];
  const updates: PollPayment[] = [];
  const phases: Record<string, PaymentPollPhase> = {};
  let visible = true;
  let checking = false;
  const session = createInstantPaymentPoller({
    now: () => now,
    setTimeout: (fn, delay) => {
      const id = ++counter;
      timers.set(id, { at: now + delay, fn });
      return id as unknown as ReturnType<typeof setTimeout>;
    },
    clearTimeout: (id) => {
      timers.delete(id as unknown as number);
    },
    request: async (p, signal) => {
      calls.push({ id: p.id, at: now, signal });
      return request ? request(p, signal) : p;
    },
    onUpdate: (p) => {
      updates.push(p);
    },
    onPhase: (id, phase) => {
      phases[id] = phase;
    },
    onChecking: (value) => {
      checking = value;
    },
    isVisible: () => visible,
  });
  async function advance(ms: number) {
    const end = now + ms;
    for (let count = 0; count < 1000; count++) {
      const next = [...timers.entries()]
        .filter(([, value]) => value.at <= end)
        .sort((a, b) => a[1].at - b[1].at)[0];
      if (!next) break;
      now = next[1].at;
      timers.delete(next[0]);
      next[1].fn();
      for (let i = 0; i < 8; i++) await Promise.resolve();
      if (count === 999) throw new Error('Timer loop');
    }
    now = end;
    for (let i = 0; i < 8; i++) await Promise.resolve();
  }
  return {
    session,
    advance,
    calls,
    updates,
    phases,
    get now() {
      return now;
    },
    get checking() {
      return checking;
    },
    get timerCount() {
      return timers.size;
    },
    hide() {
      visible = false;
    },
    show() {
      visible = true;
    },
  };
}

describe('bounded Instant payment polling', () => {
  test('status-only automatic and manual refresh preserve same-payment QR and known amount', async () => {
    const h = harness(async (p) => ({ ...p, status: 'unpaid', amount: null, qr_content: '' }));
    h.session.start([payment()]);
    await h.advance(6500);
    expect(h.updates[0].qr_content).toBe('qr');
    expect(h.updates[0].amount).toBe(100);
    expect(h.session.refresh('a')).toBe(true);
    await h.advance(6500);
    expect(h.updates[1].qr_content).toBe('qr');
    expect(h.updates[1].amount).toBe(100);
    h.session.stop();
  });
  test('refresh replaces returned QR and honors zero amount without leaking another payment QR', async () => {
    const h = harness(async (p) => ({ ...p, qr_content: `replacement-${p.id}`, amount: 0 }));
    h.session.start([payment('a'), { ...payment('b'), qr_content: 'qr-b' }]);
    await h.advance(13000);
    expect(h.updates.map((p) => [p.id, p.qr_content, p.amount])).toEqual([
      ['a', 'replacement-a', 0], ['b', 'replacement-b', 0],
    ]);
    h.session.stop();
  });
  test('terminal or remotely expired refresh clears the booking QR', async () => {
    for (const status of ['paid', 'refunded', 'failed', 'expired']) {
      const h = harness(async (p) => ({ ...p, status, qr_content: '' }));
      h.session.start([payment()]); await h.advance(6500);
      expect(h.updates[0].qr_content).toBe('');
      expect(h.phases.a).toBe('complete');
      h.session.stop();
    }
    const h = harness(async (p) => ({ ...p, qr_content: '', expires_at: 1_700_000_001 }));
    h.session.start([payment()]); await h.advance(6500);
    expect(h.updates[0].qr_content).toBe('');
    expect(h.phases.a).toBe('expired');
    h.session.stop();
  });
  test('QR creation is not paid; five groups share serial sandbox-safe cadence', async () => {
    const h = harness();
    h.session.start(['a', 'b', 'c', 'd', 'e'].map(payment));
    expect(h.updates).toHaveLength(0);
    await h.advance(65_000);
    expect(h.calls).toHaveLength(10);
    expect(h.calls.slice(0, 5).map((call) => call.id)).toEqual(['a', 'b', 'c', 'd', 'e']);
    for (let i = 1; i < h.calls.length; i++)
      expect(h.calls[i].at - h.calls[i - 1].at).toBeGreaterThanOrEqual(6500);
  });
  test('single group uses at least fifteen seconds and stops on terminal statuses', async () => {
    for (const status of ['paid', 'refunded', 'failed', 'expired']) {
      expect(paymentTerminal({ ...payment(), status })).toBe(true);
      const h = harness(async (p) => ({ ...p, status }));
      h.session.start([payment()]);
      await h.advance(60_000);
      expect(h.calls).toHaveLength(1);
      expect(h.phases.a).toBe('complete');
    }
    const h = harness();
    h.session.start([payment()]);
    await h.advance(22_000);
    expect(h.calls[1].at - h.calls[0].at).toBe(15_000);
  });
  test('five minute bound does not rewrite remote status; manual refresh still works', async () => {
    const h = harness();
    h.session.start([payment()]);
    await h.advance(300_000);
    expect(h.phases.a).toBe('timeout');
    const count = h.calls.length;
    await h.advance(60_000);
    expect(h.calls).toHaveLength(count);
    expect(h.session.refresh('a')).toBe(true);
    await h.advance(6500);
    expect(h.calls).toHaveLength(count + 1);
    expect(h.updates.at(-1)?.status).toBe('unpaid');
    expect(h.phases.a).toBe('timeout');
  });
  test('backend expiry is earlier and bounds a hung request', async () => {
    const h = harness(() => new Promise(() => {}));
    h.session.start([{ ...payment(), expires_at: (h.now + 10_000) / 1000 }]);
    await h.advance(10_000);
    expect(h.phases.a).toBe('expired');
    expect(h.calls[0].signal.aborted).toBe(true);
    expect(h.checking).toBe(false);
    expect(h.session.refresh('a')).toBe(true);
  });
  test('hung manual checks release the queue and allow a successful refresh', async () => {
    let attempts = 0;
    const h = harness((p) => {
      attempts++;
      return attempts === 1 ? new Promise(() => {}) : Promise.resolve({ ...p, status: 'paid' });
    });
    h.hide();
    h.session.start([payment()]);
    expect(h.session.refresh('a')).toBe(true);
    await h.advance(6500);
    expect(h.checking).toBe(true);
    expect(h.session.refresh('a')).toBe(false);
    await h.advance(PAYMENT_REQUEST_TIMEOUT - 1);
    expect(h.calls[0].signal.aborted).toBe(false);
    await h.advance(1);
    expect(h.calls[0].signal.aborted).toBe(true);
    expect(h.checking).toBe(false);
    expect(h.phases.a).toBe('error');
    expect(h.updates).toHaveLength(0);
    expect(h.session.refresh('a')).toBe(true);
    await h.advance(6500);
    expect(h.calls).toHaveLength(2);
    expect(h.updates[0].status).toBe('paid');
    expect(h.phases.a).toBe('complete');
    expect(h.timerCount).toBe(0);
  });
  test('automatic request timeouts count once and stop after three with backoff', async () => {
    const h = harness((_p, signal) => new Promise((_resolve, reject) => {
      signal.addEventListener('abort', () => reject(new Error('aborted')));
    }));
    h.session.start([payment()]);
    await h.advance(6500 + PAYMENT_REQUEST_TIMEOUT);
    expect(h.calls).toHaveLength(1);
    expect(h.calls[0].signal.aborted).toBe(true);
    expect(h.checking).toBe(false);
    expect(h.phases.a).toBe('polling');
    await h.advance(29_999);
    expect(h.calls).toHaveLength(1);
    await h.advance(1);
    expect(h.calls).toHaveLength(2);
    await h.advance(PAYMENT_REQUEST_TIMEOUT + 59_999);
    expect(h.calls).toHaveLength(2);
    await h.advance(1 + PAYMENT_REQUEST_TIMEOUT);
    expect(h.calls).toHaveLength(3);
    expect(h.calls.every((call) => call.signal.aborted)).toBe(true);
    expect(h.phases.a).toBe('error');
    expect(h.checking).toBe(false);
    expect(h.timerCount).toBe(0);
    await h.advance(300_000);
    expect(h.calls).toHaveLength(3);
  });
  test('automatic request timeout never extends the original five-minute deadline', async () => {
    const h = harness(() => new Promise(() => {}));
    h.hide();
    h.session.start([payment()]);
    await h.advance(290_000);
    h.show();
    await h.advance(6500);
    expect(h.calls).toHaveLength(1);
    expect(h.calls[0].signal.aborted).toBe(false);
    await h.advance(3500);
    expect(h.calls[0].signal.aborted).toBe(true);
    expect(h.phases.a).toBe('timeout');
    expect(h.checking).toBe(false);
    expect(h.timerCount).toBe(0);
    await h.advance(300_000);
    expect(h.calls).toHaveLength(1);
  });
  test('late timed-out results cannot clear a newer request or update payment', async () => {
    const resolvers: ((p: PollPayment) => void)[] = [];
    const h = harness(() => new Promise((resolve) => resolvers.push(resolve)));
    h.hide();
    h.session.start([payment()]);
    h.session.refresh('a');
    await h.advance(6500 + PAYMENT_REQUEST_TIMEOUT);
    expect(h.timerCount).toBe(0);
    expect(h.session.refresh('a')).toBe(true);
    await h.advance(0);
    expect(h.calls).toHaveLength(2);
    resolvers[0]({ ...payment(), status: 'paid' });
    await h.advance(0);
    expect(h.updates).toHaveLength(0);
    expect(h.checking).toBe(true);
    expect(h.timerCount).toBe(1);
    expect(h.session.refresh('a')).toBe(false);
    resolvers[1]({ ...payment(), status: 'paid' });
    await h.advance(0);
    expect(h.updates).toHaveLength(1);
    expect(h.checking).toBe(false);
    expect(h.timerCount).toBe(0);
    await h.advance(PAYMENT_REQUEST_TIMEOUT);
    expect(h.phases.a).toBe('complete');
    h.session.stop();
    expect(h.timerCount).toBe(0);
  });
  test('manual timeout after the five-minute bound preserves local timeout and remote status', async () => {
    const h = harness(() => new Promise(() => {}));
    h.hide();
    h.session.start([payment()]);
    await h.advance(300_000);
    expect(h.phases.a).toBe('timeout');
    expect(h.session.refresh('a')).toBe(true);
    await h.advance(PAYMENT_REQUEST_TIMEOUT);
    expect(h.calls[0].signal.aborted).toBe(true);
    expect(h.phases.a).toBe('timeout');
    expect(h.updates).toHaveLength(0);
    expect(h.checking).toBe(false);
    expect(h.timerCount).toBe(0);
    expect(h.session.refresh('a')).toBe(true);
    await h.advance(0);
    h.session.stop();
    expect(h.calls[1].signal.aborted).toBe(true);
    expect(h.timerCount).toBe(0);
    await h.advance(300_000);
    expect(h.calls).toHaveLength(2);
  });
  test('three errors or ID mismatches stop with bounded backoff and no accepted updates', async () => {
    for (const mismatch of [true, false]) {
      const h = harness(async (p) => {
        if (mismatch) return { ...p, id: 'wrong' };
        throw new Error('offline');
      });
      h.session.start([payment()]);
      await h.advance(200_000);
      expect(h.calls).toHaveLength(3);
      expect(h.phases.a).toBe('error');
      expect(h.updates).toHaveLength(0);
      expect(h.calls[1].at - h.calls[0].at).toBe(30_000);
      expect(h.calls[2].at - h.calls[1].at).toBe(60_000);
      expect(h.session.refresh('a')).toBe(true);
    }
  });
  test('manual checks never overlap; teardown aborts and ignores late results across scopes', async () => {
    let resolve!: (p: PollPayment) => void;
    const h = harness(
      () =>
        new Promise((done) => {
          resolve = done;
        }),
    );
    h.session.start([payment()]);
    await h.advance(6500);
    expect(h.session.refresh('a')).toBe(false);
    h.session.stop();
    expect(h.calls[0].signal.aborted).toBe(true);
    h.session.start([payment('b')]);
    resolve({ ...payment(), status: 'paid' });
    await h.advance(0);
    expect(h.updates).toHaveLength(0);
    expect(h.phases.b).toBe('polling');
    h.session.stop();
    await h.advance(300_000);
    expect(h.calls).toHaveLength(1);
  });
  test('hidden tabs pause calls without extending deadlines and resume when visible', async () => {
    const h = harness();
    h.hide();
    h.session.start([payment()]);
    await h.advance(60_000);
    expect(h.calls).toHaveLength(0);
    h.show();
    await h.advance(6500);
    expect(h.calls).toHaveLength(1);
    h.hide();
    await h.advance(300_000);
    expect(h.phases.a).toBe('timeout');
    expect(h.calls).toHaveLength(1);
  });
  test('manual queued refresh shares the global request gap', async () => {
    const h = harness();
    h.session.start([payment(), payment('b')]);
    await h.advance(6500);
    expect(h.session.refresh('a')).toBe(true);
    expect(h.session.refresh('b')).toBe(false);
    await h.advance(6499);
    expect(h.calls).toHaveLength(1);
    await h.advance(1);
    expect(h.calls).toHaveLength(2);
  });
});
