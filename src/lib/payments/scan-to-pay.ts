export type PaymentData = {
  payment_data?: {
    payment_id?: string;
    payment_status?: string;
    status?: string;
    status_code?: string | number;
    paid_at?: string;
    pay_time?: string;
    qr_content?: string;
  };
  payment_in_wc_data?: { method?: string; status?: string };
  expired_at?: string;
  sum_fee_cod?: number;
  sum_fee_non_cod?: number;
};

export type PaymentState = {
  phase: 'loading' | 'ready' | 'error' | 'expired';
  payment: PaymentData | null;
  error: string;
  checking: boolean;
};

export function isPaymentPaid(data: PaymentData): boolean {
  const remote = data.payment_data ?? {};
  const local = data.payment_in_wc_data ?? {};
  const status = String(remote.payment_status || remote.status || '').toLowerCase();
  const paidStatus = ['paid', 'settlement', 'settled', 'success'].includes(status);
  // QRIS pay_time is the QR creation time, and status_code 0 is not proof of payment.
  const paid =
    String(local.method || '').toLowerCase() === 'qris'
      ? !!remote.paid_at || paidStatus
      : String(remote.status_code ?? '').trim() === '0' ||
        !!remote.pay_time ||
        !!remote.paid_at ||
        paidStatus;
  return paid || String(local.status || '').toLowerCase() === 'paid';
}

export function isPaymentExpired(data: PaymentData): boolean {
  const remote = data.payment_data ?? {};
  return ['expired', 'cancelled', 'canceled', 'failed'].includes(
    String(remote.payment_status || remote.status || '').toLowerCase(),
  );
}

/** One dialog session: serial polling, bounded QR generation, and cancellable requests. */
export function createPaymentSession(options: {
  request: (signal: AbortSignal) => Promise<PaymentData>;
  onUpdate: (state: PaymentState) => void;
  onPaid: () => void;
  errorMessage: string;
  expiredMessage: string;
  schedule?: (callback: () => void, delay: number) => ReturnType<typeof setTimeout>;
  cancel?: (timer: ReturnType<typeof setTimeout>) => void;
}) {
  const schedule = options.schedule ?? setTimeout;
  const cancel = options.cancel ?? clearTimeout;
  let state: PaymentState = { phase: 'loading', payment: null, error: '', checking: false };
  let timer: ReturnType<typeof setTimeout> | undefined;
  let controller: AbortController | undefined;
  let stopped = false;
  let generationAttempts = 0;

  function update(changes: Partial<PaymentState>): void {
    state = { ...state, ...changes };
    options.onUpdate(state);
  }

  function clearTimer(): void {
    if (timer !== undefined) cancel(timer);
    timer = undefined;
  }

  function stop(): void {
    stopped = true;
    clearTimer();
    controller?.abort();
  }

  function pollAfter(delay: number): void {
    timer = schedule(() => {
      timer = undefined;
      void check();
    }, delay);
  }

  async function check(): Promise<void> {
    if (stopped || state.checking) return;
    clearTimer();
    const current = new AbortController();
    controller = current;
    update({ checking: true, error: '', phase: state.payment ? 'ready' : 'loading' });
    try {
      const data = await options.request(current.signal);
      if (stopped || current.signal.aborted) return;
      if (isPaymentPaid(data)) {
        stop();
        options.onPaid();
        return;
      }
      if (isPaymentExpired(data)) {
        update({ phase: 'expired', payment: null, error: options.expiredMessage });
        return;
      }
      if (!data.payment_data?.qr_content) {
        // Never keep displaying an old QR when the server no longer returns it.
        update({ payment: null, phase: 'loading' });
        if (++generationAttempts >= 20) throw new Error(options.errorMessage);
        pollAfter(1000);
        return;
      }
      generationAttempts = 0;
      update({ payment: data, phase: 'ready' });
      pollAfter(5000);
    } catch (cause) {
      if (stopped || current.signal.aborted) return;
      update({
        phase: state.payment ? 'ready' : 'error',
        error: cause instanceof Error ? cause.message : options.errorMessage,
      });
      // Keep a usable QR visible through a transient status-check failure.
      if (state.payment) pollAfter(5000);
    } finally {
      if (!stopped && !current.signal.aborted) update({ checking: false });
    }
  }

  return {
    start: check,
    refresh: () => {
      generationAttempts = 0;
      return check();
    },
    stop,
  };
}
