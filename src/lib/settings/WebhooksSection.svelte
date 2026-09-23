<script lang="ts">
  import { IconDeviceFloppy } from '@tabler/icons-svelte';
  import { Button } from 'bits-ui';
  import { postWordPressAction } from '../wordpress/ajax';
  import type { WebhooksBootstrap } from './types';
  import Toolbar from '$lib/ui/Toolbar.svelte';
  import { navigateSettings } from './navigation';

  let { bootstrap }: { bootstrap: WebhooksBootstrap } = $props();
  function initialCallbackUrl(): string {
    return bootstrap.callbackUrl;
  }
  let callbackUrl = $state(initialCallbackUrl());
  let saving = $state(false);
  let message = $state('');
  let error = $state(false);

  async function save(): Promise<void> {
    saving = true;
    message = '';
    error = false;

    try {
      await postWordPressAction('kiriof_store_call_back_data', { callback_url: callbackUrl.trim() });
      message = bootstrap.i18n.saved;
    } catch (requestError) {
      error = true;
      message = requestError instanceof Error ? requestError.message : bootstrap.i18n.saveFailed;
    } finally {
      saving = false;
    }
  }
</script>

<Toolbar toolbar={bootstrap.toolbar} onNavigate={navigateSettings}>
  <Button.Root class="button button-primary" disabled={saving} onclick={save}>
    <IconDeviceFloppy size={16} stroke={2} aria-hidden="true" />
    {saving ? bootstrap.i18n.saving : bootstrap.i18n.save}
  </Button.Root>
</Toolbar>
<section class="kiriof-section-card kiriof-settings-content" aria-labelledby="kiriof-webhooks-title">
  <h2 id="kiriof-webhooks-title">{bootstrap.i18n.callbackUrl}</h2>
  <label class="screen-reader-text" for="kiriof-callback-url">{bootstrap.i18n.callbackUrl}</label>
  <input
    id="kiriof-callback-url"
    class="regular-text kiriof-section-input"
    type="url"
    bind:value={callbackUrl}
    placeholder="https://"
  />
  <div class="kiriof-section-actions">
    {#if message}
      <span class:error class="kiriof-section-message" role={error ? 'alert' : 'status'}>{message}</span>
    {/if}
  </div>
</section>
