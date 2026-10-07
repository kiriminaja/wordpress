<script lang="ts">
  import { untrack } from 'svelte';
  import { createLocationGate, mapProviderRegistry } from '../map/leaflet';
  import { resolveMapConfig } from '../map/config';
  import type { MapSession, Point } from '../map/types';
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
    function show(device: typeof state.point) {
      if (disposed) return;
      canvasHidden = false;
      try {
        const provider = resolveMapConfig(config.map);
        map = mapProviderRegistry[provider.provider].createSession({
          ...provider, node: canvas, leaflet: root.L, coverage: config.map?.coverage,
          initial: state.point, geolocation: root.navigator.geolocation,
          onMove: next => { moving = next; },
          onSelect: next => select(next, expected),
          onError: code => { mapError = code === 'permission' ? strings.mapPermission : code === 'location' ? strings.mapLocationFailed : strings.mapUnavailable; },
        });
        session = map;
        if (device && !state.point) map.pick(device.latitude, device.longitude, true);
      } catch { mapError = strings.mapUnavailable || 'Map unavailable'; }
    }
    if (state.point) show(null);
    else {
      gate = createLocationGate({ geolocation: root.navigator.geolocation, onSuccess: show, onError: error => { if (!disposed) mapError = error === 'permission' ? strings.mapPermission : strings.mapLocationFailed; } });
      gate.start();
    }
    return () => { disposed = true; gate?.dispose(); map?.dispose(); if (session === map) session = undefined; };
  });
</script>

<label for="kiriof-classic-map">{strings.mapTitle || 'Delivery pin'}</label>
<div class="kiriof-classic-map-viewport">
  <!-- svelte-ignore a11y_no_noninteractive_tabindex (Leaflet keyboard map supports Enter and arrow navigation) -->
  <div bind:this={canvas} id="kiriof-classic-map" class="kiriof-classic-map" tabindex="0" role="application" aria-label={strings.mapHelp || 'Delivery location map'} hidden={canvasHidden}></div>
  <span class="kiriof-classic-map-indicator" aria-hidden="true"><svg viewBox="0 0 32 44" width="32" height="44" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 1C7.7 1 1 7.7 1 16c0 11 15 28 15 28s15-17 15-28C31 7.7 24.3 1 16 1Z" /></svg></span>
  <button type="button" class="button kiriof-classic-map-locate" aria-label={strings.mapLocate || 'Current location'} title={strings.mapLocate || 'Current location'} onclick={locate}><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v3m0 12v3M3 12h3m12 0h3M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8" /></svg></button>
  <span class="kiriof-classic-pin-state" class:is-complete={complete} role="status" hidden={moving}><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d={complete ? 'm5 12 4 4L19 6' : 'm7 7 10 10M17 7 7 17'} /></svg>{complete ? strings.pinLocation || 'Pin Location' : strings.needPinLocation || 'Need Pin Location'}</span>
</div>
<p role="status" hidden={moving || !status}>{status}</p>
<button type="button" class="button" hidden={!view.queue.error} onclick={() => controller.retry()}>{strings.retry || 'Retry'}</button>
