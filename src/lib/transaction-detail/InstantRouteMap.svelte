<script lang="ts">
  import { mountInstantRouteMap, normalizeInstantRoute } from './instant-route-map';
  import type { InstantRouteMapConfig, InstantRouteMapData } from './types';

  let { data, config, i18n, embedded = false }: {
    data?: InstantRouteMapData | null;
    config?: InstantRouteMapConfig;
    i18n: Record<string, string>;
    embedded?: boolean;
  } = $props();
  let container: HTMLDivElement | undefined = $state();
  let error = $state(false);
  const route = $derived(normalizeInstantRoute(data));
  const title = $derived(i18n.routeMap ?? 'Saved route map');
  const caption = $derived(route?.mode === 'recorded'
    ? (i18n.routeRecorded ?? 'Saved route data — not live tracking.')
    : route?.mode === 'illustration'
      ? (i18n.routeIllustration ?? 'Illustrative straight-line connection, not a driving route or live tracking.')
      : (i18n.routeUnavailable ?? 'Saved route coordinates are unavailable.'));

  $effect(() => {
    const currentRoute = route;
    const currentConfig = config;
    const element = container;
    error = false;
    if (!currentRoute || !element) return;
    if (!currentConfig) { error = true; return; }
    const session = mountInstantRouteMap(element, currentRoute, currentConfig, () => { error = true; }, {
      origin: i18n.routeOrigin ?? 'Saved pickup',
      destination: i18n.routeDestination ?? 'Saved destination',
      start: i18n.routeStart ?? 'Recorded route start',
      end: i18n.routeEnd ?? 'Recorded route end',
    });
    return () => session.dispose();
  });
</script>

<section class={embedded ? 'm-0 min-w-0 overflow-hidden border-t border-border' : 'm-0 min-w-0 overflow-hidden rounded-lg border border-border bg-card text-card-foreground'} aria-label={title}>
  <header class={embedded ? 'sr-only' : '!grid gap-1 border-b border-border p-3'}>
    <h2 class="m-0 text-sm font-semibold">{title}</h2>
    <p class="m-0 text-xs leading-relaxed text-muted-foreground">{caption}</p>
  </header>
  {#if route}
    <!-- svelte-ignore a11y_no_noninteractive_tabindex (Leaflet attaches keyboard pan/zoom handlers to this read-only map region.) -->
    <div bind:this={container} class="relative isolate h-[clamp(240px,30vw,320px)] w-full bg-muted" role="region" aria-label={title} tabindex="0"></div>
    {#if error}<p class="m-0 p-3 text-sm text-muted-foreground" role="status">{i18n.routeMapError ?? 'The saved route map could not be displayed.'}</p>{/if}
  {/if}
</section>
