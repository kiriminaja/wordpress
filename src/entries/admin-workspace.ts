import { mount, unmount } from 'svelte';
import type { Component } from 'svelte';
import type { SettingsAppBootstrap } from '../lib/settings/types';
import type { PaymentsBootstrap } from '../lib/payments/types';
import type { PickupDetailBootstrap } from '../lib/pickup-detail/types';
import type { TransactionsBootstrap } from '../lib/transactions/types';
import type { TransactionDetailBootstrap } from '../lib/transaction-detail/types';
import '../styles/toolbar.css';
import '../styles/admin-list.css';
import '../styles/payments-list.css';
import '../styles/settings-root.css';

const workspacePages = new Set([
  'kiriminaja-transaction',
  'kiriminaja-transaction-detail',
  'kiriminaja-request-pickup',
  'kiriminaja-request-pickup-detail',
  'kiriminaja-setting',
]);

type WorkspaceRoute =
  | 'transactions'
  | 'transaction-detail'
  | 'payments'
  | 'pickup-detail'
  | 'settings';
type MountedComponent = ReturnType<typeof mount>;

type RouteDefinition = {
  page: string;
  shell: string;
  root: string;
  payload: string;
  route: WorkspaceRoute;
};

const routes: RouteDefinition[] = [
  {
    page: 'kiriminaja-transaction',
    shell: '[data-kiriof-transactions-page]',
    root: '[data-kiriof-transactions-root]',
    payload: '[data-kiriof-transactions-payload]',
    route: 'transactions',
  },
  {
    page: 'kiriminaja-transaction-detail',
    shell: '[data-kiriof-transaction-detail-page]',
    root: '[data-kiriof-transaction-detail-root]',
    payload: '[data-kiriof-transaction-detail-payload]',
    route: 'transaction-detail',
  },
  {
    page: 'kiriminaja-request-pickup',
    shell: '[data-kiriof-payments-page]',
    root: '[data-kiriof-payments-root]',
    payload: '[data-kiriof-payments-payload]',
    route: 'payments',
  },
  {
    page: 'kiriminaja-request-pickup-detail',
    shell: '[data-kiriof-pickup-detail-page]',
    root: '[data-kiriof-pickup-detail-root]',
    payload: '[data-kiriof-pickup-detail-payload]',
    route: 'pickup-detail',
  },
  {
    page: 'kiriminaja-setting',
    shell: '[data-kiriof-settings-page]',
    root: '[data-kiriof-settings-root]',
    payload: '[data-kiriof-settings-payload]',
    route: 'settings',
  },
];

const loaders = {
  transactions: () => import('../lib/transactions/TransactionsApp.svelte'),
  payments: () => import('../lib/payments/PaymentsList.svelte'),
  'transaction-detail': () => import('../lib/transaction-detail/TransactionDetail.svelte'),
  'pickup-detail': () => import('../lib/pickup-detail/PickupDetail.svelte'),
  settings: (view = '') => {
    switch (view) {
      case 'account':
        return import('../lib/settings/AccountSection.svelte');
      case 'couriers':
        return import('../lib/settings/CouriersSection.svelte');
      case 'tracking':
        return import('../lib/settings/TrackingSection.svelte');
      case 'technical':
        return import('../lib/settings/TechnicalSection.svelte');
      default:
        return import('../lib/SettingsRoot.svelte');
    }
  },
};

let mounted: MountedComponent[] = [];
let controller: AbortController | null = null;
let activeShell: HTMLElement | null = null;
let navigationSequence = 0;
let loadingProgress = 0;
let loadingTimer: number | null = null;
let loadingHideTimer: number | null = null;

function loadingIndicator(): HTMLDivElement {
  const existing = document.querySelector<HTMLDivElement>('[data-kiriof-loading-indicator]');
  if (existing) return existing;

  const indicator = document.createElement('div');
  indicator.className = 'kiriof-loading-indicator';
  indicator.dataset.kiriofLoadingIndicator = 'true';
  indicator.setAttribute('role', 'progressbar');
  indicator.setAttribute('aria-label', 'Loading page');
  indicator.setAttribute('aria-valuemin', '0');
  indicator.setAttribute('aria-valuemax', '100');
  indicator.setAttribute('aria-hidden', 'true');
  indicator.innerHTML = '<span class="kiriof-loading-indicator__bar"></span>';
  document.body.append(indicator);
  return indicator;
}

function setLoadingProgress(progress: number): void {
  loadingProgress = Math.max(0, Math.min(100, progress));
  const indicator = loadingIndicator();
  indicator.style.setProperty('--kiriof-loading-progress', `${loadingProgress}%`);
  indicator.setAttribute('aria-valuenow', String(Math.round(loadingProgress)));
}

function startLoadingIndicator(): void {
  if (loadingHideTimer) window.clearTimeout(loadingHideTimer);
  if (loadingTimer) window.clearInterval(loadingTimer);

  const indicator = loadingIndicator();
  indicator.classList.remove('is-finishing');
  indicator.classList.add('is-active');
  indicator.setAttribute('aria-hidden', 'false');
  setLoadingProgress(8);

  loadingTimer = window.setInterval(() => {
    const remaining = 92 - loadingProgress;
    setLoadingProgress(loadingProgress + Math.max(0.6, remaining * 0.08));
  }, 180);
}

function finishLoadingIndicator(): void {
  if (loadingTimer) window.clearInterval(loadingTimer);
  loadingTimer = null;
  setLoadingProgress(100);

  const indicator = loadingIndicator();
  indicator.classList.add('is-finishing');
  loadingHideTimer = window.setTimeout(() => {
    indicator.classList.remove('is-active', 'is-finishing');
    indicator.setAttribute('aria-hidden', 'true');
    setLoadingProgress(0);
  }, 240);
}

function routeForUrl(url: URL): RouteDefinition | undefined {
  return routes.find((route) => route.page === url.searchParams.get('page'));
}

function isRouteAsset(script: HTMLScriptElement): boolean {
  return (
    Boolean(script.id) && /^kiriof-(?:transaction-process|request-pickup)(?:-|$)/.test(script.id)
  );
}

function loadScript(script: HTMLScriptElement): Promise<void> {
  return new Promise((resolve, reject) => {
    const nextScript = document.createElement('script');
    nextScript.id = script.id;
    nextScript.src = script.src;
    nextScript.async = false;
    nextScript.onload = () => resolve();
    nextScript.onerror = () => reject(new Error(`Unable to load ${script.src}.`));
    document.body.append(nextScript);
  });
}

async function ensureRouteAssets(source: Document): Promise<void> {
  for (const style of source.querySelectorAll<HTMLLinkElement>(
    'link[rel="stylesheet"][id^="kiriof-"], link[rel="stylesheet"][id^="woocommerce"]',
  )) {
    if (document.getElementById(style.id)) continue;
    const nextStyle = document.createElement('link');
    nextStyle.id = style.id;
    nextStyle.rel = 'stylesheet';
    nextStyle.href = style.href;
    document.head.append(nextStyle);
  }

  for (const script of source.querySelectorAll<HTMLScriptElement>('script')) {
    if (!isRouteAsset(script) || document.getElementById(script.id)) continue;
    if (script.src) await loadScript(script);
    else if (script.id.endsWith('-js-extra')) {
      const inlineScript = document.createElement('script');
      inlineScript.id = script.id;
      inlineScript.textContent = script.textContent;
      document.body.append(inlineScript);
    }
  }
}

function routeForDocument(
  source: ParentNode,
  url = new URL(window.location.href),
): RouteDefinition | undefined {
  return routeForUrl(url) ?? routes.find((route) => source.querySelector(route.shell) !== null);
}

function parsePayload<T>(source: ParentNode, route: RouteDefinition): T {
  const payload = source.querySelector<HTMLScriptElement>(route.payload)?.textContent;
  if (!payload) throw new Error(`Missing ${route.route} workspace payload.`);
  return JSON.parse(payload) as T;
}

async function clearMounted(): Promise<void> {
  const components = mounted;
  mounted = [];
  await Promise.all(components.map((component) => unmount(component)));
}

function clearBootSkeleton(host: HTMLElement): void {
  // Server-rendered placeholder (templates/_workspace-boot.php) keeps the WP
  // content area from flashing blank. Remove it only after mounting succeeds.
  host.querySelector('[data-kiriof-workspace-boot]')?.remove();
}

function showWorkspaceError(host: HTMLElement): void {
  const boot =
    host.querySelector<HTMLElement>('[data-kiriof-workspace-boot]') ??
    document.createElement('div');
  boot.replaceChildren();
  const message = document.createElement('p');
  message.textContent = 'Unable to load the workspace.';
  const retry = document.createElement('button');
  retry.type = 'button';
  retry.className = 'button';
  retry.textContent = 'Retry';
  retry.addEventListener('click', () => window.location.reload());
  boot.append(message, retry);
  boot.removeAttribute('aria-hidden');
  if (!boot.isConnected) host.append(boot);
  host.removeAttribute('aria-busy');
}

async function render(
  source: ParentNode,
  route: RouteDefinition,
  url = new URL(window.location.href),
  navigationId = navigationSequence,
): Promise<boolean> {
  const host = source.querySelector<HTMLElement>(route.root);
  if (!host) throw new Error(`Missing ${route.route} workspace root.`);
  const shell = activeShell;
  const isCurrent = () =>
    navigationId === navigationSequence &&
    host.isConnected &&
    shell === activeShell &&
    !!shell?.contains(host);
  let components: MountedComponent[];

  if (route.route === 'transactions') {
    const bootstrap = parsePayload<TransactionsBootstrap>(source, route);
    const { default: TransactionsApp } = await loaders.transactions();
    if (!isCurrent()) return false;
    components = [
      mount(TransactionsApp, { target: host, props: { bootstrap, onNavigate: navigate } }),
    ];
  } else if (route.route === 'payments') {
    const bootstrap = parsePayload<PaymentsBootstrap>(source, route);
    const { default: PaymentsList } = await loaders.payments();
    if (!isCurrent()) return false;
    components = [
      mount(PaymentsList, {
        target: host,
        props: { initialBootstrap: bootstrap, onNavigate: navigate },
      }),
    ];
  } else if (route.route === 'transaction-detail') {
    const bootstrap = parsePayload<TransactionDetailBootstrap>(source, route);
    const { default: TransactionDetail } = await loaders['transaction-detail']();
    if (!isCurrent()) return false;
    components = [
      mount(TransactionDetail, {
        target: host,
        props: {
          bootstrap,
          onNavigate: navigate,
          openAdjustDeficit: url.searchParams.get('adjust_deficit') === '1',
        },
      }),
    ];
  } else if (route.route === 'pickup-detail') {
    const bootstrap = parsePayload<PickupDetailBootstrap>(source, route);
    const { default: PickupDetail } = await loaders['pickup-detail']();
    if (!isCurrent()) return false;
    components = [
      mount(PickupDetail, { target: host, props: { bootstrap, onNavigate: navigate } }),
    ];
  } else {
    const bootstrap = parsePayload<SettingsAppBootstrap>(source, route);
    const { default: SettingsSection } = await loaders.settings(bootstrap.view);
    if (!isCurrent()) return false;
    // The bootstrap discriminant selects the matching section module above.
    components = [
      mount(SettingsSection as Component<{ bootstrap: SettingsAppBootstrap }>, {
        target: host,
        props: { bootstrap },
      }),
    ];
  }

  if (!isCurrent()) {
    await Promise.all(components.map((component) => unmount(component)));
    return false;
  }
  mounted = components;
  if (route.route === 'payments') shell?.classList.add('kiriof-payments-page--enhanced');
  clearBootSkeleton(host);
  host.removeAttribute('aria-busy');
  host.classList.add('is-mounted');
  return true;
}

function isWorkspaceUrl(value: string | URL): value is string | URL {
  const url = new URL(value, window.location.href);
  return (
    url.origin === window.location.origin &&
    url.pathname === window.location.pathname &&
    workspacePages.has(url.searchParams.get('page') ?? '')
  );
}

function syncPluginMenu(source: Document): void {
  const nextMenu = source.querySelector<HTMLElement>('#toplevel_page_kiriminaja-setting');
  const currentMenu = document.querySelector<HTMLElement>('#toplevel_page_kiriminaja-setting');
  if (!nextMenu || !currentMenu) return;

  currentMenu.className = nextMenu.className;
  const currentSubmenu = currentMenu.querySelector<HTMLElement>('.wp-submenu');
  const nextSubmenu = nextMenu.querySelector<HTMLElement>('.wp-submenu');
  if (currentSubmenu && nextSubmenu) currentSubmenu.replaceWith(nextSubmenu.cloneNode(true));
}

async function navigate(value: string | URL, push = true): Promise<void> {
  const url = new URL(value, window.location.href);
  if (!isWorkspaceUrl(url)) {
    startLoadingIndicator();
    window.location.assign(url);
    return;
  }

  const navigationId = ++navigationSequence;
  controller?.abort();
  controller = new AbortController();
  const navigationController = controller;
  startLoadingIndicator();
  activeShell?.setAttribute('aria-busy', 'true');

  try {
    const response = await fetch(url, {
      credentials: 'same-origin',
      signal: navigationController.signal,
      headers: { 'X-KiriminAja-Workspace': 'admin' },
    });
    const nextDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
    const route = routeForUrl(url);
    const nextShell = route ? nextDocument.querySelector<HTMLElement>(route.shell) : null;
    if (!response.ok || !route || !nextShell || !nextDocument.querySelector(route.payload))
      throw new Error('Unable to load workspace page.');

    await ensureRouteAssets(nextDocument);
    if (route.route === 'settings') {
      await loaders.settings(parsePayload<SettingsAppBootstrap>(nextDocument, route).view);
    } else {
      await loaders[route.route]();
    }
    if (navigationId !== navigationSequence || navigationController.signal.aborted) return;
    await clearMounted();
    if (navigationId !== navigationSequence || navigationController.signal.aborted) return;
    const replacement = nextShell.cloneNode(true) as HTMLElement;
    activeShell?.replaceWith(replacement);
    activeShell = replacement;
    if (!(await render(document, route, url, navigationId))) return;
    if (navigationId !== navigationSequence || navigationController.signal.aborted) return;
    syncPluginMenu(nextDocument);
    document.title = nextDocument.title || document.title;
    if (push) {
      url.searchParams.delete('adjust_deficit');
      history.pushState({ kiriofWorkspace: true }, '', url);
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch (error) {
    if (navigationId === navigationSequence && (error as Error).name !== 'AbortError')
      window.location.assign(url);
  } finally {
    if (navigationId === navigationSequence) {
      activeShell?.removeAttribute('aria-busy');
      finishLoadingIndicator();
    }
  }
}

function clickIsNavigable(event: MouseEvent, anchor: HTMLAnchorElement): boolean {
  return (
    !event.defaultPrevented &&
    event.button === 0 &&
    !event.metaKey &&
    !event.ctrlKey &&
    !event.shiftKey &&
    !event.altKey &&
    !anchor.target &&
    !anchor.hasAttribute('download')
  );
}

function handleClick(event: MouseEvent): void {
  const anchor = (event.target as Element | null)?.closest<HTMLAnchorElement>('a[href]');
  if (!anchor || !clickIsNavigable(event, anchor) || !isWorkspaceUrl(anchor.href)) return;
  event.preventDefault();
  void navigate(anchor.href);
}

function preloadRoute(url: URL): Promise<unknown> {
  const route = routeForUrl(url);
  if (route?.route === 'settings') {
    const section = (url.searchParams.get('section') ?? '')
      .toLowerCase()
      .replace(/[^a-z0-9_-]/g, '');
    return loaders.settings(section === 'cache' || section === 'webhooks' ? 'technical' : section);
  }
  return route ? loaders[route.route]() : Promise.resolve();
}

function handlePreload(event: Event): void {
  const anchor = (event.target as Element | null)?.closest<HTMLAnchorElement>('a[href]');
  if (!anchor || anchor.target || anchor.hasAttribute('download') || !isWorkspaceUrl(anchor.href))
    return;
  const connection = (
    navigator as Navigator & { connection?: { saveData?: boolean; effectiveType?: string } }
  ).connection;
  if (connection?.saveData || /(^|-)2g$/.test(connection?.effectiveType ?? '')) return;
  void preloadRoute(new URL(anchor.href)).catch(() => {});
}

async function start(): Promise<void> {
  const route = routeForDocument(document);
  if (!route) return;
  activeShell = document.querySelector<HTMLElement>(route.shell);
  if (!activeShell) return;
  const shell = activeShell;
  const navigationId = navigationSequence;

  document.addEventListener('click', handleClick);
  document.addEventListener('pointerover', handlePreload);
  document.addEventListener('focusin', handlePreload);
  window.addEventListener('popstate', () => void navigate(window.location.href, false));
  window.addEventListener('kiriof:settings-navigate', (event) => {
    const href = (event as CustomEvent<{ href: string }>).detail?.href;
    if (href) void navigate(href);
  });

  try {
    if (!(await render(document, route, new URL(window.location.href), navigationId))) return;
    if (
      route.route === 'transaction-detail' &&
      window.location.search.includes('adjust_deficit=')
    ) {
      const url = new URL(window.location.href);
      url.searchParams.delete('adjust_deficit');
      history.replaceState(history.state, '', url);
    }
  } catch {
    if (navigationId === navigationSequence && shell.isConnected) showWorkspaceError(shell);
    return;
  }
}

void start();
