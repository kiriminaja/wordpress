<script lang="ts">
  import { onMount } from 'svelte';
  import { IconCheck, IconX } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import { postWordPressAction } from '$lib/wordpress/ajax';
  import CourierServicePicker from '$lib/couriers/CourierServicePicker.svelte';
  import {
    initializeSelection,
    selectedCourierCount,
    selectionPayload,
    setAllServices,
    type Courier,
    type CourierPayload,
    type SelectionState,
  } from '$lib/couriers/selection';
  import type { CouriersBootstrap } from './types';
  import Toolbar from '$lib/ui/Toolbar.svelte';
  import { navigateSettings } from './navigation';

  let { bootstrap }: { bootstrap: CouriersBootstrap } = $props();
  let couriers = $state<Courier[]>([]);
  let selectionState = $state<SelectionState>({ selection: {}, remembered: {} });
  let loading = $state(true);
  let loaded = $state(false);
  let saving = $state(false);
  let error = $state('');

  function countText(): string {
    return bootstrap.i18n.count
      .replace('%1$s', String(selectedCourierCount(selectionState.selection)))
      .replace('%2$s', String(couriers.length));
  }

  async function persist(next: SelectionState): Promise<void> {
    if (!loaded || loading || saving) return;
    const previous = selectionState;
    selectionState = next;
    saving = true;
    error = '';
    try {
      await postWordPressAction(
        'kiriof_store_courier_whitelist',
        selectionPayload(next.selection, couriers),
      );
    } catch (requestError) {
      selectionState = previous;
      error = requestError instanceof Error ? requestError.message : bootstrap.i18n.saveFailed;
    } finally {
      saving = false;
    }
  }

  async function loadCouriers(): Promise<void> {
    if (loaded || saving) return;
    loading = true;
    error = '';
    try {
      const response = await postWordPressAction<CourierPayload>(
        'kiriof_get_courier_whitelist',
        {},
      );
      if (!response.data || !Array.isArray(response.data.couriers))
        throw new Error(bootstrap.i18n.loadFailed);
      const result = initializeSelection(response.data);
      couriers = result.couriers;
      selectionState = result.state;
      loaded = true;
    } catch (requestError) {
      error = requestError instanceof Error ? requestError.message : bootstrap.i18n.loadFailed;
    } finally {
      loading = false;
    }
  }

  onMount(() => {
    void loadCouriers();
  });
</script>

<Toolbar toolbar={bootstrap.toolbar} onNavigate={navigateSettings}>
  <Button
    class="kiriof-settings-action-button"
    disabled={!loaded || loading || saving}
    onclick={() => persist(setAllServices(selectionState, couriers, true))}
  >
    <IconCheck class="kiriof-settings-action-button__icon" aria-hidden="true" />
    <span>{bootstrap.i18n.enableAll}</span>
  </Button>
  <Button
    class="kiriof-settings-action-button"
    variant="outline"
    disabled={!loaded || loading || saving}
    onclick={() => persist(setAllServices(selectionState, couriers, false))}
  >
    <IconX class="kiriof-settings-action-button__icon" aria-hidden="true" />
    <span>{bootstrap.i18n.disableAll}</span>
  </Button>
</Toolbar>
<section class="kiriof-section-card kiriof-settings-content space-y-4">
  <div class="text-sm text-muted-foreground" aria-live="polite">{countText()}</div>
  {#if loading}
    <p>{bootstrap.i18n.loading}</p>
  {:else if !loaded}
    <p class="text-destructive" role="alert">{error}</p>
    <Button variant="outline" onclick={loadCouriers}>{bootstrap.i18n.retry ?? 'Retry'}</Button>
  {:else if couriers.length === 0}
    <p>{bootstrap.i18n.noCouriers}</p>
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
  {#if error && loaded}<p class="text-destructive" role="alert">{error}</p>{/if}
</section>
