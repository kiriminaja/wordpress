<script lang="ts">
  import { onDestroy, onMount } from 'svelte';
  import { Alert, AlertDescription, AlertTitle } from '$lib/components/ui/alert';
  import { Button } from '$lib/components/ui/button';
  import { IconAlertCircle, IconCheck, IconLoader2, IconRefresh, IconX } from '@tabler/icons-svelte';
  import CourierServicePicker from '$lib/couriers/CourierServicePicker.svelte';
  import {
    initializeSelection,
    selectionPayload,
    setAllServices,
    type Courier,
    type CourierPayload,
    type SelectionState,
  } from '$lib/couriers/selection';
  import type { CouriersBootstrap } from './types';
  import Toolbar from '$lib/ui/Toolbar.svelte';
  import { navigateSettings } from './navigation';
  import { postWordPressAction } from '$lib/wordpress/ajax';

  let { bootstrap }: { bootstrap: CouriersBootstrap } = $props();
  let couriers = $state<Courier[]>([]);
  let selectionState = $state<SelectionState>({ selection: {}, remembered: {} });
  let loading = $state(true);
  let loaded = $state(false);
  let saving = $state(false);
  let error = $state('');
  let saveState = $state<'idle' | 'saving' | 'saved' | 'error'>('idle');
  let failedSelection = $state<SelectionState | undefined>();
  let requestController: AbortController | undefined;
  let destroyed = false;

  function sameSelection(left: SelectionState, right: SelectionState): boolean {
    return couriers.every((courier) => {
      const leftServices = left.selection[courier.code] ?? [];
      const rightServices = right.selection[courier.code] ?? [];
      return (
        leftServices.length === rightServices.length &&
        leftServices.every((code) => rightServices.includes(code))
      );
    });
  }

  async function persist(next: SelectionState): Promise<void> {
    if (!loaded || loading || saving) return;
    if (sameSelection(next, selectionState)) return;
    const previous = selectionState;
    selectionState = next;
    saving = true;
    saveState = 'saving';
    error = '';
    failedSelection = undefined;
    try {
      await postWordPressAction(
        'kiriof_store_courier_whitelist',
        selectionPayload(next.selection, couriers),
      );
      if (destroyed) return;
      saveState = 'saved';
    } catch (requestError) {
      if (destroyed) return;
      selectionState = previous;
      error = requestError instanceof Error ? requestError.message : bootstrap.i18n.saveFailed;
      saveState = 'error';
      failedSelection = next;
    } finally {
      if (!destroyed) saving = false;
    }
  }

  async function loadCouriers(): Promise<void> {
    if (loaded || saving) return;
    requestController?.abort();
    const controller = new AbortController();
    requestController = controller;
    loading = true;
    error = '';
    try {
      const response = await postWordPressAction<CourierPayload>(
        'kiriof_get_courier_whitelist',
        {},
        { signal: controller.signal },
      );
      if (destroyed || controller.signal.aborted) return;
      if (!response.data || !Array.isArray(response.data.couriers))
        throw new Error(bootstrap.i18n.loadFailed);
      const result = initializeSelection(response.data);
      couriers = result.couriers;
      selectionState = result.state;
      loaded = true;
    } catch (requestError) {
      if (destroyed || controller.signal.aborted) return;
      error = requestError instanceof Error ? requestError.message : bootstrap.i18n.loadFailed;
    } finally {
      if (!destroyed && requestController === controller) loading = false;
    }
  }

  function setAll(enabled: boolean): void {
    if (!loaded || loading || saving) return;
    void persist(setAllServices(selectionState, couriers, enabled));
  }

  onMount(() => {
    void loadCouriers();
  });

  onDestroy(() => {
    destroyed = true;
    requestController?.abort();
  });
</script>

<Toolbar toolbar={bootstrap.toolbar} onNavigate={navigateSettings}>
  <Button
    class="kiriof-settings-action-button"
    disabled={!loaded ||
      loading ||
      saving ||
      couriers.length === 0 ||
      sameSelection(setAllServices(selectionState, couriers, true), selectionState)}
    onclick={() => setAll(true)}
  >
    <IconCheck class="kiriof-settings-action-button__icon" aria-hidden="true" />
    <span>{bootstrap.i18n.enableAll}</span>
  </Button>
  <Button
    class="kiriof-settings-action-button"
    variant="outline"
    disabled={!loaded ||
      loading ||
      saving ||
      couriers.length === 0 ||
      sameSelection(setAllServices(selectionState, couriers, false), selectionState)}
    onclick={() => setAll(false)}
  >
    <IconX class="kiriof-settings-action-button__icon" aria-hidden="true" />
    <span>{bootstrap.i18n.disableAll}</span>
  </Button>
</Toolbar>
<div class="!grid w-full min-w-0 gap-4">
  {#if loading}
    <div
      class="flex min-h-40 flex-col items-center justify-center gap-3 text-sm text-muted-foreground"
      role="status"
      aria-live="polite"
    >
      <IconLoader2 class="h-6 w-6 animate-spin text-primary" aria-hidden="true" />
      <span>{bootstrap.i18n.loading}</span>
    </div>
  {:else if !loaded}
    <Alert variant="destructive">
      <IconAlertCircle aria-hidden="true" />
      <AlertTitle>{bootstrap.i18n.loadFailed}</AlertTitle>
      <AlertDescription>{error}</AlertDescription>
      <Button variant="outline" size="sm" class="mt-3" onclick={loadCouriers}>
        <IconRefresh class="mr-1.5 h-4 w-4" aria-hidden="true" />
        {bootstrap.i18n.retry ?? 'Retry'}
      </Button>
    </Alert>
  {:else if couriers.length === 0}
    <p class="py-8 text-center text-sm text-muted-foreground">{bootstrap.i18n.noCouriers}</p>
  {:else}
    <CourierServicePicker
      {couriers}
      i18n={bootstrap.i18n}
      state={selectionState}
      disabled={saving || loading}
      onChange={(next) => {
        void persist(next);
      }}
    />
  {/if}
  {#if error && loaded}
    <Alert variant="destructive">
      <IconAlertCircle aria-hidden="true" />
      <AlertTitle>{bootstrap.i18n.saveFailed}</AlertTitle>
      <AlertDescription>{error}</AlertDescription>
      <Button
        variant="outline"
        size="sm"
        class="mt-3"
        disabled={saving || !failedSelection}
        onclick={() => failedSelection && persist(failedSelection)}
      >
        <IconRefresh class="mr-1.5 h-4 w-4" aria-hidden="true" />
        {bootstrap.i18n.retry ?? 'Retry'}
      </Button>
    </Alert>
  {/if}
  <div class="flex items-center gap-2 text-xs text-muted-foreground" aria-live="polite">
    {#if saveState === 'saving'}
      <IconLoader2 class="h-3.5 w-3.5 animate-spin" aria-hidden="true" />
      <span>{bootstrap.i18n.saving ?? 'Saving…'}</span>
    {:else if saveState === 'saved'}
      <IconCheck class="h-3.5 w-3.5 text-primary" aria-hidden="true" />
      <span>{bootstrap.i18n.saved ?? 'Saved'}</span>
    {:else if saveState === 'error'}
      <IconAlertCircle class="h-3.5 w-3.5 text-destructive" aria-hidden="true" />
      <span class="text-destructive">{bootstrap.i18n.saveFailed}</span>
    {/if}
    <span>{bootstrap.i18n.autoSave ?? 'Changes are saved automatically.'}</span>
  </div>
</div>
