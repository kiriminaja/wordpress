<script lang="ts">
  import * as Card from '$lib/components/ui/card';
  import * as Field from '$lib/components/ui/field';
  import * as Alert from '$lib/components/ui/alert';
  import { Input } from '$lib/components/ui/input';
  import { Badge } from '$lib/components/ui/badge';
  import { Spinner } from '$lib/components/ui/spinner';
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

<Card.Root aria-labelledby="kiriof-google-maps-title">
  <Card.Header>
    <Card.Title><h2 id="kiriof-google-maps-title">{bootstrap.i18n.title}</h2></Card.Title>
    <Card.Description>{bootstrap.i18n.guidance}</Card.Description>
  </Card.Header>
  <Card.Content class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center gap-2">
      <Badge tone={summary.configured ? 'success' : 'warning'}>{summary.configured ? bootstrap.i18n.configured : bootstrap.i18n.notConfigured}</Badge>
      {#if summary.configured}<code>{safeMask(summary.maskedKey)}</code>{/if}
    </div>
    <form class="flex flex-col gap-4" aria-busy={busy} onsubmit={(event) => { event.preventDefault(); void update('save'); }}>
      <Field.Group>
        <Field.Field data-disabled={busy}>
          <Field.Label for="kiriof-google-maps-key">{bootstrap.i18n.keyLabel}</Field.Label>
          <Input id="kiriof-google-maps-key" type="password" bind:value={key} autocomplete="new-password" spellcheck="false" placeholder={bootstrap.i18n.placeholder} disabled={busy} aria-describedby="kiriof-google-maps-guidance" />
          <Field.Description id="kiriof-google-maps-guidance">{bootstrap.i18n.guidance}</Field.Description>
        </Field.Field>
      </Field.Group>
      <div class="flex flex-wrap gap-2">
        <Button type="submit" disabled={busy}>
          {#if busy}<Spinner data-icon="inline-start" aria-hidden="true" />{/if}
          {busy ? bootstrap.i18n.busy : bootstrap.i18n.save}
        </Button>
        {#if summary.configured}
          <Button type="button" variant="outline" disabled={busy} onclick={() => update('remove')}>{bootstrap.i18n.remove}</Button>
        {/if}
      </div>
      {#if message}
        <Alert.Root variant={failed ? 'destructive' : 'default'} role={failed ? 'alert' : 'status'}>
          <Alert.Description>{message}</Alert.Description>
        </Alert.Root>
      {/if}
    </form>
  </Card.Content>
</Card.Root>
