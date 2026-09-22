import { mount } from 'svelte';
import OnboardingProgress from '../lib/OnboardingProgress.svelte';
import AccountPanel from '../lib/onboarding/AccountPanel.svelte';
import CouriersPanel from '../lib/onboarding/CouriersPanel.svelte';
import ShippingPanel from '../lib/onboarding/ShippingPanel.svelte';
import type { OnboardingBootstrap } from '../lib/onboarding/types';
import '../styles/foundation.css';

type Step = {
  key: string;
  label: string;
  done: boolean;
};

const host = document.querySelector<HTMLElement>('[data-kiriof-progress]');
const payload = document.querySelector<HTMLScriptElement>('[data-kiriof-onboarding-payload]');

if (host && payload?.textContent) {
  const bootstrap = JSON.parse(payload.textContent) as OnboardingBootstrap;
  const steps = JSON.parse(host.dataset.steps ?? '[]') as Step[];
  const currentStep = host.dataset.currentStep ?? steps[0]?.key ?? 'account';
  const navigationLabel = host.dataset.navigationLabel ?? '';

  mount(OnboardingProgress, {
    target: host,
    props: { steps, currentStep, navigationLabel },
  });

  const accountHost = document.querySelector<HTMLElement>('[data-kiriof-account-panel]');
  const couriersHost = document.querySelector<HTMLElement>('[data-kiriof-couriers-panel]');
  const shippingHost = document.querySelector<HTMLElement>('[data-kiriof-shipping-panel]');

  if (accountHost)
    mount(AccountPanel, { target: accountHost, props: { account: bootstrap.account } });
  if (couriersHost)
    mount(CouriersPanel, { target: couriersHost, props: { config: bootstrap.couriers } });
  if (shippingHost)
    mount(ShippingPanel, { target: shippingHost, props: { config: bootstrap.shipping } });

  host
    .closest<HTMLElement>('[data-kiriof-onboarding]')
    ?.classList.add('kiriof-onboarding--enhanced');
}
