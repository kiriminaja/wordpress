<script lang="ts">
  import { IconCheck, IconClock } from '@tabler/icons-svelte';
  import { onMount } from 'svelte';
  import type { OnboardingBootstrap } from './types';

  let { config }: { config: OnboardingBootstrap['shipping'] } = $props();
  function initialState(): { shippingReady: boolean; locationsReady: boolean } {
    return { shippingReady: config.shippingReady, locationsReady: config.locationsReady };
  }
  const initial = initialState();
  let shippingReady = $state(initial.shippingReady);
  let locationsReady = $state(initial.locationsReady);

  onMount(() => {
    const sync = (event: Event): void => {
      const detail = (event as CustomEvent<{ shippingReady: boolean; locationsReady: boolean }>).detail;
      if (!detail) return;
      shippingReady = detail.shippingReady;
      locationsReady = detail.locationsReady;
    };
    window.addEventListener('kiriof:onboarding-shipping-state', sync);
    return () => window.removeEventListener('kiriof:onboarding-shipping-state', sync);
  });
</script>

<p class="kiriof-onboarding__step-number">Step 4 of 4</p>
<h2>{config.title}</h2>
<p>{config.description}</p>
<div class="kiriof-onboarding__checks">
  <div class:is-done={shippingReady} class:is-pending={!shippingReady} class="kiriof-onboarding__check">
    {#if shippingReady}<IconCheck size={20} />{:else}<IconClock size={20} />{/if}
    <div><strong>{config.i18n.methodTitle}</strong><p>{config.i18n.methodDescription}</p></div>
  </div>
  <div class:is-done={locationsReady} class:is-pending={!locationsReady} class="kiriof-onboarding__check">
    {#if locationsReady}<IconCheck size={20} />{:else}<IconClock size={20} />{/if}
    <div><strong>{config.i18n.locationsTitle}</strong><p>{config.i18n.locationsDescription}</p></div>
  </div>
</div>
<p class="kiriof-onboarding__shipping-help">{config.i18n.help} <a href={config.settingsUrl}>{config.i18n.openSettings}</a></p>
<div class="kiriof-onboarding__message" data-step-message="shipping" role="alert"></div>
