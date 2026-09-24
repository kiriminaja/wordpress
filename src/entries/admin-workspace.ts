import { mount, unmount } from 'svelte';
import SettingsRoot from '../lib/SettingsRoot.svelte';
import AccountSection from '../lib/settings/AccountSection.svelte';
import CouriersSection from '../lib/settings/CouriersSection.svelte';
import TechnicalSection from '../lib/settings/TechnicalSection.svelte';
import TrackingSection from '../lib/settings/TrackingSection.svelte';
import WebhooksSection from '../lib/settings/WebhooksSection.svelte';
import PaymentsList from '../lib/payments/PaymentsList.svelte';
import PaymentsModals from '../lib/payments/PaymentsModals.svelte';
import TransactionsApp from '../lib/transactions/TransactionsApp.svelte';
import type { SettingsAppBootstrap } from '../lib/settings/types';
import type { PaymentsBootstrap } from '../lib/payments/types';
import type { TransactionsBootstrap } from '../lib/transactions/types';
import shadcnStyles from '../styles/shadcn-onboarding.css?inline';
import toolbarStyles from '../styles/toolbar.css?inline';
import adminListStyles from '../styles/admin-list.css?inline';
import paymentsStyles from '../styles/payments-list.css?inline';
import settingsStyles from '../styles/settings-root.css?inline';

const workspacePages = new Set([
  'kiriminaja-transaction-process',
  'kiriminaja-request-pickup',
  'kiriminaja-konfigurasi',
]);

type WorkspaceRoute = 'transactions' | 'payments' | 'settings';
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
    page: 'kiriminaja-transaction-process',
    shell: '[data-kiriof-transactions-page]',
    root: '[data-kiriof-transactions-root]',
    payload: '[data-kiriof-transactions-payload]',
    route: 'transactions',
  },
  {
    page: 'kiriminaja-request-pickup',
    shell: '[data-kiriof-payments-page]',
    root: '[data-kiriof-payments-root]',
    payload: '[data-kiriof-payments-payload]',
    route: 'payments',
  },
  {
    page: 'kiriminaja-konfigurasi',
    shell: '[data-kiriof-settings-page]',
    root: '[data-kiriof-settings-root]',
    payload: '[data-kiriof-settings-payload]',
    route: 'settings',
  },
];

let mounted: MountedComponent[] = [];
let controller: AbortController | null = null;
let activeShell: HTMLElement | null = null;

function installWorkspaceStyles(): void {
  if (document.querySelector('[data-kiriof-workspace-styles]')) return;

  const style = document.createElement('style');
  style.dataset.kiriofWorkspaceStyles = 'true';
  style.textContent = [
    shadcnStyles,
    toolbarStyles,
    adminListStyles,
    paymentsStyles,
    settingsStyles,
  ].join('\n');
  document.head.append(style);
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

function renderSettings(host: HTMLElement, bootstrap: SettingsAppBootstrap): void {
  if (bootstrap.view === 'account')
    mounted = [mount(AccountSection, { target: host, props: { bootstrap } })];
  else if (bootstrap.view === 'couriers')
    mounted = [mount(CouriersSection, { target: host, props: { bootstrap } })];
  else if (bootstrap.view === 'tracking')
    mounted = [mount(TrackingSection, { target: host, props: { bootstrap } })];
  else if (bootstrap.view === 'webhooks')
    mounted = [mount(WebhooksSection, { target: host, props: { bootstrap } })];
  else if (bootstrap.view === 'technical')
    mounted = [mount(TechnicalSection, { target: host, props: { bootstrap } })];
  else mounted = [mount(SettingsRoot, { target: host, props: { bootstrap } })];
}

function render(source: ParentNode, route: RouteDefinition): void {
  const host = source.querySelector<HTMLElement>(route.root);
  if (!host) throw new Error(`Missing ${route.route} workspace root.`);

  if (route.route === 'transactions') {
    const bootstrap = parsePayload<TransactionsBootstrap>(source, route);
    mounted = [
      mount(TransactionsApp, { target: host, props: { bootstrap, onNavigate: navigate } }),
    ];
  } else if (route.route === 'payments') {
    const bootstrap = parsePayload<PaymentsBootstrap>(source, route);
    const modalHost = source.querySelector<HTMLElement>('[data-kiriof-payments-modals-root]');
    mounted = [
      mount(PaymentsList, {
        target: host,
        props: { initialBootstrap: bootstrap, onNavigate: navigate },
      }),
    ];
    if (modalHost)
      mounted.push(mount(PaymentsModals, { target: modalHost, props: { i18n: bootstrap.modals } }));
    host
      .closest<HTMLElement>('[data-kiriof-payments-page]')
      ?.classList.add('kiriof-payments-page--enhanced');
  } else {
    renderSettings(host, parsePayload<SettingsAppBootstrap>(source, route));
  }

  host.removeAttribute('aria-busy');
  host.classList.add('is-mounted');
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
  const nextMenu = source.querySelector<HTMLElement>('#toplevel_page_kiriminaja-konfigurasi');
  const currentMenu = document.querySelector<HTMLElement>('#toplevel_page_kiriminaja-konfigurasi');
  if (!nextMenu || !currentMenu) return;

  currentMenu.className = nextMenu.className;
  const currentSubmenu = currentMenu.querySelector<HTMLElement>('.wp-submenu');
  const nextSubmenu = nextMenu.querySelector<HTMLElement>('.wp-submenu');
  if (currentSubmenu && nextSubmenu) currentSubmenu.replaceWith(nextSubmenu.cloneNode(true));
}

async function navigate(value: string | URL, push = true): Promise<void> {
  const url = new URL(value, window.location.href);
  if (!isWorkspaceUrl(url)) {
    window.location.assign(url);
    return;
  }

  controller?.abort();
  controller = new AbortController();
  activeShell?.setAttribute('aria-busy', 'true');

  try {
    const response = await fetch(url, {
      credentials: 'same-origin',
      signal: controller.signal,
      headers: { 'X-KiriminAja-Workspace': 'admin' },
    });
    const nextDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
    const route = routeForUrl(url);
    const nextShell = route ? nextDocument.querySelector<HTMLElement>(route.shell) : null;
    if (!response.ok || !route || !nextShell || !nextDocument.querySelector(route.payload))
      throw new Error('Unable to load workspace page.');

    await ensureRouteAssets(nextDocument);
    await clearMounted();
    const replacement = nextShell.cloneNode(true) as HTMLElement;
    activeShell?.replaceWith(replacement);
    activeShell = replacement;
    render(document, route);
    syncPluginMenu(nextDocument);
    document.title = nextDocument.title || document.title;
    if (push) history.pushState({ kiriofWorkspace: true }, '', url);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch (error) {
    if ((error as Error).name !== 'AbortError') window.location.assign(url);
  } finally {
    activeShell?.removeAttribute('aria-busy');
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

function start(): void {
  const route = routeForDocument(document);
  if (!route) return;
  installWorkspaceStyles();
  activeShell = document.querySelector<HTMLElement>(route.shell);
  if (!activeShell) return;

  try {
    render(document, route);
  } catch {
    window.location.reload();
    return;
  }

  document.addEventListener('click', handleClick);
  window.addEventListener('popstate', () => void navigate(window.location.href, false));
  window.addEventListener('kiriof:settings-navigate', (event) => {
    const href = (event as CustomEvent<{ href: string }>).detail?.href;
    if (href) void navigate(href);
  });
}

start();
