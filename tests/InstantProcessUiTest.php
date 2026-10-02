<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InstantProcessUiTest extends TestCase {
    public function test_collapsible_uses_composed_ghost_button_and_scoped_native_style_reset(): void {
        $source = $this->source('src/lib/transactions/InstantProcessDialog.svelte');
        $css = $this->source('src/styles/admin-list.css');
        $this->assertStringContainsString('{#snippet child({ props })}', $source);
        $this->assertStringContainsString('<Button {...props} variant="ghost"', $source);
        $this->assertStringContainsString('<IconChevronDown data-icon="inline-end"', $source);
        $this->assertStringNotContainsString('<Collapsible.Trigger class=', $source);
        $this->assertStringContainsString(".kiriof-instant-process-dialog .kiriof-button[data-slot='collapsible-trigger']", $css);
        $this->assertStringContainsString('!appearance-none', $css);
        $this->assertStringContainsString('![background-image:none]', $css);
        $this->assertStringContainsString("[data-slot='collapsible-trigger']:focus-visible", $css);
        $this->assertStringContainsString('@apply !ring-3 !ring-ring/50;', $css);
    }

    public function test_instant_checkboxes_do_not_inherit_the_vertical_express_dialog_reset(): void {
        $css = $this->source( 'src/styles/admin-list.css' );
        $this->assertStringContainsString( ".kiriof-instant-process-dialog [data-slot='field'][data-orientation='horizontal']", $css );
        $this->assertStringContainsString( '@apply !flex-row !items-start !gap-3;', $css );
        $this->assertStringContainsString( '.kiriof-instant-process-dialog dl dd', $css );
    }

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
        $this->assertStringContainsString('dispatchAttempted = true;', $source);
        $this->assertStringContainsString('result = data;', $source);
        $this->assertStringNotContainsString("status: 'unknown'", $source);
        $this->assertStringContainsString('if (completed) onComplete?.()', $source);
        $this->assertStringContainsString('if (dispatching) return;', $source);
        $this->assertStringContainsString("escapeKeydownBehavior={dispatching ? 'ignore' : 'close'}", $source);
        $this->assertStringContainsString('order_ids: JSON.stringify(validatedIds)', $source);
        $this->assertStringNotContainsString('acknowledged', $source);
        $this->assertStringNotContainsString('Checkbox', $source);
        $this->assertStringNotContainsString('allowSkip', $source);
        $this->assertStringContainsString("confirmed: 'yes'", $source);
        $this->assertStringNotContainsString('i18n[key] || key', $source);
        $this->assertStringContainsString('{#if !topAccount}', $source);
        $this->assertStringContainsString('quoteClock.start(data)', $source);
        $this->assertStringContainsString('void review(true)', $source);
        $this->assertStringContainsString('matchesSelection(data)', $source);
        $this->assertStringContainsString('completeAmount(row.before) && completeAmount(row.after)', $source);
        $this->assertStringContainsString('let orderInformationOpen = $state(false)', $source);
        $this->assertStringContainsString('<Collapsible.Root bind:open={orderInformationOpen}', $source);
        $this->assertStringContainsString('<Alert.Root', $source);
        $this->assertStringContainsString('{#if changedCount > 0}', $source);
        $this->assertStringContainsString('{#if summary.gap !== 0}', $source);
        $this->assertStringNotContainsString("text('instantQuoteValidity')", $source);
        $this->assertStringContainsString('if (expired) pin', $source);
        preg_match_all("/text\('([^']+)'\)/", $source, $matches);
        $renderer = $this->source('inc/Services/TransactionListRenderService.php');
        foreach (array_unique($matches[1]) as $key) {
            $this->assertStringContainsString('"' . $key . '" => __(', $renderer, $key);
        }
    }
}
