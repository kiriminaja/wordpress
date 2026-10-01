<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InstantPrintPreviewUiTest extends TestCase {
    private function source(string $path): string {
        return file_get_contents(PLUGIN_DIR . '/' . $path);
    }

    public function test_workspace_selects_the_correct_preview_nonce(): void {
        $source = $this->source('src/lib/transactions/TransactionsApp.svelte');
        $this->assertStringContainsString('nonce={isInstant ? bootstrap.bulk.nonce : bootstrap.bulk.printPreviewNonce}', $source);
    }

    public function test_both_transports_are_abortable_and_keep_their_existing_payloads(): void {
        $source = $this->source('src/lib/ui/PrintPreviewDialog.svelte');
        $this->assertStringContainsString("postWordPressAction<PreviewResponse>('kiriof_instant_label_preview'", $source);
        $this->assertStringContainsString('order_ids: JSON.stringify(ids)', $source);
        $this->assertStringContainsString('nonce: requestNonce, signal', $source);
        $this->assertStringContainsString("action: 'kiriof_print_label_preview', nonce: requestNonce", $source);
        $this->assertStringContainsString("body.append('oids[]', orderId)", $source);
        $this->assertStringContainsString("url.origin !== window.location.origin", $source);
        $this->assertStringContainsString("url.searchParams.get('action') !== 'kiriof_instant_labels'", $source);
        $this->assertMatchesRegularExpression('/fetch\(requestUrl,\s*\{\s*method: .POST.,\s*signal,/s', $source);
        $ajax = $this->source('src/lib/wordpress/ajax.ts');
        $this->assertStringContainsString("body.set('data[nonce]'", $ajax);
        $this->assertStringContainsString('signal: options.signal', $ajax);
    }

    public function test_identity_effect_is_untracked_and_cleans_up_on_close_and_unmount(): void {
        $source = $this->source('src/lib/ui/PrintPreviewDialog.svelte');
        $this->assertStringContainsString('const isOpen = open;', $source);
        $this->assertStringContainsString('const ids = [...orderIds];', $source);
        $this->assertStringContainsString('const type = deliveryType;', $source);
        $this->assertStringContainsString('untrack(() => {', $source);
        $this->assertStringContainsString('return () => session.cancel();', $source);
        $this->assertStringContainsString('onOpenChange=', $source);
        $this->assertStringContainsString('if (!nextOpen) close();', $source);
        $this->assertStringContainsString('onclick={() => void loadPreview()}', $source);
        $this->assertStringNotContainsString('|| loading', $source);
        $session = $this->source('src/lib/ui/print-preview-session.ts');
        $this->assertStringContainsString('this.controller?.abort()', $session);
        $this->assertStringContainsString('generation !== this.generation || controller.signal.aborted', $session);
    }

    public function test_print_is_frame_local_and_waits_for_load_and_instant_has_no_download(): void {
        $source = $this->source('src/lib/ui/PrintPreviewDialog.svelte');
        $this->assertStringContainsString('bind:this={frame}', $source);
        $this->assertStringContainsString('onload=', $source);
        $this->assertStringContainsString('event.currentTarget === frame', $source);
        $this->assertStringContainsString('disabled={!frameReady}', $source);
        $this->assertStringContainsString('frame?.contentWindow?.print()', $source);
        $this->assertStringNotContainsString('window.print()', $source);
        $this->assertStringContainsString("{#if deliveryType === 'express'}", $source);
    }
}
