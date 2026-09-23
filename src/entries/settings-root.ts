import { mount, unmount } from 'svelte';
import SettingsRoot from '../lib/SettingsRoot.svelte';
import AccountSection from '../lib/settings/AccountSection.svelte';
import CouriersSection from '../lib/settings/CouriersSection.svelte';
import TechnicalSection from '../lib/settings/TechnicalSection.svelte';
import type { SettingsAppBootstrap } from '../lib/settings/types';
import WebhooksSection from '../lib/settings/WebhooksSection.svelte';
import TrackingSection from '../lib/settings/TrackingSection.svelte';
import '../styles/toolbar.css';
import '../styles/settings-root.css';

const host = document.querySelector<HTMLElement>('[data-kiriof-settings-root]');
const payload = document.querySelector<HTMLScriptElement>('[data-kiriof-settings-payload]');

if (host && payload?.textContent) {
  const settingsHost = host;
  let component: ReturnType<typeof mount> | null = null;

  function render(bootstrap: SettingsAppBootstrap): void {
    if (component) void unmount(component);
    settingsHost.replaceChildren();

    if (bootstrap.view === 'account')
      component = mount(AccountSection, { target: settingsHost, props: { bootstrap } });
    else if (bootstrap.view === 'couriers')
      component = mount(CouriersSection, { target: settingsHost, props: { bootstrap } });
    else if (bootstrap.view === 'tracking')
      component = mount(TrackingSection, { target: settingsHost, props: { bootstrap } });
    else if (bootstrap.view === 'webhooks')
      component = mount(WebhooksSection, { target: settingsHost, props: { bootstrap } });
    else if (bootstrap.view === 'technical')
      component = mount(TechnicalSection, { target: settingsHost, props: { bootstrap } });
    else component = mount(SettingsRoot, { target: settingsHost, props: { bootstrap } });
  }

  async function navigate(href: string, push = true): Promise<void> {
    settingsHost.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(href, { credentials: 'same-origin' });
      const documentHtml = new DOMParser().parseFromString(await response.text(), 'text/html');
      const nextPayload = documentHtml.querySelector<HTMLScriptElement>(
        '[data-kiriof-settings-payload]',
      );
      if (!response.ok || !nextPayload?.textContent)
        throw new Error('Unable to load settings view.');
      render(JSON.parse(nextPayload.textContent) as SettingsAppBootstrap);
      if (push) history.pushState({ kiriofSettings: true }, '', href);
      document.title = documentHtml.title || document.title;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch {
      window.location.assign(href);
    } finally {
      settingsHost.removeAttribute('aria-busy');
    }
  }

  render(JSON.parse(payload.textContent) as SettingsAppBootstrap);
  settingsHost.removeAttribute('aria-busy');
  settingsHost.classList.add('is-mounted');
  window.addEventListener('kiriof:settings-navigate', (event) => {
    const href = (event as CustomEvent<{ href: string }>).detail.href;
    void navigate(href);
  });
  window.addEventListener('popstate', () => void navigate(window.location.href, false));
}
