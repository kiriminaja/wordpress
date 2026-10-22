import { loadGoogleMaps } from '../map/google-loader';
import { normalizePoint } from '../map/leaflet';
import { mapProviderRegistry } from '../map/providers';
import type { GoogleMapsWindow, MapSession, Point } from '../map/types';

export type AdminMapsWindow = GoogleMapsWindow & {
  kiriofAdminMapsConfig?: { provider?: string; apiKey?: string };
  kiriofSettings?: { i18n?: Record<string, string> };
  kiriofAdminMaps?: ReturnType<typeof createAdminOriginMaps>;
};

/** Only bridges origin controls: WooCommerce still owns validation and native form saving. */
export function createAdminOriginMaps(win: AdminMapsWindow) {
  const records = new Map<HTMLElement, { dispose(): void }>();
  let disposed = false;
  const selector = '#kiriof-wc-origin-map';

  function initialize(root: Document | HTMLElement = win.document) {
    if (disposed || win.kiriofAdminMapsConfig?.provider !== 'google') return;
    const nodes = Array.from(root.querySelectorAll<HTMLElement>(selector));
    if ('matches' in root && root.matches(selector)) nodes.unshift(root);
    for (const node of nodes) {
      if (records.has(node)) continue;
      const scope = win.document;
      const latitude = scope.querySelector<HTMLInputElement>('#kiriof_wc_origin_latitude');
      const longitude = scope.querySelector<HTMLInputElement>('#kiriof_wc_origin_longitude');
      if (!latitude || !longitude) continue;
      // Snapshot before any asynchronous work; zero is a real coordinate, empty is not.
      const initial = normalizePoint(latitude.value, longitude.value);
      const button = scope.querySelector<HTMLButtonElement>('#kiriof-wc-use-my-location');
      const coords = scope.querySelector<HTMLElement>('#kiriof-wc-map-coords');
      let error = scope.querySelector<HTMLElement>('#kiriof-wc-map-error');
      const ownedError = !error;
      if (!error) {
        error = win.document.createElement('p');
        error.className = 'description';
        error.hidden = true;
        error.setAttribute('role', 'status');
        node.after(error);
      }
      let active = true,
        generation = 0;
      let session: MapSession | undefined;
      const pin = node.nextElementSibling as HTMLElement | null;
      const originalPinHidden = pin?.hidden;
      if (pin) pin.hidden = !initial;
      if (initial) coords?.setAttribute('data-tip', `${initial.latitude}, ${initial.longitude}`);
      const live = () => active && !disposed && node.isConnected;
      function showError(key: string) {
        if (!live()) return;
        error!.textContent =
          win.kiriofSettings?.i18n?.[key] ||
          win.kiriofSettings?.i18n?.unknownError ||
          'Map unavailable';
        error!.hidden = false;
        error!.style.display = '';
      }
      function selected(point: Point | null) {
        if (!live()) return false;
        if (!point) return false;
        generation++;
        if (button) button.disabled = false;
        latitude!.value = point.latitude;
        longitude!.value = point.longitude;
        for (const input of [latitude!, longitude!]) {
          // Construct events in the owning document's realm.
          for (const name of ['input', 'change']) {
            const event = win.document.createEvent('Event');
            event.initEvent(name, true, false);
            input.dispatchEvent(event);
          }
        }
        coords?.setAttribute('data-tip', `${point.latitude}, ${point.longitude}`);
        error!.hidden = true;
        error!.style.display = 'none';
        if (pin) pin.hidden = false;
        return true;
      }
      function locate(event: Event) {
        event.preventDefault();
        if (!live() || !session?.isAvailable()) return;
        const request = ++generation;
        if (button) button.disabled = true;
        // Do not gate map editing on geolocation permission; request only on explicit click.
        void new Promise<GeolocationPosition>((resolve, reject) => {
          if (!win.navigator.geolocation) {
            reject({ code: 0 });
            return;
          }
          win.navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0,
          });
        })
          .then((position) => {
            if (live() && request === generation)
              session?.pick(position.coords.latitude, position.coords.longitude, true);
          })
          .catch((failure: { code?: number }) => {
            if (live() && request === generation)
              showError(
                ['geolocationUnsupported', 'permissionDenied', 'locationUnavailable', 'timeout'][
                  failure?.code ?? 0
                ] || 'unknownError',
              );
          })
          .finally(() => {
            if (active && button && (request === generation || !button.disabled))
              button.disabled = false;
          });
      }
      button?.addEventListener('click', locate);
      records.set(node, {
        dispose() {
          active = false;
          generation++;
          button?.removeEventListener('click', locate);
          if (button) button.disabled = false;
          session?.dispose();
          if (ownedError) error?.remove();
          if (pin) pin.hidden = originalPinHidden ?? false;
        },
      });
      void loadGoogleMaps(win.kiriofAdminMapsConfig.apiKey || '', win.document, win)
        .then((google) => {
          // Loader continuation must not resurrect maps after navigation or removal.
          if (!live()) return;
          session = mapProviderRegistry.google.createSession({
            node,
            window: win,
            google,
            initial,
            onSelect: selected,
            onMove: (moving) => {
              if (moving && live()) {
                generation++;
                if (button) button.disabled = false;
              }
            },
            onError: (code) =>
              showError(code === 'invalid' ? 'invalidCoordinates' : 'mapUnavailable'),
          });
        })
        .catch(() => showError('mapUnavailable'));
    }
  }
  function dispose() {
    if (disposed) return;
    disposed = true;
    for (const record of records.values()) record.dispose();
    records.clear();
    win.removeEventListener('pagehide', dispose);
  }
  win.addEventListener('pagehide', dispose);
  return { initialize, dispose };
}
