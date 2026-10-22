<script lang="ts">
  import { onDestroy, untrack } from 'svelte';
  import * as Field from '$lib/components/ui/field';
  import * as Alert from '$lib/components/ui/alert';
  import { Button } from '$lib/components/ui/button';
  import { IconMapPin } from '@tabler/icons-svelte';
  import { createProviderMapSession } from '../../buyer/map/providers';
  import { normalizePoint } from '../../buyer/map/leaflet';
  import { resolveMapConfig } from '../../buyer/map/config';
  import type { MapProviderConfigInput } from '../../buyer/map/config';
  import type { MapSession } from '../../buyer/map/types';

  let { lat, lng, config, i18n, disabled, invalid, onSelect }: {
    lat: string; lng: string; config: MapProviderConfigInput & { enabled?: boolean };
    i18n: Record<string, string>; disabled: boolean; invalid: boolean;
    onSelect: (lat: string, lng: string) => void;
  } = $props();
  let ready = $state(false);
  let error = $state('');
  let node = $state<HTMLDivElement>(null!);
  let session: MapSession | undefined;
  let generation = 0;
  let alive = true;

  // Mount with the dialog. Location permission is requested only by My Location.
  $effect(() => {
    if (!node) return;
    const element = node;
    const request = ++generation;
    const initial = untrack(() => normalizePoint(lat, lng));
    let owned: MapSession | undefined;
    void (async () => {
      try {
        if (config.enabled === false) throw new Error();
        const resolved = resolveMapConfig(config);
        const leaflet = resolved.provider === 'leaflet'
          ? ((window as Window & { L?: typeof import('leaflet') }).L ?? await import('leaflet'))
          : undefined;
        if (!alive || request !== generation) return;
        const result = await createProviderMapSession({
          ...resolved, node: element, leaflet,
          initial, defaultCenter: [-6.2088, 106.8456],
          geolocation: navigator.geolocation,
          onSelect: (point) => {
            if (!alive || disabled || request !== generation || !point) return false;
            onSelect(point.latitude, point.longitude);
            return true;
          },
          onError: () => { if (alive && request === generation) error = i18n.mapUnavailable; },
        });
        if (!alive || request !== generation) { result.dispose(); return; }
        owned = result;
        session = result;
        ready = result.isAvailable();
      } catch { if (alive && request === generation) error = i18n.mapUnavailable; }
    })();
    return () => { generation++; owned?.dispose(); session = undefined; ready = false; };
  });
  onDestroy(() => { alive = false; generation++; session?.dispose(); });
</script>

<Field.Field data-invalid={invalid} aria-invalid={invalid}>
  <Field.Label id="warehouse-pin-label">{i18n.pin}</Field.Label>
  <div class="flex flex-wrap items-center gap-2">
    <Button type="button" variant="outline" disabled={disabled || !ready} onclick={() => { if (!disabled) session?.locate(); }}>{i18n.myLocation}</Button>
    {#if normalizePoint(lat, lng)}<span class="text-sm text-muted-foreground">{i18n.pinSelected}</span>{/if}
  </div>
  <Field.Description id="warehouse-pin-description">{i18n.pinHint}</Field.Description>
  <div class="relative overflow-hidden rounded-lg border" inert={disabled}>
    <div id="warehouse-pin" bind:this={node} class="h-64 w-full" role="region" aria-labelledby="warehouse-pin-label" aria-describedby="warehouse-pin-description"></div>
    {#if normalizePoint(lat, lng)}
      <IconMapPin class="pointer-events-none absolute left-1/2 top-1/2 z-[500] size-8 -translate-x-1/2 -translate-y-full text-primary" aria-hidden="true" />
    {/if}
  </div>
  {#if error}<Alert.Root variant="destructive"><Alert.Description>{error}</Alert.Description></Alert.Root>{/if}
</Field.Field>
