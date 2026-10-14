<script lang="ts">
  import { untrack } from 'svelte';
  import { qr } from '@svelte-put/qr/svg';
  import { IconAlertCircle, IconLoader2 } from '@tabler/icons-svelte';
  import * as Alert from '$lib/components/ui/alert';
  import KiriofDialog from '$lib/ui/KiriofDialog.svelte';
  import { postWordPressAction } from '$lib/wordpress/ajax';
  import { createPaymentSession, type PaymentData, type PaymentState } from './scan-to-pay';

  let { open = $bindable(false), pickupNumber, ajaxUrl, nonce, i18n, onComplete }: {
    open?: boolean;
    pickupNumber: string;
    ajaxUrl: string;
    nonce: string;
    i18n: Record<string, string>;
    onComplete?: () => void;
  } = $props();

  let state = $state<PaymentState>({ phase: 'loading', payment: null, error: '', checking: false });
  let session: ReturnType<typeof createPaymentSession> | undefined;
  const qrContent = $derived(state.payment?.payment_data?.qr_content ?? '');
  const codCharges = $derived(Number(state.payment?.sum_fee_cod ?? 0));
  const nonCodCharges = $derived(Number(state.payment?.sum_fee_non_cod ?? 0));

  function money(value: number): string {
    return `Rp${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value)}`;
  }

  $effect(() => {
    const activePickup = open ? pickupNumber : '';
    const config = { ajaxUrl, nonce };
    if (!activePickup) return;
    const activeSession = untrack(() => {
      state = { phase: 'loading', payment: null, error: '', checking: false };
      return createPaymentSession({
        request: async (signal) => {
          const result = await postWordPressAction<PaymentData>('kiriof_get_payment_form', { payment_id: activePickup }, { ...config, signal });
          return result.data ?? {};
        },
        onUpdate: (next) => { state = next; },
        onPaid: () => { open = false; onComplete?.(); },
        errorMessage: i18n.error ?? 'Unable to load payment QR.',
        expiredMessage: i18n.paymentExpired ?? 'This payment QR is no longer available. Refresh to check for a new QR.',
      });
    });
    session = activeSession;
    void activeSession.start();
    return () => {
      activeSession.stop();
      if (session === activeSession) session = undefined;
    };
  });
</script>

<KiriofDialog
  bind:open
  class="sm:max-w-md"
  title={i18n.scanToPay ?? 'Scan to Pay'}
  description={i18n.scanDescription ?? 'Scan this QR with your banking or e-wallet app. Payment status is checked automatically.'}
  primaryLabel={state.checking ? (i18n.checkingPayment ?? 'Checking payment…') : (state.phase === 'error' ? (i18n.retry ?? 'Retry') : (i18n.refresh ?? 'Refresh'))}
  primaryDisabled={state.checking}
  secondaryLabel={i18n.cancel ?? 'Cancel'}
  onPrimary={() => session?.refresh()}
  onOpenChange={(nextOpen) => { if (!nextOpen) session?.stop(); }}
>
  {#if state.phase === 'loading'}
    <div class="!grid min-h-40 place-items-center gap-3 text-sm text-muted-foreground" role="status">
      <IconLoader2 class="size-6 animate-spin" aria-hidden="true" />
      <span>{i18n.processing ?? 'Processing…'}</span>
    </div>
  {:else if state.phase === 'ready' && state.payment}
    <div class="!grid justify-items-center gap-3">
      <svg use:qr={{ data: qrContent, margin: 4, moduleFill: 'black', anchorOuterFill: 'black', anchorInnerFill: 'black' }} class="size-64 max-w-full bg-white" role="img" aria-label={i18n.scanToPay ?? 'Payment QR code'}></svg>
      <div class="text-center">
        <strong>{i18n.code ?? 'Code'}: {state.payment.payment_data?.payment_id || pickupNumber}</strong>
        <div class="text-lg font-bold text-info">{money(nonCodCharges)}</div>
      </div>
    </div>
    <dl class="m-0 !grid gap-2 rounded-lg border border-border bg-background p-3 text-sm">
      <div class="!flex !justify-between gap-4"><dt>{i18n.codCharges ?? 'COD Package Charges'}</dt><dd class="m-0 font-semibold">{money(codCharges)}</dd></div>
      <div class="!flex !justify-between gap-4"><dt>{i18n.nonCodCharges ?? 'Non-COD Package Charges'}</dt><dd class="m-0 font-semibold">{money(nonCodCharges)}</dd></div>
      <div class="!flex !justify-between gap-4 border-t border-border pt-2"><dt>{i18n.totalCharges ?? 'Total Charges'}</dt><dd class="m-0 font-semibold">{money(codCharges + nonCodCharges)}</dd></div>
    </dl>
    {#if state.payment.expired_at}
      <div class="text-center text-sm text-muted-foreground">{i18n.expiresAt ?? 'QR will expire at'}: <strong>{state.payment.expired_at}</strong></div>
    {/if}
    <p class="m-0 text-center text-sm text-muted-foreground" role="status">{i18n.waitingForPayment ?? 'Waiting for payment…'}</p>
  {/if}
  {#if state.error}
    <Alert.Root variant="destructive">
      <IconAlertCircle aria-hidden="true" />
      <Alert.Description>{state.error}</Alert.Description>
    </Alert.Root>
  {/if}
</KiriofDialog>
