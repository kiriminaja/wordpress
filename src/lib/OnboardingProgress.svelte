<script lang="ts">
  import { onMount } from 'svelte';
  import ProgressStep from './ui/ProgressStep.svelte';

  type Step = {
    key: string;
    label: string;
    done: boolean;
  };

  let {
    steps,
    currentStep,
    navigationLabel,
  }: {
    steps: Step[];
    currentStep: string;
    navigationLabel: string;
  } = $props();

  function selectStep(step: string): void {
    window.dispatchEvent(
      new CustomEvent('kiriof:onboarding-step', {
        detail: { step },
      }),
    );
  }

  onMount(() => {
    const syncState = (event: Event): void => {
      const detail = (event as CustomEvent<{ currentStep: string; steps: Step[] }>).detail;
      if (!detail) {
        return;
      }

      currentStep = detail.currentStep;
      steps = detail.steps;
    };

    window.addEventListener('kiriof:onboarding-state', syncState);
    return () => window.removeEventListener('kiriof:onboarding-state', syncState);
  });
</script>

<nav class="kiriof-onboarding__progress kiriof-onboarding__progress--enhanced" aria-label={navigationLabel}>
  {#each steps as step, index}
    <ProgressStep
      index={index + 1}
      label={step.label}
      stepKey={step.key}
      current={step.key === currentStep}
      done={step.done}
      onclick={() => selectStep(step.key)}
    />
  {/each}
</nav>
