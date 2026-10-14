<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
  import * as Dialog from '$lib/components/ui/dialog';
  import * as Empty from '$lib/components/ui/empty';
  import * as Alert from '$lib/components/ui/alert';
  import { IconMapPinOff, IconAlertCircle } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import { postWordPressAction } from '$lib/wordpress/ajax';
  import { safeInstantTrackingUrl, safeInstantTrackingEmbedUrl, type InstantOperationMode, type InstantOperationRow } from './types';

  let { mode, orderIds, ajaxUrl, nonce, i18n, onClose }: {
    mode: InstantOperationMode; orderIds: string[]; ajaxUrl: string; nonce: string;
    i18n: Record<string, string>; onClose: (completed: boolean) => void;
  } = $props();
  let busy = $state(false);
  let rows = $state<InstantOperationRow[] | null>(null);
  let controller: AbortController | null = null;
  let disposed = false;
  let completed = false;
  const embeddedTracking = $derived(mode === 'tracking' && rows?.some(row => row.status === 'tracked' && Boolean(safeInstantTrackingEmbedUrl(row.tracking_url, window.location.origin))));
  const title = $derived(i18n[mode === 'tracking' ? 'liveTracking' : mode === 'reconcile' ? 'instantRecheck' : 'instantCancel']);
  function close(): void {
    if (busy && mode !== 'tracking') return;
    if (mode === 'tracking') { disposed = true; controller?.abort(); }
    onClose(completed);
  }
  function refreshTracking(): void {
    if (busy || mode !== 'tracking') return;
    rows = null;
    void run();
  }
  async function run(): Promise<void> {
    if (busy || rows !== null || orderIds.length !== 1 || !['tracking', 'cancel', 'reconcile'].includes(mode)) return;
    busy = true;
    controller = new AbortController();
    const values: Record<string, string> = { order_ids: JSON.stringify(orderIds) };
    if (mode === 'cancel') values.confirmed = 'yes';
    try {
      const response = await postWordPressAction<{ rows: InstantOperationRow[] }>(`kiriof_instant_${mode}`, values, { ajaxUrl, nonce, signal: controller.signal });
      if (disposed) return;
      completed = true;
      rows = orderIds.map((id) => {
        const row = response.data?.rows?.find((item) => item.id === id);
        return row && ['tracked', 'tracking_unavailable', 'tracking_pending', 'not_found', 'unknown', 'cancel_requested', 'canceled', 'reconciled'].includes(row.status) ? row : { id, status: 'unknown', tracking_url: '', message: '' };
      });
    } catch {
      if (!disposed) { completed = true; rows = orderIds.map((id) => ({ id, status: 'unknown', tracking_url: '', message: '' })); }
    } finally { if (!disposed) busy = false; }
  }
  onMount(() => { if (mode === 'tracking') void run(); });
  onDestroy(() => { disposed = true; controller?.abort(); });
</script>

<Dialog.Root open={true} onOpenChange={(value) => { if (!value) close(); }}>
  <Dialog.Content class={embeddedTracking ? 'kiriof-shadcn kiriof-transaction-dialog-content kiriof-live-tracking-dialog' : 'kiriof-shadcn kiriof-transaction-dialog-content'} showCloseButton={!busy || mode === 'tracking'} escapeKeydownBehavior={busy && mode !== 'tracking' ? 'ignore' : 'close'} interactOutsideBehavior={busy && mode !== 'tracking' ? 'ignore' : 'close'} aria-busy={busy}>
    <Dialog.Header>
      <Dialog.Title>{title}</Dialog.Title>
      <Dialog.Description>{mode === 'cancel' ? i18n.instantCancelTerms : mode === 'reconcile' ? i18n.instantRecheckDescription : i18n.instantTrackingDescription}</Dialog.Description>
    </Dialog.Header>
    {#each orderIds as id}<p>{id}</p>{/each}
    {#if busy && mode === 'tracking'}<p role="status">{i18n.instantTrackingLoading}</p>{/if}
    {#if rows}
      {#each rows as row}
        {@const trackingUrl = safeInstantTrackingUrl(row.tracking_url)}
        {@const embedUrl = safeInstantTrackingEmbedUrl(row.tracking_url, window.location.origin)}
        {@const hasTracking = mode === 'tracking' && row.status === 'tracked' && Boolean(trackingUrl)}
        {@const unavailable = mode === 'tracking' && (row.status === 'tracking_unavailable' || (row.status === 'tracked' && !trackingUrl))}
        {#if mode === 'tracking' && !hasTracking}
          <Empty.Root class="box-border">
            <Empty.Header>
              <Empty.Media variant="icon"><IconMapPinOff aria-hidden="true" /></Empty.Media>
              <Empty.Title>{row.status === 'unknown' ? i18n.instantTrackingErrorTitle : i18n.instantTrackingUnavailableTitle}</Empty.Title>
              <Empty.Description>{i18n.instantTrackingDescription}</Empty.Description>
            </Empty.Header>
            <Empty.Content>
              <Alert.Root class="box-border" variant={row.status === 'unknown' ? 'destructive' : 'default'} role={row.status === 'unknown' ? 'alert' : 'status'}>
                <IconAlertCircle aria-hidden="true" />
                <Alert.Description>{row.status === 'unknown' ? i18n.instantTrackingError : row.status === 'tracking_pending' ? i18n.instantTrackingPending : unavailable ? i18n.instantTrackingUnavailable : row.message || i18n.instantTrackingUnavailable}</Alert.Description>
              </Alert.Root>
            </Empty.Content>
          </Empty.Root>
        {:else if mode !== 'tracking'}
          <div role="status">
            <strong>{i18n[`instantResult_${row.status}`] ?? i18n.instantResult_unknown}</strong>
            {#if row.status === 'unknown'}<p>{i18n.instantOperationUnknown}</p>{:else if row.message}<p>{row.message}</p>{/if}
          </div>
        {/if}
        {#if hasTracking}
          {#if embedUrl}<iframe class="kiriof-live-tracking-frame" src={embedUrl} title={i18n.liveTracking} sandbox="allow-scripts" referrerpolicy="no-referrer"></iframe>{/if}
          <p class="text-sm text-muted-foreground">{i18n.instantTrackingEmbedHint}</p>
          <Button variant="outline" href={trackingUrl} target="_blank" rel="noopener noreferrer">{i18n.openInNewTab}</Button>
        {/if}
      {/each}
    {/if}
    <Dialog.Footer>
      <Button variant="outline" disabled={busy && mode !== 'tracking'} onclick={close}>{i18n.instantClose}</Button>
      {#if mode === 'tracking'}<Button variant="outline" disabled={busy || orderIds.length !== 1} onclick={refreshTracking}>{i18n.instantTrackingRefresh}</Button>
      {:else if !rows}<Button variant={mode === 'cancel' ? 'destructive' : 'default'} disabled={busy || orderIds.length !== 1} onclick={() => void run()}>{title}</Button>{/if}
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
