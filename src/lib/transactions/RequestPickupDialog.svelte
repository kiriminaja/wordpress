<script lang="ts">
  import * as Dialog from '$lib/components/ui/dialog';
  import * as Field from '$lib/components/ui/field';
  import { Input } from '$lib/components/ui/input';
  import * as Select from '$lib/components/ui/select';
  import { Button } from '$lib/components/ui/button';

  type Schedule = { clock: string; label: string };
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
  let schedules = $state<Schedule[]>([]);
  let summary = $state<Summary>({});
  let selectedSchedule = $state('');
  let paymentMethod = $state('');
  let paymentRequired = $state(false);
  let creditAvailable = $state(false);
  let pin = $state('');
  let errorMessage = $state('');
  let requestKey = $state('');

  const totalFee = $derived(Number(summary.sum_fee_cod ?? 0) + Number(summary.sum_fee_non_cod ?? 0));
  const canContinue = $derived(
    phase === 'schedule' &&
      Boolean(selectedSchedule) &&
      (!paymentRequired || Boolean(paymentMethod))
  );
  const canSubmitPin = $derived(phase === 'pin' && /^\d{6}$/.test(pin));

  function label(key: string, fallback: string): string {
    return i18n[key] || fallback;
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
    schedules = [];
    summary = {};
    selectedSchedule = '';
    paymentMethod = '';
    paymentRequired = false;
    creditAvailable = false;
    pin = '';

    try {
      const scheduleResult = await call('kiriof_request_pickup_schedule', { order_ids: orderIds, nonce });
      const scheduleData = ensureSuccess(scheduleResult);
      if (scheduleData.pickup_number) {
        window.location.href = `${pickupUrl}&pickup_number=${encodeURIComponent(String(scheduleData.pickup_number))}`;
        return;
      }

      schedules = Array.isArray(scheduleData.schedules) ? scheduleData.schedules : [];
      summary = scheduleData.transaction_summary || {};

      const configResult = await call('kiriof_get_payment_method_config', { nonce }, false);
      const paymentConfig = ensureSuccess(configResult);
      const hasNonCodFee = Number(summary.sum_fee_non_cod || 0) > 0;
      const isTop = paymentConfig.is_top === true;
      paymentRequired = hasNonCodFee && !isTop;

      if (paymentRequired && paymentConfig.ka_credit_enabled === true && paymentConfig.has_pin === true) {
        const balanceResult = await call('kiriof_get_credit_balance', { nonce }, false);
        const balanceData = balanceResult.data || {};
        creditAvailable = balanceResult.status === 200 && Number(balanceData.balance || 0) >= totalFee;
      }

      phase = 'schedule';
      if (!schedules.length) errorMessage = label('noSchedule', 'No pickup schedule is available.');
    } catch (error) {
      phase = 'error';
      errorMessage = error instanceof Error ? error.message : label('genericError', 'An error occurred.');
    }
  }

  async function submit(): Promise<void> {
    if (!selectedSchedule) {
      errorMessage = label('selectSchedule', 'Please select a pickup schedule.');
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
  <Dialog.Content class="max-w-xl">
    <Dialog.Header>
      <Dialog.Title>{label('schedulePickupTitle', 'Schedule for Pickup')}</Dialog.Title>
      <Dialog.Description>
        {label('schedulePickupDescription', 'Choose a pickup schedule and payment method for the selected transactions.')}
      </Dialog.Description>
    </Dialog.Header>

    {#if phase === 'loading' || phase === 'submitting'}
      <div class="flex min-h-32 items-center justify-center text-sm text-muted-foreground" aria-live="polite">
        {phase === 'submitting' ? label('processing', 'Processing…') : label('loading', 'Loading…')}
      </div>
    {:else if phase === 'error'}
      <div class="flex flex-col gap-3" role="alert">
        <p class="text-sm text-destructive">{errorMessage}</p>
        <Button variant="outline" onclick={load}>{label('retry', 'Retry')}</Button>
      </div>
    {:else if phase === 'pin'}
      <Field.FieldGroup>
        <Field.Field>
          <Field.FieldLabel for="kiriof-pickup-pin">{label('enterPin', 'Enter PIN')}</Field.FieldLabel>
          <Input id="kiriof-pickup-pin" type="password" inputmode="numeric" maxlength={6} placeholder="••••••" bind:value={pin} aria-describedby="kiriof-pickup-pin-help" />
          <Field.FieldDescription id="kiriof-pickup-pin-help">{label('pinDescription', 'Enter the 6-digit PIN configured on your profile.')}</Field.FieldDescription>
        </Field.Field>
      </Field.FieldGroup>
      {#if errorMessage}<p class="text-sm text-destructive" role="alert">{errorMessage}</p>{/if}
    {:else}
      <Field.FieldGroup>
        <div class="grid gap-2 rounded-lg border p-3 text-sm">
          <div class="flex items-center justify-between gap-4"><span>{label('codCharges', 'COD Package Charges')}</span><strong>{money(summary.sum_fee_cod || 0)}</strong></div>
          <div class="flex items-center justify-between gap-4"><span>{label('nonCodCharges', 'Non-COD Package Charges')}</span><strong>{money(summary.sum_fee_non_cod || 0)}</strong></div>
          <div class="flex items-center justify-between gap-4 border-t pt-2 font-semibold"><span>{label('totalCharges', 'Total Charges')}</span><strong>{money(totalFee)}</strong></div>
        </div>

        <Field.Field>
          <Field.FieldLabel for="kiriof-pickup-schedule">{label('availableSchedules', 'Available Schedules')}</Field.FieldLabel>
          <Select.Root type="single" bind:value={selectedSchedule}>
            <Select.Trigger id="kiriof-pickup-schedule"><Select.Value placeholder={label('selectSchedulePlaceholder', 'Select a pickup schedule')} /></Select.Trigger>
            <Select.Content>
              <Select.Group>
                {#each schedules as schedule (schedule.clock)}
                  <Select.Item value={schedule.clock}>{schedule.label}</Select.Item>
                {/each}
              </Select.Group>
            </Select.Content>
          </Select.Root>
        </Field.Field>

        {#if paymentRequired}
          <Field.Field>
            <Field.FieldLabel for="kiriof-pickup-payment">{label('paymentMethod', 'Payment Method')}</Field.FieldLabel>
            <Select.Root type="single" bind:value={paymentMethod}>
              <Select.Trigger id="kiriof-pickup-payment"><Select.Value placeholder={label('selectPaymentPlaceholder', 'Select a payment method')} /></Select.Trigger>
              <Select.Content>
                <Select.Group>
                  <Select.Item value="qris">QRIS</Select.Item>
                  {#if creditAvailable}<Select.Item value="credit">KA Credit</Select.Item>{/if}
                </Select.Group>
              </Select.Content>
            </Select.Root>
          </Field.Field>
        {:else}
          <p class="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground">{label('noPaymentRequired', 'No payment method is required for this pickup.')}</p>
        {/if}
      </Field.FieldGroup>
      {#if errorMessage}<p class="text-sm text-destructive" role="alert">{errorMessage}</p>{/if}
    {/if}

    <Dialog.Footer>
      <Button variant="outline" onclick={close}>{label('close', 'Close')}</Button>
      {#if phase === 'pin'}
        <Button onclick={submit} disabled={!canSubmitPin}>{label('confirmPickup', 'Confirm & Process')}</Button>
      {:else if phase === 'schedule'}
        <Button onclick={submit} disabled={!canContinue || !schedules.length}>{paymentMethod === 'credit' ? label('confirmPin', 'Confirm PIN') : label('pickSchedule', 'Pick Schedule')}</Button>
      {/if}
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
