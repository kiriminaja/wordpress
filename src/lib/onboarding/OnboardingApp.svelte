<script lang="ts">
  import { onMount } from 'svelte';
  import L from 'leaflet';
  import 'leaflet/dist/leaflet.css';
  import { Alert, AlertDescription, AlertTitle } from '$lib/components/ui/alert';
  import { Badge } from '$lib/components/ui/badge';
  import { Button } from '$lib/components/ui/button';
  import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '$lib/components/ui/card';
  import { Field, FieldDescription, FieldGroup, FieldLabel, FieldSet } from '$lib/components/ui/field';
  import { Input } from '$lib/components/ui/input';
  import { Separator } from '$lib/components/ui/separator';
  import { Switch } from '$lib/components/ui/switch';
  import { Textarea } from '$lib/components/ui/textarea';
  import type { OnboardingBootstrap, OnboardingCourier } from './types';

  export type OnboardingStep = 'account' | 'address' | 'couriers' | 'shipping' | 'complete';
  type Step = OnboardingStep;
  type Area = { id: string | number; text?: string; label?: string };

  let { bootstrap, steps, initialStep }: { bootstrap: OnboardingBootstrap; steps: Array<{ key: string; label: string; done: boolean }>; initialStep: Step } = $props();
  function getInitialState(): { current: Step; done: Record<string, boolean> } {
    return { current: initialStep, done: Object.fromEntries(steps.map((step) => [step.key, step.done])) };
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
  let address = $state(getInitialAddress());
  let couriers = $state<OnboardingCourier[]>([]);
  let selectedCouriers = $state<Record<string, string>>({});
  let couriersLoading = $state(false);
  let courierLoaded = $state(false);
  let subdistrictQuery = $state('');
  let subdistricts = $state<Area[]>([]);
  let subdistrictLoading = $state(false);
  let showSubdistricts = $state(false);
  let mapElement = $state<HTMLDivElement>();
  let map: L.Map | undefined;
  let marker: L.CircleMarker | undefined;
  let searchTimer: ReturnType<typeof setTimeout> | undefined;

  const order: Step[] = ['account', 'address', 'couriers', 'shipping', 'complete'];
  const currentIndex = $derived(order.indexOf(current));
  const account = $derived(bootstrap.account);
  const complete = $derived(current === 'complete');

  async function post<T>(action: string, values: Record<string, string> = {}): Promise<T> {
    const body = new URLSearchParams({ action, nonce: bootstrap.nonce, 'data[nonce]': bootstrap.nonce });
    Object.entries(values).forEach(([key, value]) => {
      body.set(key, value);
      body.set(`data[${key}]`, value);
    });
    const response = await fetch(bootstrap.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body });
    const payload = (await response.json()) as { success?: boolean; data?: T & { status?: number; message?: string } };
    const raw = payload.data;
    const result = raw && typeof raw === 'object' && 'status' in raw && 'data' in raw
      ? (raw as { data: T }).data
      : raw as T;
    const status = raw && typeof raw === 'object' && 'status' in raw ? Number((raw as { status?: number }).status) : 200;
    if (!response.ok || payload.success === false || !result || status !== 200) {
      const message = raw && typeof raw === 'object' && 'message' in raw ? String((raw as { message?: string }).message ?? '') : '';
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
    if (step === 'shipping') return Boolean(done.address && done.couriers);
    return true;
  }

  function go(step: Step): void {
    if (!canVisit(step)) {
      setMessage(!done.account ? bootstrap.i18n.accountRequired : bootstrap.i18n.shippingPrerequisite);
      current = !done.account ? 'account' : 'address';
      return;
    }
    current = step;
    setMessage('');
    if (step === 'couriers') void loadCouriers();
    if (step === 'address') setTimeout(initMap, 0);
  }

  function next(): void {
    if (current === 'shipping') return;
    const nextStep = order[Math.min(currentIndex + 1, order.length - 1)];
    go(nextStep);
  }

  function previous(): void {
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
      await post('kiriof_store_integration_data', { setup_key: key });
      done.account = true;
      setMessage(bootstrap.i18n.accountConnected, true);
      setupKey = '';
      next();
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
      setMessage(requestError instanceof Error ? requestError.message : bootstrap.i18n.disconnectFailed);
      busy = false;
    }
  }

  function updateAddress(key: string, value: string): void {
    address[key] = value;
  }

  async function searchSubdistrict(value: string): Promise<void> {
    subdistrictQuery = value;
    showSubdistricts = true;
    if (searchTimer) clearTimeout(searchTimer);
    if (value.trim().length < 3) {
      subdistricts = [];
      return;
    }
    searchTimer = setTimeout(async () => {
      subdistrictLoading = true;
      try {
        const body = new URLSearchParams({ action: 'kiriminaja_subdistrict_search', nonce: bootstrap.nonce, term: value, 'data[term]': value, 'data[search]': value });
        const response = await fetch(bootstrap.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body });
        const payload = (await response.json()) as { success?: boolean; data?: Area[] };
        if (!response.ok || payload.success === false) throw new Error(bootstrap.i18n.subdistrictSearchFailed);
        subdistricts = payload.data ?? [];
      } catch (requestError) {
        setMessage(requestError instanceof Error ? requestError.message : bootstrap.i18n.subdistrictSearchFailed);
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
    showSubdistricts = false;
  }

  async function saveAddress(): Promise<void> {
    if (Object.values({ ...address, origin_sub_district_name: address.origin_sub_district_name ?? '' }).some((value) => !String(value ?? '').trim())) {
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
      const result = await post<{ couriers?: OnboardingCourier[]; whitelist_ids?: string[] }>('kiriof_get_courier_whitelist');
      couriers = result.couriers ?? [];
      const selected = new Set(result.whitelist_ids ?? []);
      selectedCouriers = Object.fromEntries(couriers.filter((courier) => selected.has(courier.code)).map((courier) => [courier.code, courier.name]));
      courierLoaded = true;
    } catch (requestError) {
      setMessage(requestError instanceof Error ? requestError.message : bootstrap.i18n.networkError);
    } finally {
      couriersLoading = false;
    }
  }

  function toggleCourier(courier: OnboardingCourier, checked: boolean): void {
    if (checked) selectedCouriers[courier.code] = courier.name;
    else delete selectedCouriers[courier.code];
    selectedCouriers = { ...selectedCouriers };
  }

  function setAllCouriers(checked: boolean): void {
    selectedCouriers = checked ? Object.fromEntries(couriers.map((courier) => [courier.code, courier.name])) : {};
  }

  async function saveCouriers(): Promise<void> {
    const ids = Object.keys(selectedCouriers);
    if (!ids.length) {
      setMessage(bootstrap.i18n.courierRequired);
      return;
    }
    busy = true;
    try {
      await post('kiriof_store_courier_whitelist', { whitelist_ids: ids.join(','), whitelist_names: ids.map((id) => selectedCouriers[id]).join(',') });
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
        setMessage(bootstrap.i18n.shippingLocationsRequired);
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
    if (map || !mapElement) return;
    const lat = Number(address.origin_latitude) || -6.2088;
    const lng = Number(address.origin_longitude) || 106.8456;
    map = L.map(mapElement).setView([lat, lng], 15);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    marker = L.circleMarker([lat, lng], { radius: 9, color: '#5c2ecb', fillColor: '#5c2ecb', fillOpacity: 0.8 }).addTo(map);
    const update = (point: L.LatLng) => {
      address.origin_latitude = point.lat.toFixed(7);
      address.origin_longitude = point.lng.toFixed(7);
      address = { ...address };
    };
    const place = (point: L.LatLng) => { marker?.setLatLng(point); map?.setView(point, 16); update(point); };
    map.on('click', (event) => place(event.latlng));
    const locate = new L.Control({ position: 'topright' });
    locate.onAdd = () => {
      const button = L.DomUtil.create('button', 'kiriof-onboarding__locate');
      button.type = 'button'; button.title = bootstrap.i18n.currentLocation; button.setAttribute('aria-label', bootstrap.i18n.currentLocation); button.innerHTML = '<span aria-hidden="true">⌖</span>';
      L.DomEvent.disableClickPropagation(button);
      L.DomEvent.on(button, 'click', () => {
        if (!navigator.geolocation) { setMessage(bootstrap.i18n.currentLocationUnavailable); return; }
        navigator.geolocation.getCurrentPosition((position) => place(L.latLng(position.coords.latitude, position.coords.longitude)), () => setMessage(bootstrap.i18n.currentLocationFailed));
      });
      return button;
    };
    locate.addTo(map);
    update(L.latLng(lat, lng));
    setTimeout(() => map?.invalidateSize(), 100);
  }

  onMount(() => { if (current === 'address') setTimeout(initMap, 0); });
</script>

<div class="kiriof-shadcn kiriof-onboarding-app">
  <header class="kiriof-app-header">
    <div><p class="kiriof-app-eyebrow">KiriminAja setup</p><h1>Ship with clarity.</h1><p>Connect your store, confirm the pickup origin, and choose the delivery network your customers can use.</p></div>
    <div class="kiriof-app-progress" aria-label="Setup progress">{#each steps as step, index}<button class:is-active={step.key === current} class:is-done={done[step.key]} type="button" onclick={() => go(step.key as Step)} disabled={!canVisit(step.key as Step)}><span>{done[step.key] ? '✓' : index + 1}</span>{step.label}</button>{/each}</div>
  </header>

  {#if error}<Alert variant="destructive"><AlertTitle>Unable to continue</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>{/if}
  {#if success}<Alert><AlertTitle>Saved</AlertTitle><AlertDescription>{success}</AlertDescription></Alert>{/if}

  {#if current === 'account'}
    <Card><CardHeader><CardTitle>{account.title}</CardTitle><CardDescription>{account.description}</CardDescription></CardHeader><CardContent>
      {#if account.connected && account.profile}<div class="kiriof-profile-card"><span class="kiriof-profile-avatar">{account.profile.name.slice(0, 1)}</span><div><strong>{account.profile.name}</strong><span>{account.profile.email}</span></div><Badge>{account.profile.status || 'Connected'}</Badge><Button variant="outline" onclick={disconnect} disabled={busy}>Disconnect</Button></div>
      {:else}<FieldGroup><Field><FieldLabel for="setup-key">{account.i18n.setupKey}</FieldLabel><Input id="setup-key" bind:value={setupKey} placeholder={account.i18n.setupKeyPlaceholder} /><FieldDescription>{account.i18n.findKey} <a href={account.helpUrl} target="_blank" rel="noreferrer">{account.i18n.learnHow}</a></FieldDescription></Field></FieldGroup>{/if}
    </CardContent><CardFooter><Button onclick={saveAccount} disabled={busy}>{busy ? 'Connecting…' : bootstrap.i18n.continue}</Button></CardFooter></Card>
  {:else if current === 'address'}
    <Card><CardHeader><CardTitle>{bootstrap.address.title}</CardTitle><CardDescription>{bootstrap.address.description}</CardDescription></CardHeader><CardContent><FieldSet><FieldGroup><Field><FieldLabel for="origin-name">{bootstrap.address.i18n.senderName}</FieldLabel><Input id="origin-name" bind:value={address.origin_name} /></Field><Field><FieldLabel for="origin-phone">{bootstrap.address.i18n.senderPhone}</FieldLabel><Input id="origin-phone" bind:value={address.origin_phone} /></Field><Field><FieldLabel for="origin-address">{bootstrap.address.i18n.address}</FieldLabel><Textarea id="origin-address" bind:value={address.origin_address} /></Field><Field><FieldLabel for="origin-zip">{bootstrap.address.i18n.zipcode}</FieldLabel><Input id="origin-zip" bind:value={address.origin_zip_code} /></Field><Field><FieldLabel for="subdistrict">{bootstrap.address.i18n.subdistrict}</FieldLabel><Input id="subdistrict" value={subdistrictQuery || address.origin_sub_district_name || ''} placeholder={bootstrap.address.i18n.searchSubdistrict} oninput={(event: Event) => searchSubdistrict((event.currentTarget as HTMLInputElement).value)} onfocus={() => (showSubdistricts = true)} />{#if showSubdistricts && (subdistricts.length || subdistrictLoading)}<div class="kiriof-subdistrict-results" role="listbox">{#if subdistrictLoading}<p>{bootstrap.i18n.subdistrictLoading}</p>{:else}{#each subdistricts as area}<button type="button" role="option" aria-selected={false} onclick={() => selectSubdistrict(area)}>{area.text ?? area.label}</button>{/each}{/if}</div>{/if}</Field></FieldGroup></FieldSet><Separator /><div bind:this={mapElement} class="kiriof-map" aria-label={bootstrap.address.i18n.mapHelp}></div><FieldDescription>{bootstrap.address.i18n.mapHelp}</FieldDescription></CardContent><CardFooter><Button variant="outline" onclick={previous}>Back</Button><Button onclick={saveAddress} disabled={busy}>{busy ? 'Saving…' : bootstrap.i18n.continue}</Button></CardFooter></Card>
  {:else if current === 'couriers'}
    <Card><CardHeader><CardTitle>{bootstrap.couriers.title}</CardTitle><CardDescription>{bootstrap.couriers.description}</CardDescription></CardHeader><CardContent><div class="kiriof-courier-toolbar"><Button variant="outline" size="sm" onclick={() => setAllCouriers(true)}>Enable all</Button><Button variant="outline" size="sm" onclick={() => setAllCouriers(false)}>Disable all</Button><Badge variant="secondary">{Object.keys(selectedCouriers).length} {bootstrap.couriers.i18n.enabled}</Badge></div>{#if couriersLoading}<p>Loading couriers…</p>{:else}<div class="kiriof-courier-list">{#each couriers as courier}<div class="kiriof-courier-row"><div><strong>{courier.name}</strong><span>{courier.type ?? ''}</span></div><Switch checked={Object.hasOwn(selectedCouriers, courier.code)} onCheckedChange={(checked: boolean) => toggleCourier(courier, checked)} aria-label={`Enable ${courier.name}`} /></div>{/each}</div>{/if}</CardContent><CardFooter><Button variant="outline" onclick={previous}>Back</Button><Button onclick={saveCouriers} disabled={busy || couriersLoading}>{busy ? 'Saving…' : bootstrap.i18n.continue}</Button></CardFooter></Card>
  {:else if current === 'shipping'}
    <Card><CardHeader><CardTitle>{bootstrap.shipping.title}</CardTitle><CardDescription>{bootstrap.shipping.description}</CardDescription></CardHeader><CardContent><div class="kiriof-check-list"><div class:is-ready={bootstrap.shipping.shippingReady || done.shipping} class="kiriof-check"><span>{bootstrap.shipping.shippingReady || done.shipping ? '✓' : '○'}</span><div><strong>{bootstrap.shipping.i18n.methodTitle}</strong><p>{bootstrap.shipping.i18n.methodDescription}</p></div></div><div class:is-ready={bootstrap.shipping.locationsReady} class="kiriof-check"><span>{bootstrap.shipping.locationsReady ? '✓' : '○'}</span><div><strong>{bootstrap.shipping.i18n.locationsTitle}</strong><p>{bootstrap.shipping.i18n.locationsDescription}</p></div></div></div><a href={bootstrap.shipping.settingsUrl}>{bootstrap.shipping.i18n.openSettings}</a></CardContent><CardFooter><Button variant="outline" onclick={previous}>Back</Button><Button onclick={enableShipping} disabled={busy}>{busy ? 'Finishing…' : bootstrap.i18n.finish}</Button></CardFooter></Card>
  {:else}
    <Card><CardHeader><CardTitle>Your store is ready to ship</CardTitle><CardDescription>KiriminAja is configured. You can now manage shipments from your WordPress dashboard.</CardDescription></CardHeader><CardFooter><Button href={bootstrap.account.helpUrl}>Go to KiriminAja</Button></CardFooter></Card>
  {/if}
</div>
<style>
  .kiriof-onboarding-app { display: grid; gap: 1rem; max-width: 760px; margin: 0 auto; color: var(--foreground); }
  .kiriof-app-header { display: grid; gap: 1rem; padding: 1.5rem 0 .5rem; }
  .kiriof-app-eyebrow { margin: 0; color: var(--primary); font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
  .kiriof-app-header h1 { margin: .25rem 0; font-size: clamp(1.75rem, 4vw, 2.5rem); letter-spacing: -.04em; }
  .kiriof-app-header p:not(.kiriof-app-eyebrow) { max-width: 58ch; margin: 0; color: var(--muted-foreground); }
  .kiriof-app-progress { display: flex; flex-wrap: wrap; gap: .35rem; }
  .kiriof-app-progress button { display: inline-flex; align-items: center; gap: .4rem; border: 0; border-radius: .5rem; padding: .45rem .65rem; background: transparent; color: var(--muted-foreground); cursor: pointer; }
  .kiriof-app-progress button span { display: grid; place-items: center; width: 1.4rem; height: 1.4rem; border-radius: 999px; background: var(--muted); color: var(--muted-foreground); font-size: .75rem; }
  .kiriof-app-progress button.is-active { background: var(--accent); color: var(--accent-foreground); }
  .kiriof-app-progress button.is-done span { background: var(--primary); color: var(--primary-foreground); }
  .kiriof-profile-card, .kiriof-courier-row, .kiriof-check { display: flex; align-items: center; gap: .75rem; }
  .kiriof-profile-card { flex-wrap: wrap; }
  .kiriof-profile-card > div { display: grid; flex: 1; gap: .2rem; }
  .kiriof-profile-card > div span, .kiriof-courier-row span { color: var(--muted-foreground); font-size: .875rem; }
  .kiriof-profile-avatar { display: grid; place-items: center; width: 2.5rem; height: 2.5rem; border-radius: 999px; background: var(--primary); color: var(--primary-foreground); font-weight: 700; }
  .kiriof-map { min-height: 240px; overflow: hidden; border: 1px solid var(--border); border-radius: var(--radius); }
  .kiriof-subdistrict-results { display: grid; gap: .25rem; max-height: 12rem; overflow: auto; margin-top: .35rem; padding: .35rem; border: 1px solid var(--border); border-radius: var(--radius); background: var(--popover); }
  .kiriof-subdistrict-results button { padding: .5rem; border: 0; border-radius: .35rem; background: transparent; text-align: left; cursor: pointer; }
  .kiriof-subdistrict-results button:hover { background: var(--accent); }
  .kiriof-courier-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-bottom: .75rem; }
  .kiriof-courier-list { display: grid; gap: .5rem; }
  .kiriof-courier-row { justify-content: space-between; padding: .75rem; border: 1px solid var(--border); border-radius: var(--radius); }
  .kiriof-courier-row > div { display: grid; gap: .2rem; }
  .kiriof-check-list { display: grid; gap: .75rem; }
  .kiriof-check { align-items: flex-start; padding: 1rem; border: 1px solid var(--border); border-radius: var(--radius); }
  .kiriof-check > span { color: var(--muted-foreground); font-size: 1.25rem; }
  .kiriof-check.is-ready > span { color: var(--primary); }
  .kiriof-check p { margin: .25rem 0 0; color: var(--muted-foreground); }
</style>
