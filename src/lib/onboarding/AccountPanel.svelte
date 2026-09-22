<script lang="ts">
  import type { OnboardingBootstrap } from './types';

  let { account }: { account: OnboardingBootstrap['account'] } = $props();
</script>

<p class="kiriof-onboarding__step-number">Step 1 of 4</p>
<h2>{account.title}</h2>
<p>{account.description}</p>

{#if account.connected && account.profile}
  <div class="kiriof-onboarding__account-shell kiriof-onboarding__account-card">
    <h3>{account.i18n.connection}</h3>
    <div class="kiriof-onboarding__profile">
      <span>{account.profile.name.slice(0, 1)}</span>
      <div><strong>{account.profile.name}</strong><small>{account.profile.email}</small></div>
      {#if account.profile.status}<em>{account.profile.status}</em>{/if}
      <button type="button" class="button kj-disconnect">{account.i18n.disconnect}</button>
    </div>
  </div>
{:else if account.connected}
  <div class="kiriof-onboarding__account-shell kiriof-onboarding__account-card">
    <h3>{account.i18n.connection}</h3>
    <p class="kiriof-onboarding__message">{account.i18n.unavailable}</p>
    <button type="button" class="button kj-disconnect">{account.i18n.disconnect}</button>
  </div>
{:else}
  <div class="kiriof-onboarding__field-group">
    <label for="kiriof-onboarding-setup-key">{account.i18n.setupKey}</label>
    <input id="kiriof-onboarding-setup-key" class="regular-text kiriof-onboarding__field" type="text" autocomplete="off" placeholder={account.i18n.setupKeyPlaceholder} />
    <p class="description">{account.i18n.findKey} <a href={account.helpUrl} target="_blank" rel="noopener noreferrer">{account.i18n.learnHow}</a></p>
  </div>
{/if}
<div class="kiriof-onboarding__message" data-step-message="account" role="alert"></div>
