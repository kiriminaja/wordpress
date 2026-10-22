<script lang="ts">
  import { untrack } from 'svelte';
  import { qr } from '@svelte-put/qr/svg';
  import * as Alert from '$lib/components/ui/alert';
  import { Spinner } from '$lib/components/ui/spinner';
  import KiriofDialog from '$lib/ui/KiriofDialog.svelte';
  import { postWordPressAction } from '$lib/wordpress/ajax';
  import { createInstantPaymentPoller, paymentTerminal, type PollPayment, type PaymentPollPhase } from '$lib/transactions/instant-payment-poller';

  let { open = $bindable(false), paymentId, orderIds, ajaxUrl, nonce, i18n, onComplete }: {
    open?: boolean; paymentId: string; orderIds: string[]; ajaxUrl: string; nonce: string;
    i18n: Record<string, string>; onComplete?: () => void;
  } = $props();
  let payment = $state<PollPayment | null>(null);
  let checking = $state(false);
  let phase = $state<PaymentPollPhase>('idle');
  let poller: ReturnType<typeof createInstantPaymentPoller> | undefined;
  const qrContent = $derived(payment && !paymentTerminal(payment) && phase !== 'expired' ? payment.qr_content : '');
  function money(value: number | null): string { return value === null ? '—' : `Rp${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value)}`; }

  $effect(() => {
    const identity = open ? paymentId : '';
    const ids = [...orderIds];
    const config = { ajaxUrl, nonce };
    if (!identity || !ids.length) return;
    const active = untrack(() => {
      payment = null; phase = 'idle'; checking = false;
      const session = createInstantPaymentPoller({
        request: async (_payment, signal) => {
          const result = await postWordPressAction<PollPayment>('kiriof_instant_payment', { payment_id: identity, order_ids: JSON.stringify(ids) }, { ...config, signal });
          if (!result.data || result.data.id !== identity) throw new Error('Invalid payment identity');
          return { ...result.data, order_ids: ids };
        },
        onUpdate: (next) => {
          payment = next;
          if (next.status === 'paid') { open = false; onComplete?.(); }
        },
        onPhase: (_id, next) => { phase = next; },
        onChecking: (next) => { checking = next; },
        isVisible: () => document.visibilityState !== 'hidden',
      });
      session.start([{ id: identity, status: 'pending', amount: null, qr_content: '', order_ids: ids }]);
      session.refresh(identity);
      return session;
    });
    poller = active;
    return () => { active.stop(); if (poller === active) poller = undefined; };
  });
</script>

<KiriofDialog bind:open class="sm:max-w-md" title={i18n.scanToPay ?? 'Scan to Pay'} description={i18n.scanDescription ?? 'Scan this QR with your banking or e-wallet app. Payment status is checked automatically.'} primaryLabel={checking ? (i18n.checkingPayment ?? 'Checking payment…') : (i18n.refresh ?? 'Refresh')} primaryDisabled={checking || Boolean(payment && paymentTerminal(payment))} secondaryLabel={i18n.cancel ?? 'Cancel'} onPrimary={() => { poller?.refresh(paymentId); }} onOpenChange={(next) => { if (!next) poller?.stop(); }}>
  {#if !payment && checking}
    <div class="grid min-h-40 place-items-center gap-3 text-sm text-muted-foreground" role="status"><Spinner aria-hidden="true" role="presentation" /><span>{i18n.processing ?? 'Processing…'}</span></div>
  {/if}
  {#if payment}
    <div class="grid justify-items-center gap-3 text-center">
      {#if qrContent}<svg use:qr={{ data: qrContent, margin: 4, moduleFill: 'black', anchorOuterFill: 'black', anchorInnerFill: 'black' }} class="size-64 max-w-full bg-white" role="img" aria-label={i18n.scanToPay ?? 'Payment QR code'}></svg>{/if}
      <strong>{i18n.code ?? 'Code'}: {payment.id}</strong>
      <div class="text-lg font-bold text-info">{money(payment.amount)}</div>
      {#if qrContent}<p class="m-0 text-sm text-muted-foreground" role="status">{i18n.waitingForPayment ?? 'Waiting for payment…'}</p>{/if}
    </div>
  {/if}
  {#if phase === 'error' || phase === 'timeout' || phase === 'expired' || (payment && !qrContent && payment.status !== 'paid')}
    <Alert.Root role="status"><Alert.Description>{i18n.paymentExpired ?? 'This payment QR is no longer available. Refresh to check for a new QR.'}</Alert.Description></Alert.Root>
  {/if}
</KiriofDialog>
