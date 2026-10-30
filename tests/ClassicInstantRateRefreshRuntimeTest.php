<?php
use PHPUnit\Framework\TestCase;

final class ClassicInstantRateRefreshRuntimeTest extends TestCase {
	public function test_unchanged_saved_pin_recovers_a_native_instant_rate_without_map_movement(): void {
		foreach ( array( true, false ) as $without_recovery ) {
			$output = array();
			exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/classic-instant-restored-pin-runtime.php' ) . ' ' . escapeshellarg( json_encode( compact( 'without_recovery' ), JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
			$this->assertSame( 0, $status, implode( "\n", $output ) );
			$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
			$expected = $without_recovery ? array( 'kiriminaja-official_jnt_EZ' ) : array( 'kiriminaja-official_jnt_EZ', 'kiriminaja-instant:11:gosend:GO-INSTANT' );
			foreach ( $result['rounds'] as $round ) {
				$this->assertSame( $expected, $round['rates'] );
				$this->assertSame( $without_recovery ? 0 : 1, $round['calls'] );
			}
			$this->assertSame( $result['original_pin'], $result['pin'] );
			$this->assertSame( array( 'kiriminaja-official_jnt_EZ' ), $result['chosen'] );
			if ( ! $without_recovery ) {
				$rendered = array();
				$rates = array_map( static function ( string $id ): array {
					return array( 'id' => $id, 'label' => str_starts_with( $id, 'kiriminaja-instant:' ) ? 'GoSend Instant' : 'J&T EZ', 'cost' => 18000 );
				}, $result['rounds'][0]['rates'] );
				exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/classic-shipping-presentation-runtime.php' ) . ' ' . escapeshellarg( json_encode( array( 'rates' => $rates, 'chosen' => $result['chosen'][0] ), JSON_THROW_ON_ERROR ) ) . ' 2>&1', $rendered, $render_status );
				$this->assertSame( 0, $render_status, implode( "\n", $rendered ) );
				$html = json_decode( implode( "\n", $rendered ), true, 512, JSON_THROW_ON_ERROR )['html'];
				$this->assertStringContainsString( 'class="wc-enhanced-select kiriof-classic-shipping-method-select"', $html );
				$this->assertMatchesRegularExpression( '/<option[^>]+value="kiriminaja-instant:11:gosend:GO-INSTANT"/', $html );
				$this->assertMatchesRegularExpression( '/<option[^>]+value="kiriminaja-official_jnt_EZ"[^>]+selected="selected"/', $html );
			}
		}
	}

	private function runFixture( array $input = array() ): array {
		exec( kiriof_test_fixture_php_shell( array( __DIR__ . '/fixtures/classic-instant-rate-refresh-runtime.php', json_encode( $input, JSON_THROW_ON_ERROR ) ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_saved_pin_recovers_express_only_cache_without_moving_coordinates_or_chosen_courier(): void {
		$result = $this->runFixture( array( 'repeat' => 2 ) );
		$this->assertTrue( $result['steps'][0]['invalidated'] );
		$this->assertFalse( $result['steps'][1]['invalidated'], 'Do not retry the same failed context on every Woo refresh.' );
		foreach ( array( 'kiriof_buyer_destination', 'kiriof_buyer_destination_coordinates', 'chosen_shipping_methods', 'shipping_for_package_fake' ) as $key ) {
			$this->assertSame( $result['before'][ $key ], $result['after'][ $key ], $key );
		}
	}

	public function test_unexpired_instant_rate_is_reused_only_with_its_quote_receipt(): void {
		$result = $this->runFixture( array( 'instant' => true ) );
		$this->assertFalse( $result['steps'][0]['invalidated'] );
		foreach ( array( array( 'instant' => true, 'expired' => true ), array( 'instant' => true, 'quotes_missing' => true ) ) as $input ) {
			$this->assertTrue( $this->runFixture( $input )['steps'][0]['invalidated'] );
		}
	}

	public function test_changed_or_cleared_pin_address_cod_and_other_checkout_paths_do_not_recover_old_pin(): void {
		foreach ( array( 'mismatch', 'tamper', 'clear', 'cod', 'rest' ) as $flag ) {
			$this->assertFalse( $this->runFixture( array( $flag => true ) )['steps'][0]['invalidated'], $flag );
		}
		$this->assertFalse( $this->runFixture( array( 'physical' => false ) )['steps'][0]['invalidated'] );
		$this->assertTrue( $this->runFixture( array( 'separate' => true ) )['steps'][0]['invalidated'] );
		$this->assertFalse( $this->runFixture( array( 'no_cache' => true ) )['steps'][0]['invalidated'] );
		$this->assertFalse( $this->runFixture( array( 'hook' => 'woocommerce_store_api_checkout_update_order_from_request' ) )['steps'][0]['invalidated'] );
		$this->assertTrue( $this->runFixture( array( 'status' => 'no_rates', 'unrelated_status' => true ) )['steps'][0]['invalidated'] );
	}

	public function test_recent_provider_no_rates_is_not_retried_but_old_failure_can_recover(): void {
		foreach ( array( 'no_rates', 'outside_instant_radius', 'quote_failed' ) as $status ) {
			$result = $this->runFixture( array( 'status' => $status, 'repeat' => 2, 'advance' => 31 ) );
			$this->assertFalse( $result['steps'][0]['invalidated'], $status );
			$this->assertTrue( $result['steps'][1]['invalidated'], $status );
		}
	}

	public function test_hidden_address_repair_preserves_exact_pin_comparison(): void {
		foreach ( array( false, true ) as $separate ) {
			$this->assertTrue( $this->runFixture( array( 'hidden' => true, 'separate' => $separate ) )['steps'][0]['invalidated'] );
			$this->assertFalse( $this->runFixture( array( 'hidden' => true, 'separate' => $separate, 'canonical_mismatch' => true ) )['steps'][0]['invalidated'] );
			$this->assertFalse( $this->runFixture( array( 'hidden' => true, 'tamper' => true ) )['steps'][0]['invalidated'] );
		}
	}

	public function test_fixed_seven_decimal_draft_and_promoted_coordinates_share_the_recovery_comparison(): void {
		$result = $this->runFixture( array( 'draft' => true ) );
		$coordinates = array( 'latitude' => '-7.74', 'longitude' => '110.37' );
		$this->assertSame( $coordinates, $result['before']['kiriof_classic_pin_drafts']['billing'] );
		$this->assertSame( $coordinates, $result['before']['kiriof_buyer_destination_coordinates'] );
		$this->assertSame( '-7.74', $result['before']['kiriof_buyer_destination']['destination_latitude'] );
		$this->assertSame( '110.37', $result['before']['kiriof_buyer_destination']['destination_longitude'] );
		$this->assertTrue( $result['steps'][0]['invalidated'] );
	}
}
