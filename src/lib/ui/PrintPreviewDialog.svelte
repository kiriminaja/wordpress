<script lang="ts">
  import { IconDownload, IconExternalLink, IconPrinter } from '@tabler/icons-svelte';
  import { Button } from '$lib/components/ui/button';
  import KiriofDialog from '$lib/ui/KiriofDialog.svelte';

  type PreviewResponse = { url: string };

  let {
    open = $bindable(false),
    orderIds,
    ajaxUrl,
    nonce,
    i18n,
    onPrinted,
  }: {
    open?: boolean;
    orderIds: string[];
    ajaxUrl: string;
    nonce: string;
    i18n: Record<string, string>;
    onPrinted?: () => void;
  } = $props();

  let pdfUrl = $state('');
  let loading = $state(false);
  let error = $state('');

  async function loadPreview(): Promise<void> {
    if (orderIds.length === 0 || loading) return;
    loading = true;
    error = '';
    pdfUrl = '';

    try {
      const body = new URLSearchParams({ action: 'kiriof_print_label_preview', nonce });
      for (const orderId of orderIds) body.append('oids[]', orderId);
      const response = await fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body,
      });
      const payload = (await response.json()) as { success?: boolean; data?: PreviewResponse | { message?: string } };
      if (!response.ok || !payload.success || !payload.data || !('url' in payload.data)) {
        throw new Error((payload.data as { message?: string } | undefined)?.message ?? i18n.printPreviewError ?? 'Unable to load label preview.');
      }
      pdfUrl = payload.data.url;
      onPrinted?.();
    } catch (cause) {
      error = cause instanceof Error ? cause.message : i18n.printPreviewError ?? 'Unable to load label preview.';
    } finally {
      loading = false;
    }
  }

  function close(): void {
    open = false;
    pdfUrl = '';
    error = '';
  }

  function print(): void {
    window.print();
  }

  $effect(() => {
    if (open && !pdfUrl && !error && !loading) void loadPreview();
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
      <iframe class="size-full border-0 bg-white" src={pdfUrl} title={i18n.printPreview ?? 'Label preview'}></iframe>
    {/if}
  </div>

  {#if pdfUrl}
    <div class="!flex !items-center !justify-end gap-2">
      <Button variant="outline" onclick={print}><IconPrinter data-icon="inline-start" />{i18n.print ?? 'Print'}</Button>
      <Button variant="outline" href={pdfUrl} target="_blank" rel="noopener noreferrer"><IconExternalLink data-icon="inline-start" />{i18n.openInNewTab ?? 'Open in new tab'}</Button>
      <Button href={pdfUrl} target="_blank" rel="noopener noreferrer" download><IconDownload data-icon="inline-start" />{i18n.download ?? 'Download'}</Button>
    </div>
  {/if}
</KiriofDialog>
