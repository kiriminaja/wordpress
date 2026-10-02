<script lang="ts">
  import * as Dialog from '$lib/components/ui/dialog';
  import * as Field from '$lib/components/ui/field';
  import CreditPinInput from '$lib/payments/CreditPinInput.svelte';
  import PaymentMethodSelector from '$lib/payments/PaymentMethodSelector.svelte';
  import type { PaymentMethodOption } from '$lib/payments/types';
  import { onDestroy, untrack } from 'svelte';
  import { createPickupDates, pickupFlag, requiresPickupPayment, type PickupDate } from './pickup-schedule';
  import KiriofSelect from '$lib/ui/KiriofSelect.svelte';
  import { Button } from '$lib/components/ui/button';
  import { IconLoader2 } from '@tabler/icons-svelte';

  type Summary = { count_non_cod?: number | string; sum_fee_cod?: number | string; sum_fee_non_cod?: number | string };
  type ApiResult = { status?: number; message?: string; data?: Record<string, any> };

  let {
    open = $bindable(false),
    orderIds,
    ajaxUrl,
    nonce,
    pickupUrl,
    i18n,
  }: {
    open?: boolean;
    orderIds: string[];
    ajaxUrl: string;
    nonce: string;
    pickupUrl: string;
    i18n: Record<string, string>;
  } = $props();

  let phase = $state<'loading' | 'schedule' | 'pin' | 'error'>('loading');
  let pickupDates = $state<PickupDate[]>([]);
  let summary = $state<Summary>({});
  let selectedDate = $state('');
  let selectedTime = $state('');
  let paymentMethod = $state('');
  let paymentRequired = $state(false);
  let creditEnabled = $state(false);
  let creditAvailable = $state(false);
  let hasPin = $state(false);
  let creditBalance = $state<number | null>(null);
  let qrisDisabled = $state(false);
  let pin = $state('');
  let errorMessage = $state('');
  let submitting = $state(false);
  let loadId = 0;

  const totalFee = $derived(Number(summary.sum_fee_cod ?? 0) + Number(summary.sum_fee_non_cod ?? 0));
  const canContinue = $derived(
    phase === 'schedule' &&
      Boolean(selectedDate && selectedTime) &&
      (!paymentRequired || Boolean(paymentMethod))
  );
  const canSubmitPin = $derived(phase === 'pin' && /^\d{6}$/.test(pin));
  const paymentOptions = $derived<PaymentMethodOption[]>(
    paymentRequired
      ? [
          ...(creditEnabled
            ? [
                {
                  value: 'credit' as const,
                  title: 'KA Credit',
                  description: !hasPin
                    ? label('creditNoPin', 'Set a PIN on your KiriminAja profile to pay with credit.')
                    : creditBalance === null
                      ? label('creditUnavailable', 'Unable to verify credit balance.')
                      : !creditAvailable
                        ? label('creditInsufficient', 'Insufficient credit balance for this pickup.')
                        : '',
                  balance: creditBalance,
                  disabled: !creditAvailable,
                },
              ]
            : []),
          { value: 'qris', title: 'QRIS', description: label('qrisDescription', 'Maximum Transaction Rp10.000.000'), disabled: qrisDisabled },
        ]
      : [],
  );
  const selectableOptions = $derived(paymentOptions.filter((option) => !option.disabled));


  function label(key: string, fallback: string): string {
    return i18n[key] || fallback;
  }

  function parseCreditBalance(value: unknown): number | null {
    if (typeof value !== 'number' && typeof value !== 'string') return null;
    if (typeof value === 'string' && !/^\d+(?:\.\d+)?$/.test(value.trim())) return null;
    const amount = Number(value);
    return Number.isFinite(amount) && amount >= 0 ? amount : null;
  }

  const selectedDateOption = $derived(pickupDates.find((date) => date.value === selectedDate));
  const availableTimes = $derived(selectedDateOption?.times ?? []);
  const selectedSchedule = $derived(selectedDate && selectedTime ? `${selectedDate} ${selectedTime}:00` : '');

  function selectDate(value: string): void {
    selectedDate = value;
    const nextTimes = pickupDates.find((date) => date.value === value)?.times ?? [];
    if (!nextTimes.some((time) => time.value === selectedTime)) selectedTime = nextTimes[0]?.value ?? '';
  }

  function money(value: number | string): string {
    return `Rp${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value) || 0)}`;
  }

  async function call(action: string, data: Record<string, string | string[]> = {}, nested = true): Promise<ApiResult> {
    const body = new URLSearchParams();
    body.set('action', action);
    for (const [key, value] of Object.entries(data)) {
      const field = nested ? `data[${key}]` : key;
      if (Array.isArray(value)) {
        for (const item of value) body.append(`${field}[]`, item);
      } else {
        body.set(field, value);
      }
    }

    const response = await fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body,
    });
    const payload = (await response.json()) as { success?: boolean; data?: ApiResult };
    if (!response.ok || payload.success === false || !payload.data) throw new Error(label('genericError', 'An error occurred.'));
    return payload.data;
  }

  function ensureSuccess(result: ApiResult): Record<string, any> {
    if (Number(result.status) !== 200) throw new Error(result.message || label('genericError', 'An error occurred.'));
    return result.data || {};
  }

  async function load(): Promise<void> {
    const current = ++loadId;
    phase = 'loading';
    errorMessage = '';
    pickupDates = createPickupDates();
    summary = {};
    selectedDate = pickupDates[0]?.value ?? '';
    selectedTime = pickupDates[0]?.times[0]?.value ?? '';
    paymentMethod = '';
    paymentRequired = false;
    creditEnabled = false;
    creditAvailable = false;
    hasPin = false;
    creditBalance = null;
    qrisDisabled = false;
    pin = '';

    try {
      const summaryResult = await call('kiriof_request_pickup_summary', { order_ids: orderIds, nonce });
      if (current !== loadId || !open) return;
      const summaryData = ensureSuccess(summaryResult);
      summary = summaryData.transaction_summary || {};

      const configResult = await call('kiriof_get_payment_method_config', { nonce }, false);
      if (current !== loadId || !open) return;
      const paymentConfig = ensureSuccess(configResult);
      paymentRequired = requiresPickupPayment(summary, paymentConfig.is_top);
      creditEnabled = pickupFlag(paymentConfig.ka_credit_enabled);
      hasPin = pickupFlag(paymentConfig.has_pin);
      paymentMethod = paymentRequired ? 'qris' : '';
      qrisDisabled = totalFee > 10000000;

      if (paymentRequired && creditEnabled) {
        try {
          // call() unwraps WordPress's data envelope; the service's data contains balance.
          const balanceResult = await call('kiriof_get_credit_balance', { nonce }, false);
          if (current !== loadId || !open) return;
          creditBalance = Number(balanceResult.status) === 200
            ? parseCreditBalance(balanceResult.data?.balance)
            : null;
          creditAvailable = hasPin && creditBalance !== null && creditBalance >= totalFee;
        } catch {
          if (current !== loadId || !open) return;
          // A failed balance lookup must not invent a zero or prevent paying with QRIS.
          creditBalance = null;
          creditAvailable = false;
        }
      }

      if (paymentRequired) {
        paymentMethod = creditEnabled && creditAvailable ? 'credit' : !qrisDisabled ? 'qris' : '';
        if (!selectableOptions.some((option) => option.value === paymentMethod)) paymentMethod = selectableOptions[0]?.value ?? '';
      }

      phase = 'schedule';
      if (!pickupDates.length) errorMessage = label('noSchedule', 'No pickup time is available in the next seven days.');
    } catch (error) {
      if (current !== loadId || !open) return;
      phase = 'error';
      errorMessage = error instanceof Error ? error.message : label('genericError', 'An error occurred.');
    }
  }

  async function submit(): Promise<void> {
    if (submitting || (phase !== 'schedule' && phase !== 'pin')) return;
    if (!selectedSchedule) {
      errorMessage = label('selectSchedule', 'Please select a pickup date and time.');
      return;
    }
    if (paymentRequired && !paymentMethod) {
      errorMessage = label('selectPayment', 'Please select a payment method.');
      return;
    }
    if (paymentMethod === 'credit' && phase === 'schedule') {
      phase = 'pin';
      errorMessage = '';
      return;
    }
    if (paymentMethod === 'credit' && !/^\d{6}$/.test(pin)) {
      errorMessage = label('sixDigitPin', 'Please enter a 6-digit PIN.');
      return;
    }

    submitting = true;
    errorMessage = '';
    try {
      const result = await call('kiriof_request_pickup_transaction', {
        schedule: selectedSchedule,
        order_ids: orderIds,
        payment_method: paymentMethod,
        pin,
        nonce,
      });
      const data = ensureSuccess(result);
      const pickupNumber = encodeURIComponent(String(data.pickup_number || ''));
      const paymentSuffix = data.open_payment === true || data.open_payment === 1 || data.open_payment === '1' ? '&open_payment=1' : '';
      open = false;
      window.location.href = `${pickupUrl}&pickup_number=${pickupNumber}${paymentSuffix}`;
    } catch (error) {
      phase = paymentMethod === 'credit' ? 'pin' : 'schedule';
      errorMessage = error instanceof Error ? error.message : label('somethingWrong', 'Something went wrong.');
    } finally {
      submitting = false;
    }
  }

  function close(): void {
    if (!submitting) open = false;
  }

  $effect(() => {
    const identity = open ? orderIds.join('|') : '';
    if (identity) untrack(() => void load());
    return () => { loadId += 1; };
  });
  onDestroy(() => { loadId += 1; });
</script>

<Dialog.Root {open} onOpenChange={(nextOpen) => { if (!submitting) open = nextOpen; }}>
  <Dialog.Content class="kiriof-shadcn kiriof-transaction-dialog-content max-w-xl" showCloseButton={!submitting} escapeKeydownBehavior={submitting ? 'ignore' : 'close'} interactOutsideBehavior={submitting ? 'ignore' : 'close'} aria-busy={submitting}>
    <Dialog.Header>
      <Dialog.Title>{label('schedulePickupTitle', 'Schedule for Pickup')}</Dialog.Title>
      <Dialog.Description>
        {label('schedulePickupDescription', 'Choose a pickup date and time for the selected transactions.')}
      </Dialog.Description>
    </Dialog.Header>

    {#if phase === 'loading'}
      <div class="flex min-h-32 items-center justify-center text-sm text-muted-foreground" aria-live="polite">
        <IconLoader2 class="size-5 animate-spin" aria-hidden="true" />
        {label('loading', 'Loading…')}
      </div>
    {:else if phase === 'error'}
      <div class="flex flex-col gap-3" role="alert">
        <p class="text-sm text-destructive">{errorMessage}</p>
        <Button class="kiriof-dialog-secondary" variant="outline" onclick={load}>{label('retry', 'Retry')}</Button>
      </div>
    {:else if phase === 'pin'}
      <CreditPinInput id="kiriof-pickup-pin" bind:value={pin} disabled={submitting} invalid={Boolean(errorMessage)} label={label('enterPin', 'Enter PIN')} description={label('pinDescription', 'Enter the 6-digit PIN configured on your profile.')} />
      {#if errorMessage}<p class="text-sm text-destructive" role="alert">{errorMessage}</p>{/if}
    {:else}
      <Field.FieldGroup>
        <div class="kiriof-pickup-summary grid gap-2 rounded-lg border p-3 text-sm">
          <div class="flex items-center justify-between gap-4"><span>{label('codCharges', 'COD Package Charges')}</span><strong>{money(summary.sum_fee_cod || 0)}</strong></div>
          <div class="flex items-center justify-between gap-4"><span>{label('nonCodCharges', 'Non-COD Package Charges')}</span><strong>{money(summary.sum_fee_non_cod || 0)}</strong></div>
          <div class="flex items-center justify-between gap-4 border-t pt-2 font-semibold"><span>{label('totalCharges', 'Total Charges')}</span><strong>{money(totalFee)}</strong></div>
        </div>

        <Field.FieldGroup class="kiriof-pickup-datetime">
          <Field.Field>
            <Field.FieldLabel for="kiriof-pickup-date">{label('pickupDate', 'Pickup Date')}</Field.FieldLabel>
            <KiriofSelect id="kiriof-pickup-date" value={selectedDate} options={pickupDates} placeholder={label('selectDatePlaceholder', 'Select a pickup date')} disabled={submitting} onChange={selectDate} />
          </Field.Field>

          <Field.Field>
            <Field.FieldLabel for="kiriof-pickup-time">{label('pickupTime', 'Pickup Time')}</Field.FieldLabel>
            <KiriofSelect id="kiriof-pickup-time" bind:value={selectedTime} options={availableTimes} placeholder={label('selectTimePlaceholder', 'Select a pickup time')} disabled={submitting} />
          </Field.Field>
        </Field.FieldGroup>

        {#if paymentRequired && paymentOptions.length >= 1}
          <PaymentMethodSelector idPrefix="pickup-method" bind:value={paymentMethod} options={paymentOptions} label={label('paymentMethod', 'Choose Payment Method')} balanceLabel={label('creditDescription', 'Remaining Credit')} disabled={submitting} required />
        {:else}
          {#if !paymentRequired}<p class="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground">{label('noPaymentRequired', 'No payment method is required for this pickup.')}</p>{/if}
        {/if}
      </Field.FieldGroup>
      {#if errorMessage}<p class="text-sm text-destructive" role="alert">{errorMessage}</p>{/if}
    {/if}

    <Dialog.Footer>
      <Button class="kiriof-dialog-secondary" variant="ghost" disabled={submitting} onclick={close}>{label('close', 'Close')}</Button>
      {#if phase === 'pin'}
        <Button class="kiriof-dialog-primary" onclick={submit} loading={submitting} disabled={!canSubmitPin}>{submitting ? label('processing', 'Processing…') : label('confirmPickup', 'Confirm & Process')}</Button>
      {:else if phase === 'schedule'}
        <Button class="kiriof-dialog-primary" onclick={submit} loading={submitting} disabled={!canContinue || !pickupDates.length}>
          {submitting ? label('processing', 'Processing…') : label('continueToPayment', 'Continue to Payment')}
        </Button>
      {/if}
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
