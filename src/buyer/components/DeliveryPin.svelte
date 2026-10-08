<script lang="ts">
  import { untrack } from 'svelte';
  import { createLocationGate, coverageStatus } from '../map/leaflet';
  import { mapProviderRegistry } from '../map/providers';
  import { loadGoogleMaps } from '../map/google-loader';
  import { resolveMapConfig } from '../map/config';
  import type { GoogleMapsAPI, GoogleMapsWindow, MapSession, Point } from '../map/types';
  import type { ClassicPinController, NativeAddress } from '../state/classic-pin.svelte';
  import type { ClassicPinConfig, ClassicPinWindow } from '../classic/pin';

  let { controller, config, root, visible, select, strings, requirement = '' }: {
    controller: ClassicPinController; config: ClassicPinConfig; root: ClassicPinWindow;
    visible: boolean; requirement?: string; select: (point: Point | null, expected: NativeAddress) => boolean;
    strings: Record<string, string>;
  } = $props();
  let canvas: HTMLDivElement;
  let moving = $state(false);
  let canvasHidden = $state(true);
  let mapError = $state('');
  let restart = $state(0);
  let session: MapSession | undefined;
  let locate = () => { if (session) session.locate(); else restart++; };
  const view = $derived(controller.view);
  const key = $derived(view.scope + JSON.stringify(view.address));
  const complete = $derived(Boolean(view.point && !view.queue.pending && !view.queue.inFlight && !view.queue.error));
  const hasCoverage = $derived(Boolean(coverageStatus(config.map?.coverage, config.map?.coverage?.origin)));
  const status = $derived(requirement || (view.queue.error ? (view.queue.error instanceof Error ? view.queue.error.message : strings.pinSaveFailed || 'Could not save the delivery pin. Please retry.') : view.queue.pending || view.queue.inFlight ? strings.pinSaving || 'Saving delivery pin…' : mapError));

  $effect(() => {
    void key; void restart;
    const state = untrack(() => controller.getState());
    if (!visible || !canvas || !config.map?.enabled || !state.address.address_1 || !state.address.postcode) return;
    const expected = state.address;
    let disposed = false;
    let gate: ReturnType<typeof createLocationGate> | undefined;
    let map: MapSession | undefined;
    moving = false; mapError = ''; canvasHidden = true;
    function current() {
      const latest = controller.getState();
      return !disposed && latest.scope === state.scope && JSON.stringify(latest.address) === JSON.stringify(expected);
    }
    function show(device: typeof state.point) {
      if (!current()) return;
      function instantiate(provider: ReturnType<typeof resolveMapConfig>, google?: GoogleMapsAPI) {
        if (!current()) return;
        canvasHidden = false;
        map = mapProviderRegistry[provider.provider].createSession({
          ...provider, google, document: root.document, window: root as GoogleMapsWindow,
          node: canvas, leaflet: root.L, coverage: config.map?.coverage,
          initial: state.point, geolocation: root.navigator.geolocation,
          onMove: next => { if (current()) moving = next; },
          onSelect: next => current() && select(next, expected),
          onError: code => { if (current()) mapError = (code === 'permission' ? strings.mapPermission : code === 'location' ? strings.mapLocationFailed : strings.mapUnavailable) || 'Map unavailable'; },
        });
        session = map;
        if (device && !state.point && map.isAvailable()) map.pick(device.latitude, device.longitude, true);
      }
      function unavailable() { if (current()) mapError = strings.mapUnavailable || 'Map unavailable'; }
      try {
        const provider = resolveMapConfig(config.map);
        if (provider.provider === 'google') {
          void (async () => {
            try {
              const google = await loadGoogleMaps(provider.apiKey, root.document, root as GoogleMapsWindow);
              if (!current()) return;
              instantiate(provider, google);
            } catch { unavailable(); }
          })();
        } else instantiate(provider);
      } catch { unavailable(); }
    }
    if (state.point) show(null);
    else {
      gate = createLocationGate({ geolocation: root.navigator.geolocation, onSuccess: show, onError: error => { if (current()) mapError = error === 'permission' ? strings.mapPermission : strings.mapLocationFailed; } });
      gate.start();
    }
    return () => { disposed = true; gate?.dispose(); map?.dispose(); if (session === map) session = undefined; };
  });
</script>

<label for="kiriof-classic-map">{strings.mapTitle || 'Delivery pin'}</label>
<div class="kiriof-classic-map-viewport">
  <!-- svelte-ignore a11y_no_noninteractive_tabindex (Leaflet keyboard map supports Enter and arrow navigation) -->
  <div bind:this={canvas} id="kiriof-classic-map" class="kiriof-classic-map" tabindex="0" role="application" aria-label={strings.mapHelp || 'Delivery location map'} hidden={canvasHidden}></div>
  <span class="kiriof-classic-map-indicator"><svg viewBox="0 0 32 44" width="32" height="44" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M16 1C7.7 1 1 7.7 1 16c0 11 15 28 15 28s15-17 15-28C31 7.7 24.3 1 16 1Z" /><circle cx="16" cy="16" r="10" fill="white" stroke="none" /></svg>
    <span class="kiriof-classic-pin-state" class:is-complete={complete} role="status" hidden={moving}><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d={complete ? 'm5 12 4 4L19 6' : 'm7 7 10 10M17 7 7 17'} /></svg><span class="kiriof-map-screen-reader">{complete ? strings.pinLocation || 'Pin Location' : strings.needPinLocation || 'Need Pin Location'}</span></span>
  </span>
  <button type="button" class="button kiriof-classic-map-locate" aria-label={strings.mapLocate || 'Current location'} title={strings.mapLocate || 'Current location'} onclick={locate}><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v3m0 12v3M3 12h3m12 0h3M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8" /></svg></button>
  {#if hasCoverage}<span class="kiriof-classic-map-coverage" role="note" title={strings.mapCoverage || config.map?.i18n?.mapCoverage} hidden={moving}><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 3 10 18H2L12 3Z" /><path d="M12 9v4m0 3h.01" /></svg>{strings.mapCoverageBadge || config.map?.i18n?.mapCoverageBadge || 'Instant ≤ 40 km'}</span>{/if}
</div>
<p role="status" hidden={moving || !status}>{status}</p>
<button type="button" class="button" hidden={!view.queue.error} onclick={() => controller.retry()}>{strings.retry || 'Retry'}</button>
