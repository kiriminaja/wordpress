import { mount } from 'svelte';
import OnboardingApp from '../lib/onboarding/OnboardingApp.svelte';
import type { OnboardingBootstrap } from '../lib/onboarding/types';
import '../styles/kj-onboarding-svelte.css';

const host = document.querySelector<HTMLElement>('[data-kiriof-onboarding-app]');
const payload = document.querySelector<HTMLScriptElement>('[data-kiriof-onboarding-payload]');

if (host && payload?.textContent) {
  const bootstrap = JSON.parse(payload.textContent) as OnboardingBootstrap;
  mount(OnboardingApp, { target: host, props: { bootstrap } });
  host.removeAttribute('aria-busy');
  host.classList.add('is-mounted');
}
