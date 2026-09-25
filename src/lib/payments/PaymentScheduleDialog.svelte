<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import * as RadioGroup from '$lib/components/ui/radio-group';
  import KiriofDialog from '$lib/ui/KiriofDialog.svelte';

  type Schedule = { clock: string; label: string };
  type ScheduleData = {
    schedules?: Schedule[];
    transaction_summary?: { order_id?: string; sum_fee_cod?: number; sum_fee_non_cod?: number };
  };

  let {
    open = $bindable(false),
    pickupNumber,
    ajaxUrl,
    nonce,
    i18n,
    onComplete,
  }: {
    open?: boolean;
    pickupNumber: string;
    ajaxUrl: string;
    nonce: string;
    i18n: Record<string, string>;
    onComplete?: () => void;
  } = $props();

  let loading = $state(false);
  let submitting = $state(false);
  let error = $state('');
  let schedules = $state<Schedule[]>([]);
  let selectedSchedule = $state('');
  let orderId = $state('');
  let summary = $state({ cod: 0, nonCod: 0 });

  function money(value: number): string {
    return `Rp${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value)}`;
  }

  async function post<T>(values: Record<string, string>): Promise<T> {
    const response = await fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams(values),
    });
    const payload = (await response.json()) as { success?: boolean; data?: { status?: number; message?: string; data?: T } };
    if (!response.ok || !payload.success || Number(payload.data?.status ?? 0) !== 200) {
      throw new Error(payload.data?.message ?? i18n.error ?? 'Unable to load pickup schedule.');
    }
    return payload.data?.data as T;
  }

  async function load(): Promise<void> {
    if (!pickupNumber || loading) return;
    loading = true;
    error = '';
    try {
      const result = await post<ScheduleData>({
        action: 'kiriof_get_shipping_reschedule_pickup',
        'data[payment_id]': pickupNumber,
        'data[nonce]': nonce,
      });
      schedules = result.schedules ?? [];
      selectedSchedule = schedules[0]?.clock ?? '';
      orderId = result.transaction_summary?.order_id ?? '';
      summary = {
        cod: Number(result.transaction_summary?.sum_fee_cod ?? 0),
        nonCod: Number(result.transaction_summary?.sum_fee_non_cod ?? 0),
      };
    } catch (cause) {
      error = cause instanceof Error ? cause.message : i18n.error ?? 'Unable to load pickup schedule.';
    } finally {
      loading = false;
    }
  }

  async function submit(): Promise<void> {
    if (!orderId || !selectedSchedule || submitting) return;
    submitting = true;
    error = '';
    try {
      await post({
        action: 'kiriof_request_pickup_transaction',
        'data[schedule]': selectedSchedule,
        'data[order_ids][0]': orderId,
        'data[nonce]': nonce,
      });
      open = false;
      onComplete?.();
    } catch (cause) {
      error = cause instanceof Error ? cause.message : i18n.error ?? 'Unable to update pickup schedule.';
    } finally {
      submitting = false;
    }
  }

  $effect(() => {
    if (open) void load();
    else {
      schedules = [];
      selectedSchedule = '';
      orderId = '';
      error = '';
    }
  });
</script>

<KiriofDialog
  bind:open
  class="sm:max-w-lg"
  title={i18n.schedule ?? 'Schedule for Pickup'}
  description={i18n.scheduleDescription ?? 'Choose a new pickup time for this payment.'}
  secondaryLabel={i18n.cancel ?? 'Cancel'}
  secondaryDisabled={submitting}
  primaryLabel={submitting ? (i18n.processing ?? 'Processing…') : (i18n.confirmSchedule ?? 'Confirm schedule')}
  primaryDisabled={loading || submitting || !selectedSchedule || !orderId}
  onPrimary={() => void submit()}
>
  {#if loading}
    <div class="!grid min-h-32 place-items-center text-sm text-muted-foreground">{i18n.loading ?? 'Loading pickup schedules…'}</div>
  {:else}
    <div class="!grid gap-3 rounded-lg border border-border p-3 text-sm">
      <div class="!flex !items-center !justify-between gap-4"><span>{i18n.codCharges ?? 'COD Package Charges'}</span><strong>{money(summary.cod)}</strong></div>
      <div class="!flex !items-center !justify-between gap-4"><span>{i18n.nonCodCharges ?? 'Non-COD Package Charges'}</span><strong>{money(summary.nonCod)}</strong></div>
      <div class="!flex !items-center !justify-between gap-4 border-t border-border pt-2"><strong>{i18n.totalCharges ?? 'Total Charges'}</strong><strong>{money(summary.cod + summary.nonCod)}</strong></div>
    </div>
    <RadioGroup.Root bind:value={selectedSchedule} class="!grid gap-2">
      {#each schedules as schedule (schedule.clock)}
        <label class="!flex cursor-pointer !items-center gap-3 rounded-lg border border-border p-3 has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary/5">
          <RadioGroup.Item value={schedule.clock} />
          <span class="text-sm font-medium text-foreground">{schedule.label}</span>
        </label>
      {:else}
        <p class="m-0 text-sm text-muted-foreground">{i18n.noSchedule ?? 'No pickup schedule is available.'}</p>
      {/each}
    </RadioGroup.Root>
  {/if}
  {#if error}<div class="!grid gap-2"><p class="m-0 text-sm text-destructive" role="alert">{error}</p><Button variant="outline" onclick={() => void load()}>{i18n.retry ?? 'Retry'}</Button></div>{/if}
</KiriofDialog>
