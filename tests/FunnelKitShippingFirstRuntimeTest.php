<?php
use PHPUnit\Framework\TestCase;

final class FunnelKitShippingFirstRuntimeTest extends TestCase {
	private function runFixture( array $input = array() ): array {
		$input['funnelkit'] = true;
		exec( kiriof_test_fixture_php_shell( array( __DIR__ . '/fixtures/classic-instant-restored-pin-runtime.php', json_encode( $input, JSON_THROW_ON_ERROR ) ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
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
}
