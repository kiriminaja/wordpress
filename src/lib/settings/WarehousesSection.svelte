<script lang="ts">
  import { onDestroy, tick, untrack } from 'svelte';
  import Toolbar from '$lib/ui/Toolbar.svelte';
  import ActionTooltip from '$lib/ui/ActionTooltip.svelte';
  import { navigateSettings } from './navigation';
  import { Button } from '$lib/components/ui/button';
  import * as Table from '$lib/components/ui/table';
  import * as Empty from '$lib/components/ui/empty';
  import * as Dialog from '$lib/components/ui/dialog';
  import * as AlertDialog from '$lib/components/ui/alert-dialog';
  import * as Alert from '$lib/components/ui/alert';
  import * as Select from '$lib/components/ui/select';
  import { Badge } from '$lib/components/ui/badge';
  import { Spinner } from '$lib/components/ui/spinner';
  import * as Field from '$lib/components/ui/field';
  import { Input } from '$lib/components/ui/input';
  import { Textarea } from '$lib/components/ui/textarea';
  import { Switch } from '$lib/components/ui/switch';
  import { IconBuildingWarehouse, IconPlus, IconPencil, IconTrash } from '@tabler/icons-svelte';
  import SubdistrictCombobox from '$lib/onboarding/SubdistrictCombobox.svelte';
  import WarehousePin from './WarehousePin.svelte';
  import { normalizePoint } from '../../buyer/map/leaflet';
  import type { WarehousesBootstrap, WarehouseLocation, WarehouseArea } from './warehouses-types';

  let { bootstrap }: { bootstrap: WarehousesBootstrap } = $props();
  const i18n = $derived(bootstrap.i18n);
  let locations = $state<WarehouseLocation[]>(untrack(() => bootstrap.locations.map(row => ({ ...row }))));
  const customCount = $derived(locations.filter(row => !row.is_default).length);
  const canCreate = $derived(locations.length === 0 || customCount < bootstrap.customLimit);
  let open = $state(false);
  let deleting = $state(false);
  let deleteRow = $state<WarehouseLocation | null>(null);
  let busy = $state(false);
  let pendingToggle = $state<number | null>(null);
  let error = $state('');
  let formError = $state('');
  let deleteError = $state('');
  let submitted = $state(false);
  let draft = $state(blank());
  let returnFocus: HTMLElement | null = null;
  let addButton = $state<HTMLButtonElement>(null!);
  let areas = $state<WarehouseArea[]>([]);
  let searching = $state(false);
  let searchTimer: ReturnType<typeof setTimeout> | undefined;
  let searchController: AbortController | undefined;
  let searchGeneration = 0;
  let alive = true;
  const controllers = new Set<AbortController>();
  let searchError = $state('');
  const countryStates = $derived(bootstrap.countryStates ?? []);
  // WooCommerce supplies localized labels; never render a stored country/state code as copy.
  const countryLabel = $derived(countryStates.find(option => option.value === draft.country_state)?.label ?? i18n.countryState);
  const consumedEditing = new WeakSet<WarehousesBootstrap>();

  function blank(): WarehouseLocation {
    return { id: 0, name: '', sender_name: '', sender_phone: '', address: '', address_2: '',
      city: '', country_state: 'ID', zip: '', district: '', district_label: '', lat: '', lng: '',
      is_active: true, is_default: false };
  }
  function invalid(key: keyof WarehouseLocation) {
    return submitted && (key === 'district' ? !/^[1-9][0-9]*$/.test(draft.district) : !String(draft[key]).trim());
  }
  function stopSearch() {
    searchGeneration++; searchController?.abort();
    if (searchTimer) clearTimeout(searchTimer);
    searching = false;
  }
  function edit(row?: WarehouseLocation) {
    if (!alive || busy || pendingToggle !== null || (!row && !canCreate)) return;
    returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    stopSearch(); areas = []; submitted = false; formError = ''; searchError = '';
    draft = row ? { ...row } : blank(); open = true;
  }
  function closeForm(next: boolean) {
    if (busy) return;
    open = next;
    if (!next) stopSearch();
  }
  function restoreFocus(event: Event) {
    event.preventDefault();
    void tick().then(() => {
      if (!alive) return;
      (returnFocus?.isConnected ? returnFocus : addButton)?.focus();
    });
  }
  type MutationResult = { status: 200; id: number };
  type SaveResult = MutationResult & { location: WarehouseLocation };
  type ToggleResult = MutationResult & { is_active: boolean };
  function record(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
  }
  function positiveId(value: unknown): value is number {
    return typeof value === 'number' && Number.isSafeInteger(value) && value > 0;
  }
  function mutationResult(value: unknown): value is MutationResult {
    return record(value) && value.status === 200 && positiveId(value.id);
  }
  function warehouseLocation(value: unknown): value is WarehouseLocation {
    if (!record(value) || !positiveId(value.id)) return false;
    const strings = ['name', 'sender_name', 'sender_phone', 'address', 'address_2', 'city', 'country_state', 'zip', 'district', 'district_label', 'lat', 'lng'] as const;
    return strings.every(key => typeof value[key] === 'string') &&
      typeof value.is_active === 'boolean' && typeof value.is_default === 'boolean';
  }
  function saveResult(value: unknown): value is SaveResult {
    return mutationResult(value) && 'location' in value && warehouseLocation(value.location) && value.id === value.location.id;
  }
  function toggleResult(value: unknown): value is ToggleResult {
    return mutationResult(value) && 'is_active' in value && typeof value.is_active === 'boolean';
  }
  async function post(action: string, data: Record<string, string>): Promise<unknown> {
    const controller = new AbortController(); controllers.add(controller);
    try {
      const body = new URLSearchParams({ action, 'data[nonce]': bootstrap.nonce });
      for (const [key, value] of Object.entries(data)) body.set(`data[${key}]`, value);
      const response = await fetch(bootstrap.ajaxUrl, { method: 'POST', credentials: 'same-origin', body, signal: controller.signal });
      const payload: unknown = await response.json();
      if (!response.ok || !record(payload) || payload.success !== true) {
        const message = record(payload) && record(payload.data) && typeof payload.data.message === 'string' ? payload.data.message : i18n.saveFailed;
        throw new Error(message);
      }
      return payload.data;
    } finally { controllers.delete(controller); }
  }
  async function save(event: SubmitEvent) {
    event.preventDefault();
    if (!alive || busy) return;
    stopSearch();
    submitted = true; formError = '';
    if (!draft.name.trim() || !draft.sender_phone.trim() || !draft.address.trim() ||
      !/^[1-9][0-9]*$/.test(draft.district) || !draft.zip.trim() || !normalizePoint(draft.lat, draft.lng)) {
      formError = i18n.saveFailed; return;
    }
    if (!draft.id && !canCreate) { formError = i18n.limitReached; return; }
    const editingId = draft.id;
    busy = true;
    try {
      const keys = ['name', 'sender_phone', 'address', 'address_2', 'city', 'country_state', 'zip', 'district', 'district_label', 'lat', 'lng'] as const;
      const data = Object.fromEntries(keys.map(key => [key, draft[key].trim()]));
      const saved = await post(bootstrap.actions.save, { ...data, id: editingId ? String(editingId) : 'new' });
      if (!alive) return;
      if (!saveResult(saved) || (editingId ? saved.id !== editingId : locations.some(row => row.id === saved.id))) throw new Error(i18n.saveFailed);
      locations = editingId ? locations.map(row => row.id === editingId ? { ...saved.location } : row) : [...locations, { ...saved.location }];
      open = false; stopSearch();
    } catch (failure) { if (alive) formError = failure instanceof Error ? failure.message : i18n.saveFailed; }
    finally { if (alive) busy = false; }
  }
  async function toggle(row: WarehouseLocation, active: boolean) {
    if (!alive || row.is_default || pendingToggle !== null || busy) return;
    pendingToggle = row.id; error = '';
    // The bound Switch is optimistic; only the partial status field is sent and failures roll back.
    try {
      const saved = await post(bootstrap.actions.toggle, { id: String(row.id), is_active: active ? '1' : '0' });
      if (!alive) return;
      if (!toggleResult(saved) || saved.id !== row.id || saved.is_active !== active) throw new Error(i18n.saveFailed);
      locations = locations.map(item => item.id === row.id ? { ...item, is_active: saved.is_active } : item);
    } catch (failure) { if (alive) { locations = locations.map(item => item.id === row.id ? { ...item, is_active: !active } : item); error = failure instanceof Error ? failure.message : i18n.saveFailed; } }
    finally { if (alive) pendingToggle = null; }
  }
  function askDelete(row: WarehouseLocation) {
    if (!alive || row.is_default || busy || pendingToggle !== null) return;
    returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    deleteRow = row; deleteError = ''; deleting = true;
  }
  async function remove(event: MouseEvent) {
    event.preventDefault(); // AlertDialog must retain focus and server errors until success.
    if (!alive || busy || !deleteRow || deleteRow.is_default) return;
    const deletingId = deleteRow.id;
    busy = true;
    try {
      const saved = await post(bootstrap.actions.delete, { id: String(deletingId) });
      if (!alive) return;
      if (!mutationResult(saved) || saved.id !== deletingId) throw new Error(i18n.saveFailed);
      locations = locations.filter(row => row.id !== deletingId); deleting = false;
    } catch (failure) { if (alive) deleteError = failure instanceof Error ? failure.message : i18n.saveFailed; }
    finally { if (alive) busy = false; }
  }
  function search(value: string) {
    if (!alive || busy) return;
    stopSearch(); areas = [];
    if (value.trim().length < 3) return;
    const generation = searchGeneration;
    searchTimer = setTimeout(async () => {
      const controller = searchController = new AbortController(); searching = true;
      try {
        const body = new URLSearchParams({ action: 'kiriminaja_subdistrict_search', nonce: bootstrap.nonce, term: value, 'data[term]': value, 'data[search]': value });
        const response = await fetch(bootstrap.ajaxUrl, { method: 'POST', credentials: 'same-origin', body, signal: controller.signal });
        const payload = await response.json();
        if (!response.ok || payload.success === false) throw new Error();
        if (alive && open && !busy && generation === searchGeneration) {
          areas = Array.isArray(payload.data) ? payload.data.filter((row: unknown): row is WarehouseArea => {
            if (!row || typeof row !== 'object') return false;
            const area = row as WarehouseArea;
            return /^[1-9][0-9]*$/.test(String(area.id)) && typeof area.text === 'string' && area.text.trim().length > 0;
          }).map((area: WarehouseArea) => ({ ...area, city: typeof area.city === 'string' ? area.city : undefined, country_state: typeof area.country_state === 'string' ? area.country_state : undefined, zip: typeof area.zip === 'string' ? area.zip : undefined })) : [];
          searchError = '';
        }
      } catch { if (alive && open && generation === searchGeneration) searchError = i18n.searchFailed; }
      finally { if (alive && generation === searchGeneration) searching = false; }
    }, 250);
  }
  function select(area: WarehouseArea) {
    if (!alive || busy) return;
    draft.district = String(area.id); draft.district_label = String(area.text ?? area.label ?? '');
    if (area.city) draft.city = area.city;
    if (area.country_state) draft.country_state = area.country_state;
    if (area.zip) draft.zip = area.zip;
  }
  $effect(() => {
    const current = bootstrap;
    const key = current.editingKey;
    if (consumedEditing.has(current)) return;
    consumedEditing.add(current);
    untrack(() => { if (key === 'new') edit(); else if (key) { const row = locations.find(row => String(row.id) === key); if (row) edit(row); } });
  });
  onDestroy(() => { alive = false; stopSearch(); for (const controller of controllers) controller.abort(); });
</script>

<Toolbar toolbar={bootstrap.toolbar} onNavigate={navigateSettings}>
  <div class="kiriof-shadcn">
    <Button bind:ref={addButton} data-warehouse-add disabled={!canCreate || busy || pendingToggle !== null} onclick={() => edit()}><IconPlus data-icon="inline-start" aria-hidden="true" />{i18n.add}</Button>
  </div>
</Toolbar>
<section class="kiriof-shadcn flex w-full min-w-0 flex-col gap-4" aria-labelledby="warehouses-title">
  <div class="flex flex-col gap-2">
    <h2 id="warehouses-title" class="text-lg font-semibold">{i18n.title}</h2>
    <p class="text-sm text-muted-foreground">{i18n.description}</p>
    <p class="text-sm text-muted-foreground">{i18n.limit.replace('%1$d', String(customCount)).replace('%2$d', String(bootstrap.customLimit))}</p>
    {#if !canCreate}<p class="text-sm text-muted-foreground">{i18n.limitReached}</p>{/if}
  </div>
  <div class="min-w-0">
      {#if error}<Alert.Root variant="destructive" class="mb-4"><Alert.Description>{error}</Alert.Description></Alert.Root>{/if}
      {#if locations.length === 0}
        <Empty.Root><Empty.Header><Empty.Media variant="icon"><IconBuildingWarehouse aria-hidden="true" /></Empty.Media><Empty.Title>{i18n.empty}</Empty.Title></Empty.Header></Empty.Root>
      {:else}
        <Table.Root>
          <Table.Header><Table.Row><Table.Head>{i18n.name}</Table.Head><Table.Head>{i18n.region}</Table.Head><Table.Head>{i18n.status}</Table.Head><Table.Head><span class="sr-only">{i18n.edit}</span></Table.Head></Table.Row></Table.Header>
          <Table.Body>
            {#each locations as row (row.id)}
              <Table.Row data-warehouse-row={row.id}>
                <Table.Cell class="whitespace-normal break-words"><div class="font-medium">{row.name}</div>{#if row.is_default}<Badge variant="secondary">{i18n.default}</Badge>{/if}</Table.Cell>
                <Table.Cell class="whitespace-normal break-words"><div>{row.district_label || row.city}</div><div class="text-xs text-muted-foreground">{row.address}</div></Table.Cell>
                <Table.Cell><div class="flex items-center gap-2"><Switch bind:checked={row.is_active} disabled={row.is_default || busy || pendingToggle !== null} aria-label={`${i18n.status}: ${row.name}`} onCheckedChange={(checked) => void toggle(row, checked)} />{#if pendingToggle === row.id}<Spinner aria-label={i18n.loading} />{/if}<span class="text-sm">{row.is_active ? i18n.active : i18n.inactive}</span></div></Table.Cell>
                <Table.Cell><div class="flex justify-end gap-2">
                  <ActionTooltip label={`${i18n.edit}: ${row.name}`} disabled={busy || pendingToggle !== null}>
                    <Button variant="outline" size="icon-sm" aria-label={`${i18n.edit}: ${row.name}`} data-warehouse-edit={row.id} disabled={busy || pendingToggle !== null} onclick={() => edit(row)}><IconPencil aria-hidden="true" /></Button>
                  </ActionTooltip>
                  {#if !row.is_default}
                    <ActionTooltip label={`${i18n.delete}: ${row.name}`} disabled={busy || pendingToggle !== null}>
                      <Button variant="destructive" size="icon-sm" aria-label={`${i18n.delete}: ${row.name}`} data-warehouse-delete={row.id} disabled={busy || pendingToggle !== null} onclick={() => askDelete(row)}><IconTrash aria-hidden="true" /></Button>
                    </ActionTooltip>
                  {/if}
                </div></Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>
      {/if}
  </div>
</section>

<Dialog.Root {open} onOpenChange={closeForm}>
  <Dialog.Content class="kiriof-shadcn box-border min-w-0 max-h-[90vh] overflow-y-auto sm:max-w-2xl" showCloseButton={false} onCloseAutoFocus={restoreFocus} onEscapeKeydown={(event) => { if (busy) event.preventDefault(); }} onInteractOutside={(event) => { if (busy) event.preventDefault(); }}>
    <Dialog.Header><Dialog.Title>{draft.id ? i18n.edit : i18n.add}</Dialog.Title><Dialog.Description>{i18n.description}</Dialog.Description></Dialog.Header>
    <form data-warehouse-form onsubmit={save} novalidate class="flex flex-col gap-4" aria-busy={busy}>
      <Field.Set disabled={busy} class="min-w-0">
        <Field.Group class="gap-4 sm:grid sm:grid-cols-2">
          <Field.Field data-invalid={invalid('name')}><Field.Label for="warehouse-name">{i18n.name}</Field.Label><Input id="warehouse-name" bind:value={draft.name} required disabled={busy} aria-invalid={invalid('name')} /></Field.Field>
          <Field.Field data-invalid={invalid('sender_phone')}><Field.Label for="warehouse-sender_phone">{i18n.senderPhone}</Field.Label><Input id="warehouse-sender_phone" type="tel" bind:value={draft.sender_phone} required disabled={busy} aria-invalid={invalid('sender_phone')} /></Field.Field>
        </Field.Group>
        <Field.Field data-invalid={invalid('address')}><Field.Label for="warehouse-address">{i18n.address}</Field.Label><Textarea id="warehouse-address" bind:value={draft.address} required disabled={busy} aria-invalid={invalid('address')} /></Field.Field>
        <Field.Field><Field.Label for="warehouse-address_2">{i18n.address2}</Field.Label><Input id="warehouse-address_2" bind:value={draft.address_2} disabled={busy} /></Field.Field>
        <Field.Group class="gap-4 sm:grid sm:grid-cols-2">
          <Field.Field><Field.Label for="warehouse-city">{i18n.city}</Field.Label><Input id="warehouse-city" bind:value={draft.city} disabled={busy} /></Field.Field>
          <Field.Field><Field.Label for="warehouse-country_state">{i18n.countryState}</Field.Label>
            <Select.Root type="single" bind:value={draft.country_state} disabled={busy || countryStates.length === 0}>
              <Select.Trigger id="warehouse-country_state" class="w-full">{countryLabel}</Select.Trigger>
              <Select.Content><Select.Group>{#each countryStates as option (option.value)}<Select.Item value={option.value} label={option.label}>{option.label}</Select.Item>{/each}</Select.Group></Select.Content>
            </Select.Root>
          </Field.Field>
          <Field.Field data-invalid={invalid('zip')}><Field.Label for="warehouse-zip">{i18n.zip}</Field.Label><Input id="warehouse-zip" bind:value={draft.zip} required disabled={busy} aria-invalid={invalid('zip')} /></Field.Field>
        </Field.Group>
        <Field.Field data-invalid={invalid('district')}><Field.Label for="warehouse-district">{i18n.area}</Field.Label>
          <SubdistrictCombobox id="warehouse-district" disabled={busy} aria-invalid={invalid('district')} value={draft.district_label} {areas} loading={searching} placeholder={i18n.area} loadingText={i18n.loading} noResultsText={i18n.noResults} typeMoreText={i18n.typeMore} onSearch={search} onSelect={select} />
        </Field.Field>
        {#if open}
          <WarehousePin lat={draft.lat} lng={draft.lng} config={bootstrap.map} {i18n} disabled={busy} invalid={submitted && !normalizePoint(draft.lat, draft.lng)} onSelect={(lat, lng) => { if (!busy) { draft.lat = lat; draft.lng = lng; } }} />
        {/if}
      </Field.Set>
      {#if searchError}<Alert.Root variant="destructive"><Alert.Description>{searchError}</Alert.Description></Alert.Root>{/if}
      {#if formError}<Alert.Root variant="destructive"><Alert.Description>{formError}</Alert.Description></Alert.Root>{/if}
      <Dialog.Footer><Button type="button" variant="outline" disabled={busy} onclick={() => closeForm(false)}>{i18n.cancel}</Button><Button type="submit" disabled={busy}>{#if busy}<Spinner data-icon="inline-start" aria-label={i18n.loading} />{/if}{busy ? i18n.saving : i18n.save}</Button></Dialog.Footer>
    </form>
  </Dialog.Content>
</Dialog.Root>

<AlertDialog.Root open={deleting} onOpenChange={(next) => { if (!busy) deleting = next; }}>
  <AlertDialog.Content class="kiriof-shadcn box-border" onCloseAutoFocus={restoreFocus} onEscapeKeydown={(event) => { if (busy) event.preventDefault(); }}>
    <AlertDialog.Header><AlertDialog.Title>{i18n.delete}</AlertDialog.Title><AlertDialog.Description>{i18n.confirmDelete}</AlertDialog.Description></AlertDialog.Header>
    {#if deleteError}<Alert.Root variant="destructive"><Alert.Description>{deleteError}</Alert.Description></Alert.Root>{/if}
    <AlertDialog.Footer><AlertDialog.Cancel disabled={busy}>{i18n.cancel}</AlertDialog.Cancel><AlertDialog.Action variant="destructive" disabled={busy} onclick={remove}>{#if busy}<Spinner data-icon="inline-start" aria-label={i18n.loading} />{/if}{busy ? i18n.saving : i18n.delete}</AlertDialog.Action></AlertDialog.Footer>
  </AlertDialog.Content>
</AlertDialog.Root>
