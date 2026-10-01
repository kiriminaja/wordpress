<script lang="ts">
  import { onDestroy } from 'svelte';
  import * as Dialog from '$lib/components/ui/dialog';
  import { Button } from '$lib/components/ui/button';
  import { postWordPressAction } from '$lib/wordpress/ajax';
  import { safeInstantTrackingUrl, type InstantOperationMode, type InstantOperationRow } from './types';

  let { mode, orderIds, ajaxUrl, nonce, i18n, onClose }: {
    mode: InstantOperationMode; orderIds: string[]; ajaxUrl: string; nonce: string;
    i18n: Record<string, string>; onClose: (completed: boolean) => void;
  } = $props();
  let busy = $state(false);
  let rows = $state<InstantOperationRow[] | null>(null);
  let controller: AbortController | null = null;
  let disposed = false;
  const title = $derived(i18n[mode === 'tracking' ? 'liveTracking' : mode === 'reconcile' ? 'instantReconcile' : 'instantCancel']);
  function close(): void { if (busy) return; onClose(rows !== null); }
  async function run(): Promise<void> {
    if (busy || rows !== null || orderIds.length !== 1) return;
    busy = true;
    controller = new AbortController();
    const values: Record<string, string> = { order_ids: JSON.stringify(orderIds) };
    if (mode === 'cancel') values.confirmed = 'yes';
    try {
      const response = await postWordPressAction<{ rows: InstantOperationRow[] }>(`kiriof_instant_${mode}`, values, { ajaxUrl, nonce, signal: controller.signal });
      if (disposed) return;
      rows = orderIds.map((id) => {
        const row = response.data?.rows?.find((item) => item.id === id);
        return row && ['tracked', 'not_found', 'unknown', 'reconciled', 'cancel_requested', 'canceled'].includes(row.status) ? row : { id, status: 'unknown', tracking_url: '', message: '' };
      });
    } catch {
      if (!disposed) rows = orderIds.map((id) => ({ id, status: 'unknown', tracking_url: '', message: '' }));
    } finally { if (!disposed) busy = false; }
  }
  onDestroy(() => { disposed = true; controller?.abort(); });
</script>

<Dialog.Root open={true} onOpenChange={(value) => { if (!value) close(); }}>
  <Dialog.Content class="kiriof-shadcn kiriof-transaction-dialog-content" showCloseButton={!busy} escapeKeydownBehavior={busy ? 'ignore' : 'close'} interactOutsideBehavior={busy ? 'ignore' : 'close'} aria-busy={busy}>
    <Dialog.Header>
      <Dialog.Title>{title}</Dialog.Title>
      <Dialog.Description>{mode === 'cancel' ? i18n.instantCancelTerms : i18n.instantOperationDescription}</Dialog.Description>
    </Dialog.Header>
    {#each orderIds as id}<p>{id}</p>{/each}
    {#if rows}
      {#each rows as row}
        {@const trackingUrl = safeInstantTrackingUrl(row.tracking_url)}
        <div role="status">
          <strong>{i18n[`instantResult_${row.status}`] ?? i18n.instantResult_unknown}</strong>
          {#if row.status === 'unknown'}<p>{i18n.instantOperationUnknown}</p>{:else if row.message}<p>{row.message}</p>{/if}
          {#if trackingUrl}<Button variant="outline" href={trackingUrl} target="_blank" rel="noopener noreferrer">{i18n.liveTracking}</Button>{/if}
        </div>
      {/each}
    {/if}
    <Dialog.Footer>
      <Button variant="outline" disabled={busy} onclick={close}>{i18n.instantClose}</Button>
      {#if !rows}<Button variant={mode === 'cancel' ? 'destructive' : 'default'} disabled={busy || orderIds.length !== 1} onclick={() => void run()}>{title}</Button>{/if}
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
