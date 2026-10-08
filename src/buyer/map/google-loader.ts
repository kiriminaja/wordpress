import { isValidGoogleMapsKey } from './config';
import type { GoogleMapsAPI, GoogleMapsWindow } from './types';

const runtimes = new WeakMap<object, { key: string; promise: Promise<GoogleMapsAPI> }>();
let sequence = 0;
const unavailable = () => new Error('Map unavailable');

/** Shared by buyer, admin routes and onboarding. One credential/runtime per window. */
export function loadGoogleMaps(
  apiKey: string,
  document?: Document,
  window?: GoogleMapsWindow,
): Promise<GoogleMapsAPI> {
  if (!isValidGoogleMapsKey(apiKey)) return Promise.reject(unavailable());
  const candidate = window ?? (globalThis.window as unknown as GoogleMapsWindow | undefined);
  const win = candidate;
  const doc = document ?? win?.document;
  if (!win || !doc) return Promise.reject(unavailable());
  const runtimeWindow: GoogleMapsWindow = win;
  const existing = runtimes.get(win);
  if (existing) return existing.key === apiKey ? existing.promise : Promise.reject(unavailable());
  if (win.google?.maps?.Map) {
    const promise = Promise.resolve(win.google.maps);
    runtimes.set(win, { key: apiKey, promise });
    return promise;
  }
  const callback: `__kiriminajaGoogleMaps${number}` = `__kiriminajaGoogleMaps${++sequence}`;
  // Defer insertion to a microtask so even synchronous callbacks see the cached promise.
  const promise = new Promise<GoogleMapsAPI>((resolve, reject) => {
    queueMicrotask(() => {
      const script = doc.createElement('script');
      const previousAuth = runtimeWindow.gm_authFailure;
      let settled = false;
      const timer = setTimeout(() => finish(), 15000);
      function cleanup() {
        clearTimeout(timer);
        delete runtimeWindow[callback];
        script.onerror = null;
        if (runtimeWindow.gm_authFailure === authFailure) {
          if (previousAuth) runtimeWindow.gm_authFailure = previousAuth;
          else delete runtimeWindow.gm_authFailure;
        }
      }
      function finish(api?: GoogleMapsAPI) {
        if (settled) return;
        settled = true;
        cleanup();
        if (api?.Map) resolve(api);
        else {
          script.remove();
          reject(unavailable());
        }
      }
      function authFailure() {
        finish();
        try {
          previousAuth?.();
        } catch {
          /* Preserve the fixed loader diagnostic. */
        }
      }
      runtimeWindow.gm_authFailure = authFailure;
      win[callback] = () => finish(win.google?.maps);
      script.async = true;
      script.dataset.kiriminajaGoogleMaps = 'true';
      script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&callback=${callback}&v=weekly&loading=async`;
      script.onerror = () => finish();
      try {
        (doc.head ?? doc.documentElement).appendChild(script);
      } catch {
        finish();
      }
    });
  });
  // Failed loads stay cached: never silently fall back or inject a second runtime.
  runtimes.set(win, { key: apiKey, promise });
  return promise;
}
