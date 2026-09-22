<script lang="ts">
  import { onMount } from 'svelte';
  import SettingSwitch from '../ui/SettingSwitch.svelte';
  import type { OnboardingBootstrap, OnboardingCourier } from './types';

  let { config }: { config: OnboardingBootstrap['couriers'] } = $props();
  let couriers = $state<OnboardingCourier[]>([]);
  let enabled = $state<Record<string, string>>({});
  let loading = $state(true);

  function publish(action: 'toggle' | 'all' | 'none', courier?: OnboardingCourier, checked?: boolean): void {
    window.dispatchEvent(new CustomEvent('kiriof:onboarding-courier-intent', { detail: { action, courier, checked } }));
  }

  onMount(() => {
    const sync = (event: Event): void => {
      const detail = (event as CustomEvent<{ couriers: OnboardingCourier[]; enabled: Record<string, string>; loading: boolean }>).detail;
      if (!detail) return;
      couriers = detail.couriers;
      enabled = detail.enabled;
      loading = detail.loading;
    };
    window.addEventListener('kiriof:onboarding-couriers-state', sync);
    window.dispatchEvent(new CustomEvent('kiriof:onboarding-couriers-request'));
    return () => window.removeEventListener('kiriof:onboarding-couriers-state', sync);
  });
</script>

<p class="kiriof-onboarding__step-number">Step 3 of 4</p>
<h2>{config.title}</h2>
<p>{config.description}</p>
<div class="kiriof-onboarding__actions kiriof-onboarding__courier-actions">
  <div class="kiriof-onboarding__segmented-actions" role="group" aria-label="Courier bulk actions">
    <button type="button" class="button" onclick={() => publish('all')}>{config.i18n.enableAll}</button>
    <button type="button" class="button" onclick={() => publish('none')}>{config.i18n.disableAll}</button>
  </div>
  <span class="kiriof-onboarding__courier-count">{Object.keys(enabled).length} {config.i18n.enabled}</span>
</div>
{#if loading}
  <p>{config.i18n.loading}</p>
{:else if couriers.length === 0}
  <p>{config.i18n.empty}</p>
{:else}
  <div class="kiriof-onboarding__couriers">
    {#each couriers as courier}
      <div class="kiriof-onboarding__courier">
        <span><strong>{courier.name}</strong><br /><small>{courier.type ?? ''}</small></span>
        <SettingSwitch label={`${config.i18n.enable} ${courier.name}`} checked={Object.hasOwn(enabled, courier.code)} onCheckedChange={(checked) => publish('toggle', courier, checked)} />
      </div>
    {/each}
  </div>
{/if}
<div class="kiriof-onboarding__message" data-step-message="couriers" role="alert"></div>
