<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { postWordPressAction } from '../wordpress/ajax';
  import type { GoogleMapsBootstrap, GoogleMapsSummary } from './types';

  let { bootstrap }: { bootstrap: GoogleMapsBootstrap } = $props();
  function initialSummary(): GoogleMapsSummary {
    return { configured: bootstrap.configured, maskedKey: bootstrap.maskedKey };
  }
  let summary = $state<GoogleMapsSummary>(initialSummary());
  let key = $state('');
  let busy = $state(false);
  let message = $state('');
  let failed = $state(false);
  // Only a masked summary may be rendered, including when a malformed server response arrives.
  const safeMask = (value: string) => /^[A-Za-z0-9_-]{4}\*{4,249}[A-Za-z0-9_-]{3}$/.test(value) ? value : '';

  async function update(mode: 'save' | 'remove'): Promise<void> {
    if (busy) return;
    busy = true;
    message = '';
    failed = false;
    try {
      const result = await postWordPressAction<GoogleMapsSummary>(
        'kiriof_save_google_maps_settings',
        { mode, ...(mode === 'save' ? { key } : {}) },
        { nonce: bootstrap.nonce },
      );
      if (!result.data || typeof result.data.configured !== 'boolean' || typeof result.data.maskedKey !== 'string') {
        throw new Error();
      }
      summary = { configured: result.data.configured, maskedKey: safeMask(result.data.maskedKey) };
      message = bootstrap.i18n.saved;
    } catch {
      // Do not display transport errors: they could contain submitted credentials.
      failed = true;
      message = bootstrap.i18n.failed;
    } finally {
      key = '';
      busy = false;
    }
  }
</script>

<section class="kiriof-section-card" aria-labelledby="kiriof-google-maps-title">
  <h2 id="kiriof-google-maps-title">{bootstrap.i18n.title}</h2>
  <p>{bootstrap.i18n.guidance}</p>
  {#if summary.configured}
    <p>{bootstrap.i18n.configured}: <code>{safeMask(summary.maskedKey)}</code></p>
  {:else}
    <p>{bootstrap.i18n.notConfigured}</p>
  {/if}
  <form onsubmit={(event) => { event.preventDefault(); void update('save'); }}>
    <label for="kiriof-google-maps-key">{bootstrap.i18n.keyLabel}</label>
    <input id="kiriof-google-maps-key" type="password" bind:value={key} autocomplete="new-password" spellcheck="false" placeholder={bootstrap.i18n.placeholder} disabled={busy} />
    <div class="kiriof-section-actions">
      <Button type="submit" class="kiriof-settings-action-button" disabled={busy}>{busy ? bootstrap.i18n.busy : bootstrap.i18n.save}</Button>
      {#if summary.configured}
        <Button type="button" class="kiriof-settings-action-button" disabled={busy} onclick={() => update('remove')}>{bootstrap.i18n.remove}</Button>
      {/if}
      {#if message}<span class:error={failed} class="kiriof-section-message" role={failed ? 'alert' : 'status'}>{message}</span>{/if}
    </div>
  </form>
</section>
