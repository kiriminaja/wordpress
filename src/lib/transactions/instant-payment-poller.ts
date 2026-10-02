import type { InstantPayment } from './types';

export type PollPayment = InstantPayment & { expires_at?: number };
export type PaymentPollPhase = 'polling' | 'complete' | 'timeout' | 'expired' | 'error' | 'idle';
export const PAYMENT_REQUEST_GAP = 6500;
export const PAYMENT_REQUEST_TIMEOUT = 25_000;
export const PAYMENT_POLL_LIMIT = 5 * 60_000;
export function paymentTerminal(payment: InstantPayment): boolean {
  return ['paid', 'refunded', 'failed', 'expired'].includes(payment.status);
}

type Timer = ReturnType<typeof setTimeout>;
type Group = {
  payment: PollPayment;
  phase: PaymentPollPhase;
  deadline: number;
  due: number;
  failures: number;
};
type Options = {
  request: (payment: PollPayment, signal: AbortSignal) => Promise<PollPayment>;
  onUpdate: (payment: PollPayment) => void;
  onPhase: (id: string, phase: PaymentPollPhase) => void;
  onChecking?: (checking: boolean) => void;
  now?: () => number;
  setTimeout?: (callback: () => void, delay: number) => Timer;
  clearTimeout?: (timer: Timer) => void;
  isVisible?: () => boolean;
};

/** One serial, rate-limited queue for the entire result, never a booking retry.
 * Deadlines are local UI bounds only: no remote payment is cancelled or rewritten.
 */
export function createInstantPaymentPoller(options: Options) {
  const now = options.now ?? Date.now;
  const later = options.setTimeout ?? setTimeout;
  const cancel = options.clearTimeout ?? clearTimeout;
  let groups: Group[] = [];
  let timer: Timer | undefined;
  let controller: AbortController | undefined;
  let requestTimer: Timer | undefined;
  let epoch = 0;
  let active = false;
  let queued: Group | undefined;
  let nextAllowed = 0;
  let cursor = 0;

  function phase(group: Group, value: PaymentPollPhase) {
    if (group.phase !== value) {
      group.phase = value;
      options.onPhase(group.payment.id, value);
    }
  }
  function stop() {
    epoch++;
    active = false;
    if (timer !== undefined) cancel(timer);
    timer = undefined;
    if (requestTimer !== undefined) cancel(requestTimer);
    requestTimer = undefined;
    controller?.abort();
    controller = undefined;
    queued = undefined;
    groups = [];
    options.onChecking?.(false);
  }
  function expiry(payment: PollPayment): number {
    const value = payment.expires_at;
    return typeof value === 'number' && Number.isFinite(value) && value > 0
      ? value < 1e12
        ? value * 1000
        : value
      : Infinity;
  }
  function bound(group: Group) {
    if (group.phase === 'polling' && now() >= group.deadline) {
      phase(group, expiry(group.payment) <= group.deadline ? 'expired' : 'timeout');
    }
  }
  function schedule() {
    if (timer !== undefined) cancel(timer);
    timer = undefined;
    if (!active) return;
    const pending = groups.filter((group) => group.phase === 'polling');
    const deadlines = pending.map((group) => group.deadline);
    let wake = Math.min(...deadlines);
    if (!controller) {
      if (queued) wake = Math.min(wake, Math.max(now(), nextAllowed));
      else if (pending.length) {
        const due = Math.max(nextAllowed, Math.min(...pending.map((group) => group.due)));
        wake = Math.min(
          wake,
          options.isVisible?.() === false ? Math.max(due, now() + PAYMENT_REQUEST_GAP) : due,
        );
      }
    }
    if (Number.isFinite(wake)) timer = later(tick, Math.max(0, wake - now()));
  }
  function tick() {
    timer = undefined;
    if (!active) return;
    for (const group of groups) bound(group);
    if (controller) {
      schedule();
      return;
    }
    if (now() < nextAllowed) {
      schedule();
      return;
    }
    let group = queued;
    const manual = Boolean(group);
    if (!group && options.isVisible?.() !== false) {
      for (let offset = 0; offset < groups.length; offset++) {
        const index = (cursor + offset) % groups.length;
        const candidate = groups[index];
        if (candidate.phase === 'polling' && candidate.due <= now()) {
          group = candidate;
          cursor = (index + 1) % groups.length;
          break;
        }
      }
    }
    if (!group) {
      schedule();
      return;
    }
    queued = undefined;
    void check(group, manual);
  }
  async function check(group: Group, manual: boolean) {
    const current = epoch;
    const abort = new AbortController();
    controller = abort;
    options.onChecking?.(true);
    nextAllowed = now() + PAYMENT_REQUEST_GAP;
    const cadence = Math.max(
      15_000,
      groups.filter((item) => item.phase === 'polling').length * PAYMENT_REQUEST_GAP,
    );
    group.due = now() + cadence;
    function failure(timedOut = false) {
      // A local request failure must not replace an already terminal UI phase.
      if (['complete', 'timeout', 'expired'].includes(group.phase)) return;
      group.failures++;
      if ((manual && timedOut) || group.failures >= 3) phase(group, 'error');
      else group.due = now() + Math.min(60_000, cadence * 2 ** group.failures);
    }
    // Manual checks can run after the automatic deadline, but never indefinitely.
    const deadlineTimer = later(
      () => {
        if (current !== epoch || controller !== abort) return;
        bound(group);
        failure(true);
        abort.abort();
        requestTimer = undefined;
        controller = undefined;
        options.onChecking?.(false);
        schedule();
      },
      manual
        ? PAYMENT_REQUEST_TIMEOUT
        : Math.max(0, Math.min(PAYMENT_REQUEST_TIMEOUT, group.deadline - now())),
    );
    requestTimer = deadlineTimer;
    schedule();
    try {
      const payment = await options.request(group.payment, abort.signal);
      if (current !== epoch || abort.signal.aborted || !active) return;
      if (payment.id !== group.payment.id) throw new Error('Payment ID mismatch');
      group.payment = payment;
      group.failures = 0;
      group.deadline = Math.min(group.deadline, expiry(payment));
      options.onUpdate(payment);
      if (paymentTerminal(payment)) phase(group, 'complete');
      else bound(group);
    } catch {
      if (current !== epoch || abort.signal.aborted || !active) return;
      failure();
    } finally {
      if (deadlineTimer !== undefined) cancel(deadlineTimer);
      if (current === epoch && controller === abort) {
        requestTimer = undefined;
        controller = undefined;
        options.onChecking?.(false);
        schedule();
      }
    }
  }
  function start(payments: PollPayment[]) {
    stop();
    active = true;
    cursor = 0;
    const started = now();
    nextAllowed = Math.max(nextAllowed, started + PAYMENT_REQUEST_GAP);
    const seen = new Set<string>();
    groups = payments
      .filter((payment) => payment.id && !seen.has(payment.id) && Boolean(seen.add(payment.id)))
      .map((payment) => {
        const group: Group = {
          payment,
          phase: paymentTerminal(payment) ? 'complete' : payment.qr_content ? 'polling' : 'idle',
          deadline: Math.min(started + PAYMENT_POLL_LIMIT, expiry(payment)),
          due: started + PAYMENT_REQUEST_GAP,
          failures: 0,
        };
        options.onPhase(payment.id, group.phase);
        bound(group);
        return group;
      });
    schedule();
  }
  function refresh(id: string): boolean {
    if (!active || controller || queued) return false;
    const group = groups.find((item) => item.payment.id === id);
    if (!group) return false;
    queued = group;
    options.onChecking?.(true);
    schedule();
    return true;
  }
  return { start, stop, refresh };
}
