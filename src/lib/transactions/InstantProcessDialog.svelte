<script lang="ts">
  import { onDestroy, untrack } from 'svelte';
  import { qr } from '@svelte-put/qr/svg';
  import * as Dialog from '$lib/components/ui/dialog';
  import { Checkbox } from '$lib/components/ui/checkbox';
  import * as Field from '$lib/components/ui/field';
  import { Button } from '$lib/components/ui/button';
  import { postWordPressAction } from '$lib/wordpress/ajax';
  import { InstantProcessSession, quoteExpired, reviewedIds } from './instant-process-session';
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
  let error = $state('');
  let now = $state(Date.now());
  let generation = 0;
  let controller: AbortController | null = null;
  let clock: ReturnType<typeof setInterval> | null = null;
  const session = new InstantProcessSession();
  const expired = $derived(quote ? quoteExpired(quote, now) : false);
  const ids = $derived(quote ? reviewedIds(quote, checked, acknowledged) : []);
  const chosenCount = $derived(quote?.rows.filter((row) => checked[row.id] && row.eligible).length ?? 0);
  const methods = $derived(quote?.payment_methods.filter((value) => ['top', 'qris', 'credit'].includes(value)) ?? []);
  const canDispatch = $derived(Boolean(quote && !expired && !busy && confirmed && ids.length && ids.length === chosenCount && methods.includes(method) && (allowSkip || quote.rows.every((row) => row.eligible && checked[row.id])) && (method !== 'credit' || /^\d{6}$/.test(pin))));
  function text(key: string): string { return i18n[key] ?? ''; }
  function money(value: number | null): string { return value === null ? '—' : `Rp${new Intl.NumberFormat('id-ID').format(value)}`; }
  function cleanup(): void {
    generation += 1;
    controller?.abort(); controller = null;
    if (clock) clearInterval(clock);
    clock = null; pin = ''; quote = null;
  }
  function close(): void {
    if (busy) return;
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
  async function review(): Promise<void> {
    cleanup();
    const current = generation;
    controller = new AbortController();
    busy = true; result = null; error = ''; checked = {}; acknowledged = {}; confirmed = false; allowSkip = false;
    now = Date.now();
    clock = setInterval(() => { now = Date.now(); }, 1000);
    try {
      const data = await call<InstantQuote>('kiriof_instant_quote', { order_ids: JSON.stringify(orderIds) }, controller.signal);
      if (current !== generation || !open) return;
      quote = data;
      checked = Object.fromEntries(data.rows.map((row) => [row.id, row.eligible]));
      method = data.payment_methods.includes('top') ? 'top' : data.payment_methods.includes('qris') ? 'qris' : data.payment_methods.includes('credit') ? 'credit' : '';
    } catch (cause) {
      if (current === generation && open) error = cause instanceof Error ? cause.message : text('actionError');
    } finally { if (current === generation) busy = false; }
  }
  async function dispatch(): Promise<void> {
    if (!canDispatch || !quote || quoteExpired(quote) || !session.consume(quote.token)) return;
    const allIds = quote.rows.map((row) => row.id);
    const token = quote.token;
    const dispatchedIds = [...ids];
    const secret = method === 'credit' ? pin : '';
    pin = ''; quote = null; busy = true; error = '';
    const current = generation;
    controller = new AbortController();
    try {
      const data = await call<InstantDispatchResult>('kiriof_instant_dispatch', { token, order_ids: JSON.stringify(dispatchedIds), method, pin: secret, confirmed: 'yes' }, controller.signal);
      if (current === generation && open) result = { ...data, rows: allIds.map((id) => data.rows.find((row) => row.id === id) ?? { id, status: dispatchedIds.includes(id) ? 'unknown' : 'skipped', awb: '', message: dispatchedIds.includes(id) ? text('instantUnknown') : '' }) };
    } catch {
      if (current === generation && open) {
        result = { rows: allIds.map((id) => ({ id, status: dispatchedIds.includes(id) ? 'unknown' : 'skipped', awb: '', message: dispatchedIds.includes(id) ? text('instantUnknown') : '' })), payments: [] };
        error = text('instantUnknown');
      }
    } finally { if (current === generation) { pin = ''; busy = false; } }
  }
  function paymentIds(payment: InstantPayment): string[] {
    return payment.order_ids?.filter((id) => result?.rows.some((row) => row.id === id && row.status === 'booked')) ?? [];
  }
  async function refreshPayment(payment: InstantPayment): Promise<void> {
    const validatedIds = paymentIds(payment);
    if (busy || !payment.id || !validatedIds.length) return;
    busy = true; error = ''; const current = generation;
    controller = new AbortController();
    try {
      const data = await call<InstantPayment>('kiriof_instant_payment', { payment_id: payment.id, order_ids: JSON.stringify(validatedIds) }, controller.signal);
      if (current === generation && open && result && data.id === payment.id) result = { ...result, payments: result.payments.map((item) => item.id === payment.id ? { ...data, order_ids: validatedIds } : item) };
    } catch (cause) { if (current === generation && open) error = cause instanceof Error ? cause.message : text('actionError'); }
    finally { if (current === generation) busy = false; }
  }
  $effect(() => {
    const identity = open ? orderIds.join('|') : '';
    if (identity) untrack(() => void review());
    else untrack(cleanup);
    return cleanup;
  });
  $effect(() => { if (expired) { pin = ''; confirmed = false; } });
  onDestroy(cleanup);
</script>

<Dialog.Root {open} onOpenChange={(value) => { if (!value) close(); }}>
  <Dialog.Content class="kiriof-shadcn kiriof-transaction-dialog-content max-w-2xl" showCloseButton={!busy} escapeKeydownBehavior={busy ? 'ignore' : 'close'} interactOutsideBehavior={busy ? 'ignore' : 'close'} aria-busy={busy}>
    <Dialog.Header>
      <Dialog.Title>{text('processShipment')}</Dialog.Title>
      <Dialog.Description>{text('instantReviewDescription')}</Dialog.Description>
    </Dialog.Header>
    <div class="grid max-h-[60vh] gap-3 overflow-y-auto">
      {#if busy}<p role="status">{text('processing')}</p>{/if}
      {#if error}<p role="alert" class="text-sm text-destructive">{error}</p>{/if}
      {#if quote}
        <p>{text('instantBatchCount')}: {quote.batch_count}</p>
        {#if expired}<p role="alert" class="text-destructive">{text('instantQuoteExpired')}</p>{/if}
        {#each quote.rows as row (row.id)}
          <div class="grid gap-2 rounded-md border p-3 text-sm">
            <Field.Field orientation="horizontal"><Checkbox id={`instant-row-${row.id}`} disabled={!row.eligible || busy} checked={!!checked[row.id]} onCheckedChange={(value) => { checked = { ...checked, [row.id]: value }; confirmed = false; }} /><Field.Label for={`instant-row-${row.id}`}>{row.id}</Field.Label></Field.Field>
            <p>{text('instantBefore')}: {money(row.before)} → {text('instantAfter')}: {money(row.after)}</p>
            {#if row.error}<p class="text-destructive">{row.error}</p>{/if}
            {#if row.changed && row.eligible}<Field.Field orientation="horizontal"><Checkbox id={`instant-change-${row.id}`} disabled={busy} checked={!!acknowledged[row.id]} onCheckedChange={(value) => { acknowledged = { ...acknowledged, [row.id]: value }; confirmed = false; }} /><Field.Label for={`instant-change-${row.id}`}>{text('instantAcceptChange')}</Field.Label></Field.Field>{/if}
          </div>
        {/each}
        <Field.Field orientation="horizontal"><Checkbox id="instant-skip" bind:checked={allowSkip} disabled={busy} onCheckedChange={() => { confirmed = false; }} /><Field.Label for="instant-skip">{text('instantAllowSkip')}</Field.Label></Field.Field>
        <Field.Field><Field.Label for="instant-payment-method">{text('paymentMethod')}</Field.Label><select id="instant-payment-method" class="rounded-md border bg-background p-2" bind:value={method} disabled={busy} onchange={() => { pin = ''; confirmed = false; }}>{#each methods as value}<option {value}>{text(value === 'credit' ? 'instantCredit' : value === 'top' ? 'instantTop' : 'instantQris')}</option>{/each}</select></Field.Field>
        {#if method === 'credit'}<Field.Field><Field.Label for="instant-pin">{text('instantPin')}</Field.Label><input id="instant-pin" class="rounded-md border bg-background p-2" type="password" inputmode="numeric" maxlength={6} autocomplete="off" bind:value={pin} disabled={busy} /></Field.Field>{/if}
        <Field.Field orientation="horizontal"><Checkbox id="instant-confirm" bind:checked={confirmed} disabled={busy || expired} /><Field.Label for="instant-confirm">{text('instantConfirm')}</Field.Label></Field.Field>
      {/if}
      {#if result}
        {#each result.rows as row (row.id)}<div class="rounded-md border p-3"><strong>{row.id}: {text(`instantResult_${row.status}`)}</strong><p>{row.awb} {row.message}</p></div>{/each}
        {#each result.payments as payment, index (index)}
          <div class="grid gap-2 rounded-md border p-3">
            <p>{text('paymentId')}: {payment.id} — {text('paymentStatus')}: {text(`instantPayment_${payment.status}`)}</p><strong>{money(payment.amount)}</strong>
            {#if payment.qr_content && payment.status !== 'paid'}<svg use:qr={{ data: payment.qr_content }} width="240" height="240" role="img" aria-label={text('instantQrLabel')}></svg>{/if}
            {#if payment.status !== 'paid'}<Button variant="outline" disabled={busy || !paymentIds(payment).length} onclick={() => void refreshPayment(payment)}>{text('instantRefreshPayment')}</Button>{/if}
          </div>
        {/each}
      {/if}
    </div>
    <Dialog.Footer>
      <Button variant="ghost" disabled={busy} onclick={close}>{text('instantClose')}</Button>
      {#if !result}<Button variant="outline" disabled={busy} onclick={() => void review()}>{text('instantReview')}</Button><Button disabled={!canDispatch} loading={busy} onclick={() => void dispatch()}>{text('confirmProcess')}</Button>{/if}
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
