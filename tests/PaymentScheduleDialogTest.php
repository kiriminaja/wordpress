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
        $modals = file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsModals.svelte' );
        $legacy = file_get_contents( PLUGIN_DIR . '/assets/admin/js/kj-request-pickup.js' );

        $this->assertStringContainsString( 'PaymentScheduleDialog', $list );
        $this->assertStringContainsString( "action: 'kiriof_get_shipping_reschedule_pickup'", $dialog );
        $this->assertStringContainsString( "action: 'kiriof_request_pickup_transaction'", $dialog );
        $this->assertStringContainsString( 'onComplete?.();', $dialog );
        $this->assertStringNotContainsString( 'id="request-pickup-modal"', $modals );
        $this->assertStringNotContainsString( '$(document).on("click", ".kiriof-reschedule-button"', $legacy );
        $this->assertStringNotContainsString( '$(document).on("click", ".kiriof-request-pickup-submit"', $legacy );
    }
}
