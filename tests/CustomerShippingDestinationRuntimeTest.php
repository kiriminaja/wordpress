<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CustomerShippingDestinationRuntimeTest extends TestCase {
    private static function address(): array {
        return array( 'address_1' => 'Main street', 'address_2' => '', 'city' => 'Jakarta', 'state' => 'JK', 'postcode' => '12345', 'country' => 'ID' );
    }

    private static function destination( bool $pin = true ): array {
        $destination = array( 'district_id' => '222', 'district_label' => 'New district', 'postcode' => '12345', 'country' => 'ID', 'address_type' => 'shipping', 'version' => $pin ? 2 : 1 );
        return $pin ? $destination + array( 'destination_latitude' => '0', 'destination_longitude' => '106.8', 'shipping_address' => self::address() ) : $destination;
    }

    private static function profile( ?array $destination = null ): array {
        $meta = array( 'billing_address_1' => 'Billing only', 'billing_kiriof_destination_area' => '999' );
        foreach ( self::address() as $key => $value ) { $meta['shipping_' . $key] = $value; }
        if ( null !== $destination ) { $meta['_kiriof_buyer_destination'] = $destination; }
        return array( 7 => $meta );
    }

    #[Test]
    public function saved_account_pin_reads_persistent_meta_not_mutable_customer_and_preserves_zero(): void {
        $result = $this->runFixture( array( 'object' => true, 'meta' => self::profile( self::destination() ) ) );
        $this->assertSame( self::destination(), $result['destination'] );
        $this->assertSame( array(), $result['writes'] );
    }

    #[Test]
    public function reads_downgrade_changed_street_but_reject_changed_postcode_or_country_without_writes(): void {
        foreach ( array_keys( self::address() ) as $field ) {
            $meta = self::profile( self::destination() );
            $meta[7]['shipping_' . $field] = 'country' === $field ? 'US' : ( 'postcode' === $field ? '54321' : 'Changed' );
            $result = $this->runFixture( array( 'meta' => $meta ) );
            $this->assertSame( in_array( $field, array( 'postcode', 'country' ), true ) ? null : self::destination( false ), $result['destination'], $field );
            $this->assertSame( array(), $result['writes'] );
        }
    }

    #[Test]
    public function auth_isolation_applies_to_reads_writes_hydration_and_checkout_sync(): void {
        foreach ( array( 0, 8 ) as $current_user ) {
            foreach ( array( 'get', 'save', 'hydrate', 'sync', 'order' ) as $operation ) {
                $result = $this->runFixture( array( 'operation' => $operation, 'current_user' => $current_user, 'meta' => self::profile( self::destination() ), 'destination' => self::destination(), 'address' => self::address() ) );
                $this->assertSame( array(), $result['writes'], $operation );
                if ( 'get' === $operation ) { $this->assertNull( $result['destination'] ); }
            }
        }
    }

    #[Test]
    public function malformed_snapshots_fail_closed_instead_of_reviving_legacy(): void {
        $cases = array( array(), 'arbitrary', self::destination() );
        $cases[2]['destination_latitude'] = 'NaN';
        $bad = self::destination(); $bad['district_id'] = '0'; $cases[] = $bad;
        foreach ( $cases as $snapshot ) {
            $meta = self::profile();
            $meta[7]['_kiriof_buyer_destination'] = $snapshot;
            $meta[7]['shipping_kiriof_destination_area'] = '222';
            $meta[7]['shipping_kiriof_destination_area_name'] = 'New district';
            $result = $this->runFixture( array( 'meta' => $meta ) );
            $this->assertNull( $result['destination'] );
            $this->assertSame( array(), $result['writes'] );
        }
    }

    #[Test]
    public function legacy_metadata_requires_valid_identity_indonesian_country_and_five_digit_postcode(): void {
        foreach ( array( 'shipping_', 'shipping_kiriminaja-official/', '_wc_shipping/kiriminaja-official/' ) as $prefix ) {
            $meta = self::profile();
            $meta[7][$prefix . 'kiriof_destination_area'] = '222';
            $meta[7][$prefix . 'kiriof_destination_area_name'] = 'New district';
            $this->assertSame( self::destination( false ), $this->runFixture( array( 'meta' => $meta ) )['destination'] );
            foreach ( array( 'id' => '0', 'label' => '222', 'postcode' => 'ABC', 'country' => 'US', 'arbitrary' => array( 'bad' ) ) as $field => $value ) {
                $bad = $meta;
                $key = 'id' === $field ? $prefix . 'kiriof_destination_area' : ( in_array( $field, array( 'label', 'arbitrary' ), true ) ? $prefix . 'kiriof_destination_area_name' : 'shipping_' . $field );
                $bad[7][$key] = $value;
                $this->assertNull( $this->runFixture( array( 'meta' => $bad ) )['destination'], $field );
            }
        }
    }

    #[Test]
    public function save_binds_both_versions_and_mirrors_shipping_only_clear_removes_pin_and_keeps_address(): void {
        foreach ( array( true, false ) as $pin ) {
            $result = $this->runFixture( array( 'operation' => 'save', 'meta' => self::profile(), 'destination' => self::destination( $pin ), 'address' => self::address() ) );
            $this->assertSame( self::destination( $pin ), $result['meta'][7]['_kiriof_buyer_destination'] );
            $this->assertSame( self::address(), $result['meta'][7]['_kiriof_buyer_destination_address'] );
            $this->assertSame( '222', $result['meta'][7]['_wc_shipping/kiriminaja-official/kiriof_destination_area'] );
            $this->assertSame( $pin ? array( 'latitude' => '0', 'longitude' => '106.8' ) : null, $result['meta'][7]['_kiriof_buyer_destination_coordinates'] ?? null );
        }
        $clear = self::destination( false ); $clear['district_id'] = ''; $clear['district_label'] = '';
        $meta = self::profile( self::destination() ); $meta[7]['_kiriof_buyer_destination_coordinates'] = array( 'latitude' => '0', 'longitude' => '106.8' );
        $result = $this->runFixture( array( 'operation' => 'sync', 'meta' => $meta, 'destination' => $clear ) );
        $this->assertSame( $clear, $result['meta'][7]['_kiriof_buyer_destination'] );
        $this->assertArrayNotHasKey( '_kiriof_buyer_destination_coordinates', $result['meta'][7] );
        $this->assertSame( '', $result['meta'][7]['shipping_kiriof_destination_area'] );
        foreach ( self::profile()[7] as $key => $value ) { $this->assertSame( $value, $result['meta'][7][$key] ); }
    }

    #[Test]
    public function checkout_sync_is_safe_for_different_addresses_but_version_one_accepts_same_postcode(): void {
        $address = self::address(); $address['address_1'] = 'Other street in same postcode';
        $this->assertSame( array(), $this->runFixture( array( 'operation' => 'sync', 'meta' => self::profile(), 'destination' => self::destination( false ), 'checkout_address' => $address ) )['writes'] );
        foreach ( array( true, false ) as $pin ) {
            $meta = self::profile(); $meta[7]['shipping_address_1'] = 'Other street';
            $result = $this->runFixture( array( 'operation' => 'sync', 'meta' => $meta, 'destination' => self::destination( $pin ) ) );
            $this->assertSame( $pin, empty( $result['writes'] ) );
        }
        foreach ( array( 'postcode' => '99999', 'country' => 'US' ) as $field => $value ) {
            $meta = self::profile(); $meta[7]['shipping_' . $field] = $value;
            $this->assertSame( array(), $this->runFixture( array( 'operation' => 'sync', 'meta' => $meta, 'destination' => self::destination( false ) ) )['writes'] );
        }
    }

    #[Test]
    public function invalid_save_address_binding_never_partially_persists(): void {
        foreach ( array( 'address_1' => 'Different street', 'postcode' => '54321', 'country' => 'US' ) as $field => $value ) {
            $address = self::address();
            $address[$field] = $value;
            $result = $this->runFixture( array( 'operation' => 'save', 'meta' => self::profile(), 'destination' => self::destination(), 'address' => $address ) );
            $this->assertArrayHasKey( 'error', $result );
            $this->assertSame( array(), $result['writes'] );
        }
        $meta = self::profile( self::destination( false ) );
        $meta[7]['_kiriof_buyer_destination_address'] = array( 'address_1' => 'Incomplete' );
        $this->assertNull( $this->runFixture( array( 'meta' => $meta ) )['destination'] );
    }

    #[Test]
    public function session_has_strict_preference_including_explicit_clear_and_malformed_array(): void {
        $clear = self::destination( false ); $clear['district_id'] = ''; $clear['district_label'] = '';
        foreach ( array( self::destination( false ), $clear, array(), 'invalid' ) as $session_value ) {
            $input = array( 'operation' => 'checkout', 'meta' => self::profile( self::destination() ), 'session' => array( 'kiriof_buyer_destination' => $session_value ) );
            $result = $this->runFixture( $input );
            $this->assertSame( is_array( $session_value ) && ! empty( $session_value ) ? $session_value : null, $result['destination'] );
        }
        foreach ( array( array(), array( 'kiriof_buyer_destination' => null ) ) as $session ) {
            $this->assertSame( self::destination(), $this->runFixture( array( 'operation' => 'checkout', 'meta' => self::profile( self::destination() ), 'session' => $session ) )['destination'] );
        }
    }

    #[Test]
    public function hydration_restores_selection_coordinates_history_only_when_key_is_absent(): void {
        $input = array( 'operation' => 'hydrate', 'meta' => self::profile( self::destination() ) );
        $result = $this->runFixture( $input );
        $this->assertSame( self::destination(), $result['session']['kiriof_buyer_destination'] );
        $this->assertSame( '222', $result['session']['shipping_destination_id'] );
        $this->assertSame( array( 'latitude' => '0', 'longitude' => '106.8' ), $result['session']['kiriof_buyer_destination_coordinates'] );
        $this->assertSame( array( 'destination_id' => '222', 'destination_name' => 'New district' ), $result['session']['kiriof_destination_postcode_map']['12345'] );
        $this->assertSame( array(), $result['writes'] );
        foreach ( array( null, array(), self::destination( false ) ) as $value ) {
            $session = array( 'kiriof_buyer_destination' => $value );
            $this->assertSame( $session, $this->runFixture( $input + array( 'session' => $session ) )['session'] );
        }
        $this->assertSame( array(), $this->runFixture( $input + array( 'customer_id' => 8 ) )['session'] );
    }

    #[Test]
    public function processed_order_hooks_persist_only_owned_snapshot_matching_order_and_saved_profile(): void {
        foreach ( array( 'order', 'classic' ) as $operation ) {
            $input = array( 'operation' => $operation, 'meta' => self::profile(), 'destination' => self::destination(), 'address' => self::address() );
            $this->assertSame( self::destination(), $this->runFixture( $input )['meta'][7]['_kiriof_buyer_destination'] );
            $this->assertSame( array(), $this->runFixture( $input + array( 'order_user' => 8 ) )['writes'] );
            $address = self::address(); $address['address_1'] = 'Other street';
            $this->assertSame( array(), $this->runFixture( $input + array( 'order_address' => $address ) )['writes'] );
        }
        $this->assertSame( array(
            array( 'kiriof_buyer_destination_synced', 20, 2 ),
            array( 'woocommerce_checkout_order_processed', 30, 3 ),
            array( 'woocommerce_store_api_checkout_order_processed', 30, 1 ),
            array( 'woocommerce_init', 30, 0 ),
        ), $this->runFixture( array( 'operation' => 'register' ) )['hooks'] );
    }

    private function runFixture( array $input ): array {
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/customer-shipping-destination-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $exit_code );
        $text = implode( "\n", $output );
        $this->assertSame( 0, $exit_code, $text );
        return json_decode( $text, true, 512, JSON_THROW_ON_ERROR );
    }
}
