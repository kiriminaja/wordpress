import { mount } from 'svelte';
import OnboardingProgress from '../lib/OnboardingProgress.svelte';
import '../styles/foundation.css';

type Step = {
  key: string;
  label: string;
  done: boolean;
};

const host = document.querySelector<HTMLElement>('[data-kiriof-progress]');

if (host) {
  const steps = JSON.parse(host.dataset.steps ?? '[]') as Step[];
  const currentStep = host.dataset.currentStep ?? steps[0]?.key ?? 'account';
  const navigationLabel = host.dataset.navigationLabel ?? '';

  mount(OnboardingProgress, {
    target: host,
    props: { steps, currentStep, navigationLabel },
  });

  host
    .closest<HTMLElement>('[data-kiriof-onboarding]')
    ?.classList.add('kiriof-onboarding--enhanced');
}
