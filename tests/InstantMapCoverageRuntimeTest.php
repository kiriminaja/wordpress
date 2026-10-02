<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantMapCoverageRuntimeTest extends TestCase {
    private function runFixture( array $input ): array {
        $command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/instant-map-coverage-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) );
        exec( $command . ' 2>&1', $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
    }

    private function origin(): array { return array( 'origin_latitude' => '-6.2', 'origin_longitude' => '106.8' ); }
    private function circle(): array { return array( 'origin' => array( 'latitude' => '-6.2', 'longitude' => '106.8' ), 'radiusMeters' => 40000 ); }

    #[Test]
    public function coordinates_are_normalized_and_private_fields_are_not_published(): void {
        $result = $this->runFixture( array( 'operation' => 'origin', 'origin' => array( 'origin_latitude' => 0, 'origin_longitude' => 106.123456789, 'origin_phone' => 'secret', 'origin_address' => 'secret' ) ) );
        $this->assertSame( array( 'origin' => array( 'latitude' => '0', 'longitude' => '106.1234568' ), 'radiusMeters' => 40000 ), $result['coverage'] );
        foreach ( array( null, '', false, true, array(), 'NaN', '1e2', 91 ) as $invalid ) {
            $result = $this->runFixture( array( 'operation' => 'origin', 'origin' => array( 'origin_latitude' => $invalid, 'origin_longitude' => 106 ) ) );
            $this->assertNull( $result['coverage'] );
        }
        $this->assertNull( $this->runFixture( array( 'operation' => 'origin', 'origin' => array() ) )['coverage'] );
    }

    #[Test]
    public function explicit_invalid_package_origin_never_falls_back(): void {
        foreach ( array( null, false, 'invalid', array(), array( 'origin_latitude' => null, 'origin_longitude' => 106 ) ) as $invalid ) {
            $result = $this->runFixture( array( 'packages' => array( array( 'origin' => $invalid ) ) ) );
            $this->assertNull( $result['coverage'] );
            $this->assertNull( $result['map']['coverage'] );
            $this->assertNull( $result['schemas'][1]['data']['coverage'] );
            $this->assertSame( 0, $result['default_reads'] );
        }
    }

    #[Test]
    public function multiple_packages_only_publish_a_shared_valid_origin(): void {
        $origin = $this->origin();
        $same = $origin;
        $same['origin_latitude'] = '-6.200000';
        $same['origin_address'] = 'different private address';
        $result = $this->runFixture( array( 'packages' => array( array( 'origin' => $origin ), array( 'origin' => $same ) ) ) );
        $this->assertSame( $this->circle(), $result['coverage'] );
        foreach ( array( array(), array( 'origin' => null ), array( 'origin' => array( 'origin_latitude' => '-7', 'origin_longitude' => 106 ) ) ) as $other ) {
            $result = $this->runFixture( array( 'packages' => array( array( 'origin' => $origin ), $other ) ) );
            $this->assertNull( $result['coverage'] );
            $this->assertSame( 0, $result['default_reads'] );
        }
    }

    #[Test]
    public function missing_single_origin_and_missing_woocommerce_use_local_default(): void {
        foreach ( array( array(), array( 'packages' => array( array() ) ), array( 'no_wc' => true ) ) as $input ) {
            $result = $this->runFixture( $input );
            $this->assertSame( $this->circle(), $result['coverage'] );
            $this->assertSame( $this->circle(), $result['map']['coverage'] );
            $this->assertGreaterThan( 0, $result['default_reads'] );
        }
        $result = $this->runFixture( array( 'account' => true, 'packages' => array( array( 'origin' => null ) ) ) );
        $this->assertNull( $result['coverage'] );
        $this->assertSame( $this->circle(), $result['map']['coverage'] );
    }

    #[Test]
    public function cart_coverage_schema_is_readonly_nullable_and_separate_from_checkout(): void {
        $result = $this->runFixture( array( 'packages' => array( array( 'origin' => $this->origin() ) ) ) );
        $this->assertSame( 'checkout', $result['schemas'][0]['endpoint'] );
        $this->assertSame( 'kiriminaja-official', $result['schemas'][0]['namespace'] );
        $this->assertSame( array( 'destination' ), array_keys( $result['schemas'][0]['schema'] ) );
        $cart = $result['schemas'][1];
        $this->assertSame( 'cart', $cart['endpoint'] );
        $this->assertSame( 'kiriminaja-official-instant-coverage', $cart['namespace'] );
        $this->assertSame( 'ARRAY_A', $cart['schema_type'] );
        $this->assertSame( array( 'object', 'null' ), $cart['schema']['coverage']['type'] );
        $this->assertTrue( $cart['schema']['coverage']['readonly'] );
        $this->assertNull( $cart['schema']['coverage']['default'] );
        $this->assertSame( array( 'view' ), $cart['schema']['coverage']['context'] );
        $this->assertSame( array( 'coverage' => $this->circle() ), $cart['data'] );
        $source = file_get_contents( PLUGIN_DIR . '/inc/Controllers/CheckoutController.php' );
        $this->assertStringContainsString( "add_action( 'woocommerce_blocks_loaded', array( \$this, 'kiriof_register_coverage_schema' ) );", $source );
        $this->assertArrayHasKey( 'mapCoverage', $result['map']['i18n'] );
        $this->assertArrayHasKey( 'mapOutsideRadius', $result['map']['i18n'] );
    }
}
