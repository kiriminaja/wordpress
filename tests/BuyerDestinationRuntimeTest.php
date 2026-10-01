<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BuyerDestinationRuntimeTest extends TestCase {
    #[Test]
    public function real_factory_district_search_uses_injected_repository_without_network(): void {
        if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__ ) . '/' ); }
        if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }
        require_once dirname( __DIR__ ) . '/vendor/autoload.php';

        $repository = $this->getMockBuilder( \KiriminAjaOfficial\Repositories\KiriminajaApiRepository::class )
            ->disableOriginalConstructor()->onlyMethods( array( 'sub_district_search' ) )->getMock();
        $rows = array( (object) array( 'id' => 222, 'text' => 'New district' ) );
        $repository->expects( $this->once() )->method( 'sub_district_search' )->with( '12345' )
            ->willReturn( array( 'status' => true, 'data' => (object) array( 'result' => $rows ) ) );
        $factory = new \KiriminAjaOfficial\Services\CheckoutServiceFactory(
            $this->getMockBuilder( \KiriminAjaOfficial\Repositories\SettingRepository::class )->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder( \KiriminAjaOfficial\Repositories\TransactionRepository::class )->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder( \KiriminAjaOfficial\Repositories\WpPostMetaRepository::class )->disableOriginalConstructor()->getMock(),
            $repository,
            $this->getMockBuilder( \KiriminAjaOfficial\Repositories\CodFeeApiRepository::class )->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder( \KiriminAjaOfficial\Services\ShipmentLocationService::class )->disableOriginalConstructor()->getMock()
        );

        $response = $factory->districtSearch( '12345' );
        $this->assertInstanceOf( \KiriminAjaOfficial\Utils\ServiceResponse::class, $response );
        $this->assertSame( 200, $response->status );
        $this->assertSame( $rows, $response->data );
    }

    private static function destination(): array {
        return array( 'district_id' => '222', 'district_label' => 'New district', 'postcode' => '12345', 'country' => 'ID', 'address_type' => 'shipping', 'version' => 1 );
    }

    #[Test]
    public function checkout_schema_exposes_typed_destination_without_coordinates(): void {
        $result = $this->runFixture( array( 'operation' => 'schema' ) );
        $this->assertSame( 'checkout', $result['schema']['endpoint'] );
        $this->assertSame( 'kiriminaja-official', $result['schema']['namespace'] );
        $properties = $result['schema']['schema']['destination']['properties'];
        $this->assertSame( array( 'string', 'integer' ), $properties['district_id']['type'] );
        $this->assertSame( array( 'shipping' ), $properties['address_type']['enum'] );
        $this->assertSame( array( 1 ), $properties['version']['enum'] );
        $this->assertArrayNotHasKey( 'coordinates', $properties );
    }

    #[Test]
    public function modern_sync_is_authoritative_and_does_not_select_a_rate(): void {
        $native = array( 'kiriminaja-official_jnt_EZ', 'flat_rate:2' );
        $destination = self::destination();
        $destination['district_id'] = 222;
        $destination['country'] = 'id';
        $destination['postcode'] = '12 345';
        $result = $this->runFixture( array( 'operation' => 'sync', 'session' => array( 'chosen_shipping_methods' => $native ), 'data' => array(
            'action' => 'sync_checkout', 'destination' => $destination, 'destination_id' => 999, 'destination_name' => 'Stale district', 'postcode' => '99999',
            'shipping_metode_id' => 'kiriminaja-official_jne_REG', 'payment_method' => 'cod', 'insurance' => true, 'force_insurance' => false,
        ) ) );
        $this->assertArrayNotHasKey( 'error', $result );
        $this->assertSame( self::destination(), $result['session']['kiriof_buyer_destination'] );
        $this->assertSame( '222', $result['session']['destination_id'] );
        $this->assertSame( 'New district', $result['session']['destination_name'] );
        $this->assertSame( array( 'destination_id' => '222', 'destination_name' => 'New district' ), $result['session']['kiriof_destination_postcode_map']['12345'] );
        $this->assertSame( '12345', $result['session']['kiriof_checkout_postcode'] );
        $this->assertSame( $native, $result['session']['chosen_shipping_methods'] );
        $this->assertSame( 'cod', $result['session']['chosen_payment_method'] );
        $this->assertSame( 1, $result['session']['kiriof_insurance'] );
    }

    #[Test]
    public function empty_modern_sync_clears_id_name_and_token_but_preserves_rates(): void {
        $destination = self::destination();
        $destination['district_id'] = '';
        $destination['district_label'] = '';
        $native = array( 'kiriminaja-official_jnt_EZ' );
        $result = $this->runFixture( array( 'operation' => 'sync', 'session' => array( 'chosen_shipping_methods' => $native, 'destination_id' => '999', 'destination_name' => 'Stale' ), 'data' => array( 'action' => 'sync_checkout', 'destination' => $destination ) ) );
        $this->assertSame( '', $result['session']['destination_id'] );
        $this->assertSame( '', $result['session']['destination_name'] );
        $this->assertSame( '', $result['session']['kiriof_checkout_token'] );
        $this->assertSame( $native, $result['session']['chosen_shipping_methods'] );
    }

    #[Test]
    public function fee_only_sync_is_partial_safe(): void {
        $session = array( 'destination_id' => '222', 'destination_name' => 'New district', 'kiriof_insurance' => 1, 'force_insurance' => 1, 'chosen_shipping_methods' => array( 'flat_rate:2' ) );
        $result = $this->runFixture( array( 'operation' => 'sync', 'session' => $session, 'data' => array( 'action' => 'sync_checkout', 'payment_method' => 'bacs' ) ) );
        foreach ( $session as $key => $value ) { $this->assertSame( $value, $result['session'][$key] ); }
        $this->assertSame( 'bacs', $result['session']['chosen_payment_method'] );
    }

    #[Test]
    #[DataProvider( 'malformedDestinations' )]
    public function malformed_sync_is_rejected_without_mutating_session( $destination ): void {
        $session = array( 'destination_id' => '111', 'destination_name' => 'Old district' );
        $result = $this->runFixture( array( 'operation' => 'sync', 'session' => $session, 'data' => array( 'action' => 'sync_checkout', 'destination' => $destination ) ) );
        $this->assertSame( 'kiriof_invalid_destination', $result['error']['code'] );
        $this->assertSame( 400, $result['error']['status'] );
        $this->assertSame( $session, $result['session'] );
        $this->assertStringNotContainsString( '<script>', $result['error']['message'] );
    }

    public static function malformedDestinations(): array {
        $cases = array( 'null' => array( null ), 'scalar' => array( '222' ), 'empty object' => array( array() ) );
        foreach ( array(
            'district_id' => array( -1, 0, 1.5, true, array(), '-1', '0', '1e3', '2.0', ' 222', 'District', '01' ),
            'district_label' => array( null, 222, array(), '', '222', '<script>bad</script>', "Bad\nlabel" ),
            'postcode' => array( null, 12345, array(), '' ),
            'country' => array( null, 'Indonesia', '' ),
            'address_type' => array( 'billing', null ),
            'version' => array( 2, '1', true ),
        ) as $field => $values ) {
            foreach ( $values as $index => $value ) {
                $destination = self::destination();
                $destination[$field] = $value;
                $cases[$field . '-' . $index] = array( $destination );
            }
        }
        return $cases;
    }

    #[Test]
    public function modern_order_wins_over_legacy_and_persists_permanent_snapshot(): void {
        $destination = self::destination();
        $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => $destination ) ), 'shipping_address' => array( 'postcode' => '12 345', 'country' => 'id', 'additional_fields' => array( 'kiriminaja-official/kiriof_destination_area' => '999' ) ) ) );
        $this->assertArrayNotHasKey( 'error', $result );
        $this->assertSame( $destination, $result['meta']['_kiriof_buyer_destination'] );
        $this->assertSame( '222', $result['meta']['_kiriof_checkout_destination_area'] );
        $this->assertSame( 'New district', $result['meta']['_kiriof_checkout_destination_area_name'] );
        $this->assertSame( '12345', $result['meta']['_kiriof_checkout_postcode'] );
        $this->assertSame( '1', $result['meta']['_kiriof_checkout_destination_present'] );
        $this->assertSame( '1', $result['meta']['_kiriof_checkout_token'] );
    }

    #[Test]
    #[DataProvider( 'invalidOrders' )]
    public function invalid_modern_kiriminaja_checkout_never_uses_session_or_resolves_a_district( array $params ): void {
        $result = $this->order( $params );
        $this->assertSame( 'kiriof_invalid_destination', $result['error']['code'] );
        $this->assertSame( 400, $result['error']['status'] );
    }

    public static function invalidOrders(): array {
        $wrap = static fn( $destination ) => array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => $destination ) ) );
        $empty = self::destination(); $empty['district_id'] = ''; $empty['district_label'] = '';
        $foreign = self::destination(); $foreign['country'] = 'US';
        return array(
            'missing' => array( array( 'extensions' => array( 'kiriminaja-official' => array() ) ) ),
            'empty district' => array( $wrap( $empty ) ),
            'foreign country' => array( $wrap( $foreign ) ),
            'postcode mismatch' => array( $wrap( self::destination() ) + array( 'shipping_address' => array( 'postcode' => '99999', 'country' => 'ID' ) ) ),
            'country mismatch' => array( $wrap( self::destination() ) + array( 'shipping_address' => array( 'postcode' => '12345', 'country' => 'US' ) ) ),
        );
    }

    #[Test]
    public function order_getters_validate_when_request_address_is_absent(): void {
        $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => self::destination() ) ) ), array( 'order_postcode' => '99999' ) );
        $this->assertSame( 'kiriof_invalid_destination', $result['error']['code'] );
    }

    #[Test]
    public function unrelated_order_shipping_lines_override_stale_kiriminaja_session(): void {
        $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => null ) ) ), array( 'order_methods' => array( 'flat_rate' ) ) );
        $this->assertArrayNotHasKey( 'error', $result );
        $this->assertArrayNotHasKey( '_kiriof_buyer_destination', $result['meta'] );
    }

    #[Test]
    public function omitted_modern_snapshot_cannot_fall_back_to_a_modern_session(): void {
        $result = $this->runFixture( array( 'operation' => 'order', 'params' => array(), 'session' => array( 'chosen_shipping_methods' => array( 'kiriminaja-official_jnt_EZ' ), 'kiriof_buyer_destination' => self::destination(), 'kiriof_destination_area' => '222' ) ) );
        $this->assertSame( 'kiriof_invalid_destination', $result['error']['code'] );
    }

    #[Test]
    public function sync_country_must_match_known_customer_country(): void {
        $result = $this->runFixture( array( 'operation' => 'sync', 'customer_country' => 'US', 'data' => array( 'action' => 'sync_checkout', 'destination' => self::destination() ) ) );
        $this->assertSame( 'kiriof_invalid_destination', $result['error']['code'] );
        $this->assertSame( array(), $result['session'] );
    }

    #[Test]
    public function processed_hook_does_not_revive_stale_session_after_modern_transients_are_consumed(): void {
        $result = $this->runFixture( array( 'operation' => 'processed', 'meta' => array( '_kiriof_buyer_destination' => self::destination(), '_kiriof_checkout_destination_present' => '1' ), 'session' => array( 'kiriof_expedition' => 'jne_REG', 'kiriof_destination_area' => '111', 'kiriof_destination_area_name' => 'Old district' ) ) );
        $this->assertArrayNotHasKey( 'error', $result );
        $this->assertNull( $result['transaction'] );
        $this->assertSame( self::destination(), $result['meta']['_kiriof_buyer_destination'] );
    }

    #[Test]
    public function modern_clear_preserves_canonical_postcode_history_without_customer_writes(): void {
        $destination = self::destination();
        $destination['district_label'] = 'Forged district';
        $destination['postcode'] = '12 345';
        $clear = self::destination();
        $clear['district_id'] = ''; $clear['district_label'] = '';
        $result = $this->runFixture( array( 'operation' => 'sync', 'customer' => true, 'updates' => array(
            array( 'action' => 'sync_checkout', 'destination' => $destination ),
            array( 'action' => 'sync_checkout', 'destination' => $clear ),
        ) ) );
        $this->assertSame( '', $result['session']['destination_id'] );
        $this->assertSame( array( 'destination_id' => '222', 'destination_name' => 'New district' ), $result['session']['kiriof_destination_postcode_map']['12345'] );
        $this->assertSame( array( '12345' ), $result['lookup_calls'] );
        $this->assertSame( array(), $result['customer_saves'] );
    }

    #[Test]
    public function processed_hook_preserves_permanent_snapshot_and_does_not_fill_empty_label_from_session(): void {
        $meta = array( '_kiriof_buyer_destination' => self::destination(), '_kiriof_checkout_destination_present' => '1', '_kiriof_checkout_destination_area' => '222', '_kiriof_checkout_destination_area_name' => '', '_kiriof_checkout_postcode' => '12345', '_kiriof_checkout_expedition' => 'jnt_EZ' );
        $result = $this->runFixture( array( 'operation' => 'processed', 'meta' => $meta, 'session' => array( 'kiriof_destination_area_name' => 'Stale district' ) ) );
        $this->assertSame( '', $result['transaction']['kiriof_destination_area_name'] );
        $this->assertSame( '12345', $result['transaction']['destination_zipcode'] );
        $this->assertSame( self::destination(), $result['meta']['_kiriof_buyer_destination'] );
        $this->assertArrayNotHasKey( '_kiriof_checkout_destination_area', $result['meta'] );
    }

    #[Test]
    public function sync_never_erases_customer_metadata_or_fetches_for_a_clear(): void {
        $destination = self::destination();
        $destination['district_id'] = ''; $destination['district_label'] = '';
        $result = $this->runFixture( array( 'operation' => 'sync', 'customer' => true, 'data' => array( 'action' => 'sync_checkout', 'destination' => $destination ) ) );
        $this->assertSame( array(), $result['customer_saves'] );
        $this->assertSame( array(), $result['lookup_calls'] );
        $result = $this->runFixture( array( 'operation' => 'sync', 'customer' => true, 'data' => array( 'action' => 'sync_checkout', 'destination' => self::destination() ) ) );
        $this->assertSame( array(), $result['customer_saves'] );
    }

    #[Test]
    public function server_identity_overrides_label_and_caches_postcode_results(): void {
        $destination = self::destination(); $destination['district_label'] = 'Forged district';
        $update = array( 'action' => 'sync_checkout', 'destination' => $destination );
        $result = $this->runFixture( array( 'operation' => 'sync', 'updates' => array( $update, $update ) ) );
        $this->assertSame( 'New district', $result['session']['destination_name'] );
        $this->assertSame( array( '12345' ), $result['lookup_calls'] );
        $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => $destination ) ) ) );
        $this->assertSame( self::destination(), $result['meta']['_kiriof_buyer_destination'] );
    }

    #[Test]
    public function unknown_district_and_api_failure_fail_closed_without_mutation(): void {
        foreach ( array( array( 'lookup_rows' => array( array( 'id' => 111, 'text' => 'Other postcode district' ) ) ), array( 'lookup_status' => 400 ), array( 'lookup_throw' => true ) ) as $extra ) {
            $result = $this->runFixture( $extra + array( 'operation' => 'sync', 'session' => array( 'destination_id' => '111' ), 'data' => array( 'action' => 'sync_checkout', 'destination' => self::destination() ) ) );
            $this->assertArrayHasKey( 'error', $result );
            $this->assertSame( array( 'destination_id' => '111' ), $result['session'] );
            $this->assertStringNotContainsString( 'secret', $result['error']['message'] );
            $result = $this->order( array( 'extensions' => array( 'kiriminaja-official' => array( 'destination' => self::destination() ) ) ), $extra );
            $this->assertArrayHasKey( 'error', $result );
            $this->assertArrayNotHasKey( '_kiriof_buyer_destination', $result['meta'] ?? array() );
        }
    }

    #[Test]
    public function legacy_update_clears_modern_contract_marker(): void {
        $result = $this->runFixture( array( 'operation' => 'sync', 'session' => array( 'kiriof_buyer_destination' => self::destination() ), 'data' => array( 'destination_id' => 111, 'destination_name' => 'Legacy' ) ) );
        $this->assertNull( $result['session']['kiriof_buyer_destination'] );
    }

    #[Test]
    public function global_insurance_cannot_be_disabled_by_modern_payload(): void {
        $result = $this->runFixture( array( 'operation' => 'sync', 'global_insurance' => true, 'data' => array( 'action' => 'sync_checkout', 'insurance' => false, 'force_insurance' => false ) ) );
        foreach ( array( 'kiriof_insurance', 'billing_insurance', 'force_insurance', 'kiriof_force_insurance' ) as $key ) {
            $this->assertSame( 1, $result['session'][$key] );
        }
    }

    #[Test]
    public function transaction_failure_retains_retry_context_and_success_persists_customer(): void {
        $meta = array( '_kiriof_buyer_destination' => self::destination(), '_kiriof_checkout_destination_present' => '1', '_kiriof_checkout_destination_area' => '222', '_kiriof_checkout_destination_area_name' => 'New district', '_kiriof_checkout_expedition' => 'jnt_EZ' );
        $session = array( 'kiriof_buyer_destination' => self::destination(), 'kiriof_expedition' => 'jnt_EZ', 'kiriof_destination_area' => '222' );
        foreach ( array( array( 'transaction_status' => 400 ), array( 'transaction_throw' => true ) ) as $extra ) {
            $result = $this->runFixture( $extra + array( 'operation' => 'processed', 'customer' => true, 'meta' => $meta, 'session' => $session ) );
            $this->assertSame( $meta, $result['meta'] );
            $this->assertSame( $session, $result['session'] );
            $this->assertSame( array(), $result['customer_saves'] );
        }
        $result = $this->runFixture( array( 'operation' => 'processed', 'customer' => true, 'meta' => $meta, 'session' => $session ) );
        $this->assertSame( array( array( 'shipping', '222', 'New district' ) ), $result['customer_saves'] );
        $this->assertNull( $result['session']['kiriof_buyer_destination'] );
        $this->assertArrayNotHasKey( '_kiriof_checkout_expedition', $result['meta'] );
        $this->assertArrayNotHasKey( '_kiriof_checkout_destination_present', $result['meta'] );
        $this->assertSame( self::destination(), $result['meta']['_kiriof_buyer_destination'] );
    }

    #[Test]
    public function cart_hash_invalidates_fees_after_quantity_change(): void {
        $result = $this->runFixture( array( 'operation' => 'fees', 'global_insurance' => true, 'hashes' => array( 'quantity-one', 'quantity-one', 'quantity-two' ), 'session' => array( 'destination_id' => 222, 'chosen_payment_method' => 'cod', 'chosen_shipping_methods' => array( 'kiriminaja-official_jnt_EZ' ) ) ) );
        $this->assertArrayNotHasKey( 'error', $result );
        $this->assertCount( 2, $result['calculations'] );
        $this->assertTrue( $result['calculations'][0]['is_insurance'] );
        $this->assertSame( 'quantity-one', $result['contexts'][0]['cart_hash'] );
        $this->assertSame( 'quantity-two', $result['contexts'][2]['cart_hash'] );
    }

    private function order( array $params, array $extra = array() ): array {
        return $this->runFixture( $extra + array( 'operation' => 'order', 'params' => $params, 'session' => array( 'chosen_shipping_methods' => array( 'kiriminaja-official_jnt_EZ' ), 'kiriof_destination_area' => '111', 'kiriof_destination_area_name' => 'Old district' ) ) );
    }

    private function runFixture( array $input ): array {
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/buyer-destination-hardening-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $exitCode );
        $text = implode( "\n", $output );
        $this->assertSame( 0, $exitCode, $text );
        return json_decode( $text, true, 512, JSON_THROW_ON_ERROR );
    }
}
