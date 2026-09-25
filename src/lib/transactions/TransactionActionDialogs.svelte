<script lang="ts">
  import { IconAlertTriangle, IconMapPin } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import { Checkbox } from '$lib/components/ui/checkbox';
  import * as Dialog from '$lib/components/ui/dialog';
  import { Input } from '$lib/components/ui/input';

  export type ShipmentLocation = { id: number; name: string; address: string };
  export type TransactionActionData = {
    nonce: string;
    kaOrderId: string;
    currentLocationId: number;
    currentCod: number;
    codMinimum: number;
    codMaximum: number;
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
    comparison: { available?: boolean; label?: string; new_courier?: string; is_total_blocked?: boolean };
    options: CourierOption[];
    replacement_options: CourierOption[];
  };
  type AjaxError = { message?: string };

  let {
    action = $bindable(null),
    locations,
    ajaxUrl,
    i18n,
    onComplete,
  }: {
    action?: TransactionActionDialog | null;
    locations: ShipmentLocation[];
    ajaxUrl: string;
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
  const codIsValid = $derived(Number.isFinite(Number(codValue)) && Number(codValue) >= minimumCod && (!maximumCod || Number(codValue) <= maximumCod));

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
      selectedCourierKey = options[0] ? courierKey(options[0]) : '';
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

<Dialog.Root open={action !== null} onOpenChange={(open) => !open && close()}>
  {#if action?.kind === 'origin'}
    <Dialog.Content class="kiriof-shadcn kiriof-action-dialog sm:max-w-xl">
      <Dialog.Header>
        <Dialog.Title>{i18n.changeShipmentOrigin ?? i18n.changeOrigin}</Dialog.Title>
        <Dialog.Description>{i18n.changeOriginDescription ?? 'Choose another active shipment origin, then review the available courier before confirming.'}</Dialog.Description>
      </Dialog.Header>

      <div class="grid gap-3">
        <p class="text-sm font-medium text-foreground">{i18n.shipmentOrigin ?? 'Shipment origin'}</p>
        <div class="grid gap-2">
          {#each locations as location (location.id)}
            {@const current = location.id === action.data.currentLocationId}
            <button type="button" class="kiriof-action-choice" class:is-selected={originLocationId === String(location.id)} disabled={current || loading} onclick={() => { originLocationId = String(location.id); void checkOrigin(); }}>
              <span class="grid size-8 shrink-0 place-items-center rounded-md bg-muted text-muted-foreground"><IconMapPin /></span>
              <span class="min-w-0 text-left"><strong>{location.name}</strong><small>{location.address}</small></span>
              {#if current}<span class="shrink-0 text-xs text-muted-foreground">{i18n.current ?? 'Current'}</span>{/if}
            </button>
          {:else}
            <p class="text-sm text-muted-foreground">{i18n.noShipmentOrigins ?? 'No alternate shipment origin is available.'}</p>
          {/each}
        </div>
      </div>

      {#if loading}<p class="text-sm text-muted-foreground">{i18n.checkingShipping ?? 'Checking available couriers…'}</p>{/if}
      {#if originCheck}
        <div class="grid gap-3 rounded-lg border border-border p-3">
          <p class="text-sm text-muted-foreground">{originCheck.comparison.label}</p>
          {#if originCheck.comparison.is_total_blocked}
            <p class="text-sm text-destructive">{i18n.originChangeBlocked ?? 'This change cannot be processed because the adjusted order total would be below zero.'}</p>
          {:else}
            <div class="grid gap-2" role="radiogroup" aria-label={i18n.courier ?? 'Courier'}>
              {#each originOptions as option (courierKey(option))}
                <button type="button" class="kiriof-action-choice" class:is-selected={selectedCourierKey === courierKey(option)} onclick={() => { selectedCourierKey = courierKey(option); replacementConsent = false; }} disabled={loading}>
                  <span class="min-w-0 text-left"><strong>{courierLabel(option)}</strong><small>{option.price ?? formatCurrency(option.raw_price ?? 0)}</small></span>
                </button>
              {/each}
            </div>
            {#if requiresCourierConsent}
              <label class="flex items-start gap-2 text-sm text-muted-foreground"><Checkbox checked={replacementConsent} onCheckedChange={(checked) => (replacementConsent = Boolean(checked))} /><span>{i18n.courierConsent ?? 'I agree to replace the unavailable courier with the selected service.'}</span></label>
            {/if}
          {/if}
        </div>
      {/if}
      {#if error}<p class="text-sm text-destructive" role="alert">{error}</p>{/if}

      <Dialog.Footer>
        <Button variant="ghost" onclick={close} disabled={loading}>{i18n.cancel}</Button>
        <Button onclick={() => void confirmOrigin()} disabled={loading || !canConfirmOrigin}>{loading ? (i18n.processing ?? 'Processing…') : (i18n.confirm ?? 'Confirm change')}</Button>
      </Dialog.Footer>
    </Dialog.Content>
  {:else if action?.kind === 'adjust-deficit'}
    <Dialog.Content class="kiriof-shadcn kiriof-action-dialog sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>{i18n.adjustDeficit}</Dialog.Title>
        <Dialog.Description>{i18n.adjustDeficitDescription ?? 'Update the COD value so this shipment can be processed without a deficit.'}</Dialog.Description>
      </Dialog.Header>
      <label class="grid gap-1.5 text-sm font-medium text-foreground" for="kiriof-cod-value">{i18n.codValue ?? 'COD value'}<Input id="kiriof-cod-value" type="number" min={minimumCod} max={maximumCod || undefined} step="1" bind:value={codValue} disabled={loading} /></label>
      <p class="text-xs text-muted-foreground">{i18n.minimumCod ?? 'Minimum'}: {formatCurrency(minimumCod)}{#if maximumCod} · {i18n.maximumCod ?? 'Maximum'}: {formatCurrency(maximumCod)}{/if}</p>
      {#if codValue && !codIsValid}<p class="text-sm text-destructive" role="alert">{i18n.invalidCodValue ?? 'Enter a COD value within the allowed range.'}</p>{/if}
      {#if error}<p class="text-sm text-destructive" role="alert">{error}</p>{/if}
      <Dialog.Footer>
        <Button variant="ghost" onclick={close} disabled={loading}>{i18n.cancel}</Button>
        <Button onclick={() => void adjustDeficit()} disabled={loading || !codIsValid}>{loading ? (i18n.processing ?? 'Processing…') : (i18n.confirmProcess ?? 'Confirm & process')}</Button>
      </Dialog.Footer>
    </Dialog.Content>
  {:else if action?.kind === 'cancel-deficit'}
    <Dialog.Content class="kiriof-shadcn kiriof-action-dialog sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>{i18n.cancelDeficit ?? 'Cancel deficit order'}</Dialog.Title>
        <Dialog.Description>{i18n.cancelDeficitDescription ?? 'Are you sure you want to cancel this deficit COD order? This cannot be undone.'}</Dialog.Description>
      </Dialog.Header>
      <div class="flex items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-foreground"><IconAlertTriangle class="mt-0.5 shrink-0 text-destructive" /><span>{i18n.cancelDeficitWarning ?? 'The shipment and its related order will be cancelled.'}</span></div>
      {#if error}<p class="text-sm text-destructive" role="alert">{error}</p>{/if}
      <Dialog.Footer>
        <Button variant="ghost" onclick={close} disabled={loading}>{i18n.cancel}</Button>
        <Button variant="destructive" onclick={() => void cancelDeficit()} disabled={loading}>{loading ? (i18n.processing ?? 'Processing…') : (i18n.cancelDeficit ?? 'Cancel deficit order')}</Button>
      </Dialog.Footer>
    </Dialog.Content>
  {:else if action?.kind === 'cancel'}
    <Dialog.Content class="kiriof-shadcn kiriof-action-dialog sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>{i18n.cancelShipment ?? i18n.cancel}</Dialog.Title>
        <Dialog.Description>{i18n.cancelShipmentDescription ?? 'Provide a reason before cancelling this shipment.'}</Dialog.Description>
      </Dialog.Header>
      <label class="grid gap-1.5 text-sm font-medium text-foreground" for="kiriof-cancel-reason">{i18n.cancelReason ?? 'Cancellation reason'}<textarea id="kiriof-cancel-reason" class="min-h-24 w-full rounded-lg border border-input bg-background px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3" bind:value={cancelReason} disabled={loading} maxlength="500"></textarea></label>
      <p class="text-xs text-muted-foreground">{i18n.cancelReasonHint ?? 'Enter at least 4 characters.'}</p>
      {#if cancelReason && cancelReason.trim().length < 4}<p class="text-sm text-destructive" role="alert">{i18n.cancelReasonInvalid ?? 'Enter at least 4 characters.'}</p>{/if}
      {#if error}<p class="text-sm text-destructive" role="alert">{error}</p>{/if}
      <Dialog.Footer>
        <Button variant="ghost" onclick={close} disabled={loading}>{i18n.cancel}</Button>
        <Button variant="destructive" onclick={() => void cancelTransaction()} disabled={loading || cancelReason.trim().length < 4}>{loading ? (i18n.processing ?? 'Processing…') : (i18n.cancelShipment ?? i18n.cancel)}</Button>
      </Dialog.Footer>
    </Dialog.Content>
  {/if}
</Dialog.Root>
