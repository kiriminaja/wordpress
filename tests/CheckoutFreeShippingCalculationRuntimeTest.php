<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Real calculation and coupon services; only external repository/cart boundaries are stubbed. */
final class CheckoutFreeShippingCalculationRuntimeTest extends TestCase {
    private function calculate( array $input ): array {
        $input += array( 'free_coupon' => true, 'insurance' => false, 'force_insurance' => true, 'global_insurance' => 'no', 'cod' => true );
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/checkout-free-shipping-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
    }

    public static function quote_sources(): iterable {
        yield 'SDK pricing' => array( false );
        yield 'cached SDK pricing' => array( true );
    }

    #[DataProvider( 'quote_sources' )]
    public function test_blocks_free_shipping_retains_real_quote_insurance_and_cod( bool $cached ): void {
        $result = $this->calculate( array( 'blocks' => true, 'cached' => $cached ) );
        $this->assertSame( 200, $result['status'] );
        $this->assertSame( $cached ? 0 : 1, $result['api_calls'] );
        $calc = $result['data']['calculation_result'];
        $this->assertSame( 'jne', $calc['selected_expedition']['service'] );
        $this->assertSame( 'REG', $calc['selected_expedition']['service_type'] );
        $this->assertSame( $result['raw_quote']['results'][0], $calc['selected_expedition'] );
        $this->assertSame( 20000, $calc['ongkir_fee_raw'] );
        $this->assertSame( 18000, $calc['ongkir_fee_amt'] );
        $this->assertSame( 1250, $calc['insurance_amt'] );
        $this->assertSame( 3251, $calc['cod_amt'] );
        $this->assertSame( 122501, $calc['calc_total_amt'] );
        $this->assertSame( $result['raw_quote'], $result['data']['pricing'] );
        $this->assertSame( array( 'jne' ), $result['data']['pricing_payload']['courier'] );
        // Coupon application is separate, waiving only buyer delivery after calculation.
        $this->assertSame( 0, $result['adjusted']['cost'] );
        $this->assertSame( 18000, $result['adjusted']['original_cost'] );
        $this->assertSame( 18000, $result['adjusted']['discount_amount'] );
    }

    public function test_classic_free_shipping_keeps_existing_synthetic_shortcut_by_default(): void {
        foreach ( array( array(), array( 'blocks' => false ) ) as $input ) {
            $result = $this->calculate( $input );
            $this->assertSame( 200, $result['status'] );
            $this->assertSame( 0, $result['api_calls'] );
            $this->assertNull( $result['data']['pricing'] );
            $this->assertSame( array(), $result['data']['pricing_payload'] );
            $calc = $result['data']['calculation_result'];
            $this->assertSame( 0, $calc['ongkir_fee_raw'] );
            $this->assertSame( 0, $calc['ongkir_fee_amt'] );
            $this->assertSame( 0, $calc['insurance_amt'] );
            $this->assertSame( 0, $calc['cod_amt'] );
            $this->assertSame( 100000, $calc['calc_total_amt'] );
        }
    }

    public static function insurance_cases(): iterable {
        yield 'forced insurance despite opt out' => array( false, true, 'no', 1250 );
        yield 'buyer opt in' => array( true, false, 'no', 1250 );
        yield 'merchant enabled' => array( false, false, 'yes', 1250 );
        yield 'fully optional opt out' => array( false, false, 'no', 0 );
    }

    #[DataProvider( 'insurance_cases' )]
    public function test_blocks_insurance_condition_uses_real_quote( bool $insurance, bool $force, string $global, int $expected ): void {
        $result = $this->calculate( array( 'blocks' => true, 'insurance' => $insurance, 'force_insurance' => $force, 'global_insurance' => $global, 'cod' => false ) );
        $this->assertSame( $expected, $result['data']['calculation_result']['insurance_amt'] );
        $this->assertSame( 0, $result['data']['calculation_result']['cod_amt'] );
        $this->assertSame( (int) $insurance, $result['api_payload']['insurance'] );
    }

    public function test_blocks_free_coupon_cannot_invent_missing_or_disabled_services(): void {
        foreach ( array( array( 'expedition' => 'jne_UNKNOWN' ), array( 'disabled' => true ) ) as $input ) {
            $result = $this->calculate( array( 'blocks' => true ) + $input );
            $this->assertSame( 400, $result['status'] );
            $this->assertSame( 0, $result['api_calls'] );
        }
        $missing = $this->calculate( array( 'blocks' => true, 'missing_rate' => true ) );
        $this->assertSame( 400, $missing['status'] );
        $this->assertSame( 1, $missing['api_calls'] );
    }
}
