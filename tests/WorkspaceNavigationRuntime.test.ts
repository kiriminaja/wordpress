import { describe, expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';
import { happy } from './helpers/ui-runtime';

// Only the module boundary is replaced: route selection, events, fetches, asset
// readiness, DOM replacement and history all execute the production entry.
// Components are protocol fakes, not coverage of Svelte rendering or CSS.
const entry = new Bun.Transpiler({ loader: 'ts' })
  .transformSync(
    readFileSync(new URL('../src/entries/admin-workspace.ts', import.meta.url), 'utf8'),
  )
  .replace(/^import\s*(?!\()[^;]*;\s*$/gm, '')
  .replace(/\bimport\s*\(/g, '__load(');
const execute = new Function(
  'window',
  'document',
  'navigator',
  'history',
  'DOMParser',
  'fetch',
  'AbortController',
  'mount',
  'unmount',
  '__load',
  entry,
);

const definitions = {
  transactions: ['kiriminaja-transaction', 'transactions', 'TransactionsApp'],
  detail: ['kiriminaja-transaction-detail', 'transaction-detail', 'TransactionDetail'],
  payments: ['kiriminaja-request-pickup', 'payments', 'PaymentsList'],
  pickup: ['kiriminaja-request-pickup-detail', 'pickup-detail', 'PickupDetail'],
  settings: ['kiriminaja-setting', 'settings', 'SettingsRoot'],
} as const;
type Route = keyof typeof definitions;

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (error: Error) => void;
  const promise = new Promise<T>((yes, no) => {
    resolve = yes;
    reject = no;
  });
  return { promise, resolve, reject };
}

function url(route: Route, extra = '') {
  return `https://fixture.test/wp-admin/admin.php?page=${definitions[route][0]}${extra}`;
}

function shell(route: Route, payload: object | null = {}, assets = '') {
  const name = definitions[route][1];
  return `<html><head><title>${route}</title>${assets}</head><body>
    <section data-kiriof-${name}-page>
      <div data-kiriof-${name}-root aria-busy="true">
        <div data-kiriof-workspace-boot aria-hidden="true">Loading workspace</div>
      </div>
      ${payload === null ? '' : `<script type="application/json" data-kiriof-${name}-payload>${JSON.stringify(payload)}</script>`}
    </section></body></html>`;
}

async function until(check: () => boolean, label = 'runtime update') {
  for (let attempt = 0; attempt < 200; attempt++) {
    if (check()) return;
    await new Promise((resolve) => setTimeout(resolve, 1));
  }
  throw new Error(`Timed out waiting for ${label}`);
}

function runtime(route: Route = 'transactions', payload: object | null = {}) {
  const window = new happy.Window({
    url: url(route),
    settings: { disableJavaScriptFileLoading: true, disableJavaScriptEvaluation: true },
  });
  window.document.write(shell(route, payload));
  // Route script readiness is driven explicitly, never by a network request.
  const append = window.document.body.append.bind(window.document.body);
  window.document.body.append = (...nodes: any[]) => {
    for (const node of nodes) {
      if (node?.tagName === 'SCRIPT' && node.hasAttribute('src')) node.removeAttribute('src');
    }
    append(...nodes);
  };
  const loads: string[] = [];
  const mounts: {
    component: string;
    target: HTMLElement;
    props: Record<string, any>;
    boot: boolean;
  }[] = [];
  const unmounts: unknown[] = [];
  const requests: {
    url: string;
    options: RequestInit;
    response: ReturnType<typeof deferred<Response>>;
  }[] = [];
  const gates = new Map<string, ReturnType<typeof deferred<void>>>();
  const modules = new Map<string, Promise<{ default: string }>>();
  let mountError: Error | undefined;
  let reloads = 0;
  Object.defineProperty(window.location, 'reload', {
    configurable: true,
    value: () => {
      reloads++;
    },
  });
  const load = (path: string) => {
    if (!modules.has(path)) {
      const name = path.split('/').at(-1)!.replace('.svelte', '');
      loads.push(name);
      modules.set(
        path,
        (gates.get(name)?.promise ?? Promise.resolve()).then(() => ({ default: name })),
      );
    }
    return modules.get(path)!;
  };
  const fetch = (value: URL, options: RequestInit) => {
    const response = deferred<Response>();
    requests.push({ url: String(value), options, response });
    // Intentionally ignore abort here, like a response already queued by the
    // browser: sequence guards must still reject stale results.
    return response.promise;
  };
  return {
    window,
    loads,
    mounts,
    unmounts,
    requests,
    gates,
    get reloads() {
      return reloads;
    },
    failMount(error: Error) {
      mountError = error;
    },
    start() {
      execute(
        window,
        window.document,
        window.navigator,
        window.history,
        window.DOMParser,
        fetch,
        AbortController,
        (component: string, options: { target: HTMLElement; props: Record<string, any> }) => {
          if (mountError) throw mountError;
          const record = {
            component,
            ...options,
            boot: !!options.target.querySelector('[data-kiriof-workspace-boot]'),
          };
          mounts.push(record);
          const node = window.document.createElement('p');
          node.dataset.runtimeComponent = component;
          options.target.append(node);
          return { node };
        },
        (component: { node: HTMLElement }) => {
          unmounts.push(component);
          component.node.remove();
        },
        load,
      );
    },
    link(route: Route, attributes: Record<string, string> = {}, extra = '') {
      const anchor = window.document.createElement('a');
      anchor.href = url(route, extra);
      for (const [name, value] of Object.entries(attributes)) anchor.setAttribute(name, value);
      window.document.body.append(anchor);
      return anchor;
    },
    event(anchor: HTMLAnchorElement, type: string) {
      const event = new window.MouseEvent(type, { bubbles: true, cancelable: true, button: 0 });
      anchor.dispatchEvent(event);
      return event;
    },
    respond(index: number, route: Route, assets = '') {
      requests[index].response.resolve(new Response(shell(route, {}, assets)));
    },
    close() {
      window.happyDOM.abort();
    },
  };
}

async function ready(app: ReturnType<typeof runtime>) {
  app.start();
  await until(() => app.mounts.length === 1, 'initial mount');
  // start() registers delegation after its awaited render continuation.
  await new Promise((resolve) => setTimeout(resolve, 0));
}

describe('workspace entry routing runtime (component import/mount protocol fakes)', () => {
  for (const route of Object.keys(definitions) as Route[]) {
    test(`${route} startup imports only its active component and retains boot through mount`, async () => {
      const app = runtime(route);
      const gate = deferred<void>();
      app.gates.set(definitions[route][2], gate);
      try {
        app.start();
        expect(app.loads).toEqual([definitions[route][2]]);
        expect(app.mounts).toHaveLength(0);
        expect(app.window.document.querySelector('[data-kiriof-workspace-boot]')).not.toBeNull();
        gate.resolve();
        await until(() => app.mounts.length === 1);
        expect(app.mounts[0].boot).toBe(true);
        expect(app.mounts[0].component).toBe(definitions[route][2]);
        expect(app.window.document.querySelector('[data-kiriof-workspace-boot]')).toBeNull();
        expect(app.mounts[0].target.classList.contains('is-mounted')).toBe(true);
      } finally {
        app.close();
      }
    });
  }

  for (const [view, component] of [
    ['account', 'AccountSection'],
    ['couriers', 'CouriersSection'],
    ['tracking', 'TrackingSection'],
    ['technical', 'TechnicalSection'],
  ]) {
    test(`settings ${view} loads only its selected section`, async () => {
      const app = runtime('settings', { view });
      try {
        await ready(app);
        expect(app.loads).toEqual([component]);
        expect(app.mounts[0].props.bootstrap).toEqual({ view });
      } finally {
        app.close();
      }
    });
  }

  test('hover and focus preload route code, including pickup detail, without page fetches', async () => {
    const app = runtime();
    try {
      await ready(app);
      app.event(app.link('pickup'), 'pointerover');
      app.event(app.link('settings', {}, '&section=account'), 'focusin');
      await until(() => app.loads.length === 3);
      expect(app.loads).toEqual(['TransactionsApp', 'PickupDetail', 'AccountSection']);
      expect(app.requests).toHaveLength(0);
      expect(app.mounts).toHaveLength(1);
      expect(app.window.location.href).toBe(url('transactions'));
    } finally {
      app.close();
    }
  });

  for (const connection of [
    { saveData: true },
    { effectiveType: '2g' },
    { effectiveType: 'slow-2g' },
  ]) {
    test(`preloading is disabled for ${JSON.stringify(connection)}`, async () => {
      const app = runtime();
      try {
        await ready(app);
        Object.defineProperty(app.window.navigator, 'connection', {
          value: connection,
          configurable: true,
        });
        app.event(app.link('pickup'), 'pointerover');
        app.event(app.link('payments'), 'focusin');
        expect(app.loads).toEqual(['TransactionsApp']);
        expect(app.requests).toHaveLength(0);
      } finally {
        app.close();
      }
    });
  }

  test('external, download and new-tab anchors are neither intercepted nor preloaded', async () => {
    const app = runtime();
    try {
      await ready(app);
      const links = [
        app.link('pickup', { download: '' }),
        app.link('payments', { target: '_blank' }),
        app.link('settings', {
          href: 'https://external.test/wp-admin/admin.php?page=kiriminaja-setting',
        }),
      ];
      for (const anchor of links) {
        app.event(anchor, 'pointerover');
        app.event(anchor, 'focusin');
        // Cancel native default after production delegation, avoiding happy-dom
        // navigation while still observing whether the entry intercepted it.
        let intercepted = false;
        const cancelNative = (event: Event) => {
          intercepted = event.defaultPrevented;
          event.preventDefault();
        };
        app.window.document.addEventListener('click', cancelNative, { once: true });
        app.event(anchor, 'click');
        expect(intercepted).toBe(false);
      }
      expect(app.loads).toEqual(['TransactionsApp']);
      expect(app.requests).toHaveLength(0);
    } finally {
      app.close();
    }
  });

  for (const failure of ['payload', 'import', 'mount'] as const) {
    test(`${failure} startup failure preserves a visible retry instead of an empty root`, async () => {
      const app = runtime('transactions', failure === 'payload' ? null : {});
      if (failure === 'import') {
        const gate = deferred<void>();
        app.gates.set('TransactionsApp', gate);
        app.start();
        gate.reject(new Error('chunk unavailable'));
      } else {
        if (failure === 'mount') app.failMount(new Error('mount failed'));
        app.start();
      }
      try {
        await until(() => !!app.window.document.querySelector('button'), 'retry UI');
        const boot = app.window.document.querySelector('[data-kiriof-workspace-boot]');
        expect(boot).not.toBeNull();
        expect(boot!.hasAttribute('aria-hidden')).toBe(false);
        expect(boot!.textContent).toContain('Unable to load the workspace.');
        const retry = boot!.querySelector('button')!;
        expect(retry.textContent).toBe('Retry');
        retry.click();
        expect(app.reloads).toBe(1);
        expect(app.mounts).toHaveLength(0);
      } finally {
        app.close();
      }
    });
  }

  test('soft navigation retains the mounted shell until route script and component are ready', async () => {
    const app = runtime();
    try {
      await ready(app);
      const oldShell = app.window.document.querySelector('[data-kiriof-transactions-page]');
      const component = deferred<void>();
      app.gates.set('PickupDetail', component);
      expect(app.event(app.link('pickup'), 'click').defaultPrevented).toBe(true);
      expect(app.requests).toHaveLength(1);
      expect(app.requests[0].options.credentials).toBe('same-origin');
      expect(app.requests[0].options.headers).toEqual({ 'X-KiriminAja-Workspace': 'admin' });
      app.respond(
        0,
        'pickup',
        '<script id="kiriof-request-pickup-js" src="https://fixture.test/pickup.js"></script>',
      );
      await until(
        () => !!app.window.document.getElementById('kiriof-request-pickup-js'),
        'route script',
      );
      expect(oldShell!.isConnected).toBe(true);
      expect(app.unmounts).toHaveLength(0);
      expect(app.window.location.href).toBe(url('transactions'));
      app.window.document
        .getElementById('kiriof-request-pickup-js')!
        .dispatchEvent(new app.window.Event('load'));
      await until(() => app.loads.includes('PickupDetail'));
      expect(oldShell!.isConnected).toBe(true);
      expect(app.unmounts).toHaveLength(0);
      component.resolve();
      await until(() => app.window.location.href === url('pickup'), 'pickup history');
      expect(oldShell!.isConnected).toBe(false);
      expect(app.unmounts).toHaveLength(1);
      expect(app.mounts.at(-1)!.component).toBe('PickupDetail');
      expect(app.window.document.title).toBe('pickup');
    } finally {
      app.close();
    }
  });

  test('late stale fetch cannot replace the newer shell, title or history', async () => {
    const app = runtime();
    try {
      await ready(app);
      app.event(app.link('payments'), 'click');
      app.event(app.link('pickup'), 'click');
      expect(app.requests).toHaveLength(2);
      expect(app.requests[0].options.signal!.aborted).toBe(true);
      app.respond(1, 'pickup');
      await until(() => app.window.location.href === url('pickup'));
      const historyLength = app.window.history.length;
      const latest = app.window.document.querySelector('[data-kiriof-pickup-detail-page]');
      app.respond(0, 'payments');
      await new Promise((resolve) => setTimeout(resolve, 20));
      expect(latest!.isConnected).toBe(true);
      expect(app.mounts.map((mount) => mount.component)).toEqual([
        'TransactionsApp',
        'PickupDetail',
      ]);
      expect(app.window.location.href).toBe(url('pickup'));
      expect(app.window.history.length).toBe(historyLength);
      expect(app.window.document.title).toBe('pickup');
    } finally {
      app.close();
    }
  });
});
