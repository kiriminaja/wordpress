<script lang="ts">
  import { untrack } from 'svelte';
  import { PrintPreviewSession } from './print-preview-session';
  import { IconDownload, IconExternalLink, IconPrinter } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import KiriofDialog from '$lib/ui/KiriofDialog.svelte';
  import { postWordPressAction } from '$lib/wordpress/ajax';

  type PreviewResponse = { url: string };

  let {
    open = $bindable(false),
    orderIds,
    ajaxUrl,
    nonce,
    i18n,
    deliveryType = 'express',
  }: {
    open?: boolean;
    orderIds: string[];
    ajaxUrl: string;
    nonce: string;
    i18n: Record<string, string>;
    deliveryType?: 'express' | 'instant';
  } = $props();

  let pdfUrl = $state('');
  let loading = $state(false);
  let error = $state('');
  let frame = $state<HTMLIFrameElement>();

  let frameReady = $state(false);
  const session = new PrintPreviewSession((state) => {
    pdfUrl = state.url;
    loading = state.loading;
    error = state.error;
    frameReady = false;
  });

  async function loadPreview(ids = [...orderIds], type = deliveryType, requestUrl = ajaxUrl, requestNonce = nonce): Promise<void> {
    const fallbackError = i18n.printPreviewError ?? 'Unable to load label preview.';
    if (!open || ids.length === 0) {
      session.cancel();
      return;
    }

    await session.load(async (signal) => {
      if (type === 'instant') {
        const response = await postWordPressAction<PreviewResponse>('kiriof_instant_label_preview', { order_ids: JSON.stringify(ids) }, { ajaxUrl: requestUrl, nonce: requestNonce, signal });
        if (!response.data?.url) throw new Error(fallbackError);
        const url = new URL(response.data.url, window.location.href);
        if (url.origin !== window.location.origin || url.searchParams.get('action') !== 'kiriof_instant_labels') throw new Error(fallbackError);
        return url.href;
      }
      const body = new URLSearchParams({ action: 'kiriof_print_label_preview', nonce: requestNonce });
      for (const orderId of ids) body.append('oids[]', orderId);
      const response = await fetch(requestUrl, {
        method: 'POST',
        signal,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body,
      });
      let payload: { success?: boolean; data?: PreviewResponse | { message?: string } };
      try {
        payload = (await response.json()) as { success?: boolean; data?: PreviewResponse | { message?: string } };
      } catch {
        throw new Error(fallbackError);
      }
      if (!response.ok || !payload.success || !payload.data || !('url' in payload.data)) {
        throw new Error((payload.data as { message?: string } | undefined)?.message ?? fallbackError);
      }
      return payload.data.url;
    }, fallbackError, (message) => {
      window.dispatchEvent(new CustomEvent('kiriof:print-preview-error', { detail: { orderIds: ids, error: message } }));
    });
  }

  function close(): void {
    session.cancel();
    open = false;
  }

  function print(): void {
    if (!frameReady || !open) return;
    frame?.contentWindow?.focus();
    frame?.contentWindow?.print();
  }

  $effect(() => {
    // Track only request identity, never the loading/result state written by the request.
    const isOpen = open;
    const ids = [...orderIds];
    const type = deliveryType;
    const endpoint = ajaxUrl;
    const token = nonce;
    untrack(() => {
      if (isOpen) void loadPreview(ids, type, endpoint, token);
      else session.cancel();
    });
    return () => session.cancel();
  });

</script>

<KiriofDialog
  bind:open
  class="!max-w-[calc(100vw-3rem)] sm:!max-w-[calc(100vw-4rem)]"
  title={i18n.printPreview ?? 'Label preview'}
  description={i18n.printPreviewDescription ?? 'Review the shipping label before printing.'}
  primaryLabel={i18n.close ?? 'Close'}
  primaryVariant="outline"
  onPrimary={close}
  onOpenChange={(nextOpen) => { if (!nextOpen) close(); }}
>
  <div class="!grid h-[calc(100vh-16rem)] min-h-80 overflow-hidden rounded-lg border border-border bg-muted/30">
    {#if loading}
      <div class="!grid place-items-center gap-3 text-sm text-muted-foreground">
        <span class="size-6 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-primary"></span>
        <span>{i18n.loadingPreview ?? 'Loading label preview…'}</span>
      </div>
    {:else if error}
      <div class="!grid place-items-center gap-3 p-6 text-center">
        <p class="max-w-md text-sm text-destructive" role="alert">{error}</p>
        <Button variant="outline" onclick={() => void loadPreview()}>{i18n.retry ?? 'Retry'}</Button>
      </div>
    {:else if pdfUrl}
      {#key pdfUrl}
        <iframe bind:this={frame} onload={(event) => { if (open && !loading && pdfUrl && event.currentTarget === frame) frameReady = true; }} class="size-full border-0 bg-white" src={pdfUrl} title={i18n.printPreview ?? 'Label preview'}></iframe>
      {/key}
    {/if}
  </div>

  {#if pdfUrl}
    <div class="!flex !items-center !justify-end gap-2">
      <Button variant="outline" onclick={print} disabled={!frameReady}><IconPrinter data-icon="inline-start" />{i18n.print ?? 'Print'}</Button>
      <Button variant="outline" href={pdfUrl} target="_blank" rel="noopener noreferrer"><IconExternalLink data-icon="inline-start" />{i18n.openInNewTab ?? 'Open in new tab'}</Button>
      {#if deliveryType === 'express'}<Button href={pdfUrl} target="_blank" rel="noopener noreferrer" download><IconDownload data-icon="inline-start" />{i18n.download ?? 'Download'}</Button>{/if}
    </div>
  {/if}
</KiriofDialog>
