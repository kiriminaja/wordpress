<script lang="ts">
  import { onMount } from 'svelte';
  import { IconEye, IconEyeOff, IconRefresh } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import { Spinner } from '$lib/components/ui/spinner';
  import * as Card from '$lib/components/ui/card';
  import * as Field from '$lib/components/ui/field';
  import * as InputGroup from '$lib/components/ui/input-group';
  import * as Alert from '$lib/components/ui/alert';
  import { postWordPressAction } from '../wordpress/ajax';
  import { mapsCheckResult, mapsCheckUrl, type MapsCheckCode } from './google-maps-check';
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
  let reveal = $state(false);
  let preview = $state<HTMLIFrameElement>();
  let probe = $state<{ url: string; channel: string; origin: string } | null>(null);
  let checkState = $state<'idle' | 'checking' | 'ready' | 'error'>('idle');
  let checkCode = $state<MapsCheckCode>('not_configured');
  let testedOrigin = $state('');
  let mounted = false;
  let timer: ReturnType<typeof setTimeout> | undefined;
  const safeMask = (value: string) => /^[A-Za-z0-9_-]{4}\*{4,249}[A-Za-z0-9_-]{3}$/.test(value) ? value : '';
  const checkMessage = $derived(
    checkState === 'checking' ? bootstrap.i18n.checking : checkState === 'ready' ? bootstrap.i18n.browserReady :
    checkCode === 'not_configured' ? bootstrap.i18n.browserNotConfigured :
    checkCode === 'network_error' ? bootstrap.i18n.browserNetwork : checkCode === 'timeout' ? bootstrap.i18n.browserTimeout : bootstrap.i18n.browserFailed,
  );

  function stopCheck(): void {
    clearTimeout(timer);
    probe = null;
    preview = undefined;
  }

  function checkBrowser(): void {
    stopCheck();
    if (!mounted || !summary.configured) {
      checkState = 'idle';
      checkCode = 'not_configured';
      return;
    }
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    const channel = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    const url = mapsCheckUrl(bootstrap.browserCheckUrl, channel, window.location);
    if (!url) {
      checkState = 'error';
      checkCode = 'network_error';
      return;
    }
    testedOrigin = url.origin;
    checkState = 'checking';
    probe = { url: url.href, origin: url.origin, channel };
    timer = setTimeout(() => {
      if (mounted && probe?.channel === channel && checkState === 'checking') {
        checkState = 'error';
        checkCode = 'timeout';
        stopCheck();
      }
    }, 20000);
  }

  onMount(() => {
    mounted = true;
    function receive(event: MessageEvent): void {
      if (!probe || !preview) return;
      const result = mapsCheckResult(event, preview, probe.origin, probe.channel);
      if (!result) return;
      clearTimeout(timer);
      checkState = result.status;
      checkCode = result.code;
    }
    window.addEventListener('message', receive);
    checkBrowser();
    return () => {
      mounted = false;
      window.removeEventListener('message', receive);
      stopCheck();
    };
  });

  async function update(mode: 'save' | 'remove'): Promise<void> {
    if (busy) return;
    busy = true;
    message = '';
    failed = false;
    stopCheck();
    checkState = 'idle';
    checkCode = 'not_configured';
    try {
      const result = await postWordPressAction<GoogleMapsSummary>(
        'kiriof_save_google_maps_settings',
        { mode, ...(mode === 'save' ? { key } : {}) },
        { nonce: bootstrap.nonce },
      );
      if (!result.data || typeof result.data.configured !== 'boolean' || typeof result.data.maskedKey !== 'string') throw new Error();
      if (!mounted) return;
      summary = { configured: result.data.configured, maskedKey: safeMask(result.data.maskedKey) };
      message = bootstrap.i18n.saved;
    } catch {
      if (!mounted) return;
      failed = true;
      message = bootstrap.i18n.failed;
    } finally {
      key = '';
      reveal = false;
      busy = false;
      if (mounted) checkBrowser();
    }
  }
</script>

<section class="kiriof-shadcn" aria-labelledby="kiriof-google-maps-title">
  <Card.Root>
    <Card.Header>
      <div class="flex min-w-0 flex-col gap-2">
        <Card.Title id="kiriof-google-maps-title" role="heading" aria-level={2}>{bootstrap.i18n.title}</Card.Title>
        <Card.Description>{bootstrap.i18n.guidance}</Card.Description>
      </div>
    </Card.Header>
    <Card.Content class="flex flex-col gap-6">
      {#if summary.configured}
        <p>{bootstrap.i18n.configured}: <code class="break-all">{safeMask(summary.maskedKey)}</code></p>
      {:else}
        <p>{bootstrap.i18n.notConfigured}</p>
      {/if}
      <form onsubmit={(event) => { event.preventDefault(); void update('save'); }}>
        <Field.FieldGroup>
          <Field.Field data-disabled={busy} data-invalid={failed}>
            <Field.FieldLabel for="kiriof-google-maps-key">{bootstrap.i18n.keyLabel}</Field.FieldLabel>
            <InputGroup.Root>
              <InputGroup.Input id="kiriof-google-maps-key" type={reveal ? 'text' : 'password'} bind:value={key} autocomplete="new-password" spellcheck="false" autocapitalize="none" placeholder={bootstrap.i18n.placeholder} disabled={busy} aria-invalid={failed} aria-describedby="kiriof-google-maps-key-hint" />
              <InputGroup.Addon align="inline-end">
                <InputGroup.Button type="button" size="icon-sm" disabled={busy} aria-label={reveal ? bootstrap.i18n.hideKey : bootstrap.i18n.showKey} aria-pressed={reveal} onclick={() => { reveal = !reveal; }}>
                  {#if reveal}<IconEyeOff />{:else}<IconEye />{/if}
                </InputGroup.Button>
              </InputGroup.Addon>
            </InputGroup.Root>
            <Field.FieldDescription id="kiriof-google-maps-key-hint">{bootstrap.i18n.keyHint}</Field.FieldDescription>
          </Field.Field>
          <Field.Field orientation="horizontal" class="flex-wrap">
            <Button type="submit" disabled={busy}>{#if busy}<Spinner data-icon="inline-start" />{/if}{busy ? bootstrap.i18n.busy : bootstrap.i18n.save}</Button>
            {#if summary.configured}<Button type="button" variant="outline" disabled={busy} onclick={() => update('remove')}>{bootstrap.i18n.remove}</Button>{/if}
          </Field.Field>
          {#if message}
            <Alert.Root variant={failed ? 'destructive' : 'default'} role={failed ? 'alert' : 'status'} id="kiriof-google-maps-save-message">
              <Alert.Description>{message}</Alert.Description>
            </Alert.Root>
          {/if}
        </Field.FieldGroup>
      </form>
      <Field.FieldSet class="m-0 min-w-0 border-0 p-0">
        <Field.FieldLegend>{bootstrap.i18n.browserCheckTitle}</Field.FieldLegend>
        <Field.FieldDescription>{bootstrap.i18n.browserCheckGuidance}</Field.FieldDescription>
        {#if testedOrigin}<p>{bootstrap.i18n.originLabel}: <span class="break-all">{testedOrigin}</span></p>{/if}
        <Alert.Root class="box-border" variant={checkState === 'error' ? 'destructive' : 'default'} role={checkState === 'error' ? 'alert' : 'status'} data-kiriof-google-maps-check aria-live="polite" aria-busy={checkState === 'checking'}>
          {#if checkState === 'checking'}<Spinner />{/if}
          <Alert.Description>{checkMessage}</Alert.Description>
        </Alert.Root>
        {#if probe}
          {#key probe.channel}
            <iframe bind:this={preview} src={probe.url} title={bootstrap.i18n.previewTitle} data-kiriof-google-maps-probe class="box-border block h-80 w-full rounded-md border" referrerpolicy="strict-origin-when-cross-origin"></iframe>
          {/key}
        {/if}
      </Field.FieldSet>
    </Card.Content>
    <Card.Footer class="flex-wrap gap-2">
      <Button type="button" variant="outline" disabled={busy || !summary.configured} onclick={checkBrowser}>
        <IconRefresh data-icon="inline-start" />{bootstrap.i18n.checkAgain}
      </Button>
    </Card.Footer>
  </Card.Root>
</section>
