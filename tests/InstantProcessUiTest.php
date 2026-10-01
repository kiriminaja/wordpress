<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InstantProcessUiTest extends TestCase {
    private function source(string $path): string {
        return file_get_contents(PLUGIN_DIR . '/' . $path);
    }

    public function test_instant_selection_uses_local_guards_without_express_actions(): void {
        $source = $this->source('inc/Services/TransactionListViewModelFactory.php');
        $this->assertStringContainsString('InstantShipmentContext::canProcess( $row )', $source);
        $this->assertStringContainsString('InstantLabelService::canPrint( $row )', $source);
        $this->assertStringContainsString('$wc_order->is_paid()', $source);
        $this->assertStringContainsString("'canProcess' => (bool) \$can_process_instant", $source);
        $this->assertStringContainsString("'changeOrigin' => \$is_express && \$is_processable", $source);
        $this->assertStringContainsString("\$can_cancel            = \$is_express ? ( '' !== \$awb && ! in_array( (string) \$row->status, \$terminal_statuses, true ) ) : InstantShipmentState::canCancel( \$row );", $source);
        $this->assertStringContainsString("'cancel'       => ( ! \$is_express || ! \$is_deficit ) && \$can_cancel", $source);
        $this->assertStringNotContainsString('->build(', $source);
    }

    public function test_instant_workspace_has_its_own_processing_and_shared_partitioned_printing(): void {
        $source = $this->source('src/lib/transactions/TransactionsApp.svelte');
        $this->assertStringContainsString('const readOnly = $derived(isOrderIssue)', $source);
        $this->assertStringContainsString("row.deliveryType === (isInstant ? 'instant' : 'express')", $source);
        $this->assertStringContainsString("disabled={row.selection.disabled || row.deliveryType !== (isInstant ? 'instant' : 'express')}", $source);
        $this->assertStringNotContainsString("disabled={row.deliveryType === 'instant'", $source);
        $this->assertStringContainsString('selectedRows.filter((row) => row.selection.canProcess)', $source);
        $this->assertStringContainsString('selectedRows.filter((row) => row.selection.canPrint)', $source);
        $this->assertStringContainsString('openInstantDialog(processOrderIds)', $source);
        $this->assertStringContainsString("deliveryType={isInstant ? 'instant' : 'express'}", $source);
        $this->assertStringContainsString('if (!isInstant && pickupOrderIds.length > 0)', $source);
        $this->assertStringContainsString('instantDialogOpen || printPreviewOpen', $source);
    }

    public function test_modal_never_blindly_retries_and_refreshes_list_only_on_close(): void {
        $source = $this->source('src/lib/transactions/InstantProcessDialog.svelte');
        $this->assertStringContainsString('session.consume(quote.token)', $source);
        $this->assertStringContainsString("? 'unknown' : 'skipped'", $source);
        $this->assertStringContainsString('if (completed) onComplete?.()', $source);
        $this->assertStringContainsString('if (busy) return;', $source);
        $this->assertStringContainsString("escapeKeydownBehavior={busy ? 'ignore' : 'close'}", $source);
        $this->assertStringContainsString('order_ids: JSON.stringify(validatedIds)', $source);
        $this->assertStringContainsString('acknowledged', $source);
        $this->assertStringContainsString('instantConfirm', $source);
        $this->assertStringNotContainsString('i18n[key] || key', $source);
        preg_match_all("/text\('([^']+)'\)/", $source, $matches);
        $renderer = $this->source('inc/Services/TransactionListRenderService.php');
        foreach (array_unique($matches[1]) as $key) {
            $this->assertStringContainsString('"' . $key . '" => __(', $renderer, $key);
        }
    }
}
