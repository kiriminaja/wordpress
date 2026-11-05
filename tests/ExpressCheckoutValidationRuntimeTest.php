<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExpressCheckoutValidationRuntimeTest extends TestCase {
    private function runCase( string $mode, array $amounts = array() ): array {
        $output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/express-checkout-validation-runtime.php' ) . ' ' . escapeshellarg( $mode ) . ' ' . escapeshellarg( json_encode( $amounts, JSON_THROW_ON_ERROR ) ) );
        return json_decode( (string) $output, true, 512, JSON_THROW_ON_ERROR );
    }

    #[Test]
    public function rejects_invalid_selection_and_late_policy_without_calculation(): void {
        foreach ( array( 'disabled', 'disconnected', 'unknown', 'zone', 'price', 'duplicate', 'cod', 'street', 'country', 'missing-field', 'malformed-origin' ) as $mode ) {
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
    public function pins_effective_full_origin_for_pricing_and_retries(): void {
        foreach ( array( 'valid' => 456, 'custom-origin' => 789 ) as $mode => $district ) {
            $result = $this->runCase( $mode );
            $this->assertTrue( $result['ok'] );
            $this->assertSame( $district, $result['snapshot']['origin_snapshot']['origin_sub_district_id'] );
            $this->assertSame( $result['pricing_origin'], $result['snapshot']['origin_snapshot'] );
            foreach ( array( 'origin_name', 'origin_phone', 'origin_address' ) as $field ) {
                $this->assertNotEmpty( $result['snapshot']['origin_snapshot'][$field] );
            }
        }
    }

    #[Test]
    public function normalizes_only_equivalent_postcode_formatting_before_pricing(): void {
        $result = $this->runCase( 'formatted-postcode' );
        $this->assertTrue( $result['ok'] );
        $this->assertSame( '12345', $result['pricing_postcode'] );
        $this->assertSame( array(), $result['logs'] );
        $result = $this->runCase( 'postcode-mismatch' );
        $this->assertFalse( $result['ok'] );
        $this->assertSame( 0, $result['calls'] );
    }

    #[Test]
    public function logs_fixed_failure_stages_without_private_checkout_values(): void {
        foreach ( array( 'street' => 'destination_mismatch', 'unknown' => 'rate_mismatch', 'malformed-origin' => 'origin_invalid', 'fee' => 'fee_amount_mismatch', 'total' => 'order_amount_mismatch' ) as $mode => $code ) {
            $result = $this->runCase( $mode );
            $this->assertFalse( $result['ok'] );
            $this->assertSame( array( array( 'warning', 'Express checkout quote validation failed.', array( 'operation' => 'validate_express_quote', 'code' => $code, 'backtrace' => false ), 'kiriminaja_checkout' ) ), $result['logs'] );
        }
    }

    #[Test]
    public function accepts_heizl_tax_rounding_without_changing_buyer_or_carrier_amounts(): void {
        // 77,500 includes 11% VAT; Woo retains fractional net items but rounds tax and grand totals.
        foreach ( array( 1, 2, 3 ) as $quantity ) {
            $items = round( 77500 * $quantity / 1.11, 6 );
            $tax = round( 77500 * $quantity - $items );
            $total = 77500 * $quantity + 5700 + 200;
            $result = $this->runCase( 'valid', array( 'decimals' => 0, 'items' => $items, 'tax' => $tax, 'shipping' => 5700, 'insurance' => 200, 'total' => $total ) );
            $this->assertTrue( $result['ok'], json_encode( $result ) );
            $this->assertSame( $total, $result['order_total'] );
            $calc = $result['snapshot']['validated_calculation']['calculation_result'];
            $this->assertEquals( 5700, $calc['ongkir_fee_amt'] );
            $this->assertEquals( 200, $calc['insurance_amt'] );
            $this->assertEquals( $items, $calc['cart_total_after_discount'] );
            $this->assertSame( array(), $result['logs'] );
        }
    }

    #[Test]
    public function compares_only_final_total_at_store_precision_not_an_arbitrary_tolerance(): void {
        $cases = array(
            array( 0, 100.49, 112, true ),
            array( 0, 100.50, 113, true ),
            array( 0, 100.50, 112, false ),
            array( 0, 100.49, 113, false ),
            array( 0, 100.49, 111, false ),
            array( 2, 100.004, 112, true ),
            array( 2, 100.005, 112.01, true ),
            array( 2, 100.005, 112, false ),
            array( 2, 100.004, 112.01, false ),
            array( 2, 100.004, 111.99, false ),
        );
        foreach ( $cases as list( $decimals, $items, $total, $accepted ) ) {
            $result = $this->runCase( 'valid', array( 'decimals' => $decimals, 'items' => $items, 'shipping' => 10, 'insurance' => 2, 'total' => $total ) );
            $this->assertSame( $accepted, $result['ok'], json_encode( compact( 'decimals', 'items', 'total', 'result' ) ) );
            if ( $accepted ) {
                $this->assertEquals( $total, $result['order_total'] );
            } else {
                $this->assertSame( 'order_amount_mismatch', $result['logs'][0][2]['code'] );
            }
        }
    }

    #[Test]
    public function preserves_discounted_items_and_merchant_fees_without_loosening_logistics_checks(): void {
        $items = round( 69750 / 1.11, 6 );
        $amounts = array( 'decimals' => 0, 'items' => $items, 'tax' => round( 69750 - $items ), 'shipping' => 5700, 'insurance' => 200, 'other_fees' => 1000, 'total' => 76650 );
        $result = $this->runCase( 'valid', $amounts );
        $this->assertTrue( $result['ok'], json_encode( $result ) );
        $this->assertSame( 76650, $result['order_total'] );
        foreach ( array( 'line_shipping' => 'rate_mismatch', 'line_insurance' => 'fee_amount_mismatch' ) as $field => $code ) {
            $changed = $amounts;
            $changed[$field] = ( 'line_shipping' === $field ? 5700 : 200 ) + 0.25;
            $result = $this->runCase( 'valid', $changed );
            $this->assertFalse( $result['ok'] );
            $this->assertSame( $code, $result['logs'][0][2]['code'] );
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
