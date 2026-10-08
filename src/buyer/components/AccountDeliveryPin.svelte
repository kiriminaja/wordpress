<script lang="ts">
  import { untrack } from 'svelte';
  import { createLocationGate, coverageStatus } from '../map/leaflet';
  import { mapProviderRegistry } from '../map/providers';
  import { loadGoogleMaps } from '../map/google-loader';
  import { resolveMapConfig } from '../map/config';
  import { addressKey, readAddress } from '../account/address';
  import type { AccountConfig, AccountRoot, AccountView, Address } from '../account/types';
  import type { GoogleMapsAPI, GoogleMapsWindow, MapError, MapSession, Point } from '../map/types';
  let { state: view, config, root, form, select, synchronize }: {
    state: AccountView; config: AccountConfig; root: AccountRoot; form: HTMLFormElement;
    select(point: Point | null, expected: Address): boolean; synchronize(): void;
  } = $props();
  const strings = $derived({ ...config.i18n, ...config.map?.i18n });
  const key = $derived(addressKey(view.address));
  const visible = $derived(view.address.country === 'ID' && config.map?.enabled !== false);
  const outside = $derived(coverageStatus(config.map?.coverage, view.pin)?.inside === false);
  const legend = $derived(Boolean(coverageStatus(config.map?.coverage, config.map?.coverage?.origin)));
  let canvas: HTMLDivElement;
  let hidden = $state(true), moving = $state(false), available = $state(false), status = $state('');
  let session: MapSession | undefined;
  $effect(() => {
    void key;
    const expected = untrack(() => ({ ...view.address }));
    const initial = untrack(() => view.pin);
    hidden = true; moving = false; available = false; status = '';
    if (!visible || !canvas) return;
    let disposed = false;
    let map: MapSession | undefined;
    function current() { return !disposed && addressKey(expected) === addressKey(readAddress(form)); }
    function error(code: MapError) {
      if (!current()) return;
      if (code !== 'invalid') { hidden = true; available = false; }
      status = (code === 'invalid' ? strings.mapInvalid : code === 'permission' ? strings.mapPermission : code === 'location' ? strings.mapLocationFailed : strings.mapUnavailable) || (code === 'unavailable' ? 'Map unavailable' : '');
    }
    const gate = createLocationGate({
      geolocation: root.navigator?.geolocation,
      onError: error,
      onSuccess(device) {
        if (!current()) return;
        function instantiate(provider: ReturnType<typeof resolveMapConfig>, google?: GoogleMapsAPI) {
          if (!current()) return;
          hidden = false;
          map = mapProviderRegistry[provider.provider].createSession({
            ...provider, google, document: root.document, window: root as unknown as GoogleMapsWindow, node: canvas, leaflet: root.L, initial, coverage: config.map?.coverage,
            defaultCenter: [Number(device.latitude), Number(device.longitude)],
            geolocation: root.navigator?.geolocation,
            schedule: (callback, delay) => root.setTimeout(callback, delay),
            cancel: handle => root.clearTimeout(handle as number),
            onError: error,
            onSelect(point) {
              if (!current() || !select(point, expected)) return false;
              status = point ? strings.mapPlaced || '' : '';
              return true;
            },
            onMove(next) {
              if (!current()) return;
              moving = next;
              status = (next ? strings.mapMoving : view.pin ? strings.mapPlaced : '') || '';
            },
          });
          session = map;
          if (!map.isAvailable()) return;
          if (!initial) map.pick(device.latitude, device.longitude, true);
          status = view.pin ? strings.mapPlaced || '' : '';
          available = true;
        }
        try {
          const provider = resolveMapConfig(config.map);
          if (provider.provider === 'google') {
            void (async () => {
              try {
                const google = await loadGoogleMaps(provider.apiKey, root.document, root as unknown as GoogleMapsWindow);
                if (!current()) return;
                instantiate(provider, google);
              } catch { error('unavailable'); }
            })();
          } else instantiate(provider);
        } catch { error('unavailable'); }
      },
    });
    // Synchronous geolocation implementations must not subscribe the lifecycle to pin writes.
    untrack(() => gate.start());
    return () => { disposed = true; gate.dispose(); map?.dispose(); if (session === map) session = undefined; };
  });
  function locate(event: MouseEvent) {
    event.preventDefault();
    if (hidden || !available) return;
    synchronize();
    if (!hidden && available) session?.locate();
  }
</script>

<section class="kiriof-buyer-map" aria-label={strings.mapTitle || ''} hidden={!visible}>
  <h3 class="kiriof-buyer-map__title">{strings.mapTitle || ''}</h3>
  <p class="kiriof-buyer-map__coverage-legend" role="note" hidden={!legend}>{legend ? strings.mapCoverage || '' : ''}</p>
  <p class="kiriof-buyer-map__coverage-warning" role="note" aria-live="polite" hidden={!outside}>{outside ? strings.mapOutsideRadius || '' : ''}</p>
  <div class="kiriof-buyer-map__viewport" class:is-moving={moving} {hidden}>
    <div bind:this={canvas} class="kiriof-buyer-map__canvas" aria-label={strings.mapHelp || ''} {...{ 'aria-description': strings.mapKeyboard || '' }}></div>
    <span class="kiriof-buyer-map__indicator" aria-hidden="true" hidden={!moving && !view.pin}><svg viewBox="0 0 32 44" focusable="false"><path fill="currentColor" stroke="white" stroke-width="2" d="M16 1C7.7 1 1 7.7 1 16c0 11 15 26 15 26s15-15 15-26C31 7.7 24.3 1 16 1Z"/><circle cx="16" cy="16" r="5" fill="white"/></svg></span>
    <button type="button" class="kiriof-buyer-map__locate" title={strings.mapPermission || ''} disabled={!available} onclick={locate}>{strings.mapLocate || ''}</button>
  </div>
  <p class="kiriof-buyer-map__status" role="status" aria-live="polite">{status}</p>
</section>
