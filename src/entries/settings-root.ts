import { mount } from 'svelte';
import SettingsRoot from '../lib/SettingsRoot.svelte';
import AccountSection from '../lib/settings/AccountSection.svelte';
import CouriersSection from '../lib/settings/CouriersSection.svelte';
import TechnicalSection from '../lib/settings/TechnicalSection.svelte';
import type { SettingsAppBootstrap } from '../lib/settings/types';
import WebhooksSection from '../lib/settings/WebhooksSection.svelte';
import TrackingSection from '../lib/settings/TrackingSection.svelte';
import '../styles/settings-root.css';

const host = document.querySelector<HTMLElement>('[data-kiriof-settings-root]');
const payload = document.querySelector<HTMLScriptElement>('[data-kiriof-settings-payload]');

if (host && payload?.textContent) {
  const bootstrap = JSON.parse(payload.textContent) as SettingsAppBootstrap;

  if (bootstrap.view === 'account') {
    mount(AccountSection, { target: host, props: { bootstrap } });
  } else if (bootstrap.view === 'couriers') {
    mount(CouriersSection, { target: host, props: { bootstrap } });
  } else if (bootstrap.view === 'tracking') {
    mount(TrackingSection, { target: host, props: { bootstrap } });
  } else if (bootstrap.view === 'webhooks') {
    mount(WebhooksSection, { target: host, props: { bootstrap } });
  } else if (bootstrap.view === 'technical') {
    mount(TechnicalSection, { target: host, props: { bootstrap } });
  } else {
    mount(SettingsRoot, { target: host, props: { bootstrap } });
  }

  host.removeAttribute('aria-busy');
  host.classList.add('is-mounted');
}
