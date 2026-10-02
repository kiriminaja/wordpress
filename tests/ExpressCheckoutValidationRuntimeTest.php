<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExpressCheckoutValidationRuntimeTest extends TestCase {
    private function runCase( string $mode ): array {
        $output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/express-checkout-validation-runtime.php' ) . ' ' . escapeshellarg( $mode ) );
        return json_decode( (string) $output, true, 512, JSON_THROW_ON_ERROR );
    }

    #[Test]
    public function rejects_invalid_selection_and_late_policy_without_calculation(): void {
        foreach ( array( 'disabled', 'disconnected', 'unknown', 'zone', 'price', 'duplicate', 'cod', 'street', 'country', 'missing-field' ) as $mode ) {
            $result = $this->runCase( $mode );
            $this->assertFalse( $result['ok'], $mode );
            $this->assertSame( 0, $result['calls'], $mode );
        }
    }

    #[Test]
    public function rejects_changed_fees_and_final_amount_without_repricing_order(): void {
        foreach ( array( 'fee', 'total' ) as $mode ) {
            $result = $this->runCase( $mode );
            $this->assertFalse( $result['ok'], $mode );
            $this->assertSame( 1, $result['calls'], $mode );
        }
    }

    #[Test]
    public function pins_json_safe_calculation_with_current_shipping_coupon(): void {
        foreach ( array( 'valid' => 10, 'coupon' => 5 ) as $mode => $amount ) {
            $result = $this->runCase( $mode );
            $this->assertTrue( $result['ok'], $mode );
            $this->assertSame( 1, $result['calls'] );
            $this->assertEquals( $amount, $result['snapshot']['validated_calculation']['calculation_result']['ongkir_fee_amt'] );
            $this->assertSame( 'jne', $result['snapshot']['validated_calculation']['calculation_result']['selected_expedition']['service'] );
            $this->assertArrayNotHasKey( 'payload', $result['snapshot']['validated_calculation'] );
            $this->assertStringNotContainsString( 'private-', json_encode( $result['snapshot'] ) );
        }
    }
    #[Test]
    public function leaves_tax_and_currency_to_woocommerce(): void {
        // The quote is compared in Woo's current monetary unit, not converted/repriced.
        $result = $this->runCase( 'tax' );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( 1, $result['calls'] );
        $this->assertEquals( 112, $result['snapshot']['validated_calculation']['calculation_result']['calc_total_amt'] );
    }
}
