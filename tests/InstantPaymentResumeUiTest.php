<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InstantPaymentResumeUiTest extends TestCase {
    public function test_transaction_rows_remove_payment_text_and_link_only_eligible_payment_action(): void {
        $source = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' );
        $this->assertStringNotContainsString( 'row.instantPayment.method', $source );
        $this->assertStringNotContainsString( 'row.instantPayment.status', $source );
        $this->assertStringNotContainsString( 'row.instantPayment.id', $source );
        $this->assertStringContainsString( '{#if row.actions.paymentUrl}', $source );
        $this->assertStringContainsString( 'href={row.actions.paymentUrl} aria-label={bootstrap.i18n.scanToPay}><IconQrcode />', $source );
    }

    public function test_payment_list_has_delivery_column_and_partitioned_scan_to_pay_dialogs(): void {
        $source = file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' );
        $this->assertStringContainsString( '<Table.Head>{bootstrap.i18n.deliveryType}</Table.Head>', $source );
        $this->assertStringContainsString( "row.deliveryType === 'instant' ? bootstrap.i18n.instant : bootstrap.i18n.regular", $source );
        $this->assertStringNotContainsString( 'bootstrap.i18n.instantPaymentId', $source );
        $this->assertStringContainsString( 'colspan={9}', $source );
        $this->assertStringContainsString( '<InstantScanToPayDialog bind:open={instantPaymentDialogOpen}', $source );
        $this->assertStringContainsString( 'const row = paymentDeepLink(params, bootstrap.rows);', $source );
        $this->assertStringContainsString( "params.delete('instant_payment_id')", $source );
    }

    public function test_instant_dialog_only_reads_matching_group_via_authorized_instant_endpoint(): void {
        $source = file_get_contents( PLUGIN_DIR . '/src/lib/payments/InstantScanToPayDialog.svelte' );
        $this->assertStringContainsString( "'kiriof_instant_payment'", $source );
        $this->assertStringContainsString( 'order_ids: JSON.stringify(ids)', $source );
        $this->assertStringContainsString( 'result.data.id !== identity', $source );
        $this->assertStringContainsString( 'active.stop()', $source );
        $this->assertStringNotContainsString( 'kiriof_get_payment_form', $source );
        $this->assertStringNotContainsString( 'kiriof_instant_dispatch', $source );
    }
}
