<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InstantProcessUiTest extends TestCase {
    public function test_payment_qr_is_centered_and_refresh_action_lives_in_the_footer(): void {
        $source = $this->source( 'src/lib/transactions/InstantProcessDialog.svelte' );
        $css = $this->source( 'src/styles/admin-list.css' );
        $this->assertStringContainsString( '<svg class="kiriof-instant-payment-qr"', $source );
        $this->assertMatchesRegularExpression( '/\.kiriof-instant-process-dialog \.kiriof-instant-payment-qr\s*\{\s*@apply !block !mx-auto !justify-self-center !max-w-full !h-auto !bg-white;/', $css );
        $footer = substr( $source, strpos( $source, '<Dialog.Footer>' ) );
        $this->assertStringContainsString( "text('instantRefreshPayment')", $footer );
        $this->assertStringNotContainsString( "text('instantRefreshPayment')", substr( $source, 0, strpos( $source, '<Dialog.Footer>' ) ) );
    }
    public function test_collapsible_uses_visible_composed_button_and_scoped_native_style_reset(): void {
        $source = $this->source('src/lib/transactions/InstantProcessDialog.svelte');
        $css = $this->source('src/styles/admin-list.css');
        $this->assertStringContainsString('{#snippet child({ props })}', $source);
        $this->assertStringContainsString('<Button {...props} variant="outline"', $source);
        $this->assertStringContainsString('<IconChevronDown data-icon="inline-end"', $source);
        $this->assertStringNotContainsString('<Collapsible.Trigger class=', $source);
        $this->assertStringContainsString(".kiriof-instant-process-dialog .kiriof-button[data-slot='collapsible-trigger']", $css);
        $this->assertStringContainsString('!appearance-none', $css);
        $this->assertStringContainsString('![background-image:none]', $css);
        $this->assertStringContainsString("[data-slot='collapsible-trigger']:focus-visible", $css);
        $this->assertStringContainsString('@apply !ring-3 !ring-ring/50;', $css);
        $this->assertStringContainsString('.kiriof-instant-process-dialog .kiriof-instant-order-trigger', $css);
        $this->assertStringContainsString('!border !border-border !bg-background', $css);
    }

    public function test_pin_cells_are_visible_without_preflight_and_native_input_does_not_cover_them(): void {
        $css = $this->source( 'src/styles/admin-list.css' );
        $this->assertStringContainsString( ".kiriof-transaction-dialog-content input:not([data-pin-input-input])", $css );
        $this->assertStringNotContainsString( ".kiriof-transaction-dialog-content input {", $css );
        $this->assertMatchesRegularExpression( "/input\\[data-pin-input-input\\]\\s*\\{[^}]*!border-0[^}]*!bg-transparent[^}]*!text-transparent[^}]*!shadow-none[^}]*!outline-none[^}]*;/", $css );
        $this->assertMatchesRegularExpression( "/\\[data-slot='input-otp-slot'\\]\\s*\\{[^}]*!w-full[^}]*!border !border-solid[^}]*!rounded-lg/", $css );
        $this->assertStringContainsString( "[data-slot='input-otp-slot'][data-active]", $css );
        $this->assertStringContainsString( "[data-slot='input-otp-slot'][aria-invalid='true']", $css );
        $this->assertMatchesRegularExpression( '/\[data-pin-input-root\]\s*\{[^}]*!grid !grid-cols-6 !w-full[^}]*!gap-2/', $css );
        $this->assertMatchesRegularExpression( "/\[data-slot='input-otp-slot'\]\s*\{[^}]*!border-solid[^}]*!border-muted-foreground[^}]*!bg-background/", $css );
        $this->assertDoesNotMatchRegularExpression( "/\[data-slot='input-otp-slot'\]\s*\{[^}]*!border-input/", $css );
    }

    public function test_collapsed_order_information_is_not_rendered_and_hidden_content_cannot_be_displayed_by_grid_styles(): void {
        $source = $this->source( 'src/lib/transactions/InstantProcessDialog.svelte' );
        $css = $this->source( 'src/styles/admin-list.css' );
        $this->assertStringContainsString( 'let orderInformationOpen = $state(false)', $source );
        $this->assertStringContainsString( 'if (!refresh) { orderInformationOpen = false;', $source );
        $this->assertMatchesRegularExpression( '/<Collapsible\.Content[^>]*>\s*\{#if orderInformationOpen\}[\s\S]*?\{\/if\}\s*<\/Collapsible\.Content>/', $source );
        $this->assertMatchesRegularExpression( "/\\.kiriof-instant-process-dialog \\[data-slot='collapsible-content'\\]\\[hidden\\],[^{}]*\\[data-state='closed'\\]\\s*\\{\\s*@apply !hidden;/", $css );
    }

    public function test_both_shipment_dialogs_use_shared_loading_and_submission_feedback(): void {
        foreach ( array( 'InstantProcessDialog', 'RequestPickupDialog' ) as $name ) {
            $source = $this->source( 'src/lib/transactions/' . $name . '.svelte' );
            $this->assertStringContainsString( "import ShipmentSummarySkeleton from '\$lib/payments/ShipmentSummarySkeleton.svelte'", $source );
            $this->assertStringContainsString( "import ShipmentOperationProgress from '\$lib/payments/ShipmentOperationProgress.svelte'", $source );
            $this->assertStringContainsString( "import { Spinner } from '\$lib/components/ui/spinner'", $source );
            $this->assertStringContainsString( '<ShipmentSummarySkeleton variant="' . ( 'InstantProcessDialog' === $name ? 'instant' : 'express' ) . '"', $source );
            $this->assertStringContainsString( '<ShipmentOperationProgress label=', $source );
            $this->assertStringContainsString( '<Spinner data-icon="inline-start" aria-hidden="true" role="presentation" />', $source );
            $this->assertStringNotContainsString( 'IconLoader2', $source );
            $this->assertStringNotContainsString( 'loading={', $source );
        }
        $progress = $this->source( 'src/lib/payments/ShipmentOperationProgress.svelte' );
        $this->assertStringContainsString( "import * as Alert from '\$lib/components/ui/alert'", $progress );
        $this->assertStringContainsString( 'role="status" aria-live="polite" aria-busy="true"', $progress );
        $this->assertStringContainsString( '<Spinner aria-hidden="true" role="presentation" />', $progress );
        $this->assertStringContainsString( '<Alert.Description>{label}</Alert.Description>', $progress );
        $skeleton = $this->source( 'src/lib/payments/ShipmentSummarySkeleton.svelte' );
        $this->assertStringContainsString( "import { Skeleton } from '\$lib/components/ui/skeleton'", $skeleton );
        $this->assertStringContainsString( 'aria-busy="true" role="status" aria-label={label}', $skeleton );
        $this->assertStringContainsString( 'aria-hidden="true"', $skeleton );
    }

    public function test_instant_uses_radio_cards_and_separate_masked_pin_step(): void {
        $source = $this->source('src/lib/transactions/InstantProcessDialog.svelte');
        $pin = $this->source('src/lib/payments/CreditPinInput.svelte');
        $methods = $this->source('src/lib/payments/PaymentMethodSelector.svelte');
        $this->assertStringContainsString("import CreditPinInput from '\$lib/payments/CreditPinInput.svelte'", $source);
        $this->assertStringContainsString("import PaymentMethodSelector from '\$lib/payments/PaymentMethodSelector.svelte'", $source);
        $this->assertStringContainsString('<PaymentMethodSelector idPrefix="instant-method" bind:value={method}', $source);
        $this->assertStringContainsString('<CreditPinInput id="instant-credit-pin" bind:value={pin}', $source);
        $this->assertStringContainsString('disabled={busy || expired} invalid={Boolean(error)}', $source);
        $this->assertFileDoesNotExist(PLUGIN_DIR . '/src/lib/transactions/InstantCreditPin.svelte');
        $this->assertStringContainsString('<RadioGroup.Root bind:value', $methods);
        $this->assertStringContainsString('<Field.Set', $methods);
        $this->assertStringNotContainsString('<select', $source);
        $this->assertStringNotContainsString('<input', $source);
        $this->assertStringContainsString("step === 'pin'", $source);
        $this->assertStringContainsString('kiriof_instant_validate_credit', $source);
        $this->assertStringContainsString('<InputOTP.Root', $pin);
        $this->assertStringContainsString('maxlength={6}', $pin);
        $this->assertStringContainsString('pattern={REGEXP_ONLY_DIGITS}', $pin);
        $this->assertStringContainsString('type="password"', $pin);
        $this->assertStringContainsString('<InputOTP.Slot {cell} mask={!focused || disabled || revealedIndex !== index}', $pin);
        $this->assertStringContainsString('{#each cells as cell, index (cell)}', $pin);
        $this->assertStringNotContainsString( '<InputOTP.Group', $pin );
        $this->assertStringContainsString( '<Field.Label for={id} class="sr-only">', $pin );
        $this->assertStringContainsString( '<Field.Description id={`${id}-help`} class="sr-only m-0">', $pin );
        $this->assertStringNotContainsString('<InputOTP.Separator', $pin);
        $this->assertStringContainsString('autocomplete="off" inputmode="numeric"', $pin);
        $this->assertStringContainsString('aria-describedby={`${id}-help`}', $pin);
        $this->assertStringContainsString('aria-invalid={invalid || undefined}', $pin);
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
        $this->assertStringContainsString("if (expired) { pin = ''; step = 'summary'; }", $source);
        preg_match_all("/text\('([^']+)'\)/", $source, $matches);
        $renderer = $this->source('inc/Services/TransactionListRenderService.php');
        foreach (array_unique($matches[1]) as $key) {
            $this->assertStringContainsString('"' . $key . '" => __(', $renderer, $key);
        }
    }
}
