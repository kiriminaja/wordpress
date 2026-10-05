<script lang="ts">
  import { onDestroy, untrack } from 'svelte';
  import { IconAlertTriangle, IconChevronDown, IconArrowUp, IconArrowDown } from '@tabler/icons-svelte';
  import { qr } from '@svelte-put/qr/svg';
  import * as Dialog from '$lib/components/ui/dialog';
  import * as Alert from '$lib/components/ui/alert';
  import * as Collapsible from '$lib/components/ui/collapsible';
  import { Button } from '$lib/components/ui/button';
  import CreditPinInput from '$lib/payments/CreditPinInput.svelte';
  import PaymentMethodSelector from '$lib/payments/PaymentMethodSelector.svelte';
  import ShipmentSummarySkeleton from '$lib/payments/ShipmentSummarySkeleton.svelte';
  import ShipmentOperationProgress from '$lib/payments/ShipmentOperationProgress.svelte';
  import { Spinner } from '$lib/components/ui/spinner';
  import type { PaymentMethodOption } from '$lib/payments/types';
  import { postWordPressAction } from '$lib/wordpress/ajax';
  import { InstantProcessSession, quoteExpired, createInstantQuoteClock, isTopAccount, quoteSummary } from './instant-process-session';
  import { createInstantPaymentPoller, paymentTerminal, type PaymentPollPhase, type PollPayment } from './instant-payment-poller';
  import type { InstantQuote, InstantDispatchResult, InstantPayment } from './types';

  let { open = $bindable(false), orderIds, ajaxUrl, nonce, i18n, onComplete }: {
    open?: boolean; orderIds: string[]; ajaxUrl: string; nonce: string; i18n: Record<string, string>; onComplete?: () => void;
  } = $props();
  let quote = $state<InstantQuote | null>(null);
  let result = $state<InstantDispatchResult | null>(null);
  let method = $state('');
  let pin = $state('');
  let step = $state<'summary' | 'pin'>('summary');
  let busy = $state(false);
  let dispatching = $state(false);
  let error = $state('');
  let checkingPayments = $state(false);
  let pollPhases = $state<Record<string, PaymentPollPhase>>({});
  let remaining = $state(0);
  let orderInformationOpen = $state(false);
  let dispatchAttempted = $state(false);
  let displayRows = $state<InstantQuote['rows']>([]);
  let generation = 0;
  let controller: AbortController | null = null;
  const quoteClock = createInstantQuoteClock({
    onTick: (value) => { remaining = value; },
    onExpire: () => { if (open && quote && !result && !busy) void review(true); },
  });
  const session = new InstantProcessSession();
  const paymentPoller = createInstantPaymentPoller({
    request: async (payment, signal) => {
      const validatedIds = paymentIds(payment);
      if (!validatedIds.length) throw new Error(text('actionError'));
      const data = await call<PollPayment>('kiriof_instant_payment', { payment_id: payment.id, order_ids: JSON.stringify(validatedIds) }, signal);
      return { ...data, order_ids: validatedIds };
    },
    onUpdate: (payment) => {
      if (open && result) result = { ...result, payments: result.payments.map((item) => item.id === payment.id ? payment : item) };
    },
    onPhase: (id, phase) => { pollPhases = { ...pollPhases, [id]: phase }; },
    onChecking: (value) => { checkingPayments = value; },
    isVisible: () => typeof document === 'undefined' || document.visibilityState !== 'hidden',
  });
  const expired = $derived(quote ? remaining <= 0 : false);
  const topAccount = $derived(quote ? isTopAccount(quote) : false);
  const summary = $derived(quote ? quoteSummary(quote, Object.fromEntries(quote.rows.map((row) => [row.id, true]))) : null);
  const changedCount = $derived(quote?.rows.filter((row) => row.eligible && row.before !== row.after).length ?? 0);
  const ids = $derived(quote?.rows.map((row) => row.id) ?? []);
  const methods = $derived(quote?.payment_methods.filter((value) => ['top', 'qris', 'credit'].includes(value)) ?? []);
  const creditBalance = $derived(completeAmount(quote?.credit_balance ?? null) ? quote!.credit_balance! : null);
  const creditAvailable = $derived(creditBalance !== null && summary !== null && summary.unavailableCount === 0 && creditBalance >= summary.after);
  const paymentOptions = $derived<PaymentMethodOption[]>(methods.filter((value) => value === 'credit' || value === 'qris').map((value) => ({
    value: value as 'credit' | 'qris', title: text(value === 'credit' ? 'instantCredit' : 'instantQris'),
    balance: value === 'credit' ? creditBalance : undefined,
    disabled: value === 'credit' && !creditAvailable,
    description: value === 'credit' ? creditBalance === null ? text('instantCreditUnavailable') : !creditAvailable ? text('instantCreditInsufficient') : '' : text('instantQrisDescription'),
  })));
  const canContinue = $derived(Boolean(quote && !expired && !busy && !dispatchAttempted && matchesSelection(quote) && quote.rows.every((row) => row.eligible && completeAmount(row.before) && completeAmount(row.after)) && methods.includes(method) && (method !== 'credit' || creditAvailable)));
  const canDispatch = $derived(canContinue && (method !== 'credit' || (step === 'pin' && /^\d{6}$/.test(pin))));
  function completeAmount(value: number | null): boolean {
    return typeof value === 'number' && Number.isFinite(value) && value >= 0;
  }
  function continueSummary(): void {
    if (!canContinue) return;
    if (method === 'credit') { pin = ''; error = ''; step = 'pin'; }
    else void dispatch();
  }
  function backToSummary(): void {
    if (busy) return;
    pin = ''; error = ''; step = 'summary';
  }
  async function validatePinAndDispatch(): Promise<void> {
    if (!canDispatch || !quote || step !== 'pin') return;
    const current = generation;
    const request = new AbortController();
    controller = request; busy = true; error = '';
    let valid = false;
    try {
      const data = await call<{ valid: boolean }>('kiriof_instant_validate_credit', { token: quote.token, order_ids: JSON.stringify(ids), pin }, request.signal);
      if (current === generation && open && !request.signal.aborted) {
        if (data.valid !== true) throw new Error(text('instantPinInvalid'));
        valid = true;
      }
    } catch (cause) {
      if (current === generation && open && !request.signal.aborted) { pin = ''; error = cause instanceof Error ? cause.message : text('instantPinInvalid'); }
    } finally {
      if (current === generation) { controller = null; busy = false; }
    }
    if (valid && current === generation && open && quote) {
      if (quoteExpired(quote)) { pin = ''; step = 'summary'; void review(true); }
      else await dispatch();
    }
  }
  function matchesSelection(value: InstantQuote): boolean {
    const requested = new Set(orderIds);
    const returned = new Set(value.rows.map((row) => row.id));
    return requested.size > 0 && requested.size === orderIds.length && returned.size === value.rows.length && returned.size === requested.size && value.rows.every((row) => requested.has(row.id));
  }
  function text(key: string): string { return i18n[key] ?? ''; }
  function money(value: number | null): string { return value === null ? '—' : `Rp${new Intl.NumberFormat('id-ID').format(value)}`; }
  function cleanup(): void {
    generation += 1;
    paymentPoller.stop(); pollPhases = {};
    controller?.abort(); controller = null;
    quoteClock.stop();
    remaining = 0; pin = ''; step = 'summary'; quote = null;
    busy = false; dispatching = false;
  }
  function close(): void {
    if (dispatching) return;
    const completed = result !== null || dispatchAttempted;
    open = false;
    cleanup();
    result = null;
    if (completed) onComplete?.();
  }
  async function call<T>(action: string, values: Record<string, string>, signal: AbortSignal): Promise<T> {
    const response = await postWordPressAction<T>(action, values, { ajaxUrl, nonce, signal });
    if (!response.data) throw new Error(text('actionError'));
    return response.data;
  }
  async function review(refresh = false): Promise<void> {
    if (busy || result || !open) return;
    const previousMethod = refresh ? method : '';
    cleanup();
    const current = generation;
    const request = new AbortController();
    controller = request;
    busy = true; error = '';
    if (!refresh) { orderInformationOpen = false; displayRows = []; }
    try {
      const data = await call<InstantQuote>('kiriof_instant_quote', { order_ids: JSON.stringify(orderIds) }, request.signal);
      if (current !== generation || !open) return;
      // Reject stale/malformed responses once; never create an automatic retry loop.
      if (!data.token || data.token === previousToken || !Array.isArray(data.rows) || !Array.isArray(data.payment_methods) || typeof data.expires_at !== 'number' || !Number.isFinite(data.expires_at) || quoteExpired(data) || !matchesSelection(data)) throw new Error(text('instantQuoteRefreshFailed'));
      previousToken = data.token;
      quote = data; displayRows = data.rows;
      const usableCredit = typeof data.credit_balance === 'number' && Number.isFinite(data.credit_balance) && data.credit_balance >= 0 && data.rows.every((row) => row.eligible && completeAmount(row.after)) && data.credit_balance >= data.rows.reduce((total, row) => total + row.after!, 0);
      method = isTopAccount(data) ? 'top' : data.payment_methods.includes(previousMethod) && (previousMethod !== 'credit' || usableCredit) ? previousMethod : data.payment_methods.includes('credit') && usableCredit ? 'credit' : data.payment_methods.includes('qris') ? 'qris' : '';
      quoteClock.start(data);
    } catch (cause) {
      if (current === generation && open && !request.signal.aborted) error = cause instanceof Error ? cause.message : text('instantQuoteRefreshFailed');
    } finally { if (current === generation) { controller = null; busy = false; } }
  }
  let previousToken = '';
  function orderLabel(id: string): string {
    const row = displayRows.find((item) => item.id === id);
    return row?.wc_order_number ? `${id} (#${row.wc_order_number})` : id;
  }
  function courierLabel(courier: string): string {
    return courier === 'gosend' ? 'GoSend' : courier === 'grab_express' ? 'GrabExpress' : courier;
  }
  async function dispatch(): Promise<void> {
    if (!canDispatch || !quote || quoteExpired(quote) || !session.consume(quote.token)) return;
    const token = quote.token;
    const dispatchedIds = [...ids];
    const secret = method === 'credit' ? pin : '';
    dispatchAttempted = true;
    quoteClock.stop();
    pin = ''; quote = null; remaining = 0; busy = true; dispatching = true; error = '';
    const current = generation;
    controller = new AbortController();
    try {
      const data = await call<InstantDispatchResult>('kiriof_instant_dispatch', { token, order_ids: JSON.stringify(dispatchedIds), method, pin: secret, confirmed: 'yes' }, controller.signal);
      if (current === generation && open) {
        result = data;
        quoteClock.stop();
        paymentPoller.start(data.payments.filter((payment) => paymentIds(payment).length));
      }
    } catch (cause) {
      if (current === generation && open) {
        // The token is consumed. Surface the failure without inventing row outcomes
        // or allowing an unsafe retry after an uncertain dispatch request.
        error = cause instanceof Error ? cause.message : text('actionError');
      }
    } finally { if (current === generation) { pin = ''; busy = false; dispatching = false; } }
  }
  function paymentIds(payment: InstantPayment): string[] {
    return payment.order_ids?.filter((id) => result?.rows.some((row) => row.id === id && row.status === 'booked')) ?? [];
  }
  function refreshPayment(payment: InstantPayment): void {
    if (busy || checkingPayments || !payment.id || !paymentIds(payment).length) return;
    paymentPoller.refresh(payment.id);
  }
  function pollStopped(payment: InstantPayment): string {
    const phase = pollPhases[payment.id];
    return phase === 'timeout' ? text('instantPaymentPollTimeout') : phase === 'expired' ? text('instantPaymentPollExpired') : phase === 'error' ? text('instantPaymentPollError') : '';
  }
  $effect(() => {
    const identity = open ? orderIds.join('|') : '';
    if (identity) untrack(() => { previousToken = ''; dispatchAttempted = false; result = null; void review(); });
    else untrack(cleanup);
    return cleanup;
  });
  $effect(() => { if (expired) { pin = ''; step = 'summary'; } });
  onDestroy(() => { cleanup(); quoteClock.dispose(); });
</script>

<Dialog.Root {open} onOpenChange={(value) => { if (!value) close(); }}>
  <Dialog.Content class="kiriof-shadcn kiriof-transaction-dialog-content kiriof-instant-process-dialog sm:max-w-3xl" showCloseButton={!dispatching} escapeKeydownBehavior={dispatching ? 'ignore' : 'close'} interactOutsideBehavior={dispatching ? 'ignore' : 'close'} aria-busy={busy}>
    <Dialog.Header>
      <Dialog.Title>{text('processShipment')}</Dialog.Title>
      <Dialog.Description class="m-0">{text('instantReviewDescription')}</Dialog.Description>
    </Dialog.Header>
    <div class="grid max-h-[60vh] gap-3 overflow-y-auto">
      {#if busy && !quote && !dispatching}<ShipmentSummarySkeleton variant="instant" label={text('instantRefreshingPrices')} />{/if}
      {#if busy && (quote || dispatching)}<ShipmentOperationProgress label={dispatching ? text('processing') : step === 'pin' ? text('instantPinValidating') : text('instantRefreshingPrices')} />{/if}
      {#if error}<p role="alert" class="m-0 text-sm text-destructive">{error}</p>{/if}
      {#if quote && step === 'summary'}
        <Alert.Root class="border-warning/30 bg-warning/10 text-foreground" role="note">
          <IconAlertTriangle class="size-4 text-warning" aria-hidden="true" />
          <Alert.Title>{text('instantRatesTitle')}</Alert.Title>
          <Alert.Description class="text-xs"><p class="m-0">{text('instantRatesNotice')}</p></Alert.Description>
        </Alert.Root>
        {#each quote.rows.filter((row) => row.error) as row (row.id)}
          <p role="alert" class="m-0 text-sm text-destructive">{orderLabel(row.id)}: {row.error}</p>
        {/each}
        <Collapsible.Root bind:open={orderInformationOpen} class="grid gap-2 rounded-xl border p-3">
          <Collapsible.Trigger aria-label={text('instantOrderInformation')}>
            {#snippet child({ props })}
              <Button {...props} variant="outline" class="kiriof-instant-order-trigger group w-full min-w-0 h-auto gap-2">
                <span class="min-w-0 flex-1 text-left">{text('instantOrderInformation')}</span>
                {#if changedCount > 0}<span>{changedCount} / {quote?.rows.length ?? 0} {text('instantOrdersChanged')}</span>{/if}
                <IconChevronDown data-icon="inline-end" class="transition-transform group-data-[state=open]:rotate-180" aria-hidden="true" />
              </Button>
            {/snippet}
          </Collapsible.Trigger>
          <Collapsible.Content class="grid gap-2">
            {#if orderInformationOpen}
            {#each quote.rows as row (row.id)}
              <div class="grid gap-2 rounded-lg border p-3 text-sm" data-order-id={row.id}>
                <div class="flex items-center justify-between gap-2">
                  <span class="min-w-0 break-words font-medium">{orderLabel(row.id)}</span>
                  <span class="shrink-0 tabular-nums">{#if row.before !== row.after}{money(row.before)} → {/if}{money(row.after)}</span>
                </div>
                <dl class="m-0 grid gap-2">
                  {#if row.courier}<div class="flex flex-wrap justify-between gap-2"><dt class="text-muted-foreground">{text('instantCourierService')}</dt><dd class="m-0 font-medium">{courierLabel(row.courier)} {row.service ?? ''}</dd></div>{/if}
                  {#if row.origin_label}<div class="flex flex-wrap justify-between gap-2"><dt class="text-muted-foreground">{text('instantOrigin')}</dt><dd class="m-0 max-w-full break-words">{row.origin_label}</dd></div>{/if}
                  {#if row.destination_label}<div class="flex flex-wrap justify-between gap-2"><dt class="text-muted-foreground">{text('instantRecipient')}</dt><dd class="m-0 max-w-full break-words">{row.destination_label}</dd></div>{/if}
                </dl>
                {#if !row.eligible}<span class="text-xs text-destructive">{text('instantUnavailable')}</span>{:else if row.before !== row.after}<span class="text-xs text-warning">{text('instantChanged')}</span>{/if}
              </div>
            {/each}
            {/if}
          </Collapsible.Content>
        </Collapsible.Root>
        {#if summary}
          <section class="grid gap-2 rounded-xl border p-3" aria-label={text('instantShippingInformation')}>
            <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="m-0 text-sm font-semibold">{text('instantShippingInformation')}</h3><span class="text-xs text-muted-foreground">{quote.rows.length} {text('instantSelectedOrders')}</span></div>
            <dl class="m-0 grid gap-2 text-sm">
              {#if summary.gap !== 0}
                <div class="flex justify-between gap-2"><dt>{text('instantBefore')}</dt><dd class="m-0 tabular-nums">{money(summary.before)}</dd></div>
                <div class="flex justify-between gap-2"><dt>{text('instantAfter')}</dt><dd class="m-0 tabular-nums">{money(summary.after)}</dd></div>
                <div class="flex justify-between gap-2"><dt>{text('instantPriceGap')}</dt><dd class={`m-0 flex items-center gap-1 tabular-nums ${summary.gap > 0 ? 'text-destructive' : 'text-success'}`}>{money(Math.abs(summary.gap))}{#if summary.gap > 0}<IconArrowUp class="size-4" aria-label={text('instantPriceIncrease')} />{:else}<IconArrowDown class="size-4" aria-label={text('instantPriceDecrease')} />{/if}</dd></div>
              {/if}
              <div class="flex justify-between gap-2 font-semibold" title={text('instantTotalScope')}><dt>{text('instantTotalShipment')}</dt><dd class="m-0 tabular-nums">{summary.unavailableCount > 0 ? '—' : money(summary.after)}</dd></div>
            </dl>
          </section>
        {/if}
        {#if !topAccount}
          <PaymentMethodSelector idPrefix="instant-method" bind:value={method} options={paymentOptions} label={text('paymentMethod')} balanceLabel={text('creditDescription')} disabled={busy || expired} required onValueChange={() => { pin = ''; error = ''; }} />
        {/if}
      {/if}
      {#if quote && step === 'pin'}
        <p class="m-0" role="status">{text('instantPinStep')}</p>
        <p class="m-0">{text('instantTotalShipment')}: {money(summary?.after ?? null)}</p>
        <CreditPinInput id="instant-credit-pin" bind:value={pin} disabled={busy || expired} invalid={Boolean(error)} label={text('instantPin')} description={text('instantPinDescription')} />
      {/if}
      {#if result}
        {#each result.rows as row (row.id)}<div class="rounded-md border p-3"><strong>{orderLabel(row.id)}: {text(`instantResult_${row.status}`)}</strong><p class="m-0 text-sm">{row.awb} {row.message}</p></div>{/each}
        {#each result.payments as payment, index (index)}
          <div class="grid gap-2 rounded-md border p-3">
            <p class="m-0 text-sm">{text('paymentId')}: {payment.id} — {text('paymentStatus')}: {text(`instantPayment_${payment.status}`)}</p><strong>{money(payment.amount)}</strong>
            {#if payment.qr_content && !paymentTerminal(payment) && pollPhases[payment.id] !== 'expired'}<svg use:qr={{ data: payment.qr_content }} width="240" height="240" role="img" aria-label={text('instantQrLabel')}></svg>{/if}
            {#if pollStopped(payment)}<p role="status" class="m-0 text-sm">{pollStopped(payment)}</p>{/if}
            {#if checkingPayments}<p role="status" class="m-0 text-sm">{text('instantPaymentChecking')}</p>{/if}
            {#if !paymentTerminal(payment)}<Button variant="outline" disabled={busy || checkingPayments || !paymentIds(payment).length} onclick={() => void refreshPayment(payment)}>{text('instantRefreshPayment')}</Button>{/if}
          </div>
        {/each}
      {/if}
    </div>
    <Dialog.Footer>
      {#if !result && step === 'pin' && !dispatchAttempted}
        <Button variant="outline" disabled={busy} onclick={backToSummary}>{text('instantBackSummary')}</Button>
      {:else}
        <Button variant="ghost" disabled={dispatching} onclick={close}>{text('instantClose')}</Button>
      {/if}
      {#if !result}
        {#if step === 'pin' && !dispatchAttempted}
          <Button disabled={!canDispatch} onclick={() => void validatePinAndDispatch()}>{#if busy}<Spinner data-icon="inline-start" aria-hidden="true" role="presentation" />{/if}{busy ? text('instantPinValidating') : text('instantValidatePin')}{quote ? ` (${remaining}s)` : ''}</Button>
        {:else}
          {#if error && !quote && !dispatchAttempted}<Button variant="outline" disabled={busy} onclick={() => void review()}>{text('instantReview')}</Button>{/if}
          <Button disabled={!canContinue} onclick={continueSummary}>{#if busy}<Spinner data-icon="inline-start" aria-hidden="true" role="presentation" />{/if}{dispatching ? text('processing') : busy ? text('instantRefreshingPrices') : topAccount ? text('confirmProcess') : text('instantContinuePayment')}{quote ? ` (${remaining}s)` : ''}</Button>
        {/if}
      {/if}
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
