<script lang="ts">
  import { IconAlertTriangle, IconArrowDown, IconArrowUp, IconExternalLink, IconMapPin } from '@tabler/icons-svelte';
  import { Checkbox } from '$lib/components/ui/checkbox';
  import * as Alert from '$lib/components/ui/alert';
  import KiriofInput from '$lib/ui/KiriofInput.svelte';
  import KiriofDialog from '$lib/ui/KiriofDialog.svelte';
  import ShipmentLocationCombobox from './ShipmentLocationCombobox.svelte';
  import CourierOptionCombobox from './CourierOptionCombobox.svelte';
  export type ShipmentLocation = { id: number; name: string; address: string };
  export type TransactionActionData = {
    nonce: string;
    kaOrderId: string;
    currentLocationId: number;
    currentOrigin: string;
    currentOriginAddress: string;
    currentCod: number;
    codMinimum: number;
    codMaximum: number;
    shippingCost: number;
    insuranceFee: number;
    codFee: number;
    itemPrice: number;
    itemDiscount: number;
    shippingDiscount: number;
    itemCoupon: string;
    shippingCoupon: string;
  };
  export type TransactionActionDialog =
    | { kind: 'origin'; data: TransactionActionData }
    | { kind: 'adjust-deficit'; data: TransactionActionData }
    | { kind: 'cancel-deficit'; data: TransactionActionData }
    | { kind: 'cancel'; data: TransactionActionData };

  type CourierOption = {
    courier?: string;
    service?: string;
    service_code: string;
    service_name: string;
    price?: string;
    raw_price?: number;
    discount_amount?: number;
  };
  type OriginCheck = {
    comparison: {
      available?: boolean;
      label?: string;
      new_courier?: string;
      service_code?: string;
      service_name?: string;
      is_total_blocked?: boolean;
      required_refund?: number;
      previous_courier?: string;
      previous_subtotal?: number;
      previous_paid_shipping?: number;
      previous_discount?: number;
      new_paid_shipping?: number;
      new_discount?: number;
      previous_total?: number;
      new_total?: number;
    };
    options: CourierOption[];
    replacement_options: CourierOption[];
  };
  type AjaxError = { message?: string };

  let {
    action = $bindable(null),
    locations,
    ajaxUrl,
    locationsUrl,
    i18n,
    onComplete,
  }: {
    action?: TransactionActionDialog | null;
    locations: ShipmentLocation[];
    ajaxUrl: string;
    locationsUrl: string;
    i18n: Record<string, string>;
    onComplete?: () => void;
  } = $props();

  let originLocationId = $state('');
  let originCheck = $state<OriginCheck | null>(null);
  let selectedCourierKey = $state('');
  let replacementConsent = $state(false);
  let codValue = $state('');
  let cancelReason = $state('');
  let loading = $state(false);
  let error = $state('');

  const originOptions = $derived(originCheck?.comparison.available ? originCheck.options : originCheck?.replacement_options ?? []);
  const currentCourierKey = $derived(originCheck?.comparison.service_code && originCheck?.comparison.service_name ? `${originCheck.comparison.service_code}|${originCheck.comparison.service_name}` : '');
  const selectedCourier = $derived(originOptions.find((option) => courierKey(option) === selectedCourierKey));
  const requiresCourierConsent = $derived(Boolean(
    selectedCourier && (
      !originCheck?.comparison.available ||
      selectedCourier.service_code !== originCheck.options[0]?.service_code ||
      selectedCourier.service_name !== originCheck.options[0]?.service_name
    ),
  ));
  const canConfirmOrigin = $derived(Boolean(
    originLocationId && selectedCourier && !originCheck?.comparison.is_total_blocked && (!requiresCourierConsent || replacementConsent),
  ));
  const minimumCod = $derived(action?.kind === 'adjust-deficit' ? action.data.codMinimum : 0);
  const maximumCod = $derived(action?.kind === 'adjust-deficit' ? action.data.codMaximum : 0);
  const currentOriginLocationId = $derived(action?.kind === 'origin' ? action.data.currentLocationId : 0);
  const courierChanged = $derived(Boolean(originCheck?.comparison.previous_courier && originCheck?.comparison.new_courier && originCheck.comparison.previous_courier !== originCheck.comparison.new_courier));
  const codIsValid = $derived(Number.isFinite(Number(codValue)) && Number(codValue) >= minimumCod && (!maximumCod || Number(codValue) <= maximumCod));
  const adjustedCod = $derived(Number(codValue) || 0);
  const totalShipping = $derived(action?.kind === 'adjust-deficit' ? action.data.shippingCost + action.data.insuranceFee + action.data.codFee : 0);
  const estimatedPayout = $derived(adjustedCod - totalShipping);

  $effect(() => {
    const current = action;
    originLocationId = '';
    originCheck = null;
    selectedCourierKey = '';
    replacementConsent = false;
    codValue = current?.kind === 'adjust-deficit' ? String(Math.round(current.data.currentCod)) : '';
    cancelReason = '';
    loading = false;
    error = '';
  });

  function close(): void {
    if (!loading) action = null;
  }

  async function cancelTransaction(): Promise<void> {
    if (!action || action.kind !== 'cancel' || cancelReason.trim().length < 4) return;
    loading = true;
    error = '';
    try {
      await post({
        action: 'kiriof_cancel_transaction',
        'data[nonce]': action.data.nonce,
        'data[order_id]': action.data.kaOrderId,
        'data[reason]': cancelReason.trim(),
      });
      onComplete?.();
      window.location.reload();
    } catch (cause) {
      error = cause instanceof Error ? cause.message : i18n.actionError ?? 'Unable to complete this action.';
      loading = false;
    }
  }

  function courierKey(option: CourierOption): string {
    return `${option.service_code}|${option.service_name}`;
  }

  function courierLabel(option: CourierOption): string {
    const courier = option.courier?.trim() ?? '';
    const service = option.service?.trim() ?? '';
    if (!service || courier.toLowerCase().includes(service.toLowerCase())) return courier || option.service_name;
    return `${courier} ${service}`.trim();
  }

  function formatCurrency(value: number): string {
    return `Rp${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Math.max(0, value))}`;
  }

  function hasAmountChanged(previous = 0, next = 0): boolean {
    return Math.abs(next - previous) >= 0.5;
  }

  function amountTone(previous = 0, next = 0): string {
    if (!hasAmountChanged(previous, next)) return 'text-foreground';
    return next > previous ? 'text-destructive' : 'text-emerald-700';
  }

  async function post<T>(values: Record<string, string>): Promise<T> {
    const response = await fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams(values),
    });
    const payload = (await response.json()) as { success?: boolean; data?: T | AjaxError };
    if (!response.ok || !payload.success) throw new Error((payload.data as AjaxError | undefined)?.message ?? i18n.actionError ?? 'Unable to complete this action.');
    return payload.data as T;
  }

  async function checkOrigin(): Promise<void> {
    if (!action || action.kind !== 'origin' || !originLocationId) return;
    loading = true;
    error = '';
    originCheck = null;
    selectedCourierKey = '';
    replacementConsent = false;
    try {
      const result = await post<OriginCheck>({
        action: 'kiriof_change_origin_check',
        order_id: action.data.kaOrderId,
        location_id: originLocationId,
        nonce: action.data.nonce,
      });
      originCheck = result;
      const options = result.comparison.available ? result.options : result.replacement_options;
      const current = options.find((option) => courierKey(option) === `${result.comparison.service_code ?? ''}|${result.comparison.service_name ?? ''}`);
      selectedCourierKey = current ? courierKey(current) : (options[0] ? courierKey(options[0]) : '');
    } catch (cause) {
      error = cause instanceof Error ? cause.message : i18n.actionError ?? 'Unable to complete this action.';
    } finally {
      loading = false;
    }
  }

  async function confirmOrigin(): Promise<void> {
    if (!action || action.kind !== 'origin' || !selectedCourier || !canConfirmOrigin) return;
    loading = true;
    error = '';
    try {
      await post({
        action: 'kiriof_change_origin',
        order_id: action.data.kaOrderId,
        location_id: originLocationId,
        courier_service: selectedCourier.service_code,
        courier_service_name: selectedCourier.service_name,
        courier_consent: requiresCourierConsent && replacementConsent ? '1' : '0',
        nonce: action.data.nonce,
      });
      onComplete?.();
      window.location.reload();
    } catch (cause) {
      error = cause instanceof Error ? cause.message : i18n.actionError ?? 'Unable to complete this action.';
      loading = false;
    }
  }

  async function adjustDeficit(): Promise<void> {
    if (!action || action.kind !== 'adjust-deficit' || !codIsValid) return;
    loading = true;
    error = '';
    try {
      await post({
        action: 'kiriof_cod_adjust',
        'data[nonce]': action.data.nonce,
        'data[order_package_id]': action.data.kaOrderId,
        'data[new_total_cod]': String(Math.round(Number(codValue))),
      });
      onComplete?.();
      window.location.reload();
    } catch (cause) {
      error = cause instanceof Error ? cause.message : i18n.actionError ?? 'Unable to complete this action.';
      loading = false;
    }
  }

  async function cancelDeficit(): Promise<void> {
    if (!action || action.kind !== 'cancel-deficit') return;
    loading = true;
    error = '';
    try {
      await post({
        action: 'kiriof_cancel_deficit',
        'data[nonce]': action.data.nonce,
        'data[order_package_id]': action.data.kaOrderId,
      });
      onComplete?.();
      window.location.reload();
    } catch (cause) {
      error = cause instanceof Error ? cause.message : i18n.actionError ?? 'Unable to complete this action.';
      loading = false;
    }
  }
</script>

{#if action?.kind === 'origin'}
  <KiriofDialog
    open={action !== null}
    onOpenChange={(open) => !open && close()}
    class="sm:max-w-xl"
    title={i18n.changeShipmentOrigin ?? i18n.changeOrigin}
    description={i18n.changeOriginDescription ?? 'Choose another active shipment origin, then review the available courier before confirming.'}
    secondaryLabel={i18n.cancel}
    secondaryDisabled={loading}
    primaryLabel={loading ? (i18n.processing ?? 'Processing…') : (i18n.confirm ?? 'Confirm change')}
    primaryDisabled={loading || !canConfirmOrigin}
    onSecondary={close}
    onPrimary={() => void confirmOrigin()}
  >
    <div class="!grid gap-2">
      <div class="!flex !items-center !justify-between gap-2">
        <p class="m-0 text-sm font-medium text-foreground">{i18n.shipmentOrigin ?? 'Shipment origin'}</p>
        <a class="!inline-flex !items-center gap-1 text-xs font-semibold text-primary no-underline" href={locationsUrl}><IconExternalLink />{i18n.manageShipmentLocations ?? 'Manage shipment locations'}</a>
      </div>
      {#if locations.some((location) => location.id !== currentOriginLocationId)}
        <ShipmentLocationCombobox
          value={originLocationId}
          {locations}
          currentLocationId={currentOriginLocationId}
          currentLocation={locations.find((location) => location.id === currentOriginLocationId)}
          disabled={loading}
          placeholder={i18n.selectShipmentOrigin ?? 'Select shipment origin'}
          onChange={(value) => { originLocationId = value; void checkOrigin(); }}
        />
      {:else}
        <p class="m-0 text-sm text-muted-foreground">{i18n.noShipmentOrigins ?? 'No alternate shipment origin is available.'}</p>
      {/if}
    </div>

    {#if loading}<p class="m-0 text-sm text-muted-foreground">{i18n.checkingShipping ?? 'Checking available couriers…'}</p>{/if}
    {#if originCheck}
      <div class="!grid gap-2">
        {#if hasAmountChanged(originCheck.comparison.previous_paid_shipping, originCheck.comparison.new_paid_shipping)}
          {@const shippingIncreased = (originCheck.comparison.new_paid_shipping ?? 0) > (originCheck.comparison.previous_paid_shipping ?? 0)}
          <Alert.Root variant={shippingIncreased ? 'destructive' : 'default'} class={shippingIncreased ? '' : 'border-emerald-200 bg-emerald-50 text-emerald-900'}>
            {#if shippingIncreased}<IconArrowUp />{:else}<IconArrowDown />{/if}
            <Alert.Description class={shippingIncreased ? '' : 'text-emerald-800'}>{originCheck.comparison.label}</Alert.Description>
          </Alert.Root>
        {:else}
          <Alert.Root><Alert.Description>{originCheck.comparison.label}</Alert.Description></Alert.Root>
        {/if}
        {#if originCheck.comparison.is_total_blocked}
          <p class="m-0 text-sm text-destructive">{i18n.originChangeBlocked ?? 'This change cannot be processed because the adjusted order total would be below zero.'}</p>
        {:else}
          <CourierOptionCombobox
            value={selectedCourierKey}
            options={originOptions}
            disabled={loading}
            placeholder={i18n.selectCourier ?? 'Select courier'}
            selectedLabel={courierLabel}
            onChange={(value) => { selectedCourierKey = value; replacementConsent = false; }}
          />
          {#if originCheck.comparison.available}
            <div class="!grid gap-0 text-sm">
              <h3 class="m-0 pb-2 text-base font-semibold text-foreground">{i18n.orderBreakdown ?? 'Order summary'}</h3>
              <div class="!flex !items-center !justify-between gap-3 border-b border-border py-2 text-muted-foreground"><span>{i18n.courier ?? 'Courier'}</span><strong class="text-foreground">{originCheck.comparison.previous_courier ?? originCheck.comparison.new_courier ?? '—'}{#if courierChanged} → {originCheck.comparison.new_courier}{/if}</strong></div>
              <div class="!flex !items-center !justify-between gap-3 border-b border-border py-2 text-muted-foreground"><span>{i18n.orderSubtotal ?? 'Sub Total'}</span><strong class="text-foreground">{formatCurrency(originCheck.comparison.previous_subtotal ?? 0)}</strong></div>
              <div class="!flex !items-center !justify-between gap-3 border-b border-border py-2 text-muted-foreground"><span>{i18n.shipping ?? 'Shipping'}</span><span class="!flex items-center gap-1"><span>{formatCurrency(originCheck.comparison.previous_paid_shipping ?? 0)}</span>{#if hasAmountChanged(originCheck.comparison.previous_paid_shipping, originCheck.comparison.new_paid_shipping)}<strong class={amountTone(originCheck.comparison.previous_paid_shipping, originCheck.comparison.new_paid_shipping)}>→ {formatCurrency(originCheck.comparison.new_paid_shipping ?? 0)}</strong>{/if}</span></div>
              {#if (originCheck.comparison.previous_discount ?? 0) !== 0 || (originCheck.comparison.new_discount ?? 0) !== 0}<div class="!flex !items-center !justify-between gap-3 border-b border-border py-2 text-muted-foreground"><span>{i18n.shippingDiscount ?? 'Shipping discount'}</span><span class="!flex items-center gap-1"><span>{formatCurrency(originCheck.comparison.previous_discount ?? 0)}</span>{#if hasAmountChanged(originCheck.comparison.previous_discount, originCheck.comparison.new_discount)}<strong class={amountTone(originCheck.comparison.previous_discount, originCheck.comparison.new_discount)}>→ {formatCurrency(originCheck.comparison.new_discount ?? 0)}</strong>{/if}</span></div>{/if}
              <div class="!flex !items-center !justify-between gap-3 pt-2"><strong class="text-foreground">{i18n.orderTotal ?? 'Order total'}</strong>{#if originCheck.comparison.is_total_blocked}<strong class="text-destructive">{i18n.blocked ?? 'Blocked'}</strong>{:else}<span class="!flex items-center gap-1"><strong class="text-foreground">{formatCurrency(originCheck.comparison.previous_total ?? 0)}</strong>{#if hasAmountChanged(originCheck.comparison.previous_total, originCheck.comparison.new_total)}<strong class={amountTone(originCheck.comparison.previous_total, originCheck.comparison.new_total)}>→ {formatCurrency(originCheck.comparison.new_total ?? 0)}</strong>{/if}</span>{/if}</div>
            </div>
          {/if}
          {#if requiresCourierConsent}
            <label class="!flex items-start gap-2 text-sm text-muted-foreground"><Checkbox checked={replacementConsent} onCheckedChange={(checked) => (replacementConsent = Boolean(checked))} /><span>{i18n.courierConsent ?? 'I agree to replace the unavailable courier with the selected service.'}</span></label>
          {/if}
        {/if}
      </div>
    {/if}
    {#if error}<p class="text-sm text-destructive" role="alert">{error}</p>{/if}
  </KiriofDialog>
{:else if action?.kind === 'adjust-deficit'}
  <KiriofDialog
    open={action !== null}
    onOpenChange={(open) => !open && close()}
    class="sm:max-w-md"
    title={i18n.adjustDeficit}
    description={i18n.adjustDeficitDescription ?? 'Update the COD value so this shipment can be processed without a deficit.'}
    secondaryLabel={i18n.cancel}
    secondaryDisabled={loading}
    primaryLabel={loading ? (i18n.processing ?? 'Processing…') : (i18n.confirmProcess ?? 'Confirm & process')}
    primaryDisabled={loading || !codIsValid}
    onSecondary={close}
    onPrimary={() => void adjustDeficit()}
  >
    <label class="!grid gap-1.5 text-sm font-medium text-foreground" for="kiriof-cod-value">{i18n.codValue ?? 'COD value'}<KiriofInput id="kiriof-cod-value" type="text" inputmode="numeric" min={minimumCod} max={maximumCod || undefined} bind:value={codValue} formatNumber disabled={loading} /></label>
    <p class="m-0 text-xs text-muted-foreground">{i18n.minimumCod ?? 'Minimum'}: {formatCurrency(minimumCod)}{#if maximumCod} · {i18n.maximumCod ?? 'Maximum'}: {formatCurrency(maximumCod)}{/if}</p>
    <dl class="!grid gap-2 rounded-lg border border-border p-3 text-sm">
      <div class="!flex !items-center !justify-between gap-4 text-muted-foreground"><dt>{i18n.orderSubtotal ?? 'Sub Total'}</dt><dd class="m-0 text-foreground">{formatCurrency(action.data.itemPrice)}</dd></div>
      <div class="!flex !items-center !justify-between gap-4 text-muted-foreground"><dt>{i18n.totalShipping ?? 'Total Shipping'}</dt><dd class="m-0 text-foreground">{formatCurrency(totalShipping)}</dd></div>
      <div class="!flex !items-center !justify-between gap-4 pl-4 text-muted-foreground"><dt>{i18n.shipping ?? 'Shipping'}</dt><dd class="m-0 text-foreground">{formatCurrency(action.data.shippingCost)}</dd></div>
      {#if action.data.insuranceFee > 0}<div class="!flex !items-center !justify-between gap-4 pl-4 text-muted-foreground"><dt>{i18n.insurance ?? 'Insurance'}</dt><dd class="m-0 text-foreground">{formatCurrency(action.data.insuranceFee)}</dd></div>{/if}
      {#if action.data.codFee > 0}<div class="!flex !items-center !justify-between gap-4 pl-4 text-muted-foreground"><dt>{i18n.codFee ?? 'COD Fee'}</dt><dd class="m-0 text-foreground">{formatCurrency(action.data.codFee)}</dd></div>{/if}
      {#if action.data.itemDiscount > 0}<div class="!flex !items-center !justify-between gap-4 text-muted-foreground"><dt>{action.data.itemCoupon || i18n.itemDiscount || 'Item Discount'}</dt><dd class="m-0 text-emerald-700">−{formatCurrency(action.data.itemDiscount)}</dd></div>{/if}
      {#if action.data.shippingDiscount > 0}<div class="!flex !items-center !justify-between gap-4 text-muted-foreground"><dt>{action.data.shippingCoupon || i18n.shippingDiscount || 'Shipping Discount'}</dt><dd class="m-0 text-emerald-700">−{formatCurrency(action.data.shippingDiscount)}</dd></div>{/if}
      <div class="!flex !items-center !justify-between gap-4 border-t border-border pt-2"><dt class="font-semibold text-foreground">{i18n.codPaidByBuyer ?? 'COD Paid By Buyer'}</dt><dd class="m-0 font-semibold text-foreground">{formatCurrency(adjustedCod)}</dd></div>
      <div class="!flex !items-center !justify-between gap-4"><dt class="font-semibold text-foreground">{i18n.estimatedCodPayout ?? 'Estimated COD Payout'}</dt><dd class:!text-destructive={estimatedPayout < 0} class="m-0 font-semibold text-emerald-700">{formatCurrency(estimatedPayout)}</dd></div>
    </dl>
    {#if codValue && !codIsValid}<p class="text-sm text-destructive" role="alert">{i18n.invalidCodValue ?? 'Enter a COD value within the allowed range.'}</p>{/if}
    {#if error}<p class="text-sm text-destructive" role="alert">{error}</p>{/if}
  </KiriofDialog>
{:else if action?.kind === 'cancel-deficit'}
  <KiriofDialog
    open={action !== null}
    onOpenChange={(open) => !open && close()}
    class="sm:max-w-md"
    title={i18n.cancelDeficit ?? 'Cancel deficit order'}
    description={i18n.cancelDeficitDescription ?? 'Are you sure you want to cancel this deficit COD order? This cannot be undone.'}
    secondaryLabel={i18n.cancel}
    secondaryDisabled={loading}
    primaryLabel={loading ? (i18n.processing ?? 'Processing…') : (i18n.cancelDeficit ?? 'Cancel deficit order')}
    primaryVariant="destructive"
    primaryDisabled={loading}
    onSecondary={close}
    onPrimary={() => void cancelDeficit()}
  >
    <div class="!flex items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-foreground"><IconAlertTriangle class="mt-0.5 shrink-0 text-destructive" /><span>{i18n.cancelDeficitWarning ?? 'The shipment and its related order will be cancelled.'}</span></div>
    {#if error}<p class="text-sm text-destructive" role="alert">{error}</p>{/if}
  </KiriofDialog>
{:else if action?.kind === 'cancel'}
  <KiriofDialog
    open={action !== null}
    onOpenChange={(open) => !open && close()}
    class="sm:max-w-md"
    title={i18n.cancelShipment ?? i18n.cancel}
    description={i18n.cancelShipmentDescription ?? 'Provide a reason before cancelling this shipment.'}
    secondaryLabel={i18n.cancel}
    secondaryDisabled={loading}
    primaryLabel={loading ? (i18n.processing ?? 'Processing…') : (i18n.cancelShipment ?? i18n.cancel)}
    primaryVariant="destructive"
    primaryDisabled={loading || cancelReason.trim().length < 4}
    onSecondary={close}
    onPrimary={() => void cancelTransaction()}
  >
    <label class="!grid gap-1.5 text-sm font-medium text-foreground" for="kiriof-cancel-reason">{i18n.cancelReason ?? 'Cancellation reason'}<textarea id="kiriof-cancel-reason" class="min-h-24 w-full rounded-lg border border-border bg-background px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3" bind:value={cancelReason} disabled={loading} maxlength="500"></textarea></label>
    <p class="text-xs text-muted-foreground">{i18n.cancelReasonHint ?? 'Enter at least 4 characters.'}</p>
    {#if cancelReason && cancelReason.trim().length < 4}<p class="text-sm text-destructive" role="alert">{i18n.cancelReasonInvalid ?? 'Enter at least 4 characters.'}</p>{/if}
    {#if error}<p class="text-sm text-destructive" role="alert">{error}</p>{/if}
  </KiriofDialog>
{/if}
