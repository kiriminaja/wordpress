<script lang="ts">
  import { onDestroy, onMount } from 'svelte';
  import type * as L from 'leaflet';
  import { preloadedLeaflet } from '../transaction-detail/instant-route-map';
  import { resolveMapConfig } from '../../buyer/map/config';
  import { createProviderMapSession } from '../../buyer/map/providers';
  import { loadGoogleMaps } from '../../buyer/map/google-loader';
  import type { MapSession } from '../../buyer/map/types';
  import { Alert, AlertDescription, AlertTitle } from '$lib/components/ui/alert';
  import { Badge } from '$lib/components/ui/badge';
  import { Button } from '$lib/components/ui/button';
  import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
  } from '$lib/components/ui/card';
  import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
    FieldSet,
  } from '$lib/components/ui/field';
  import * as InputGroup from '$lib/components/ui/input-group';
  import { Input } from '$lib/components/ui/input';
  import { Separator } from '$lib/components/ui/separator';
  import CourierServicePicker from '$lib/couriers/CourierServicePicker.svelte';
  import {
    initializeSelection,
    hasSelection,
    selectionPayload,
    setAllServices,
    type CourierPayload,
    type SelectionState,
  } from '$lib/couriers/selection';
  import { Textarea } from '$lib/components/ui/textarea';
  import SubdistrictCombobox from './SubdistrictCombobox.svelte';
  import {
    IconAlertCircle,
    IconCheck,
    IconChevronLeft,
    IconChevronRight,
    IconExternalLink,
    IconHelp,
    IconLoader2,
    IconPlugConnected,
    IconRefresh,
    IconSearch,
    IconX,
  } from '@tabler/icons-svelte';
  import type { OnboardingBootstrap, OnboardingCourier, OnboardingStep } from './types';
  import ActionTooltip from '$lib/ui/ActionTooltip.svelte';

  type Step = OnboardingStep;
  type Area = { id: string | number; text?: string; label?: string };

  let {
    bootstrap,
  }: {
    bootstrap: OnboardingBootstrap;
  } = $props();

  function getInitialState(): { current: Step; done: Record<string, boolean> } {
    return {
      current: bootstrap.initialStep,
      done: Object.fromEntries(bootstrap.steps.map((step) => [step.key, step.done])),
    };
  }

  const initial = getInitialState();
  let current = $state<Step>(initial.current);
  let done = $state<Record<string, boolean>>(initial.done);
  let error = $state('');
  let success = $state('');
  let busy = $state(false);
  let setupKey = $state('');

  function getInitialAddress(): Record<string, string> {
    return { ...bootstrap.address.values };
  }

  function getInitialAccount(): OnboardingBootstrap['account'] {
    return structuredClone(bootstrap.account);
  }

  let address = $state(getInitialAddress());
  let account = $state(getInitialAccount());
  let couriers = $state<OnboardingCourier[]>([]);
  let courierState = $state<SelectionState>({ selection: {}, remembered: {} });
  const prefix = $props.id();
  let courierSearch = $state('');
  let courierLoadError = $state('');
  let couriersLoading = $state(false);
  let courierLoaded = $state(false);
  let courierLoadGeneration = 0;
  let courierLoadController: AbortController | undefined;
  let subdistrictQuery = $state('');
  let subdistricts = $state<Area[]>([]);
  let subdistrictLoading = $state(false);
  let mapElement = $state<HTMLDivElement>();
  let map: L.Map | undefined;
  let marker: L.CircleMarker | undefined;
  let googleSession: MapSession | undefined;
  let mapGeneration = 0;
  let mapReady = $state(false);
  let googleMap = $state(false);
  let searchTimer: ReturnType<typeof setTimeout> | undefined;

  const order: Step[] = ['account', 'address', 'couriers', 'shipping', 'complete'];
  const currentIndex = $derived(order.indexOf(current));
  const accountReady = $derived(Boolean(account.connected || done.account));
  const canSubmitAccount = $derived(Boolean(accountReady || setupKey.trim()));
  const allCouriersEnabled = $derived(
    couriers.length > 0 &&
      couriers.every((courier) =>
        courier.services.every((service) =>
          (courierState.selection[courier.code] ?? []).includes(service.code),
        ),
      ),
  );

  const stepTitle = $derived.by(() => {
    switch (current) {
      case 'account':
        return account.title;
      case 'address':
        return bootstrap.address.title;
      case 'couriers':
        return bootstrap.couriers.title;
      case 'shipping':
        return bootstrap.shipping.title;
      case 'complete':
        return 'Your store is ready to ship';
      default:
        return '';
    }
  });

  const stepDescription = $derived.by(() => {
    switch (current) {
      case 'account':
        return account.description;
      case 'address':
        return bootstrap.address.description;
      case 'couriers':
        return bootstrap.couriers.description;
      case 'shipping':
        return bootstrap.shipping.description;
      case 'complete':
        return 'KiriminAja is configured. You can now manage shipments from your WordPress dashboard.';
      default:
        return '';
    }
  });

  async function post<T>(
    action: string,
    values: Record<string, string> = {},
    signal?: AbortSignal,
  ): Promise<T> {
    const body = new URLSearchParams({
      action,
      nonce: bootstrap.nonce,
      'data[nonce]': bootstrap.nonce,
    });
    Object.entries(values).forEach(([key, value]) => {
      body.set(key, value);
      body.set(`data[${key}]`, value);
    });
    const response = await fetch(bootstrap.ajaxUrl, {
      method: 'POST',
      signal,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body,
    });
    const payload = (await response.json()) as {
      success?: boolean;
      data?: T & { status?: number; message?: string };
    };
    const raw = payload.data;
    const result =
      raw && typeof raw === 'object' && 'status' in raw && 'data' in raw
        ? (raw as { data: T }).data
        : (raw as T);
    const status =
      raw && typeof raw === 'object' && 'status' in raw
        ? Number((raw as { status?: number }).status)
        : 200;
    if (!response.ok || payload.success === false || !result || status !== 200) {
      const message =
        raw && typeof raw === 'object' && 'message' in raw
          ? String((raw as { message?: string }).message ?? '')
          : '';
      throw new Error(message || bootstrap.i18n.networkError);
    }
    return result;
  }

  function setMessage(message: string, isSuccess = false): void {
    error = isSuccess ? '' : message;
    success = isSuccess ? message : '';
  }

  function canVisit(step: Step): boolean {
    if (step === 'account') return true;
    if (!done.account) return false;
    if (step === 'couriers') return Boolean(done.account);
    if (step === 'shipping') return Boolean(done.address && done.couriers);
    if (step === 'complete') return Boolean(done.shipping);
    return true;
  }

  function go(step: Step): void {
    if (busy) return;
    if (!canVisit(step)) {
      setMessage(
        !done.account ? bootstrap.i18n.accountRequired : bootstrap.i18n.shippingPrerequisite,
      );
      current = !done.account ? 'account' : 'address';
      return;
    }
    if (current === 'address' && step !== 'address') disposeMap();
    current = step;
    setMessage('');
    if (step === 'couriers') void loadCouriers();
    if (step === 'address') setTimeout(initMap, 50);
  }

  function next(): void {
    if (current === 'complete') return;
    const nextStep = order[Math.min(currentIndex + 1, order.length - 1)];
    go(nextStep);
  }

  function previous(): void {
    if (currentIndex <= 0) return;
    go(order[Math.max(currentIndex - 1, 0)]);
  }

  async function saveAccount(): Promise<void> {
    if (busy) return;
    const key = setupKey.trim();
    if (done.account && !key) {
      next();
      return;
    }
    if (!key) {
      setMessage('Setup key is required.');
      return;
    }
    busy = true;
    resetCouriers();
    try {
      const result = await post<{
        connected?: boolean;
        profile?: OnboardingBootstrap['account']['profile'];
        profileError?: boolean;
      }>('kiriof_store_integration_data', { setup_key: key });
      account = {
        ...account,
        connected: result.connected ?? true,
        profile: result.profile ?? null,
        profileError: result.profileError ?? !result.profile,
      };
      done.account = true;
      setMessage(bootstrap.i18n.accountConnected, true);
      setupKey = '';
      busy = false;
      next();
    } catch (requestError) {
      setMessage(requestError instanceof Error ? requestError.message : bootstrap.i18n.saveFailed);
    } finally {
      busy = false;
    }
  }

  async function disconnect(): Promise<void> {
    if (busy) return;
    if (!window.confirm(bootstrap.i18n.disconnectConfirm)) return;
    busy = true;
    resetCouriers();
    try {
      await post('kiriof_disconnect_integration');
      done.account = false;
      account = { ...account, connected: false, profile: null };
      window.location.reload();
    } catch (requestError) {
      setMessage(
        requestError instanceof Error ? requestError.message : bootstrap.i18n.disconnectFailed,
      );
      busy = false;
    }
  }

  async function searchSubdistrict(value: string): Promise<void> {
    subdistrictQuery = value;
    if (searchTimer) clearTimeout(searchTimer);
    if (value.trim().length < 3) {
      subdistricts = [];
      return;
    }
    searchTimer = setTimeout(async () => {
      subdistrictLoading = true;
      try {
        const body = new URLSearchParams({
          action: 'kiriminaja_subdistrict_search',
          nonce: bootstrap.nonce,
          term: value,
          'data[term]': value,
          'data[search]': value,
        });
        const response = await fetch(bootstrap.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body,
        });
        const payload = (await response.json()) as { success?: boolean; data?: Area[] };
        if (!response.ok || payload.success === false) {
          throw new Error(bootstrap.i18n.subdistrictSearchFailed);
        }
        subdistricts = payload.data ?? [];
      } catch (requestError) {
        setMessage(
          requestError instanceof Error
            ? requestError.message
            : bootstrap.i18n.subdistrictSearchFailed,
        );
      } finally {
        subdistrictLoading = false;
      }
    }, 250);
  }

  function selectSubdistrict(area: Area): void {
    const id = String(area.id);
    const label = String(area.text ?? area.label ?? '');
    address.origin_sub_district_id = id;
    address.origin_sub_district_name = label;
    subdistrictQuery = label;
  }

  async function saveAddress(): Promise<void> {
    if (busy) return;
    if (
      Object.values({
        ...address,
        origin_sub_district_name: address.origin_sub_district_name ?? '',
      }).some((value) => !String(value ?? '').trim())
    ) {
      setMessage(bootstrap.i18n.shippingAddressRequired);
      return;
    }
    busy = true;
    try {
      await post('kiriof_store_origin_data', address as Record<string, string>);
      done.address = true;
      setMessage(bootstrap.i18n.addressSaved, true);
      busy = false;
      next();
    } catch (requestError) {
      setMessage(requestError instanceof Error ? requestError.message : bootstrap.i18n.saveFailed);
    } finally {
      busy = false;
    }
  }

  function invalidateCourierCompletion(): void {
    done.couriers = false;
    done.shipping = false;
  }

  function resetCouriers(): void {
    courierLoadController?.abort();
    courierLoadGeneration += 1;
    couriers = [];
    courierState = { selection: {}, remembered: {} };
    courierLoaded = false;
    couriersLoading = false;
    courierLoadError = '';
    invalidateCourierCompletion();
  }

  function changeCourierSelection(next: SelectionState): void {
    if (busy || couriersLoading || !courierLoaded || courierLoadError) return;
    courierState = next;
    invalidateCourierCompletion();
    setMessage('');
  }

  async function loadCouriers(): Promise<void> {
    if (busy || !done.account || couriersLoading || courierLoaded) return;
    const generation = ++courierLoadGeneration;
    courierLoadController?.abort();
    courierLoadController = new AbortController();
    couriersLoading = true;
    courierLoadError = '';
    try {
      const result = await post<CourierPayload>(
        'kiriof_get_courier_whitelist',
        {},
        courierLoadController.signal,
      );
      if (generation !== courierLoadGeneration) return;
      if (!Array.isArray(result.couriers)) throw new Error(bootstrap.i18n.networkError);
      const loaded = initializeSelection(result);
      couriers = loaded.couriers;
      courierState = loaded.state;
      courierLoaded = true;
      setMessage('');
    } catch (requestError) {
      if (generation !== courierLoadGeneration) return;
      courierLoaded = false;
      invalidateCourierCompletion();
      courierLoadError =
        requestError instanceof Error ? requestError.message : bootstrap.i18n.networkError;
      setMessage(courierLoadError);
    } finally {
      if (generation === courierLoadGeneration) couriersLoading = false;
    }
  }

  function enableAllCouriers(): void {
    if (!courierLoaded || couriersLoading || busy) return;
    changeCourierSelection(setAllServices(courierState, couriers, true));
  }

  function disableAllCouriers(): void {
    if (!courierLoaded || couriersLoading || busy) return;
    changeCourierSelection(setAllServices(courierState, couriers, false));
  }

  async function saveCouriers(): Promise<void> {
    if (busy || couriersLoading || !courierLoaded || courierLoadError) return;
    if (!hasSelection(courierState.selection)) {
      setMessage(bootstrap.i18n.courierRequired);
      return;
    }
    busy = true;
    try {
      await post(
        'kiriof_store_courier_whitelist',
        selectionPayload(courierState.selection, couriers),
      );
      done.couriers = true;
      setMessage(bootstrap.i18n.couriersSaved, true);
      busy = false;
      next();
    } catch (requestError) {
      setMessage(requestError instanceof Error ? requestError.message : bootstrap.i18n.saveFailed);
    } finally {
      busy = false;
    }
  }

  async function enableShipping(): Promise<void> {
    if (busy) return;
    if (!done.address || !done.couriers) {
      setMessage(bootstrap.i18n.shippingPrerequisite);
      return;
    }
    busy = true;
    try {
      const result = await post<{ locations_ready?: boolean }>('kiriof_enable_shipping_method');
      if (!result.locations_ready) {
        done.shipping = false;
        setMessage(bootstrap.i18n.shippingLocationsRequired);
        return;
      }
      done.shipping = true;
      busy = false;
      next();
    } catch (requestError) {
      setMessage(requestError instanceof Error ? requestError.message : bootstrap.i18n.saveFailed);
    } finally {
      busy = false;
    }
  }

  function disposeMap(): void {
    mapGeneration++;
    mapReady = false;
    googleSession?.dispose();
    googleSession = undefined;
    map?.off();
    map?.remove();
    map = undefined;
    marker = undefined;
  }

  function locate(): void {
    if (googleSession) { googleSession.locate(); return; }
    if (!navigator.geolocation) {
      setMessage(bootstrap.i18n.currentLocationUnavailable);
      return;
    }
    const generation = mapGeneration;
    navigator.geolocation.getCurrentPosition(
      (position) => {
        if (generation !== mapGeneration || !map) return;
        const point = { lat: position.coords.latitude, lng: position.coords.longitude };
        marker?.setLatLng(point);
        map.setView(point, 16);
        address.origin_latitude = point.lat.toFixed(7);
        address.origin_longitude = point.lng.toFixed(7);
        address = { ...address };
      },
      () => { if (generation === mapGeneration) setMessage(bootstrap.i18n.currentLocationFailed); },
    );
  }

  async function initMap(): Promise<void> {
    disposeMap();
    if (!mapElement || current !== 'address') return;
    const generation = mapGeneration;
    const element = mapElement;
    const latValue = Number(address.origin_latitude);
    const lngValue = Number(address.origin_longitude);
    const lat = address.origin_latitude && Number.isFinite(latValue) ? latValue : -6.2088;
    const lng = address.origin_longitude && Number.isFinite(lngValue) ? lngValue : 106.8456;
    const savedPoint = address.origin_latitude && address.origin_longitude && Number.isFinite(latValue) && Number.isFinite(lngValue) && Math.abs(latValue) <= 90 && Math.abs(lngValue) <= 180
      ? { latitude: latValue, longitude: lngValue } : null;
    try {
      if (bootstrap.map?.enabled === false) throw new Error('Map unavailable');
      const config = resolveMapConfig(bootstrap.map);
      googleMap = config.provider === 'google';
      if (config.provider === 'google') {
        const google = await loadGoogleMaps(config.apiKey);
        if (generation !== mapGeneration || element !== mapElement || current !== 'address') return;
        const session = await createProviderMapSession({
          ...config,
          google, node: element, defaultCenter: [lat, lng],
          initial: savedPoint,
          geolocation: navigator.geolocation,
          onSelect: (point) => {
            if (generation !== mapGeneration || !point) return false;
            address.origin_latitude = point.latitude;
            address.origin_longitude = point.longitude;
            address = { ...address };
          },
          onError: (code) => {
            if (generation === mapGeneration) setMessage(code === 'permission'
              ? bootstrap.i18n.currentLocationUnavailable
              : code === 'location' ? bootstrap.i18n.currentLocationFailed : bootstrap.i18n.networkError);
          },
        });
        if (generation !== mapGeneration || element !== mapElement || current !== 'address') { session.dispose(); return; }
        googleSession = session;
        mapReady = session.isAvailable();
        return;
      }
      const L = preloadedLeaflet();
      if (!L) throw new Error('Map unavailable');
      map = L.map(element).setView([lat, lng], 15);
      L.tileLayer(config.tiles, { maxZoom: 19, attribution: config.attribution }).addTo(map);
      marker = L.circleMarker([lat, lng], {
        radius: 8, color: 'var(--primary)', fillColor: 'var(--primary)', fillOpacity: 0.85,
      }).addTo(map);
      map.on('click', (event) => {
        if (generation !== mapGeneration) return;
        marker?.setLatLng(event.latlng);
        map?.setView(event.latlng, 16);
        address.origin_latitude = event.latlng.lat.toFixed(7);
        address.origin_longitude = event.latlng.lng.toFixed(7);
        address = { ...address };
      });
      address.origin_latitude = lat.toFixed(7);
      address.origin_longitude = lng.toFixed(7);
      address = { ...address };
      mapReady = true;
      setTimeout(() => { if (generation === mapGeneration) map?.invalidateSize(); }, 150);
    } catch {
      if (generation === mapGeneration) setMessage(bootstrap.i18n.networkError);
    }
  }

  onMount(() => {
    if (current === 'address') setTimeout(initMap, 50);
    if (current === 'couriers') void loadCouriers();
  });

  onDestroy(() => {
    courierLoadController?.abort();
    courierLoadGeneration += 1;
    if (searchTimer) clearTimeout(searchTimer);
    disposeMap();
  });
</script>

<div
  class="kiriof-shadcn kiriof-onboarding-app relative flex h-full max-h-screen w-full flex-col items-center justify-between overflow-hidden box-border p-3 sm:p-4 md:p-6"
>
  <div
    class="kiriof-onboarding-background fixed inset-0 pointer-events-none"
    aria-hidden="true"
  ></div>

  <div class="absolute right-3 top-3 z-20 flex items-center gap-2 sm:right-4 sm:top-4">
    {#if bootstrap.helpUrl}
      <ActionTooltip label="Need help?">
        <Button
          variant="outline"
          size="icon"
          aria-label="Need help?"
          onclick={() => window.open(bootstrap.helpUrl, '_blank', 'noopener,noreferrer')}
        >
          <IconHelp data-icon="inline-start" />
        </Button>
      </ActionTooltip>
    {/if}
    {#if bootstrap.dashboardUrl}
      <ActionTooltip label="Close setup">
        <Button
          variant="outline"
          size="icon"
          aria-label="Close setup"
          disabled={busy}
          onclick={() => window.location.assign(bootstrap.dashboardUrl || 'admin.php')}
        >
          <IconX data-icon="inline-start" />
        </Button>
      </ActionTooltip>
    {/if}
  </div>

  <div
    class="relative z-10 my-auto flex w-full max-w-[640px] flex-col items-center justify-center min-h-0"
  >
    <Card
      class="flex flex-col w-full max-h-[calc(100dvh-7rem)] overflow-hidden rounded-xl border border-border bg-card shadow-xl"
    >
      {#if current !== 'complete'}
        <CardHeader class="shrink-0 border-b border-border/60 bg-muted/10 px-6 pt-5 pb-4">
          <div class="max-w-[540px] space-y-1.5">
            <CardTitle
              class="text-lg font-semibold leading-tight tracking-[-0.02em] text-foreground sm:text-xl"
            >
              {stepTitle}
            </CardTitle>
            <CardDescription class="max-w-[48ch] text-sm leading-5 text-muted-foreground">
              {stepDescription}
            </CardDescription>
          </div>
        </CardHeader>
      {/if}

      <!-- Feedback Alerts -->
      {#if error && !(current === 'couriers' && courierLoadError)}
        <div class="shrink-0 px-6 pb-2">
          <Alert variant="destructive">
            <AlertTitle>Unable to continue</AlertTitle>
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        </div>
      {/if}
      {#if success}
        <div class="shrink-0 px-6 pb-2">
          <Alert>
            <AlertTitle>Saved</AlertTitle>
            <AlertDescription>{success}</AlertDescription>
          </Alert>
        </div>
      {/if}

      <!-- Step Content Panels (scrollable internally if viewport is small) -->
      <CardContent class="flex-1 min-h-0 overflow-y-auto px-6 py-3">
        {#if current === 'account'}
          {#if accountReady}
            <div
              class="flex flex-col justify-between gap-4 rounded-xl border border-primary/25 bg-primary/5 p-4 sm:flex-row sm:items-center"
            >
              <div class="flex items-center gap-3">
                <div
                  class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary"
                >
                  <IconPlugConnected class="h-5 w-5" />
                </div>
                <div class="space-y-1">
                  <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <span>{account.profile?.name || 'KiriminAja account connected'}</span>
                    <Badge class="bg-primary/10 text-primary hover:bg-primary/15">Connected</Badge>
                  </div>
                  <div class="text-xs leading-5 text-muted-foreground">
                    {#if account.profile?.email}
                      {account.profile.email}
                    {:else if account.profileError}
                      The account is connected. Profile details are temporarily unavailable.
                    {:else}
                      Your setup key is active and this store can continue onboarding.
                    {/if}
                  </div>
                </div>
              </div>
              <Button
                variant="destructive"
                size="sm"
                onclick={disconnect}
                disabled={busy}
                class="shrink-0"
              >
                {account.i18n.disconnect}
              </Button>
            </div>
          {:else}
            <FieldGroup>
              <Field>
                <FieldLabel for="setup-key">{account.i18n.setupKey}</FieldLabel>
                <Input
                  id="setup-key"
                  bind:value={setupKey}
                  placeholder={account.i18n.setupKeyPlaceholder}
                  autocomplete="off"
                  spellcheck="false"
                />
                <FieldDescription class="text-xs">
                  {account.i18n.findKey}
                  <a
                    href={account.helpUrl}
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex items-center gap-0.5 text-primary hover:underline"
                  >
                    {account.i18n.learnHow}
                    <IconExternalLink class="inline h-3 w-3" />
                  </a>
                </FieldDescription>
              </Field>
            </FieldGroup>
          {/if}
        {:else if current === 'address'}
          <FieldSet>
            <FieldGroup class="space-y-2.5">
              <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                <Field>
                  <FieldLabel for="origin-name">{bootstrap.address.i18n.senderName}</FieldLabel>
                  <Input id="origin-name" bind:value={address.origin_name} />
                </Field>
                <Field>
                  <FieldLabel for="origin-phone">{bootstrap.address.i18n.senderPhone}</FieldLabel>
                  <Input id="origin-phone" bind:value={address.origin_phone} />
                </Field>
              </div>

              <Field>
                <FieldLabel for="origin-address">{bootstrap.address.i18n.address}</FieldLabel>
                <Textarea
                  id="origin-address"
                  bind:value={address.origin_address}
                  rows={3}
                  class="min-h-20 resize-none"
                />
              </Field>

              <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                <Field>
                  <FieldLabel for="origin-zip">{bootstrap.address.i18n.zipcode}</FieldLabel>
                  <Input id="origin-zip" bind:value={address.origin_zip_code} />
                </Field>
                <Field>
                  <FieldLabel>{bootstrap.address.i18n.subdistrict}</FieldLabel>
                  <SubdistrictCombobox
                    value={subdistrictQuery || address.origin_sub_district_name || ''}
                    areas={subdistricts}
                    loading={subdistrictLoading}
                    placeholder={bootstrap.address.i18n.searchSubdistrict}
                    loadingText={bootstrap.i18n.subdistrictLoading}
                    noResultsText={bootstrap.i18n.subdistrictNoResults}
                    typeMoreText={bootstrap.i18n.subdistrictTypeMore}
                    onSearch={searchSubdistrict}
                    onSelect={selectSubdistrict}
                  />
                </Field>
              </div>
            </FieldGroup>
          </FieldSet>

          <Separator class="my-3" />

          <div class="space-y-1.5">
            <div class="relative isolate overflow-hidden rounded-lg border border-border">
              <!-- svelte-ignore a11y_no_noninteractive_tabindex (Map keyboard controls.) -->
              <div bind:this={mapElement} class="kiriof-map h-40 sm:h-44 w-full" role="region" tabindex="0" aria-label={bootstrap.address.i18n.mapHelp}></div>
              {#if googleMap && mapReady}
                <span class="pointer-events-none absolute left-1/2 top-1/2 z-10 -translate-x-1/2 -translate-y-1/2 text-2xl text-primary" aria-hidden="true">●</span>
              {/if}
              <button type="button" class="absolute right-2 top-2 z-10 flex size-9 items-center justify-center rounded-md border border-border bg-background text-xl text-foreground shadow-sm" aria-label={bootstrap.i18n.currentLocation} disabled={!mapReady} onclick={locate}><span aria-hidden="true">⌖</span></button>
            </div>
            <FieldDescription
              class="flex items-center justify-between text-xs text-muted-foreground"
            >
              <span>{bootstrap.address.i18n.mapHelp}</span>
              <span class="font-mono text-[11px] text-muted-foreground">
                {address.origin_latitude
                  ? `${Number(address.origin_latitude).toFixed(4)}, ${Number(address.origin_longitude).toFixed(4)}`
                  : ''}
              </span>
            </FieldDescription>
          </div>
        {:else if current === 'couriers'}
          <div class="mb-4 flex min-w-0 flex-wrap items-center gap-2">
            <InputGroup.Root class="!h-8 !w-full !min-w-0 !bg-background sm:!w-auto sm:!flex-1">
              <InputGroup.Addon align="inline-start"><IconSearch class="size-4" aria-hidden="true" /></InputGroup.Addon>
              <InputGroup.Input
                id={`${prefix}-courier-search`}
                type="search"
                bind:value={courierSearch}
                aria-label={bootstrap.couriers.i18n.searchCouriers ?? 'Search couriers or services'}
                placeholder={bootstrap.couriers.i18n.searchCouriers ?? 'Search couriers or services'}
              />
            </InputGroup.Root>
            <Button
              variant="secondary"
              size="sm"
              disabled={busy ||
                couriersLoading ||
                !courierLoaded ||
                couriers.length === 0 ||
                allCouriersEnabled}
              onclick={enableAllCouriers}
            >
              <IconCheck class="mr-1.5 h-4 w-4" aria-hidden="true" />
              {bootstrap.couriers.i18n.enableAll || 'Enable all'}
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={busy ||
                couriersLoading ||
                !courierLoaded ||
                !hasSelection(courierState.selection)}
              onclick={disableAllCouriers}
            >
              <IconX class="mr-1.5 h-4 w-4" aria-hidden="true" />
              {bootstrap.couriers.i18n.disableAll || 'Disable all'}
            </Button>
          </div>

          {#if couriersLoading}
            <div
              class="flex min-h-40 flex-col items-center justify-center gap-3 text-sm text-muted-foreground"
              role="status"
              aria-live="polite"
            >
              <IconLoader2 class="h-6 w-6 animate-spin text-primary" aria-hidden="true" />
              <span>{bootstrap.couriers.i18n.loading || 'Loading couriers…'}</span>
            </div>
          {:else if courierLoadError}
            <Alert variant="destructive">
              <IconAlertCircle aria-hidden="true" />
              <AlertDescription>{courierLoadError}</AlertDescription>
              <Button
                variant="outline"
                size="sm"
                class="mt-3"
                onclick={loadCouriers}
                disabled={busy}
              >
                <IconRefresh class="mr-1.5 h-4 w-4" aria-hidden="true" />
                {bootstrap.couriers.i18n.retry || 'Retry'}
              </Button>
            </Alert>
          {:else if couriers.length === 0}
            <div class="py-8 text-center text-sm text-muted-foreground">
              <p>{bootstrap.couriers.i18n.empty || 'No courier services are available.'}</p>
            </div>
          {:else}
            <CourierServicePicker
              {couriers}
              compact
              bind:search={courierSearch}
              showSearch={false}
              state={courierState}
              i18n={bootstrap.couriers.i18n}
              disabled={busy || couriersLoading || !courierLoaded}
              onChange={changeCourierSelection}
            />
            <p class="mt-3 text-xs leading-5 text-muted-foreground">
              {bootstrap.couriers.i18n.onboardingSaveHint ||
                'Your selection is saved when you continue.'}
            </p>
          {/if}
        {:else if current === 'shipping'}
          <div class="space-y-3">
            <div class="flex items-start gap-3 rounded-lg border border-border bg-card/60 p-4">
              <div
                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full {bootstrap
                  .shipping.shippingReady || done.shipping
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-muted text-muted-foreground'}"
              >
                {#if bootstrap.shipping.shippingReady || done.shipping}
                  <IconCheck class="h-3.5 w-3.5 stroke-[2.5]" />
                {:else}
                  <span class="text-xs">○</span>
                {/if}
              </div>
              <div class="space-y-1">
                <div class="text-sm font-medium text-foreground">
                  {bootstrap.shipping.i18n.methodTitle}
                </div>
                <p class="text-xs leading-relaxed text-muted-foreground">
                  {bootstrap.shipping.i18n.methodDescription}
                </p>
              </div>
            </div>

            <div class="flex items-start gap-3 rounded-lg border border-border bg-card/60 p-4">
              <div
                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full {bootstrap
                  .shipping.locationsReady
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-muted text-muted-foreground'}"
              >
                {#if bootstrap.shipping.locationsReady}
                  <IconCheck class="h-3.5 w-3.5 stroke-[2.5]" />
                {:else}
                  <span class="text-xs">○</span>
                {/if}
              </div>
              <div class="space-y-1">
                <div class="text-sm font-medium text-foreground">
                  {bootstrap.shipping.i18n.locationsTitle}
                </div>
                <p class="text-xs leading-relaxed text-muted-foreground">
                  {bootstrap.shipping.i18n.locationsDescription}
                </p>
              </div>
            </div>
          </div>

          <div class="pt-2">
            <a
              href={bootstrap.shipping.settingsUrl || bootstrap.shippingUrl}
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
            >
              <span>{bootstrap.shipping.i18n.openSettings}</span>
              <IconExternalLink class="h-3.5 w-3.5" />
            </a>
          </div>
        {:else}
          <div
            class="flex min-h-[300px] flex-col items-center justify-center gap-5 py-10 text-center"
          >
            <div
              class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary"
            >
              <IconCheck class="h-8 w-8 stroke-[2.5]" />
            </div>
            <p class="m-0 max-w-md text-sm leading-6 text-muted-foreground">
              {stepDescription}
            </p>
          </div>
        {/if}
      </CardContent>

      <!-- Navigation Footer -->
      <CardFooter
        class="shrink-0 flex items-center justify-between rounded-b-2xl border-t border-border/60 bg-muted/20 px-6 py-3.5"
      >
        <div>
          {#if currentIndex > 0 && current !== 'complete'}
            <Button variant="outline" onclick={previous} disabled={busy}>
              <IconChevronLeft class="mr-1 h-4 w-4" />
              <span>{bootstrap.i18n.back || 'Back'}</span>
            </Button>
          {/if}
        </div>

        <div>
          {#if current === 'account'}
            <Button onclick={saveAccount} disabled={busy || !canSubmitAccount}>
              {#if busy}
                <IconLoader2 class="mr-1.5 h-4 w-4 animate-spin" />
                <span>Connecting…</span>
              {:else}
                <span
                  >{accountReady ? bootstrap.i18n.continue || 'Continue' : 'Connect account'}</span
                >
                <IconChevronRight class="ml-1 h-4 w-4" />
              {/if}
            </Button>
          {:else if current === 'address'}
            <Button onclick={saveAddress} disabled={busy}>
              {#if busy}
                <IconLoader2 class="mr-1.5 h-4 w-4 animate-spin" />
                <span>Saving…</span>
              {:else}
                <span>{bootstrap.i18n.continue || 'Continue'}</span>
                <IconChevronRight class="ml-1 h-4 w-4" />
              {/if}
            </Button>
          {:else if current === 'couriers'}
            <Button
              onclick={saveCouriers}
              disabled={busy ||
                couriersLoading ||
                !courierLoaded ||
                Boolean(courierLoadError) ||
                !hasSelection(courierState.selection)}
            >
              {#if busy}
                <IconLoader2 class="mr-1.5 h-4 w-4 animate-spin" />
                <span>Saving…</span>
              {:else}
                <span>{bootstrap.i18n.continue || 'Continue'}</span>
                <IconChevronRight class="ml-1 h-4 w-4" />
              {/if}
            </Button>
          {:else if current === 'shipping'}
            <Button onclick={enableShipping} disabled={busy}>
              {#if busy}
                <IconLoader2 class="mr-1.5 h-4 w-4 animate-spin" />
                <span>Finishing…</span>
              {:else}
                <span>{bootstrap.i18n.finish || 'Finish setup'}</span>
                <IconChevronRight class="ml-1 h-4 w-4" />
              {/if}
            </Button>
          {:else}
            <Button
              href={bootstrap.dashboardUrl || 'admin.php?page=kiriminaja-setting'}
              class="no-underline hover:no-underline focus:no-underline"
            >
              Go to KiriminAja
            </Button>
          {/if}
        </div>
      </CardFooter>
    </Card>
  </div>
</div>
