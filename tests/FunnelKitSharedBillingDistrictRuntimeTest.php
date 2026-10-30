<?php
use PHPUnit\Framework\TestCase;

final class FunnelKitSharedBillingDistrictRuntimeTest extends TestCase {
	private const LABEL = "Sari Harjo, Ngaglik, O'City, DI Yogyakarta, 55581";

	private function runFixture( array $input = array() ): array {
		exec( kiriof_test_fixture_php_shell( array( __DIR__ . '/fixtures/funnelkit-shared-billing-district-runtime.php', json_encode( $input, JSON_THROW_ON_ERROR ) ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_pre_collection_and_final_required_validation_accept_single_address_shipping_district(): void {
		$result = $this->runFixture( array(
			'override' => array(
				'wfacp_billing_same_as_shipping' => null,
				'billing_country' => 'ID',
				'billing_address_1' => 'Jalan Example 123',
				'billing_postcode' => '', 'billing_city' => '', 'billing_state' => '',
				'shipping_method' => array( 'kiriminaja-instant:7:gosend:instant' ),
			),
			'omit' => array( 'wfacp_billing_same_as_shipping', 'kiriof_destination_area', 'kiriof_destination_area_name' ),
		) );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( '31554', $result['pre_collection']['kiriof_destination_area'] );
		$this->assertSame( self::LABEL, $result['data']['kiriof_destination_area_name'] );
	}

	public function test_single_address_payload_without_flags_uses_verified_shipping_district_for_billing(): void {
		foreach ( array( 'kiriminaja-instant:7:gosend:instant', 'kiriminaja-official_jnt_EZ' ) as $method ) {
			$result = $this->runFixture( array(
				'omit' => array( 'wfacp_billing_same_as_shipping', 'kiriof_destination_area', 'kiriof_destination_area_name' ),
				'override' => array(
					'billing_country' => 'ID',
					'billing_address_1' => 'Jalan Example 123',
					'billing_city' => '', 'billing_state' => '', 'billing_postcode' => '',
					'shipping_method' => array( $method ),
				),
				'builder_clears' => true,
			) );
			$this->assertSame( array(), $result['errors'] );
			$this->assertSame( '31554', $result['sanitized_billing'] );
			$this->assertSame( '31554', $result['data']['kiriof_destination_area'] );
			$this->assertSame( self::LABEL, $result['data']['kiriof_destination_area_name'] );
			$this->assertSame( array( '55581' ), $result['calls'] );
		}
	}

	public function test_no_flag_different_or_conflicting_billing_address_is_not_repaired(): void {
		foreach ( array(
			array( 'billing_address_1' => 'Different billing street' ),
			array( 'billing_city' => 'Different city' ),
			array( 'billing_postcode' => '11111' ),
			array( 'billing_country' => 'US' ),
			array( 'billing_address_2' => 'Different unit' ),
			array( 'billing_state' => array( 'YO' ) ),
		) as $override ) {
			$result = $this->runFixture( array(
				'omit' => array( 'wfacp_billing_same_as_shipping' ),
				'override' => array_replace( array( 'billing_country' => 'ID', 'billing_address_1' => 'Jalan Example 123' ), $override ),
			) );
			$this->assertArrayHasKey( 'kiriof_destination_area_required', $result['errors'] );
			$this->assertSame( '', $result['sanitized_billing'] );
			$this->assertSame( array(), $result['calls'] );
		}
	}

	public function test_shared_missing_empty_and_stale_billing_are_bound_before_collection_and_required_validation(): void {
		foreach ( array(
			array(),
			array( 'omit' => array( 'kiriof_destination_area', 'kiriof_destination_area_name' ) ),
			array( 'override' => array( 'kiriof_destination_area' => '999', 'kiriof_destination_area_name' => 'Old, District, City, Province, 11111' ) ),
			array( 'override' => array( 'wfacp_billing_same_as_shipping' => 0, 'billing_same_as_shipping' => '1' ) ),
			array( 'override' => array( 'shipping_postcode' => '', 'kiriof_shipping_derived_address' => 'postcode' ) ),
			array( 'builder_clears' => true ),
		) as $input ) {
			$result = $this->runFixture( $input );
			$this->assertSame( array(), $result['errors'], json_encode( $input ) );
			$this->assertSame( '31554', $result['sanitized_billing'] );
			foreach ( array( 'kiriof_destination_area', 'kiriof_shipping_destination_area' ) as $key ) {
				$this->assertSame( '31554', $result['pre_collection'][ $key ] );
				$this->assertSame( self::LABEL, $result['pre_collection'][ $key . '_name' ] );
				$this->assertSame( '31554', $result['data'][ $key ] );
				$this->assertSame( self::LABEL, $result['data'][ $key . '_name' ] );
				$this->assertSame( '31554', $result['order_accessor'][ $key ] );
				$this->assertSame( self::LABEL, $result['order_accessor'][ $key . '_name' ] );
			}
			$this->assertSame( '', $result['pre_collection']['billing_country'] );
			$this->assertSame( '', $result['pre_collection']['billing_postcode'] );
			$this->assertArrayNotHasKey( 'billing_kiriof_destination_area', $result['data'] );
			$this->assertSame( '31554', $result['destination']['district_id'] );
			$this->assertSame( self::LABEL, $result['destination']['district_label'] );
			$this->assertSame( '55581', $result['destination']['postcode'] );
			$this->assertSame( array( '55581' ), $result['calls'] );
			$this->assertSame( $result['session_before'], $result['session_after'] );
		}
	}

	public function test_different_billing_keeps_woocommerce_billing_required_validation(): void {
		$result = $this->runFixture( array( 'override' => array( 'wfacp_billing_same_as_shipping' => '1' ) ) );
		$this->assertSame( '', $result['sanitized_billing'] );
		$this->assertSame( array( '<strong>Billing Subdistrict</strong> is a required field.' ), $result['errors']['kiriof_destination_area_required'] );
		$this->assertSame( array(), $result['calls'] );
		$result = $this->runFixture( array( 'override' => array( 'wfacp_billing_same_as_shipping' => '1', 'kiriof_destination_area' => '999', 'kiriof_destination_area_name' => 'Separate district' ) ) );
		$this->assertSame( '999', $result['data']['kiriof_destination_area'] );
		$this->assertSame( 'Separate district', $result['order_accessor']['kiriof_destination_area_name'] );
	}

	public function test_unrelated_foreign_virtual_pickup_and_malformed_requests_do_not_hydrate(): void {
		foreach ( array(
			array( 'omit' => array( '_wfacp_post_id' ) ),
			array( 'omit' => array( 'wfacp_billing_same_as_shipping' ) ),
			array( 'omit' => array( 'shipping_country' ) ),
			array( 'skip_process' => true ),
			array( 'virtual' => true ),
			array( 'override' => array( '_wfacp_post_id' => array( '123' ) ) ),
			array( 'override' => array( '_wfacp_post_id' => '123junk' ) ),
			array( 'override' => array( 'wfacp_billing_same_as_shipping' => array( '0' ) ) ),
			array( 'override' => array( 'wfacp_billing_same_as_shipping' => false ) ),
			array( 'override' => array( 'wfacp_billing_same_as_shipping' => 'on' ) ),
			array( 'override' => array( 'shipping_country' => 'US' ) ),
			array( 'override' => array( 'shipping_country' => array( 'ID' ) ) ),
			array( 'override' => array( 'shipping_postcode' => '11111' ) ),
			array( 'override' => array( 'shipping_postcode' => array( '55581' ) ) ),
			array( 'override' => array( 'kiriof_shipping_destination_area' => array( '31554' ) ) ),
			array( 'override' => array( 'kiriof_shipping_destination_area_name' => array( self::LABEL ) ) ),
			array( 'override' => array( 'kiriof_shipping_destination_area_name' => 'Bad label' ) ),
			array( 'override' => array( 'kiriof_destination_area' => array( '31554' ) ) ),
			array( 'override' => array( 'kiriof_destination_area_name' => array( self::LABEL ) ) ),
			array( 'override' => array( 'shipping_method' => array( 'local_pickup:1' ) ) ),
			array( 'override' => array( 'shipping_method' => array( 'pickup_location:1' ) ) ),
			array( 'override' => array( 'shipping_method' => array( array( 'local_pickup:1' ) ) ) ),
		) as $input ) {
			$result = $this->runFixture( $input );
			$this->assertSame( $input['override']['kiriof_destination_area'] ?? '', $result['pre_collection']['kiriof_destination_area'], json_encode( $input ) );
			$this->assertSame( array(), $result['calls'], json_encode( $input ) );
			$this->assertSame( $result['session_before'], $result['session_after'] );
		}
	}

	public function test_unverified_wrong_or_ambiguous_lookup_never_satisfies_billing_required(): void {
		$row = array( 'id' => 31554, 'text' => self::LABEL, 'zip_code' => '55581' );
		foreach ( array(
			array( 'rows' => array() ),
			array( 'rows' => array( $row, $row ) ),
			array( 'rows' => array( array_replace( $row, array( 'id' => 999 ) ) ) ),
			array( 'rows' => array( array_replace( $row, array( 'text' => 'Village, District, City, Province, 11111' ) ) ) ),
			array( 'rows' => array( array_replace( $row, array( 'zip_code' => '11111' ) ) ) ),
			array( 'status' => 500 ), array( 'throws' => true ),
		) as $input ) {
			$result = $this->runFixture( $input );
			$this->assertSame( '', $result['sanitized_billing'] );
			$this->assertArrayHasKey( 'kiriof_destination_area_required', $result['errors'] );
			$this->assertSame( array( '55581' ), $result['calls'] );
			$this->assertSame( '', $result['order_accessor']['kiriof_destination_area'] );
		}
	}
}
