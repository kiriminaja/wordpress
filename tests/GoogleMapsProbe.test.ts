import { expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../assets/admin/js/google-maps-browser-check.js', import.meta.url), 'utf8');
const channel = 'abcdef0123456789abcdef0123456789';

// Execute only the shipped iframe script. SDK and clock boundaries are inert,
// deterministic fixtures: this suite never loads Google or starts a web server.
function probe(apiKey = 'AIza-synthetic_probe_key', origin = 'https://store.test') {
  let now = 0, next = 0, tiles: (() => void) | undefined, maps = 0, removed = 0, cleared = 0;
  const timers = new Map<number, { at: number; callback: () => void }>();
  const events = new Map<string, () => void>();
  const messages: Array<{ data: any; origin: string }> = [];
  const scripts: any[] = [];
  const status = { textContent: '' };
  const config = { channel, apiKey, targetOrigin: 'https://fixture.test', probeOrigin: 'https://store.test', i18n: { ready: 'Map ready', error: 'Map failed' } };
  const window: any = {
    location: { origin },
    parent: { postMessage(data: unknown, origin: string) { messages.push({ data, origin }); } },
    setTimeout(callback: () => void, delay: number) { const id = ++next; timers.set(id, { callback, at: now + delay }); return id; },
    clearTimeout(id: number) { timers.delete(id); },
    addEventListener(name: string, callback: () => void) { events.set(name, callback); },
    removeEventListener(name: string) { events.delete(name); },
    google: { maps: { Map: class {
      constructor() { maps++; }
      addListener(name: string, callback: () => void) { expect(name).toBe('tilesloaded'); tiles = callback; return { remove() { removed++; } }; }
    }, event: { clearInstanceListeners() { cleared++; } } } },
  };
  const document = {
    getElementById(id: string) { return id.endsWith('-config') ? { textContent: JSON.stringify(config) } : id.endsWith('-status') ? status : {}; },
    createElement(name: string) { expect(name).toBe('script'); return { remove() { this.removed = true; }, removed: false }; },
    head: { appendChild(script: unknown) { scripts.push(script); } },
  };
  runInNewContext(source, { window, document });
  function tick(ms: number) {
    const end = now + ms;
    while (true) {
      const entry = Array.from(timers).filter(([, timer]) => timer.at <= end).sort((a, b) => a[1].at - b[1].at)[0];
      if (!entry) break;
      now = entry[1].at; timers.delete(entry[0]); entry[1].callback();
    }
    now = end;
  }
  return { window, messages, scripts, status, tick, sdk: () => window['kiriofGoogleMapsCheck_' + channel]?.(), tiles: () => tiles?.(), dispose: () => events.get('pagehide')?.(), pending: () => timers.size, maps: () => maps, removed: () => removed, cleared: () => cleared };
}

function expectResult(h: ReturnType<typeof probe>, status: string, code: string, index = 0) {
  expect(h.messages[index]).toEqual({ origin: 'https://fixture.test', data: { type: 'kiriof-google-maps-check', channel, status, code } });
}

test('probe fails closed before Google loads when redirected away from the configured storefront origin', () => {
  const h = probe('AIza-synthetic_probe_key', 'https://fixture.test');
  expectResult(h, 'error', 'render_failed');
  expect(h.scripts).toHaveLength(0); expect(h.maps()).toBe(0); h.dispose();
});

test('probe without a saved key reports not configured without loading an SDK', () => {
  const h = probe(''); expectResult(h, 'error', 'not_configured');
  expect(h.scripts).toHaveLength(0); expect(h.pending()).toBe(0); h.dispose();
});

test('probe requires actual tiles and the authentication grace delay, not an SDK callback', () => {
  const h = probe();
  expect(h.scripts).toHaveLength(1);
  expect(h.scripts[0].referrerPolicy).toBe('strict-origin-when-cross-origin');
  h.sdk(); expect(h.maps()).toBe(1); expect(h.messages).toHaveLength(0);
  h.tick(500); expect(h.messages).toHaveLength(0);
  h.tiles(); h.tick(799); expect(h.messages).toHaveLength(0);
  h.tick(1); expectResult(h, 'ready', 'tiles_loaded'); expect(h.status.textContent).toBe('Map ready');
  h.tick(20000); expect(h.messages).toHaveLength(1); h.dispose();
});

test('authentication failure before the callback prevents map rendering and never leaks provider details', () => {
  const h = probe(); h.window.gm_authFailure('credential-bearing provider error'); h.sdk(); h.tick(20000);
  expectResult(h, 'error', 'auth_failed'); expect(h.maps()).toBe(0); expect(h.messages).toHaveLength(1); h.dispose();
});

test('authentication failure during tile grace or after readiness overrides success', () => {
  for (const delay of [0, 800]) {
    const h = probe(); h.sdk(); h.tiles(); h.tick(delay);
    h.window.gm_authFailure(); h.tick(20000);
    expectResult(h, 'error', 'auth_failed', delay ? 1 : 0);
    expect(h.messages).toHaveLength(delay ? 2 : 1); expect(h.status.textContent).toBe('Map failed'); h.dispose();
  }
});

test('SDK network failure and deadline report distinct safe errors', () => {
  const network = probe(); network.scripts[0].onerror(new Error('raw secret')); network.tick(20000);
  expectResult(network, 'error', 'network_error'); expect(network.messages).toHaveLength(1); network.dispose();
  const timeout = probe(); timeout.sdk(); timeout.tick(14999); expect(timeout.messages).toHaveLength(0);
  timeout.tick(1); expectResult(timeout, 'error', 'timeout'); timeout.dispose();
});

test('pagehide disposes pending timers, SDK hooks, script and map listeners', () => {
  const h = probe(); h.sdk(); h.tiles(); const auth = h.window.gm_authFailure;
  h.dispose(); expect(h.pending()).toBe(0); expect(h.removed()).toBeGreaterThan(0); expect(h.cleared()).toBe(1);
  expect(h.scripts[0].removed).toBe(true); expect(h.scripts[0].onerror).toBeNull();
  expect(h.window.gm_authFailure).toBeUndefined(); expect(h.window['kiriofGoogleMapsCheck_' + channel]).toBeUndefined();
  h.tick(20000); auth(); expect(h.messages).toHaveLength(0);
});

test('tiles arriving just before the deadline retain the full authentication grace', () => {
  for (const reject of [false, true]) {
    const h = probe(); h.sdk(); h.tick(14999); h.tiles();
    h.tick(799); expect(h.messages).toHaveLength(0);
    if (reject) h.window.gm_authFailure();
    h.tick(1);
    expectResult(h, reject ? 'error' : 'ready', reject ? 'auth_failed' : 'tiles_loaded');
    h.tick(20000); expect(h.messages).toHaveLength(1); h.dispose();
  }
});
