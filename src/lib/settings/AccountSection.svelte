<script lang="ts">
  import { Button } from 'bits-ui';
  import { postWordPressAction } from '../wordpress/ajax';
  import type { AccountBootstrap } from './types';

  let { bootstrap }: { bootstrap: AccountBootstrap } = $props();
  let setupKey = $state('');
  let busy = $state<'connect' | 'disconnect' | null>(null);
  let message = $state('');

  async function connect(): Promise<void> {
    if (!setupKey.trim()) {
      message = bootstrap.i18n.enterSetupKey;
      return;
    }
    busy = 'connect';
    message = '';
    try {
      await postWordPressAction('kiriof_store_integration_data', { setup_key: setupKey.trim() });
      window.location.reload();
    } catch (error) {
      message = error instanceof Error ? error.message : bootstrap.i18n.connectionFailed;
      busy = null;
    }
  }

  async function disconnect(): Promise<void> {
    if (!window.confirm(bootstrap.i18n.disconnectConfirm)) return;
    busy = 'disconnect';
    message = '';
    try {
      await postWordPressAction('kiriof_disconnect_integration', {});
      window.location.reload();
    } catch (error) {
      message = error instanceof Error ? error.message : bootstrap.i18n.disconnectFailed;
      busy = null;
    }
  }
</script>

<div class="kiriof-account">
  {#if bootstrap.couriers.length}
    <section class="kiriof-section-card"><h2>{bootstrap.i18n.enabledCouriers}</h2><div class="kiriof-courier-chips">{#each bootstrap.couriers as courier}<span><strong>{courier.code.toUpperCase()}</strong>{courier.name}</span>{/each}</div></section>
  {/if}
  <section class="kiriof-section-card">
    <h2>{bootstrap.i18n.connection}</h2>
    {#if bootstrap.connected && bootstrap.profile}
      <div class="kiriof-profile"><span class="kiriof-profile__avatar">{bootstrap.profile.name.slice(0, 1)}</span><div><strong>{bootstrap.profile.name}</strong><span>{bootstrap.profile.email}</span></div><span class="kiriof-diagnostics__badge">{bootstrap.profile.status}</span><Button.Root class="button kiriof-danger-button" disabled={busy !== null} onclick={disconnect}>{busy === 'disconnect' ? bootstrap.i18n.disconnecting : bootstrap.i18n.disconnect}</Button.Root></div>
    {:else if bootstrap.connected}
      <p class="kiriof-settings-root__message is-error">{bootstrap.profileError ? bootstrap.i18n.accountUnavailable : bootstrap.i18n.connectedUnavailable}</p>
      <Button.Root class="button kiriof-danger-button" disabled={busy !== null} onclick={disconnect}>{busy === 'disconnect' ? bootstrap.i18n.disconnecting : bootstrap.i18n.disconnect}</Button.Root>
    {:else}
      <label class="kiriof-connect__label" for="kiriof-account-key">{bootstrap.i18n.setupKey}</label>
      <div class="kiriof-inline-form"><input id="kiriof-account-key" class="regular-text" bind:value={setupKey} placeholder={bootstrap.i18n.setupKeyPlaceholder} /><Button.Root class="button button-primary" disabled={busy !== null} onclick={connect}>{busy === 'connect' ? bootstrap.i18n.connecting : bootstrap.i18n.connect}</Button.Root></div>
      <p class="description">{bootstrap.i18n.agreementPrefix} <a href={bootstrap.termsUrl} target="_blank" rel="noopener noreferrer">{bootstrap.i18n.terms}</a> {bootstrap.i18n.agreementAnd} <a href={bootstrap.privacyUrl} target="_blank" rel="noopener noreferrer">{bootstrap.i18n.privacy}</a>.</p>
    {/if}
    {#if message}<p class="kiriof-section-message error" role="alert">{message}</p>{/if}
  </section>
</div>
