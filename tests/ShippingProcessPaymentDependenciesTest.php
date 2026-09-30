<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShippingProcessPaymentDependenciesTest extends TestCase
{
    #[Test]
    public function payment_service_receives_repositories_from_composition_root(): void
    {
        $service    = file_get_contents( PLUGIN_DIR . '/inc/Services/ShippingProcessServices/GetShippingProcessPayment.php' );
        $controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/ShippingProcessController.php' );
        $init       = file_get_contents( PLUGIN_DIR . '/inc/Init.php' );

        $this->assertStringContainsString( 'KiriminajaApiRepository $apiRepository', $service );
        $this->assertStringContainsString( 'PaymentRepository $paymentRepository', $service );
        $this->assertStringContainsString( 'TransactionRepository $transactionRepository', $service );
        $this->assertStringNotContainsString( 'new \\KiriminAjaOfficial\\Repositories\\', $service );
        $this->assertStringContainsString( 'GetShippingProcessPayment $shipping_process_payment', $controller );
        $this->assertStringContainsString( 'TransactionRepository $transaction_repository', $controller );
        $this->assertStringContainsString( 'KiriminajaApiRepository $api_repository', $controller );
        $this->assertStringNotContainsString( 'new KiriminajaApiRepository()', $controller );
        $this->assertStringContainsString( 'new Services\ShippingProcessServices\GetShippingProcessPayment(', $init );
    }
}
