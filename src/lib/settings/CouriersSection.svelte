<script lang="ts">
  import { onMount } from 'svelte';
  import { IconCheck, IconX } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import { postWordPressAction } from '../wordpress/ajax';
  import SettingSwitch from '../ui/SettingSwitch.svelte';
  import type { CouriersBootstrap } from './types';
  import Toolbar from '$lib/ui/Toolbar.svelte';
  import { navigateSettings } from './navigation';

  type Courier = { code: string; name: string; type?: string };
  type CourierPayload = { couriers: Courier[]; whitelist_ids: string[] };

  let { bootstrap }: { bootstrap: CouriersBootstrap } = $props();
  let couriers = $state<Courier[]>([]);
  let enabled = $state<Record<string, string>>({});
  let loading = $state(true);
  let saving = $state(false);
  let error = $state('');

  function countText(): string {
    return bootstrap.i18n.count.replace('%1$s', String(Object.keys(enabled).length)).replace('%2$s', String(couriers.length));
  }

  async function persist(next: Record<string, string>, previous: Record<string, string>): Promise<void> {
    enabled = next;
    saving = true;
    error = '';
    try {
      const ids = Object.keys(enabled);
      await postWordPressAction('kiriof_store_courier_whitelist', { whitelist_ids: ids.join(','), whitelist_names: ids.map((id) => enabled[id]).join(',') });
    } catch (requestError) {
      enabled = previous;
      error = requestError instanceof Error ? requestError.message : bootstrap.i18n.saveFailed;
    } finally {
      saving = false;
    }
  }

  function toggle(courier: Courier, checked: boolean): void {
    const previous = { ...enabled };
    const next = { ...enabled };
    if (checked) next[courier.code] = courier.name;
    else delete next[courier.code];
    void persist(next, previous);
  }

  function setAll(checked: boolean): void {
    const previous = { ...enabled };
    const next = checked ? Object.fromEntries(couriers.map((courier) => [courier.code, courier.name])) : {};
    void persist(next, previous);
  }

  onMount(async () => {
    try {
      const response = await postWordPressAction<CourierPayload>('kiriof_get_courier_whitelist', {});
      couriers = response.data?.couriers ?? [];
      const selected = new Set(response.data?.whitelist_ids ?? []);
      enabled = Object.fromEntries(couriers.filter((courier) => selected.has(courier.code)).map((courier) => [courier.code, courier.name]));
    } catch (requestError) {
      error = requestError instanceof Error ? requestError.message : bootstrap.i18n.loadFailed;
    } finally {
      loading = false;
    }
  });
</script>

<Toolbar toolbar={bootstrap.toolbar} onNavigate={navigateSettings}>
  <Button class="kiriof-settings-action-button" disabled={loading || saving} onclick={() => setAll(true)}>
    <IconCheck class="kiriof-settings-action-button__icon" aria-hidden="true" />
    <span>{bootstrap.i18n.enableAll}</span>
  </Button>
  <Button class="kiriof-settings-action-button" variant="outline" disabled={loading || saving} onclick={() => setAll(false)}>
    <IconX class="kiriof-settings-action-button__icon" aria-hidden="true" />
    <span>{bootstrap.i18n.disableAll}</span>
  </Button>
</Toolbar>
<section class="kiriof-section-card kiriof-settings-content">
  <div class="kiriof-courier-toolbar"><span>{countText()}</span></div>
  {#if loading}<p>{bootstrap.i18n.loading}</p>{:else if error && couriers.length === 0}<p class="kiriof-settings-root__message is-error">{error}</p>{:else if couriers.length === 0}<p>{bootstrap.i18n.noCouriers}</p>{:else}<div class="kiriof-courier-grid">{#each couriers as courier}<article><div><strong>{courier.name}</strong><span>{courier.type ?? ''}</span></div><SettingSwitch label={courier.name} checked={Object.hasOwn(enabled, courier.code)} disabled={saving} onCheckedChange={(checked) => toggle(courier, checked)} /></article>{/each}</div>{/if}
  {#if error && couriers.length}<p class="kiriof-section-message error" role="alert">{error}</p>{/if}
</section>
