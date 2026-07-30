<?php
use KiriminAjaOfficial\Services\TransactionProcessServices\SendRequestPickupTransactionService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

require_once PLUGIN_DIR . '/inc/Utils/ServiceResponse.php';
require_once PLUGIN_DIR . '/inc/Base/BaseService.php';
require_once PLUGIN_DIR . '/inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php';

final class RequestPickupDiscountPayloadRuntimeTest extends TestCase {
    #[Test]
    public function zero_discount_omits_stale_discount_percentage(): void {
        $payload = $this->appendDiscountFields(
            (object) array(
                'discount_amount'     => 0,
                'discount_percentage' => 15,
                'shipping_cost'       => 20000,
            )
        );

        $this->assertArrayNotHasKey( 'discount_amount', $payload );
        $this->assertArrayNotHasKey( 'discount_percentage', $payload );
    }

    #[Test]
    public function positive_discount_sends_amount_and_derived_percentage(): void {
        $payload = $this->appendDiscountFields(
            (object) array(
                'discount_amount'     => 5000,
                'discount_percentage' => null,
                'shipping_cost'       => 20000,
            )
        );

        $this->assertSame( 5000, $payload['discount_amount'] );
        $this->assertSame( 25.0, $payload['discount_percentage'] );
    }

    private function appendDiscountFields( object $transaction ): array {
        $service = new SendRequestPickupTransactionService();
        $method  = new ReflectionMethod( $service, 'appendPickupDiscountFields' );
        $method->setAccessible( true );

        return $method->invoke( $service, array( 'order_id' => 'ORDER-1' ), $transaction );
    }
}
