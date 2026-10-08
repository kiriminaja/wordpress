<?php

use PHPUnit\Framework\TestCase;

final class ExpressCouponRepricingRuntimeTest extends TestCase {
    private function runtime(): array {
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/express-coupon-repricing-runtime.php' ) . ' 2>&1', $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
    }

    public function test_native_selected_ninja_rate_reprices_raw_cache_on_every_coupon_change(): void {
        $result = $this->runtime();
        $expected = array( 'none' => 18000, 'fixed' => 13000, 'removed' => 18000, 'fixed_again' => 13000, 'removed_again' => 18000, 'percent' => 13500, 'over_cap' => 0, 'free' => 0, 'free_removed' => 18000 );
        foreach ( $expected as $state => $cost ) {
            $row = $result['states'][$state];
            $this->assertSame( $cost, $row['cost'], $state );
            $this->assertSame( 1, $row['api_calls'], $state );
            $this->assertSame( $cost, $row['native_adjusted']['cost'], $state );
            $meta = $row['meta']['kiriminaja-official_ninja_STANDARD'];
            $this->assertSame( 18000, $meta['original_cost'], $state );
            $this->assertSame( 18000 - $cost, $meta['discount_amount'], $state );
            if ( 'free' !== $state ) {
                $this->assertTrue( $row['validation']['valid'], $state );
            }
            $first = $cost < 16000 && 'free' !== $state ? 'kiriminaja-official_ninja_STANDARD' : 'kiriminaja-official_jne_REG';
            $this->assertSame( $first, $row['ids'][0], $state );
        }
        $this->assertSame( $result['quote'], $result['cached_quote'] );
        $this->assertSame( 1, $result['api_calls'], 'Factory calculation should share the raw provider cache.' );
        $this->assertSame( 200, $result['calculation']['status'] );
        $calc = $result['calculation']['data']['calculation_result'];
        $this->assertSame( $result['quote']['results'][0], $calc['selected_expedition'] );
        $this->assertSame( 20000, $calc['ongkir_fee_raw'] );
        $this->assertSame( 18000, $calc['ongkir_fee_amt'] );
        $this->assertSame( 1250, $calc['insurance_amt'] );
    }

    public function test_every_empty_express_recalculation_cleans_only_express_metadata(): void {
        $result = $this->runtime();
        foreach ( $result['cleanup'] as $case => $metadata ) {
            $this->assertSame( array( 'kiriminaja-instant:3:gosend:instant' => array( 'cost' => 999, 'original_cost' => 999 ) ), $metadata, $case );
        }
    }
}
