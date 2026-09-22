import { mount } from 'svelte';
import OnboardingApp from '../lib/onboarding/OnboardingApp.svelte';
import type { OnboardingStep } from '../lib/onboarding/OnboardingApp.svelte';
import type { OnboardingBootstrap } from '../lib/onboarding/types';
import '../styles/shadcn-onboarding.css';
import '../styles/kj-onboarding-svelte.css';

type Step = { key: string; label: string; done: boolean };

const host = document.querySelector<HTMLElement>('[data-kiriof-onboarding-app]');
const payload = document.querySelector<HTMLScriptElement>('[data-kiriof-onboarding-payload]');

if (host && payload?.textContent) {
  const bootstrap = JSON.parse(payload.textContent) as OnboardingBootstrap;
  const progressHost = document.querySelector<HTMLElement>('[data-kiriof-progress]');
  const steps = JSON.parse(progressHost?.dataset.steps ?? '[]') as Step[];
  const initialStep = (progressHost?.dataset.currentStep ??
    steps[0]?.key ??
    'account') as OnboardingStep;

  mount(OnboardingApp, { target: host, props: { bootstrap, steps, initialStep } });
  host.closest<HTMLElement>('[data-kiriof-onboarding]')?.classList.add('kiriof-onboarding--app');
  host
    .closest<HTMLElement>('[data-kiriof-onboarding]')
    ?.querySelector('.kiriof-onboarding__progress-fallback')
    ?.remove();
  host.parentElement?.querySelector('[data-kiriof-onboarding-fallback]')?.remove();
}
