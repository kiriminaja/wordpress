<script lang="ts">
  import { onDestroy, onMount } from 'svelte';
  import DotField from '$lib/backgrounds/DotField.svelte';
  import L from 'leaflet';
  import 'leaflet/dist/leaflet.css';
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
  import { Field, FieldDescription, FieldGroup, FieldLabel, FieldSet } from '$lib/components/ui/field';
  import { Input } from '$lib/components/ui/input';
  import { Separator } from '$lib/components/ui/separator';
  import { Switch } from '$lib/components/ui/switch';
  import { Textarea } from '$lib/components/ui/textarea';
  import SubdistrictCombobox from './SubdistrictCombobox.svelte';
  import {
    IconCheck,
    IconChevronLeft,
    IconChevronRight,
    IconExternalLink,
    IconHelp,
    IconLoader2,
    IconPlugConnected,
    IconX,
  } from '@tabler/icons-svelte';
  import type { OnboardingBootstrap, OnboardingCourier, OnboardingStep } from './types';

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
  let prefersReducedMotion = $state(false);

  function getInitialAddress(): Record<string, string> {
    return { ...bootstrap.address.values };
  }

  function getInitialAccount(): OnboardingBootstrap['account'] {
    return structuredClone(bootstrap.account);
  }

  let address = $state(getInitialAddress());
  let account = $state(getInitialAccount());
  let couriers = $state<OnboardingCourier[]>([]);
  let selectedCouriers = $state<Record<string, string>>({});
  let couriersLoading = $state(false);
  let courierLoaded = $state(false);
  let subdistrictQuery = $state('');
  let subdistricts = $state<Area[]>([]);
  let subdistrictLoading = $state(false);
  let mapElement = $state<HTMLDivElement>();
  let map: L.Map | undefined;
  let marker: L.CircleMarker | undefined;
  let searchTimer: ReturnType<typeof setTimeout> | undefined;

  const order: Step[] = ['account', 'address', 'couriers', 'shipping', 'complete'];
  const currentIndex = $derived(order.indexOf(current));
  const accountReady = $derived(Boolean(account.connected || done.account));
  const canSubmitAccount = $derived(Boolean(accountReady || setupKey.trim()));

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

  async function post<T>(action: string, values: Record<string, string> = {}): Promise<T> {
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
    if (!canVisit(step)) {
      setMessage(!done.account ? bootstrap.i18n.accountRequired : bootstrap.i18n.shippingPrerequisite);
      current = !done.account ? 'account' : 'address';
      return;
    }
    if (current === 'address' && step !== 'address' && map) {
      map.remove();
      map = undefined;
    }
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
    } catch (requestError) {
      setMessage(requestError instanceof Error ? requestError.message : bootstrap.i18n.saveFailed);
    } finally {
      busy = false;
    }
  }

  async function disconnect(): Promise<void> {
    if (!window.confirm(bootstrap.i18n.disconnectConfirm)) return;
    busy = true;
    try {
      await post('kiriof_disconnect_integration');
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
      next();
    } catch (requestError) {
      setMessage(requestError instanceof Error ? requestError.message : bootstrap.i18n.saveFailed);
    } finally {
      busy = false;
    }
  }

  async function loadCouriers(): Promise<void> {
    if (!done.account || couriersLoading || courierLoaded) return;
    couriersLoading = true;
    try {
      const result = await post<{ couriers?: OnboardingCourier[]; whitelist_ids?: string[] }>(
        'kiriof_get_courier_whitelist',
      );
      couriers = result.couriers ?? [];
      const selected = new Set(result.whitelist_ids ?? []);
      selectedCouriers = Object.fromEntries(
        couriers
          .filter((courier) => selected.has(courier.code))
          .map((courier) => [courier.code, courier.name]),
      );
      courierLoaded = true;
    } catch (requestError) {
      setMessage(
        requestError instanceof Error ? requestError.message : bootstrap.i18n.networkError,
      );
    } finally {
      couriersLoading = false;
    }
  }

  function isCourierSelected(code: string): boolean {
    return Boolean(selectedCouriers[code]);
  }

  function toggleCourier(courier: OnboardingCourier, checked: boolean): void {
    const nextCouriers = { ...selectedCouriers };
    if (checked) {
      nextCouriers[courier.code] = courier.name;
    } else {
      delete nextCouriers[courier.code];
    }
    selectedCouriers = nextCouriers;
  }

  function setAllCouriers(checked: boolean): void {
    selectedCouriers = checked
      ? Object.fromEntries(couriers.map((courier) => [courier.code, courier.name]))
      : {};
  }

  async function saveCouriers(): Promise<void> {
    const ids = Object.keys(selectedCouriers);
    if (!ids.length) {
      setMessage(bootstrap.i18n.courierRequired);
      return;
    }
    busy = true;
    try {
      await post('kiriof_store_courier_whitelist', {
        whitelist_ids: ids.join(','),
        whitelist_names: ids.map((id) => selectedCouriers[id]).join(','),
      });
      done.couriers = true;
      setMessage(bootstrap.i18n.couriersSaved, true);
      next();
    } catch (requestError) {
      setMessage(requestError instanceof Error ? requestError.message : bootstrap.i18n.saveFailed);
    } finally {
      busy = false;
    }
  }

  async function enableShipping(): Promise<void> {
    if (!done.address || !done.couriers) {
      setMessage(bootstrap.i18n.shippingPrerequisite);
      return;
    }
    busy = true;
    try {
      const result = await post<{ locations_ready?: boolean }>('kiriof_enable_shipping_method');
      done.shipping = true;
      if (!result.locations_ready) {
        return;
      }
      done.shipping = true;
      next();
    } catch (requestError) {
      setMessage(requestError instanceof Error ? requestError.message : bootstrap.i18n.saveFailed);
    } finally {
      busy = false;
    }
  }

  function initMap(): void {
    if (map) {
      map.remove();
      map = undefined;
    }
    if (!mapElement) return;
    const lat = Number(address.origin_latitude) || -6.2088;
    const lng = Number(address.origin_longitude) || 106.8456;
    map = L.map(mapElement).setView([lat, lng], 15);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    marker = L.circleMarker([lat, lng], {
      radius: 8,
      color: '#5c2ecb',
      fillColor: '#5c2ecb',
      fillOpacity: 0.85,
    }).addTo(map);
    const update = (point: L.LatLng) => {
      address.origin_latitude = point.lat.toFixed(7);
      address.origin_longitude = point.lng.toFixed(7);
      address = { ...address };
    };
    const place = (point: L.LatLng) => {
      marker?.setLatLng(point);
      map?.setView(point, 16);
      update(point);
    };
    map.on('click', (event) => place(event.latlng));
    const locate = new L.Control({ position: 'topright' });
    locate.onAdd = () => {
      const button = L.DomUtil.create('button', 'kiriof-onboarding__locate');
      button.type = 'button';
      button.title = bootstrap.i18n.currentLocation;
      button.setAttribute('aria-label', bootstrap.i18n.currentLocation);
      button.innerHTML = '<span aria-hidden="true">⌖</span>';
      L.DomEvent.disableClickPropagation(button);
      L.DomEvent.on(button, 'click', () => {
        if (!navigator.geolocation) {
          setMessage(bootstrap.i18n.currentLocationUnavailable);
          return;
        }
        navigator.geolocation.getCurrentPosition(
          (position) => place(L.latLng(position.coords.latitude, position.coords.longitude)),
          () => setMessage(bootstrap.i18n.currentLocationFailed),
        );
      });
      return button;
    };
    locate.addTo(map);
    update(L.latLng(lat, lng));
    setTimeout(() => map?.invalidateSize(), 150);
  }

  onMount(() => {
    prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (current === 'address') setTimeout(initMap, 50);
    if (current === 'couriers') void loadCouriers();
  });

  onDestroy(() => {
    if (searchTimer) clearTimeout(searchTimer);
    if (map) {
      map.remove();
      map = undefined;
    }
  });
</script>

<div
  class="kiriof-shadcn kiriof-onboarding-app relative z-10 flex h-full max-h-screen w-full flex-col items-center justify-between overflow-hidden box-border p-3 sm:p-4 md:p-6"
>
  <!-- Full Viewport Animated Dither Background -->
  <div class="fixed inset-0 -z-0 pointer-events-none overflow-hidden bg-[#090514]" aria-hidden="true">
    <div
      class="absolute inset-0 bg-[radial-gradient(circle_at_50%_25%,rgba(92,46,203,0.35),rgba(9,5,20,0.98)_75%)]"
    ></div>
    <DotField
      dotRadius={2}
      dotSpacing={17}
      cursorRadius={280}
      bulgeStrength={42}
      glowRadius={210}
      waveAmplitude={1.2}
      gradientFrom="rgba(196, 181, 253, 0.72)"
      gradientTo="rgba(126, 34, 206, 0.38)"
      glowColor="rgba(88, 28, 135, 0.5)"
      disableAnimation={prefersReducedMotion}
      class="absolute inset-0 h-full w-full opacity-90"
    />
  </div>

  <!-- Centered Wizard Wrapper -->
  <div class="relative z-10 my-auto flex w-full max-w-[640px] flex-col items-center justify-center min-h-0">
    <!-- Top Utility Bar -->
    <header class="mb-2 flex shrink-0 w-full items-center justify-between px-1">
      <a
        href={bootstrap.dashboardUrl || '#'}
        class="group inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/95 px-3.5 py-1.5 shadow-md backdrop-blur-md transition-all hover:scale-[1.02] hover:bg-white hover:shadow-lg dark:bg-card/90"
        aria-label="WordPress Dashboard"
      >
        {#if bootstrap.logoUrl}
          <img src={bootstrap.logoUrl} alt="KiriminAja" class="h-6 w-auto object-contain" />
        {:else}
          <span class="text-sm font-bold tracking-tight text-[#5c2ecb]">KiriminAja</span>
        {/if}
      </a>

      <div class="flex items-center gap-2">
        {#if bootstrap.helpUrl}
          <a
            href={bootstrap.helpUrl}
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/20 bg-white/85 text-muted-foreground shadow-sm backdrop-blur-md transition-all hover:bg-white hover:text-foreground dark:bg-card/85"
            aria-label="Need help?"
            title="Need help?"
          >
            <IconHelp class="h-4 w-4" />
          </a>
        {/if}
        {#if bootstrap.dashboardUrl}
          <a
            href={bootstrap.dashboardUrl}
            class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/20 bg-white/85 text-muted-foreground shadow-sm backdrop-blur-md transition-all hover:bg-white hover:text-foreground dark:bg-card/85"
            aria-label="Close setup"
            title="Close setup"
          >
            <IconX class="h-4 w-4" />
          </a>
        {/if}
      </div>
    </header>

    <!-- Centered Wizard Card -->
    <Card
      class="flex flex-col w-full max-h-[calc(100vh-5.5rem)] overflow-hidden rounded-2xl border border-white/40 bg-white/95 shadow-2xl shadow-purple-950/30 backdrop-blur-xl transition-all dark:border-border dark:bg-card/95"
    >
      {#if current !== 'complete'}
        <CardHeader class="shrink-0 border-b border-border/60 bg-muted/10 px-6 pt-5 pb-4">
          <div class="max-w-[540px] space-y-1.5">
            <CardTitle class="text-lg font-semibold leading-tight tracking-[-0.02em] text-foreground sm:text-xl">
              {stepTitle}
            </CardTitle>
            <CardDescription class="max-w-[48ch] text-sm leading-5 text-muted-foreground">
              {stepDescription}
            </CardDescription>
          </div>
        </CardHeader>
      {/if}

      <!-- Feedback Alerts -->
      {#if error}
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
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary">
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
            <div
              bind:this={mapElement}
              class="kiriof-map h-40 sm:h-44 w-full overflow-hidden rounded-lg border border-border"
              aria-label={bootstrap.address.i18n.mapHelp}
            ></div>
            <FieldDescription class="flex items-center justify-between text-xs text-muted-foreground">
              <span>{bootstrap.address.i18n.mapHelp}</span>
              <span class="font-mono text-[11px] text-muted-foreground">
                {address.origin_latitude
                  ? `${Number(address.origin_latitude).toFixed(4)}, ${Number(address.origin_longitude).toFixed(4)}`
                  : ''}
              </span>
            </FieldDescription>
          </div>
        {:else if current === 'couriers'}
          <div class="mb-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Button variant="secondary" size="sm" onclick={() => setAllCouriers(true)}>
                {bootstrap.couriers.i18n.enableAll || 'Enable all'}
              </Button>
              <Button variant="ghost" size="sm" onclick={() => setAllCouriers(false)}>
                {bootstrap.couriers.i18n.disableAll || 'Disable all'}
              </Button>
            </div>
            <Badge variant="secondary">
              {Object.keys(selectedCouriers).length}
              {bootstrap.couriers.i18n.enabled || 'enabled'}
            </Badge>
          </div>

          {#if couriersLoading}
            <div class="flex items-center justify-center gap-2 py-12 text-muted-foreground">
              <IconLoader2 class="h-5 w-5 animate-spin" />
              <span>{bootstrap.couriers.i18n.loading || 'Loading couriers…'}</span>
            </div>
          {:else if couriers.length === 0}
            <div class="py-8 text-center text-muted-foreground">
              <p>{bootstrap.couriers.i18n.empty || 'No courier services are available.'}</p>
            </div>
          {:else}
            <div class="max-h-[320px] space-y-2 overflow-y-auto pr-1">
              {#each couriers as courier (courier.code)}
                {@const selected = isCourierSelected(courier.code)}
                <div
                  class="flex items-center justify-between rounded-xl border p-3.5 transition-all {selected
                    ? 'border-primary/50 bg-primary/5 shadow-xs'
                    : 'border-border bg-card/60 hover:bg-muted/40'}"
                >
                  <div class="space-y-0.5">
                    <div class="text-sm font-semibold text-foreground">{courier.name}</div>
                    {#if courier.type}
                      <div class="text-xs text-muted-foreground">{courier.type}</div>
                    {/if}
                  </div>
                  <Switch
                    checked={selected}
                    onCheckedChange={(checked: boolean) => toggleCourier(courier, checked)}
                    aria-label={`Enable ${courier.name}`}
                  />
                </div>
              {/each}
            </div>
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
          <div class="flex min-h-[300px] flex-col items-center justify-center gap-5 py-10 text-center">
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
                <span>{accountReady ? bootstrap.i18n.continue || 'Continue' : 'Connect account'}</span>
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
            <Button onclick={saveCouriers} disabled={busy || couriersLoading}>
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
              href={bootstrap.dashboardUrl || 'admin.php?page=kiriminaja-konfigurasi'}
              class="no-underline hover:no-underline focus:no-underline"
            >
              Go to KiriminAja
            </Button>
          {/if}
        </div>
      </CardFooter>
    </Card>

    <!-- Bottom Footnote -->
    <footer class="mt-2 shrink-0 text-center text-[11px] font-medium tracking-wide text-white/50">
      KiriminAja Official WooCommerce Extension
    </footer>
  </div>
</div>
