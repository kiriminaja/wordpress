<?php
use PHPUnit\Framework\TestCase;

final class ExpressPricingHierarchyRuntimeTest extends TestCase {
    public function test_pricing_requires_verified_kecamatan_and_preserves_village_identity(): void {
        $output = array();
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/express-pricing-hierarchy-runtime.php' ) . ' 2>&1', $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        $result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
        $this->assertTrue( $result['first']['status'] );
        $this->assertTrue( $result['repeat']['status'] );
        $this->assertTrue( $result['cached_without_postcode']['status'] );
        foreach ( array( 'wrong_postcode', 'missing_origin', 'unknown_without_postcode', 'wrong_village' ) as $case ) {
            $this->assertFalse( $result[$case]['status'], $case );
            $this->assertSame( 'Could not resolve shipping address hierarchy.', $result[$case]['data'] );
        }
        // Cold lookups, mismatch revalidation, unknown village, and explicit UI warming.
        $this->assertCount( 5, $result['searches'] );
        $this->assertCount( 4, $result['posts'] );
        $this->assertTrue( $result['warm']['status'] );
        $this->assertSame( 0, $result['warm_additional_searches'] );
		$this->assertCount( 4, $result['logs'] );
		$this->assertSame( array( 'destination_hierarchy_unavailable', 'origin_hierarchy_unavailable', 'origin_hierarchy_unavailable', 'destination_hierarchy_unavailable' ), array_column( array_column( $result['logs'], 2 ), 'code' ) );
		foreach ( $result['logs'] as $log ) {
			$this->assertSame( array( 'warning', 'Express shipping address verification failed.', array( 'operation' => 'get_pricing', 'code' => $log[2]['code'], 'backtrace' => false ), 'kiriminaja_shipping' ), $log );
		}
		foreach ( array( '61475', '55791', 'Sidokerto', '31483', '46310' ) as $private ) {
			$this->assertStringNotContainsString( $private, json_encode( $result['logs'] ) );
		}
        foreach ( $result['cached_identities'] as $key => $identity ) {
            $this->assertMatchesRegularExpression( '/\A[a-f0-9]{64}\z/', $key );
            $this->assertSame( array( 'district_id', 'city_id', 'province_id', 'postcode', 'expires' ), array_keys( $identity ) );
        }
        foreach ( $result['posts'] as [ $endpoint, $payload ] ) {
            $this->assertSame( '/api/mitra/v6.1/shipping_price', $endpoint );
            $this->assertSame( 2275, $payload['origin'] );
            $this->assertSame( 548, $payload['destination'] );
            $this->assertSame( 46310, $payload['subdistrict_origin'] );
            $this->assertSame( 31483, $payload['subdistrict_destination'] );
            $this->assertArrayNotHasKey( 'origin_postcode', $payload );
            $this->assertArrayNotHasKey( 'destination_postcode', $payload );
        }
    }
}
