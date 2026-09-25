<script lang="ts">
  import * as Dialog from '$lib/components/ui/dialog';
  import * as Field from '$lib/components/ui/field';
  import * as InputOTP from '$lib/components/ui/input-otp';
  import { REGEXP_ONLY_DIGITS } from 'bits-ui';
  import * as RadioGroup from '$lib/components/ui/radio-group';
  import * as Select from '$lib/components/ui/select';
  import KiriofSelect from '$lib/ui/KiriofSelect.svelte';
  import { Button } from '$lib/components/ui/button';
  import { IconCreditCard, IconQrcode } from '@tabler/icons-svelte';

  type PickupDate = { value: string; label: string; times: Array<{ value: string; label: string }> };
  type Summary = { sum_fee_cod?: number | string; sum_fee_non_cod?: number | string };
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

  let phase = $state<'loading' | 'schedule' | 'pin' | 'submitting' | 'error'>('loading');
  let pickupDates = $state<PickupDate[]>([]);
  let summary = $state<Summary>({});
  let selectedDate = $state('');
  let selectedTime = $state('');
  let paymentMethod = $state('');
  let paymentRequired = $state(false);
  let creditEnabled = $state(false);
  let creditAvailable = $state(false);
  let hasPin = $state(false);
  let creditBalance = $state(0);
  let qrisDisabled = $state(false);
  let pin = $state('');
  let errorMessage = $state('');
  let requestKey = $state('');

  const totalFee = $derived(Number(summary.sum_fee_cod ?? 0) + Number(summary.sum_fee_non_cod ?? 0));
  const canContinue = $derived(
    phase === 'schedule' &&
      Boolean(selectedDate && selectedTime) &&
      (!paymentRequired || Boolean(paymentMethod))
  );
  const canSubmitPin = $derived(phase === 'pin' && /^\d{6}$/.test(pin));
  const paymentOptions = $derived(
    paymentRequired
      ? [
          ...(creditEnabled
            ? [
                {
                  value: 'credit',
                  title: 'KA Credit',
                  description: creditAvailable
                    ? label('creditDescription', `Remaining Credit ${money(creditBalance)}`)
                    : hasPin
                      ? label('creditInsufficient', 'Insufficient credit balance for this pickup.')
                      : label('creditNoPin', 'Set a PIN on your KiriminAja profile to pay with credit.'),
                  icon: IconCreditCard,
                  disabled: !creditAvailable,
                },
              ]
            : []),
          { value: 'qris', title: 'QRIS', description: label('qrisDescription', 'Maximum Transaction Rp10.000.000'), icon: IconQrcode, disabled: qrisDisabled },
        ]
      : [],
  );
  const selectableOptions = $derived(paymentOptions.filter((option) => !option.disabled));

  function label(key: string, fallback: string): string {
    return i18n[key] || fallback;
  }

  function localDateValue(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function createPickupDates(): PickupDate[] {
    const now = new Date();
    const earliest = new Date(now.getTime() + 60 * 60 * 1000);
    const dates: PickupDate[] = [];

    for (let offset = 0; offset <= 7; offset += 1) {
      const date = new Date(now.getFullYear(), now.getMonth(), now.getDate() + offset);
      const value = localDateValue(date);
      const firstHour = offset === 0 ? Math.max(8, Math.ceil(earliest.getHours() + earliest.getMinutes() / 60)) : 8;
      const times = [];

      for (let hour = firstHour; hour <= 21; hour += 1) {
        const slot = new Date(date.getFullYear(), date.getMonth(), date.getDate(), hour);
        if (slot.getTime() < earliest.getTime()) continue;
        const start = `${String(hour).padStart(2, '0')}:00`;
        const end = `${String(Math.min(hour + 5, 22)).padStart(2, '0')}:00`;
        times.push({ value: start, label: `${start} - ${end}` });
      }

      if (times.length) {
        dates.push({
          value,
          label: new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(date),
          times,
        });
      }
    }

    return dates;
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
    const payload = (await response.json()) as { data?: ApiResult };
    if (!response.ok || !payload.data) throw new Error(label('genericError', 'An error occurred.'));
    return payload.data;
  }

  function ensureSuccess(result: ApiResult): Record<string, any> {
    if (result.status !== 200) throw new Error(result.message || label('genericError', 'An error occurred.'));
    return result.data || {};
  }

  async function load(): Promise<void> {
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
    creditBalance = 0;
    qrisDisabled = false;
    pin = '';

    try {
      const summaryResult = await call('kiriof_request_pickup_summary', { order_ids: orderIds, nonce });
      const summaryData = ensureSuccess(summaryResult);
      summary = summaryData.transaction_summary || {};

      const configResult = await call('kiriof_get_payment_method_config', { nonce }, false);
      const paymentConfig = ensureSuccess(configResult);
      const hasNonCodFee = Number(summary.sum_fee_non_cod || 0) > 0;
      const isTop = paymentConfig.is_top === true;
      paymentRequired = hasNonCodFee && !isTop;
      creditEnabled = paymentConfig.ka_credit_enabled === true;
      hasPin = paymentConfig.has_pin === true;
      paymentMethod = paymentRequired ? 'qris' : '';
      qrisDisabled = totalFee > 10000000;

      if (paymentRequired && creditEnabled) {
        const balanceResult = await call('kiriof_get_credit_balance', { nonce }, false);
        const balanceData = balanceResult.data || {};
        creditBalance = Number(balanceData.balance || 0);
        creditAvailable = balanceResult.status === 200 && hasPin && creditBalance >= totalFee;
      }

      if (paymentRequired) {
        paymentMethod = creditEnabled && creditAvailable ? 'credit' : !qrisDisabled ? 'qris' : '';
        if (!selectableOptions.some((option) => option.value === paymentMethod)) paymentMethod = selectableOptions[0]?.value ?? '';
      }

      phase = 'schedule';
      if (!pickupDates.length) errorMessage = label('noSchedule', 'No pickup time is available in the next seven days.');
    } catch (error) {
      phase = 'error';
      errorMessage = error instanceof Error ? error.message : label('genericError', 'An error occurred.');
    }
  }

  async function submit(): Promise<void> {
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

    phase = 'submitting';
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
    }
  }

  function close(): void {
    open = false;
  }

  $effect(() => {
    if (!open) {
      requestKey = '';
      return;
    }
    const nextKey = orderIds.join('|');
    if (nextKey && nextKey !== requestKey) {
      requestKey = nextKey;
      void load();
    }
  });
</script>

<Dialog.Root bind:open>
  <Dialog.Content class="kiriof-shadcn kiriof-transaction-dialog-content max-w-xl">
    <Dialog.Header>
      <Dialog.Title>{label('schedulePickupTitle', 'Schedule for Pickup')}</Dialog.Title>
      <Dialog.Description>
        {label('schedulePickupDescription', 'Choose a pickup date and time for the selected transactions.')}
      </Dialog.Description>
    </Dialog.Header>

    {#if phase === 'loading' || phase === 'submitting'}
      <div class="flex min-h-32 items-center justify-center text-sm text-muted-foreground" aria-live="polite">
        {phase === 'submitting' ? label('processing', 'Processing…') : label('loading', 'Loading…')}
      </div>
    {:else if phase === 'error'}
      <div class="flex flex-col gap-3" role="alert">
        <p class="text-sm text-destructive">{errorMessage}</p>
        <Button class="kiriof-dialog-secondary" variant="outline" onclick={load}>{label('retry', 'Retry')}</Button>
      </div>
    {:else if phase === 'pin'}
      <Field.FieldGroup>
        <Field.Field>
          <Field.FieldLabel for="kiriof-pickup-pin">{label('enterPin', 'Enter PIN')}</Field.FieldLabel>
          <InputOTP.Root inputId="kiriof-pickup-pin" type="password" bind:value={pin} maxlength={6} pattern={REGEXP_ONLY_DIGITS} inputmode="numeric" autocomplete="one-time-code" aria-label={label('enterPin', 'Enter PIN')} aria-describedby="kiriof-pickup-pin-help">
            {#snippet children({ cells })}
              <InputOTP.Group>
                {#each cells.slice(0, 3) as cell, index (index)}
                  <InputOTP.Slot {cell} mask />
                {/each}
              </InputOTP.Group>
              <InputOTP.Separator />
              <InputOTP.Group>
                {#each cells.slice(3, 6) as cell, index (index)}
                  <InputOTP.Slot {cell} mask />
                {/each}
              </InputOTP.Group>
            {/snippet}
          </InputOTP.Root>
          <Field.FieldDescription id="kiriof-pickup-pin-help">{label('pinDescription', 'Enter the 6-digit PIN configured on your profile.')}</Field.FieldDescription>
        </Field.Field>
      </Field.FieldGroup>
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
            <KiriofSelect id="kiriof-pickup-date" value={selectedDate} options={pickupDates} placeholder={label('selectDatePlaceholder', 'Select a pickup date')} onChange={selectDate} />
          </Field.Field>

          <Field.Field>
            <Field.FieldLabel for="kiriof-pickup-time">{label('pickupTime', 'Pickup Time')}</Field.FieldLabel>
            <KiriofSelect id="kiriof-pickup-time" bind:value={selectedTime} options={availableTimes} placeholder={label('selectTimePlaceholder', 'Select a pickup time')} />
          </Field.Field>
        </Field.FieldGroup>

        {#if paymentRequired && paymentOptions.length >= 1}
          <Field.Field>
            <Field.FieldLabel>{label('paymentMethod', 'Choose Payment Method')}<span class="text-destructive">*</span></Field.FieldLabel>
            <RadioGroup.Root bind:value={paymentMethod} class="kiriof-payment-methods">
              {#each paymentOptions as option (option.value)}
                {@const PaymentIcon = option.icon}
                <label class="kiriof-payment-method-card" class:is-selected={paymentMethod === option.value} class:is-disabled={option.disabled} aria-disabled={option.disabled ? 'true' : undefined}>
                  <span class="kiriof-payment-method-card__icon"><PaymentIcon /></span>
                  <span class="kiriof-payment-method-card__copy"><strong>{option.title}</strong><span>{option.description}</span></span>
                  <RadioGroup.Item value={option.value} aria-label={option.title} disabled={option.disabled} />
                </label>
              {/each}
            </RadioGroup.Root>
          </Field.Field>
        {:else}
          {#if !paymentRequired}<p class="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground">{label('noPaymentRequired', 'No payment method is required for this pickup.')}</p>{/if}
        {/if}
      </Field.FieldGroup>
      {#if errorMessage}<p class="text-sm text-destructive" role="alert">{errorMessage}</p>{/if}
    {/if}

    <Dialog.Footer>
      <Button class="kiriof-dialog-secondary" variant="ghost" onclick={close}>{label('close', 'Close')}</Button>
      {#if phase === 'pin'}
        <Button class="kiriof-dialog-primary" onclick={submit} disabled={!canSubmitPin}>{label('confirmPickup', 'Confirm & Process')}</Button>
      {:else if phase === 'schedule'}
        <Button class="kiriof-dialog-primary" onclick={submit} disabled={!canContinue || !pickupDates.length}>{label('continueToPayment', 'Continue to Payment')}</Button>
      {/if}
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
