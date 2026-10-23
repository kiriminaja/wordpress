<script lang="ts">
  import { onMount } from 'svelte';
  import { IconEye, IconEyeOff, IconRefresh, IconChevronDown, IconCheck } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import { Spinner } from '$lib/components/ui/spinner';
  import * as Card from '$lib/components/ui/card';
  import * as Field from '$lib/components/ui/field';
  import * as InputGroup from '$lib/components/ui/input-group';
  import * as Alert from '$lib/components/ui/alert';
  import { postWordPressAction } from '../wordpress/ajax';
  import { mapsCheckResult, mapsCheckUrl, type MapsCheckCode } from './google-maps-check';
  import type { GoogleMapsBootstrap, GoogleMapsSummary } from './types';
  import { cn } from '$lib/utils';

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
  let detailsOpen = $state(false);
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
  <Card.Root size="sm">
    <Card.Header>
      <Card.Title id="kiriof-google-maps-title" role="heading" aria-level={2}>{bootstrap.i18n.title}</Card.Title>
    </Card.Header>
    <Card.Content class="flex min-w-0 flex-col gap-3">
      {#if summary.configured}
        <p class="m-0! flex flex-wrap items-center gap-x-2 gap-y-1">{bootstrap.i18n.configured}: <code class="break-all">{safeMask(summary.maskedKey)}</code></p>
      {:else}
        <p class="m-0!">{bootstrap.i18n.notConfigured}</p>
      {/if}
      <form onsubmit={(event) => { event.preventDefault(); void update('save'); }}>
        <Field.FieldGroup class="gap-3">
          <Field.Field data-disabled={busy} data-invalid={failed}>
            <Field.FieldLabel for="kiriof-google-maps-key">{bootstrap.i18n.keyLabel}</Field.FieldLabel>
            <div class="flex min-w-0 flex-wrap items-center gap-2">
            <InputGroup.Root class="h-11 min-w-0 basis-full sm:basis-64 sm:flex-1">
              <InputGroup.Input id="kiriof-google-maps-key" type={reveal ? 'text' : 'password'} bind:value={key} autocomplete="new-password" spellcheck="false" autocapitalize="none" placeholder={bootstrap.i18n.placeholder} disabled={busy} aria-invalid={failed} aria-describedby="kiriof-google-maps-key-hint" />
              <InputGroup.Addon align="inline-end">
                <InputGroup.Button type="button" size="icon-sm" class="size-11" disabled={busy} aria-label={reveal ? bootstrap.i18n.hideKey : bootstrap.i18n.showKey} aria-pressed={reveal} onclick={() => { reveal = !reveal; }}>
                  {#if reveal}<IconEyeOff />{:else}<IconEye />{/if}
                </InputGroup.Button>
              </InputGroup.Addon>
            </InputGroup.Root>
            <Button type="submit" class="min-h-11" disabled={busy}>{#if busy}<Spinner data-icon="inline-start" />{/if}{busy ? bootstrap.i18n.busy : bootstrap.i18n.save}</Button>
            {#if summary.configured}<Button type="button" variant="ghost" class="min-h-11" disabled={busy} onclick={() => update('remove')}>{bootstrap.i18n.remove}</Button>{/if}
            </div>
            <span id="kiriof-google-maps-key-hint" hidden>{bootstrap.i18n.placeholder} {bootstrap.i18n.keyHint}</span>
          </Field.Field>
          {#if message}
            {#if failed}
            <Alert.Root variant="destructive" role="alert" id="kiriof-google-maps-save-message">
              <Alert.Description>{message}</Alert.Description>
            </Alert.Root>
            {:else}
              <p class="m-0!" role="status" id="kiriof-google-maps-save-message">{message}</p>
            {/if}
          {/if}
        </Field.FieldGroup>
      </form>
      <div class="flex min-w-0 flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <div class="min-w-0 basis-full sm:basis-64 sm:flex-1" data-kiriof-google-maps-check role={checkState === 'error' ? 'alert' : 'status'} aria-live="polite" aria-busy={checkState === 'checking'}>
          {#if checkState === 'error'}
            <Alert.Root variant="destructive" role="presentation"><Alert.Description>{checkMessage}</Alert.Description></Alert.Root>
          {:else}
            <p class="m-0! flex items-center gap-2">
              {#if checkState === 'checking'}<Spinner aria-hidden="true" />{:else if checkState === 'ready'}<IconCheck class="size-4 shrink-0" aria-hidden="true" />{/if}
              {checkMessage}
            </p>
          {/if}
        </div>
        <div class="flex flex-wrap items-center gap-1">
          <Button type="button" variant="ghost" class="min-h-11" disabled={busy || !summary.configured} onclick={checkBrowser}>
            <IconRefresh data-icon="inline-start" />{bootstrap.i18n.checkAgain}
          </Button>
          <Button type="button" variant="ghost" class="min-h-11" aria-expanded={detailsOpen} aria-controls="kiriof-google-maps-details" onclick={() => { detailsOpen = !detailsOpen; }}>
            {bootstrap.i18n.details}<IconChevronDown data-icon="inline-end" class={cn(detailsOpen && 'rotate-180')} />
          </Button>
        </div>
      </div>
      <!-- Keep a real map viewport mounted while collapsed so automatic checks still receive tiles. -->
      <div id="kiriof-google-maps-details" aria-hidden={!detailsOpen} inert={!detailsOpen} class={cn('min-w-0', !detailsOpen && 'h-0 overflow-hidden')}>
        <div class="flex min-w-0 flex-col gap-3">
        <Card.Description class="m-0!">{bootstrap.i18n.guidance}</Card.Description>
        <Card.Description class="m-0!">{bootstrap.i18n.browserCheckGuidance}</Card.Description>
        {#if testedOrigin}<p class="m-0!">{bootstrap.i18n.originLabel}: <span class="break-all">{testedOrigin}</span></p>{/if}
        {#if probe}
          {#key probe.channel}
            <iframe bind:this={preview} src={probe.url} title={bootstrap.i18n.previewTitle} data-kiriof-google-maps-probe class="box-border block h-52 w-full rounded-md border" referrerpolicy="strict-origin-when-cross-origin"></iframe>
          {/key}
        {/if}
        </div>
      </div>
    </Card.Content>
  </Card.Root>
</section>
