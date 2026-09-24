<script lang="ts">
  import { IconEye, IconEyeOff, IconInfoCircle, IconLink } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import Toolbar from '$lib/ui/Toolbar.svelte';
  import { courierImage } from '$lib/transactions/courier-images';
  import { postWordPressAction } from '../wordpress/ajax';
  import { navigateSettings } from './navigation';
  import type { AccountBootstrap } from './types';

  let { bootstrap }: { bootstrap: AccountBootstrap } = $props();
  function initialAccountState(): Pick<AccountBootstrap, 'connected' | 'profile' | 'profileError'> {
    return {
      connected: bootstrap.connected,
      profile: bootstrap.profile,
      profileError: bootstrap.profileError,
    };
  }
  const initialAccount = initialAccountState();
  let setupKey = $state('');
  let busy = $state(false);
  let message = $state('');
  let connected = $state(initialAccount.connected);
  let profile = $state(initialAccount.profile);
  let profileError = $state(initialAccount.profileError);
  let revealSetupKey = $state(false);

  async function updateConnection(): Promise<void> {
    if (!setupKey.trim()) {
      message = bootstrap.i18n.enterSetupKey;
      return;
    }

    busy = true;
    message = '';
    try {
      const response = await postWordPressAction<{
        connected?: boolean;
        profile?: AccountBootstrap['profile'];
        profileError?: boolean;
      }>('kiriof_store_integration_data', { setup_key: setupKey.trim() });
      connected = response.data?.connected ?? true;
      profile = response.data?.profile ?? profile;
      profileError = response.data?.profileError ?? !profile;
      setupKey = '';
    } catch (error) {
      message = error instanceof Error ? error.message : bootstrap.i18n.connectionFailed;
    } finally {
      busy = false;
    }
  }
</script>

<Toolbar toolbar={bootstrap.toolbar} onNavigate={navigateSettings} />
<div class="kiriof-account kiriof-settings-content">
  <section class="kiriof-section-card kiriof-account-couriers">
    <h2>{bootstrap.i18n.enabledCouriers}</h2>
    {#if bootstrap.couriers.length}
      <div class="kiriof-account-courier-grid">
        {#each bootstrap.couriers as courier (courier.code)}
          <div class="kiriof-account-courier-logo" title={courier.name}>
            {#if courierImage(courier.code, courier.name)}
              <img src={courierImage(courier.code, courier.name)} alt={courier.name} />
            {:else}
              <span>{courier.name}</span>
            {/if}
          </div>
        {/each}
      </div>
    {:else}
      <p class="description">—</p>
    {/if}
  </section>

  <section class="kiriof-section-card kiriof-connection-card">
    <h2>{bootstrap.i18n.connection}</h2>
    <div class="kiriof-connection-layout">
      <div class="kiriof-connection-form">
        {#if connected && profile}
          <div class="kiriof-linked-profile">
            <div>
              <div class="kiriof-linked-profile__name">
                <strong>{profile.name}</strong>
                {#if profile.paymentMethod}<span>{profile.paymentMethod}</span>{/if}
              </div>
              <span>{profile.email}</span>
            </div>
            <span class="kiriof-linked-profile__badge"><IconLink />{bootstrap.i18n.linkedAccount}</span>
          </div>
        {:else if connected}
          <p class="kiriof-settings-root__message is-error">
            {profileError ? bootstrap.i18n.accountUnavailable : bootstrap.i18n.connectedUnavailable}
          </p>
        {/if}

        <label class="kiriof-connect__label" for="kiriof-account-key">{bootstrap.i18n.setupKey} <span aria-hidden="true">*</span></label>
        <div class="kiriof-setup-key-field">
          <Input
            id="kiriof-account-key"
            class="kiriof-setup-key-input"
            type={revealSetupKey ? 'text' : 'password'}
            bind:value={setupKey}
            placeholder={bootstrap.i18n.setupKeyPlaceholder}
            autocomplete="off"
          />
          <button
            type="button"
            class="kiriof-setup-key-toggle"
            onclick={() => (revealSetupKey = !revealSetupKey)}
            aria-label={revealSetupKey ? 'Hide setup key' : 'Show setup key'}
          >
            {#if revealSetupKey}<IconEyeOff />{:else}<IconEye />{/if}
          </button>
        </div>

        <div class="kiriof-connection-consent">
          <IconInfoCircle />
          <div>
            <strong>{bootstrap.i18n.privacyTitle}</strong>
            <p>{bootstrap.i18n.agreementPrefix} <a href={bootstrap.termsUrl} target="_blank" rel="noopener noreferrer">{bootstrap.i18n.terms}</a> {bootstrap.i18n.agreementAnd} <a href={bootstrap.privacyUrl} target="_blank" rel="noopener noreferrer">{bootstrap.i18n.privacy}</a>.</p>
          </div>
        </div>

        {#if message}<p class="kiriof-section-message error" role="alert">{message}</p>{/if}

        <Button class="kiriof-update-connection" disabled={busy || !setupKey.trim()} onclick={updateConnection}>
          <IconLink class="kiriof-update-connection__icon" aria-hidden="true" />
          <span>{busy ? bootstrap.i18n.connecting : connected ? bootstrap.i18n.updateConnection : bootstrap.i18n.connect}</span>
        </Button>
      </div>

      <aside class="kiriof-credentials-guide">
        <h3>{bootstrap.i18n.credentialsTitle}</h3>
        <ol>
          {#each bootstrap.i18n.credentialsSteps as step, index}
            <li>
              {#if index === 0}
                <span>{step}</span>
                <a href={bootstrap.dashboardUrl} target="_blank" rel="noopener noreferrer">{bootstrap.dashboardUrl}</a>
              {:else}
                {step}
              {/if}
            </li>
          {/each}
        </ol>
      </aside>
    </div>
  </section>
</div>
