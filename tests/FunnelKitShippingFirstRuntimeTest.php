<?php
use PHPUnit\Framework\TestCase;

final class FunnelKitShippingFirstRuntimeTest extends TestCase {
	private function runFixture( array $input = array() ): array {
		$input['funnelkit'] = true;
		exec( kiriof_test_fixture_php_shell( array( __DIR__ . '/fixtures/classic-instant-restored-pin-runtime.php', json_encode( $input, JSON_THROW_ON_ERROR ) ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_single_district_removed_fields_reach_real_rates_and_final_unregistered_shipping_fields(): void {
		$keys = array( 'shipping_city', 'shipping_state', 'shipping_postcode' );
		foreach ( array( 'absent', 'empty_marker', 'hidden_values' ) as $mode ) {
			foreach ( array( false, true ) as $cleared ) {
				$options = array( 'final' => true );
				if ( 'absent' === $mode ) {
					$options['omit'] = $keys;
				} elseif ( 'empty_marker' === $mode ) {
					$options['hidden'] = true;
				} else {
					$options['override'] = array( 'kiriof_shipping_derived_address' => 'city,state,postcode' );
				}
				$options[ $cleared ? 'final_override' : 'final_omit' ] = $cleared ? array_fill_keys( $keys, '' ) : $keys;
				$result = $this->runFixture( $options );
				$this->assertSame( array( 'kiriminaja-instant:11:gosend:GO-INSTANT' ), $result['rates'], json_encode( $result ) );
				$this->assertSame( 1, $result['calls'] );
				foreach ( array( 'shipping_city' => 'Jakarta', 'shipping_state' => 'JK', 'shipping_postcode' => '12345' ) as $key => $value ) {
					$this->assertSame( $value, $result['final'][ $key ], $mode );
				}
			}
		}
	}

	public function test_single_district_present_manual_clears_do_not_enable_hierarchy_or_quote(): void {
		$result = $this->runFixture( array( 'override' => array( 'shipping_city' => '', 'shipping_state' => '', 'shipping_postcode' => '' ), 'final' => true ) );
		$this->assertSame( array(), $result['rates'] );
		$this->assertSame( 0, $result['calls'] );
		$this->assertSame( '', $result['final']['shipping_city'] );
	}

	public function test_shipping_first_survives_native_review_setters_and_adds_real_rates(): void {
		foreach ( array( false, true ) as $hidden ) {
			foreach ( array( false, true ) as $different_billing ) {
				foreach ( array( '' => 'gosend:GO-INSTANT', 'grab' => 'grab_express:GRAB-BIKE' ) as $courier => $suffix ) {
					$result = $this->runFixture( compact( 'hidden', 'different_billing', 'courier' ) );
					$this->assertSame( array( 'kiriminaja-instant:11:' . $suffix ), $result['rates'], json_encode( $result ) );
					$this->assertSame( 1, $result['calls'] );
					$this->assertSame( $result['destination']['shipping_address'], $result['package'] );
					$this->assertSame( 'Shipping', $result['customer']['shipping_first_name'] );
					$this->assertSame( '081234567890', $result['customer']['shipping_phone'] );
					if ( $different_billing ) { $this->assertSame( 'Different billing street', $result['customer']['billing_address_1'] ); }
				}
			}
		}
	}

	public function test_foreign_malformed_and_explicit_empty_shipping_never_repair_from_billing(): void {
		foreach ( array( array( 'shipping_country' => 'US' ), array( 'shipping_country' => array( 'ID' ) ), array( 'shipping_address_1' => '' ), array( 'shipping_city' => array( 'Jakarta' ) ), array( 'shipping_phone' => '' ), array( '_wfacp_post_id' => array( '123' ) ), array( '_wfacp_post_id' => '123junk' ), array( 'wfacp_billing_same_as_shipping' => array( '0' ) ) ) as $override ) {
			$result = $this->runFixture( array( 'different_billing' => true, 'override' => $override ) );
			$this->assertSame( array(), $result['rates'], json_encode( $override ) );
			$this->assertSame( 0, $result['calls'] );
		}
	}

	public function test_funnelkit_native_hidden_checkbox_on_keeps_shipping_identity(): void {
		$result = $this->runFixture( array( 'native_checkbox' => true, 'different_billing' => true, 'hidden' => true ) );
		$this->assertSame( array( 'kiriminaja-instant:11:gosend:GO-INSTANT' ), $result['rates'] );
		$this->assertSame( 'Shipping', $result['customer']['shipping_first_name'] );
		$this->assertSame( 'Different billing street', $result['customer']['billing_address_1'] );
	}

	public function test_shipping_only_and_gist_visible_flag_use_shipping_without_native_checkbox(): void {
		foreach ( array( array(), array( 'billing_same_as_shipping' => '1' ) ) as $override ) {
			$result = $this->runFixture( array( 'omit' => array( 'wfacp_billing_same_as_shipping' ), 'override' => $override ) );
			$this->assertSame( array( 'kiriminaja-instant:11:gosend:GO-INSTANT' ), $result['rates'] );
		}
	}

	private function phoneMetadata( string $scope = 'billing' ): string {
		return json_encode( array( $scope => array( 'code' => '62', 'number' => '81229917900', 'hidden' => 'no' ) ), JSON_THROW_ON_ERROR );
	}

	public function test_official_national_widget_phone_and_shared_hidden_names_reach_real_quote_and_final_shipping(): void {
		foreach ( array( false, true ) as $omit_names ) {
			$override = array( 'shipping_first_name' => '', 'shipping_last_name' => '', 'billing_first_name' => 'Laksana', 'billing_last_name' => 'Adi', 'billing_phone' => '81229917900', 'wfacp_input_phone_field' => $this->phoneMetadata() );
			$result = $this->runFixture( array( 'override' => $override, 'omit' => $omit_names ? array( 'shipping_first_name', 'shipping_last_name' ) : array(), 'final' => true ) );
			$this->assertSame( array( 'kiriminaja-instant:11:gosend:GO-INSTANT' ), $result['rates'], json_encode( $result ) );
			$this->assertSame( 1, $result['calls'] );
			$this->assertSame( 'Laksana', $result['customer']['shipping_first_name'] );
			$this->assertSame( 'Adi', $result['customer']['shipping_last_name'] );
			$this->assertSame( '6281229917900', $result['customer']['shipping_phone'] );
			$this->assertSame( '81229917900', $result['customer']['billing_phone'] );
			$this->assertSame( '6281229917900', $result['final']['shipping_phone'] );
			$this->assertSame( 'Laksana', $result['final']['shipping_first_name'] );
			$this->assertSame( '81229917900', $result['final']['billing_phone'] );
		}
	}

	public function test_shipping_widget_uses_its_own_metadata_with_different_billing(): void {
		$result = $this->runFixture( array( 'different_billing' => true, 'final' => true, 'override' => array( 'shipping_phone' => '81229917900', 'wfacp_input_phone_field' => $this->phoneMetadata( 'shipping' ) ) ) );
		$this->assertSame( 1, $result['calls'], json_encode( $result ) );
		$this->assertSame( '6281229917900', $result['final']['shipping_phone'] );
		$this->assertSame( 'Different billing street', $result['customer']['billing_address_1'] );
	}

	public function test_national_phone_requires_matching_bounded_indonesian_widget_metadata(): void {
		$without_metadata = $this->runFixture( array( 'override' => array( 'billing_phone' => '81229917900' ) ) );
		$this->assertSame( 0, $without_metadata['calls'] );
		$valid = json_decode( $this->phoneMetadata(), true );
		$wrong_code = $valid;
		$wrong_code['billing']['code'] = '1';
		$mismatch = $valid;
		$mismatch['billing']['number'] = '81200000000';
		$invalid_number = $valid;
		$invalid_number['billing']['number'] = '';
		$array_number = $valid;
		$array_number['billing']['number'] = array( '81229917900' );
		$unknown_shape = $valid;
		$unknown_shape['billing']['full_number'] = '+181229917900';
		foreach ( array( '{', '{}', '[]', str_repeat( ' ', 4097 ), array( 'billing' => $valid['billing'] ), json_encode( $wrong_code ), json_encode( $mismatch ), json_encode( $invalid_number ), json_encode( $array_number ), json_encode( $unknown_shape ), $this->phoneMetadata( 'shipping' ) ) as $metadata ) {
			$result = $this->runFixture( array( 'override' => array( 'billing_phone' => '81229917900', 'wfacp_input_phone_field' => $metadata ), 'final' => true ) );
			$this->assertSame( 0, $result['calls'], json_encode( $metadata ) );
			$this->assertSame( array(), $result['rates'] );
			$this->assertSame( '', $result['final']['shipping_phone'] );
		}
		foreach ( array( array( 'billing_phone' => '' ), array( 'billing_phone' => array( '81229917900' ) ), array( 'billing_phone' => "+62 812\n29917900" ), array( 'shipping_country' => 'US' ), array( 'billing_country' => 'US' ), array( '_wfacp_post_id' => '123junk' ) ) as $invalid ) {
			$result = $this->runFixture( array( 'override' => array_replace( array( 'billing_phone' => '81229917900', 'wfacp_input_phone_field' => $this->phoneMetadata() ), $invalid ) ) );
			$this->assertSame( 0, $result['calls'], json_encode( $invalid ) );
		}
	}

	public function test_different_billing_empty_or_missing_shipping_names_do_not_inherit_billing(): void {
		foreach ( array( false, true ) as $omit ) {
			$result = $this->runFixture( array( 'different_billing' => true, 'override' => array( 'shipping_first_name' => '', 'shipping_last_name' => '' ), 'omit' => $omit ? array( 'shipping_first_name', 'shipping_last_name' ) : array(), 'final' => true ) );
			$this->assertSame( 0, $result['calls'], json_encode( $result ) );
			$this->assertSame( '', $result['final']['shipping_first_name'] );
		}
	}
}
