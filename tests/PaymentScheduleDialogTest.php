<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaymentScheduleDialogTest extends TestCase
{
    #[Test]
    public function payments_reschedule_uses_svelte_dialog_and_existing_ajax_contracts(): void
    {
        $list = file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' );
        $dialog = file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentScheduleDialog.svelte' );

        $this->assertStringContainsString( 'PaymentScheduleDialog', $list );
        $this->assertStringContainsString( "action: 'kiriof_get_shipping_reschedule_pickup'", $dialog );
        $this->assertStringContainsString( "action: 'kiriof_request_pickup_transaction'", $dialog );
        $this->assertStringContainsString( 'onComplete?.();', $dialog );
        $this->assertFileDoesNotExist( PLUGIN_DIR . '/assets/admin/js/kj-request-pickup.js' );
        $this->assertFileDoesNotExist( PLUGIN_DIR . '/src/lib/payments/PaymentsModals.svelte' );
    }
}
