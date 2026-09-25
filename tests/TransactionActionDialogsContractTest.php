<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionActionDialogsContractTest extends TestCase
{
    #[Test]
    public function svelte_action_dialogs_cover_origin_and_cod_adjustment_request_contracts(): void
    {
        $dialog = file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionActionDialogs.svelte' );

        $this->assertStringContainsString( "action: 'kiriof_change_origin_check'", $dialog );
        $this->assertStringContainsString( "action: 'kiriof_change_origin'", $dialog );
        $this->assertStringContainsString( 'courier_consent:', $dialog );
        $this->assertStringContainsString( "action: 'kiriof_cod_adjust'", $dialog );
        $this->assertStringContainsString( "'data[order_package_id]': action.data.kaOrderId", $dialog );
        $this->assertStringContainsString( "'data[new_total_cod]': String(Math.round(Number(codValue)))", $dialog );
        $this->assertStringContainsString( "action: 'kiriof_cancel_deficit'", $dialog );
        $this->assertStringContainsString( "action: 'kiriof_cancel_transaction'", $dialog );
        $this->assertStringContainsString( 'requiresCourierConsent', $dialog );
        $this->assertStringContainsString( 'codIsValid', $dialog );
        $this->assertStringNotContainsString( 'WCBackboneModal', $dialog );
    }

    #[Test]
    public function servers_enforce_origin_and_cod_adjustment_eligibility(): void
    {
        $transaction_controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php' );
        $cod_controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/CodAdjustmentController.php' );
        $detail_data = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionDetailPageData.php' );

        $this->assertStringContainsString( "'new' !== (string) \$kiriof_transaction->status || ! empty( \$kiriof_transaction->awb )", $transaction_controller );
        $this->assertStringContainsString( 'Origin can only be changed before pickup is requested.', $transaction_controller );
        $this->assertStringContainsString( 'Order is not flagged as deficit', $cod_controller );
        $this->assertStringContainsString( 'Must not be less than Rp%s to avoid deficit', $cod_controller );
        $this->assertStringContainsString( 'Must not exceed Rp%s', $cod_controller );
        $this->assertStringContainsString( "'codMaximum' => defined( 'KIRIOF_MAX_COD_AMOUNT' )", $detail_data );
    }
}
