<script lang="ts">
  import { onDestroy } from 'svelte';
  import { qr } from '@svelte-put/qr/svg';
  import KiriofDialog from '$lib/ui/KiriofDialog.svelte';
  import { postWordPressAction } from '$lib/wordpress/ajax';

  type PaymentData = {
    payment_data?: { payment_id?: string; payment_status?: string; status?: string; status_code?: string | number; paid_at?: string; pay_time?: string; qr_content?: string };
    payment_in_wc_data?: { method?: string; status?: string };
    expired_at?: string;
    sum_fee_non_cod?: number;
  };

  let { open = $bindable(false), pickupNumber, i18n }: {
    open?: boolean;
    pickupNumber: string;
    i18n: Record<string, string>;
  } = $props();

  let phase = $state<'loading' | 'ready' | 'error'>('loading');
  let error = $state('');
  let payment = $state<PaymentData | null>(null);
  let qrContent = $state('');
  let timer: ReturnType<typeof setTimeout> | undefined;
  let requestId = 0;

  function money(value: number): string {
    return `Rp${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value)}`;
  }

  function stop(): void {
    requestId += 1;
    clearTimeout(timer);
  }

  async function load(attempt = 0): Promise<void> {
    if (!pickupNumber || !open) return;
    clearTimeout(timer);
    const current = ++requestId;
    phase = 'loading';
    error = '';
    try {
      const result = await postWordPressAction<PaymentData>('kiriof_get_payment_form', { payment_id: pickupNumber });
      if (current !== requestId || !open) return;
      const data = result.data ?? {};
      const remote = data.payment_data ?? {};
      const local = data.payment_in_wc_data ?? {};
      const status = String(remote.payment_status || remote.status || '').toLowerCase();
      const paidStatus = ['paid', 'settlement', 'settled', 'success'].includes(status);
      const paid = String(local.method || '').toLowerCase() === 'qris'
        ? !!remote.paid_at || paidStatus
        : String(remote.status_code || '').trim() === '0' || !!remote.pay_time || !!remote.paid_at || paidStatus;
      if (paid || String(local.status || '').toLowerCase() === 'paid') {
        stop();
        open = false;
        window.location.reload();
        return;
      }
      if (!remote.qr_content) {
        if (attempt < 20) {
          timer = setTimeout(() => void load(attempt + 1), 1000);
          return;
        }
        throw new Error(i18n.error ?? 'Unable to load payment QR.');
      }
      payment = data;
      qrContent = remote.qr_content;
      phase = 'ready';
    } catch (cause) {
      if (current !== requestId || !open) return;
      error = cause instanceof Error ? cause.message : i18n.error ?? 'Unable to load payment QR.';
      phase = 'error';
    }
  }

  $effect(() => {
    if (open && pickupNumber) void load();
    else {
      stop();
      payment = null;
      qrContent = '';
    }
  });
  onDestroy(stop);
</script>

<KiriofDialog
  bind:open
  class="sm:max-w-md"
  title={i18n.scanToPay ?? 'Scan to Pay'}
  primaryLabel={i18n.refresh ?? 'Refresh'}
  primaryDisabled={phase === 'loading'}
  secondaryLabel={i18n.cancel ?? 'Cancel'}
  onPrimary={() => void load()}
  onOpenChange={(nextOpen) => { if (!nextOpen) stop(); }}
>
  {#if phase === 'loading'}
    <div class="grid min-h-40 place-items-center text-sm text-muted-foreground" role="status">{i18n.processing ?? 'Processing…'}</div>
  {:else if phase === 'error'}
    <p class="m-0 text-sm text-destructive" role="alert">{error}</p>
  {:else if payment}
    <div class="grid justify-items-center gap-3">
      <svg use:qr={{ data: qrContent, margin: 4, moduleFill: 'black', anchorOuterFill: 'black', anchorInnerFill: 'black' }} class="size-64 max-w-full bg-white" role="img" aria-label={i18n.scanToPay ?? 'Payment QR code'}></svg>
      <div class="text-center"><strong>{i18n.code}: {payment.payment_data?.payment_id ?? ''}</strong><div class="text-lg font-bold text-info">{money(Number(payment.sum_fee_non_cod ?? 0))}</div></div>
    </div>
    <div class="grid gap-2 rounded-lg border border-border bg-background p-3 text-sm">
      <div class="flex justify-between gap-4"><span>{i18n.codCharges}</span><strong>{money(0)}</strong></div>
      <div class="flex justify-between gap-4"><span>{i18n.nonCodCharges}</span><strong>{money(Number(payment.sum_fee_non_cod ?? 0))}</strong></div>
      <div class="flex justify-between gap-4 border-t border-border pt-2"><span>{i18n.totalCharges}</span><strong>{money(Number(payment.sum_fee_non_cod ?? 0))}</strong></div>
    </div>
    <div class="text-center text-sm text-destructive">{i18n.expiresAt}: <strong>{payment.expired_at ?? ''}</strong></div>
  {/if}
</KiriofDialog>
