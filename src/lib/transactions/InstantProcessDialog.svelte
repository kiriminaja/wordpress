<script lang="ts">
  import { onDestroy, untrack } from 'svelte';
  import { IconAlertTriangle, IconChevronDown, IconArrowUp, IconArrowDown } from '@tabler/icons-svelte';
  import { qr } from '@svelte-put/qr/svg';
  import * as Dialog from '$lib/components/ui/dialog';
  import { Checkbox } from '$lib/components/ui/checkbox';
  import * as Field from '$lib/components/ui/field';
  import { Button } from '$lib/components/ui/button';
  import { postWordPressAction } from '$lib/wordpress/ajax';
  import { InstantProcessSession, quoteExpired, reviewedIds, createInstantQuoteClock, isTopAccount, quoteSummary, reviewSelection } from './instant-process-session';
  import { createInstantPaymentPoller, paymentTerminal, type PaymentPollPhase, type PollPayment } from './instant-payment-poller';
  import type { InstantQuote, InstantDispatchResult, InstantPayment } from './types';

  let { open = $bindable(false), orderIds, ajaxUrl, nonce, i18n, onComplete }: {
    open?: boolean; orderIds: string[]; ajaxUrl: string; nonce: string; i18n: Record<string, string>; onComplete?: () => void;
  } = $props();
  let quote = $state<InstantQuote | null>(null);
  let result = $state<InstantDispatchResult | null>(null);
  let checked = $state<Record<string, boolean>>({});
  let acknowledged = $state<Record<string, boolean>>({});
  let confirmed = $state(false);
  let allowSkip = $state(false);
  let method = $state('');
  let pin = $state('');
  let busy = $state(false);
  let dispatching = $state(false);
  let error = $state('');
  let checkingPayments = $state(false);
  let pollPhases = $state<Record<string, PaymentPollPhase>>({});
  let remaining = $state(0);
  let showAll = $state(false);
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
  const summary = $derived(quote ? quoteSummary(quote, checked) : null);
  const visibleRows = $derived(quote ? (showAll ? quote.rows : quote.rows.slice(0, 5)) : []);
  const changedCount = $derived(quote?.rows.filter((row) => row.eligible && row.changed).length ?? 0);
  const selectionIncomplete = $derived(Boolean(quote?.rows.some((row) => !row.eligible || !checked[row.id])));
  const ids = $derived(quote ? reviewedIds(quote, checked, acknowledged) : []);
  const chosenCount = $derived(quote?.rows.filter((row) => checked[row.id] && row.eligible).length ?? 0);
  const methods = $derived(quote?.payment_methods.filter((value) => ['top', 'qris', 'credit'].includes(value)) ?? []);
  const canDispatch = $derived(Boolean(quote && !expired && !busy && confirmed && ids.length && ids.length === chosenCount && methods.includes(method) && (allowSkip || quote.rows.every((row) => row.eligible && checked[row.id])) && (method !== 'credit' || /^\d{6}$/.test(pin))));
  function text(key: string): string { return i18n[key] ?? ''; }
  function money(value: number | null): string { return value === null ? '—' : `Rp${new Intl.NumberFormat('id-ID').format(value)}`; }
  function cleanup(): void {
    generation += 1;
    paymentPoller.stop(); pollPhases = {};
    controller?.abort(); controller = null;
    quoteClock.stop();
    remaining = 0; pin = ''; quote = null;
    busy = false; dispatching = false;
  }
  function close(): void {
    if (dispatching) return;
    const completed = result !== null;
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
    const previousChecked = refresh ? { ...checked } : undefined;
    const previousSkip = refresh && allowSkip;
    const previousMethod = refresh ? method : '';
    cleanup();
    const current = generation;
    const request = new AbortController();
    controller = request;
    busy = true; error = ''; acknowledged = {}; confirmed = false;
    if (!refresh) { checked = {}; allowSkip = false; showAll = false; displayRows = []; }
    try {
      const data = await call<InstantQuote>('kiriof_instant_quote', { order_ids: JSON.stringify(orderIds) }, request.signal);
      if (current !== generation || !open) return;
      // Reject stale/malformed responses once; never create an automatic retry loop.
      if (!data.token || data.token === previousToken || !Array.isArray(data.rows) || !Array.isArray(data.payment_methods) || typeof data.expires_at !== 'number' || !Number.isFinite(data.expires_at) || quoteExpired(data)) throw new Error(text('instantQuoteRefreshFailed'));
      previousToken = data.token;
      quote = data; displayRows = data.rows;
      checked = reviewSelection(data, previousChecked);
      allowSkip = previousSkip;
      method = isTopAccount(data) ? 'top' : data.payment_methods.includes(previousMethod) ? previousMethod : data.payment_methods.includes('qris') ? 'qris' : data.payment_methods.includes('credit') ? 'credit' : '';
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
    const allIds = quote.rows.map((row) => row.id);
    const token = quote.token;
    const dispatchedIds = [...ids];
    const secret = method === 'credit' ? pin : '';
    quoteClock.stop();
    pin = ''; quote = null; remaining = 0; busy = true; dispatching = true; error = '';
    const current = generation;
    controller = new AbortController();
    try {
      const data = await call<InstantDispatchResult>('kiriof_instant_dispatch', { token, order_ids: JSON.stringify(dispatchedIds), method, pin: secret, confirmed: 'yes' }, controller.signal);
      if (current === generation && open) {
        result = { ...data, rows: allIds.map((id) => data.rows.find((row) => row.id === id) ?? { id, status: dispatchedIds.includes(id) ? 'unknown' : 'skipped', awb: '', message: dispatchedIds.includes(id) ? text('instantUnknown') : '' }) };
        quoteClock.stop();
        paymentPoller.start(data.payments.filter((payment) => paymentIds(payment).length));
      }
    } catch {
      if (current === generation && open) {
        result = { rows: allIds.map((id) => ({ id, status: dispatchedIds.includes(id) ? 'unknown' : 'skipped', awb: '', message: dispatchedIds.includes(id) ? text('instantUnknown') : '' })), payments: [] };
        error = text('instantUnknown');
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
    if (identity) untrack(() => { previousToken = ''; void review(); });
    else untrack(cleanup);
    return cleanup;
  });
  $effect(() => { if (expired) { pin = ''; confirmed = false; } });
  onDestroy(() => { cleanup(); quoteClock.dispose(); });
</script>

<Dialog.Root {open} onOpenChange={(value) => { if (!value) close(); }}>
  <Dialog.Content class="kiriof-shadcn kiriof-transaction-dialog-content kiriof-instant-process-dialog sm:max-w-3xl" showCloseButton={!dispatching} escapeKeydownBehavior={dispatching ? 'ignore' : 'close'} interactOutsideBehavior={dispatching ? 'ignore' : 'close'} aria-busy={busy}>
    <Dialog.Header>
      <Dialog.Title>{text('processShipment')}</Dialog.Title>
      <Dialog.Description>{text('instantReviewDescription')}</Dialog.Description>
    </Dialog.Header>
    <div class="grid max-h-[60vh] gap-3 overflow-y-auto">
      {#if busy}<p role="status">{dispatching ? text('processing') : text('instantRefreshingPrices')}</p>{/if}
      {#if error}<p role="alert" class="text-sm text-destructive">{error}</p>{/if}
      {#if quote}
        <div class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-foreground" role="note">
          <IconAlertTriangle class="mt-0.5 size-5 shrink-0 text-warning" aria-hidden="true" />
          <div class="grid gap-1"><strong>{text('instantRatesTitle')}</strong><p>{text('instantRatesNotice')}</p></div>
        </div>
        <section class="grid gap-3 rounded-xl border p-4" aria-label={text('instantOrderInformation')}>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-base font-semibold">{text('instantOrderInformation')}</h3>
            <span class="text-sm text-muted-foreground">{changedCount} / {quote.rows.length} {text('instantOrdersChanged')}</span>
          </div>
          {#each visibleRows as row (row.id)}
            <div class="overflow-hidden rounded-lg border">
              <div class="flex items-center gap-3 px-3 py-3">
                <Checkbox id={`instant-row-${row.id}`} disabled={!row.eligible || busy || expired} checked={!!checked[row.id]} aria-label={`${text('instantSelectOrder')} ${orderLabel(row.id)}`} onCheckedChange={(value) => { checked = { ...checked, [row.id]: value }; confirmed = false; }} />
                <details class="group min-w-0 flex-1" open={quote.rows.length === 1 || !row.eligible}>
                  <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm [&::-webkit-details-marker]:hidden">
                    <span class="min-w-0 break-words font-medium">{orderLabel(row.id)}<span class="mt-1 block text-xs text-muted-foreground">{money(row.before)} → {money(row.after)}</span></span>
                    <IconChevronDown class="size-4 shrink-0 transition-transform group-open:rotate-180" aria-hidden="true" />
                  </summary>
                  <div class="mt-3 grid gap-3 border-t pt-3 text-sm">
                    <dl class="grid gap-2">
                      {#if row.courier}<div class="flex flex-wrap justify-between gap-2"><dt class="text-muted-foreground">{text('instantCourierService')}</dt><dd class="font-medium">{courierLabel(row.courier)} {row.service ?? ''}</dd></div>{/if}
                      {#if row.origin_label}<div class="flex flex-wrap justify-between gap-2"><dt class="text-muted-foreground">{text('instantOrigin')}</dt><dd class="max-w-full break-words">{row.origin_label}</dd></div>{/if}
                      {#if row.destination_label}<div class="flex flex-wrap justify-between gap-2"><dt class="text-muted-foreground">{text('instantRecipient')}</dt><dd class="max-w-full break-words">{row.destination_label}</dd></div>{/if}
                      <div class="flex justify-between gap-2"><dt class="text-muted-foreground">{text('instantBefore')}</dt><dd>{money(row.before)}</dd></div>
                      <div class="flex justify-between gap-2"><dt class="text-muted-foreground">{text('instantAfter')}</dt><dd>{money(row.after)}</dd></div>
                    </dl>
                    {#if row.error}<p role="alert" class="text-destructive">{row.error}</p>{/if}
                    {#if row.changed && row.eligible}<Field.Field class="items-start" orientation="horizontal"><Checkbox id={`instant-change-${row.id}`} disabled={busy || expired || !checked[row.id]} checked={!!acknowledged[row.id]} onCheckedChange={(value) => { acknowledged = { ...acknowledged, [row.id]: value }; confirmed = false; }} /><Field.Label class="text-sm font-normal" for={`instant-change-${row.id}`}>{text('instantAcceptChange')}</Field.Label></Field.Field>{/if}
                  </div>
                </details>
                {#if !row.eligible}<span class="shrink-0 text-xs text-destructive">{text('instantUnavailable')}</span>{:else if row.changed}<span class="shrink-0 text-xs text-warning">{text('instantChanged')}</span>{/if}
              </div>
            </div>
          {/each}
          {#if quote.rows.length > 5}<Button class="justify-self-center" variant="secondary" disabled={busy} onclick={() => { showAll = !showAll; }}>{showAll ? text('instantShowLess') : `${text('instantShowMore')} (${quote.rows.length - 5})`}</Button>{/if}
        </section>
        {#if summary}
          <section class="grid gap-3 rounded-xl border p-4" aria-label={text('instantShippingInformation')}>
            <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="text-base font-semibold">{text('instantShippingInformation')}</h3><span class="text-sm text-muted-foreground">{summary.selectedCount} {text('instantSelectedOrders')}</span></div>
            <dl class="grid gap-2 text-sm">
              <div class="flex justify-between gap-3"><dt>{text('instantBefore')}</dt><dd class="tabular-nums">{money(summary.before)}</dd></div>
              <div class="flex justify-between gap-3"><dt>{text('instantAfter')}</dt><dd class="tabular-nums">{money(summary.after)}</dd></div>
              <div class="flex justify-between gap-3"><dt>{text('instantPriceGap')}</dt><dd class={`flex items-center gap-1 tabular-nums ${summary.gap > 0 ? 'text-destructive' : summary.gap < 0 ? 'text-success' : ''}`}>{money(Math.abs(summary.gap))}{#if summary.gap > 0}<IconArrowUp class="size-4" aria-label={text('instantPriceIncrease')} />{:else if summary.gap < 0}<IconArrowDown class="size-4" aria-label={text('instantPriceDecrease')} />{/if}</dd></div>
              <div class="flex justify-between gap-3 border-t pt-3 text-base font-semibold"><dt>{text('instantTotalShipment')}</dt><dd class="tabular-nums">{money(summary.after)}</dd></div>
            </dl>
            <p class="text-xs text-muted-foreground">{text('instantTotalScope')}</p>
          </section>
        {/if}
        {#if selectionIncomplete}<Field.Field class="items-start" orientation="horizontal"><Checkbox id="instant-skip" bind:checked={allowSkip} disabled={busy || expired} onCheckedChange={() => { confirmed = false; }} /><Field.Label class="text-sm font-normal" for="instant-skip">{text('instantAllowSkip')}</Field.Label></Field.Field>{/if}
        {#if !topAccount}
          <Field.Field><Field.Label for="instant-payment-method">{text('paymentMethod')}</Field.Label><select id="instant-payment-method" class="w-full rounded-md border bg-background p-2 text-sm" bind:value={method} disabled={busy || expired} onchange={() => { pin = ''; confirmed = false; }}>{#each methods as value}<option {value}>{text(value === 'credit' ? 'instantCredit' : value === 'top' ? 'instantTop' : 'instantQris')}</option>{/each}</select></Field.Field>
          {#if method === 'credit'}<Field.Field><Field.Label for="instant-pin">{text('instantPin')}</Field.Label><input id="instant-pin" class="w-full rounded-md border bg-background p-2 text-sm" type="password" inputmode="numeric" maxlength={6} autocomplete="off" bind:value={pin} disabled={busy || expired} /></Field.Field>{/if}
        {/if}
        <Field.Field class="items-start" orientation="horizontal"><Checkbox id="instant-confirm" bind:checked={confirmed} disabled={busy || expired} /><Field.Label class="text-sm font-normal" for="instant-confirm">{topAccount ? text('instantConfirmTop') : text('instantConfirm')}</Field.Label></Field.Field>
        <p class="text-xs text-muted-foreground">{text('instantQuoteValidity')}</p>
      {/if}
      {#if result}
        {#each result.rows as row (row.id)}<div class="rounded-md border p-3"><strong>{orderLabel(row.id)}: {text(`instantResult_${row.status}`)}</strong><p>{row.awb} {row.message}</p></div>{/each}
        {#each result.payments as payment, index (index)}
          <div class="grid gap-2 rounded-md border p-3">
            <p>{text('paymentId')}: {payment.id} — {text('paymentStatus')}: {text(`instantPayment_${payment.status}`)}</p><strong>{money(payment.amount)}</strong>
            {#if payment.qr_content && !paymentTerminal(payment) && pollPhases[payment.id] !== 'expired'}<svg use:qr={{ data: payment.qr_content }} width="240" height="240" role="img" aria-label={text('instantQrLabel')}></svg>{/if}
            {#if pollStopped(payment)}<p role="status" class="text-sm">{pollStopped(payment)}</p>{/if}
            {#if checkingPayments}<p role="status" class="text-sm">{text('instantPaymentChecking')}</p>{/if}
            {#if !paymentTerminal(payment)}<Button variant="outline" disabled={busy || checkingPayments || !paymentIds(payment).length} onclick={() => void refreshPayment(payment)}>{text('instantRefreshPayment')}</Button>{/if}
          </div>
        {/each}
      {/if}
    </div>
    <Dialog.Footer>
      <Button variant="ghost" disabled={dispatching} onclick={close}>{text('instantClose')}</Button>
      {#if !result}
        {#if error && !quote}<Button variant="outline" disabled={busy} onclick={() => void review()}>{text('instantReview')}</Button>{/if}
        <Button disabled={!canDispatch} loading={busy} onclick={() => void dispatch()}>{dispatching ? text('processing') : busy ? text('instantRefreshingPrices') : topAccount ? text('confirmProcess') : text('instantContinuePayment')}{quote ? ` (${remaining}s)` : ''}</Button>
      {/if}
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
