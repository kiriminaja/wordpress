<?php
use PHPUnit\Framework\TestCase;

final class ClassicCheckoutInsuranceSyncRuntimeTest extends TestCase {
	private function run_case( array $input = array() ): array {
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/classic-insurance-sync-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_native_review_adds_real_quote_insurance_despite_free_shipping_and_99_percent_coupon(): void {
		$result = $this->run_case();
		$step = $result['steps'][0];
		$this->assertSame( array( 'Insurance' => 1250 ), $step['fees'] );
		$this->assertSame( 2250, $step['total'] );
		$this->assertSame( 1, $step['session']['kiriof_insurance'] );
		$this->assertSame( 1, $step['session']['billing_insurance'] );
		$this->assertSame( 1, $result['calls'] );
		$this->assertSame( 1, $result['pricing_payload']['insurance'] );
		$this->assertSame( 0, $step['sync_calls'] );
		$this->assertFalse( $step['after_sync']['shipping_for_package_0'] );
		$this->assertSame( 0, $step['after_sync']['kiriof_cached_insurance_amt'] );
		$this->assertSame( 0, $step['after_sync']['kiriof_cached_cod_amt'] );
		$this->assertNull( $step['after_sync']['kiriof_cached_fee_context'] );
	}

	public function test_unchecked_clears_the_native_fee_and_stale_amounts(): void {
		$result = $this->run_case( array( 'steps' => array( array( 'post_data' => 'kiriof_insurance=0&kiriof_insurance=1' ), array( 'post_data' => 'kiriof_insurance=0' ) ) ) );
		$this->assertSame( array( 'Insurance' => 1250 ), $result['steps'][0]['fees'] );
		$this->assertSame( array(), $result['steps'][1]['fees'] );
		$this->assertSame( 1000, $result['steps'][1]['total'] );
		$this->assertSame( 0, $result['steps'][1]['session']['kiriof_insurance'] );
		$this->assertSame( 0, $result['steps'][1]['session']['kiriof_cached_insurance_amt'] );
	}

	public function test_server_global_and_carrier_force_cannot_be_overridden_by_posted_flags(): void {
		foreach ( array( array( 'global' => 'yes' ), array( 'carrier_force' => true ) ) as $input ) {
			$result = $this->run_case( $input + array( 'steps' => array( array( 'post_data' => 'kiriof_insurance=0&kiriof_force_insurance=0' ) ) ) );
			$this->assertSame( array( 'Insurance' => 1250 ), $result['steps'][0]['fees'] );
		}
		$result = $this->run_case( array( 'steps' => array( array( 'post_data' => 'kiriof_insurance=0&kiriof_force_insurance=1' ) ) ) );
		$this->assertSame( array(), $result['steps'][0]['fees'] );
	}

	public function test_partial_foreign_and_malformed_payloads_preserve_consent_and_do_not_mutate_other_session_fields(): void {
		foreach ( array( '', 'payment_method=cod', 'insurance=0&billing_insurance=0', 'kiriof_insurance[]=0', 'kiriof_insurance[x]=1', 'kiriof_insurance=yes', array( 'kiriof_insurance' => '0' ) ) as $payload ) {
			$result = $this->run_case( array( 'session' => array( 'kiriof_insurance' => 1, 'billing_insurance' => 1 ), 'steps' => array( array( 'post_data' => $payload ) ) ) );
			$this->assertSame( 1, $result['steps'][0]['session']['kiriof_insurance'] );
			$this->assertSame( array( 'kiriminaja-official:jnt_EZ' ), $result['steps'][0]['after_sync']['chosen_shipping_methods'] );
			$this->assertSame( 'bacs', $result['steps'][0]['after_sync']['chosen_payment_method'] );
		}
	}

	public function test_instant_never_adds_insurance_and_express_return_restores_preference(): void {
		foreach ( array( 'yes', 'no' ) as $global ) {
			$result = $this->run_case( array( 'global' => $global, 'steps' => array( array( 'post_data' => 'kiriof_insurance=1' ), array( 'post_data' => '', 'method' => 'kiriminaja-instant:1:gosend:instant' ), array( 'post_data' => '', 'method' => 'kiriminaja-official:jnt_EZ' ) ) ) );
			$this->assertSame( array( 'Insurance' => 1250 ), $result['steps'][0]['fees'] );
			$this->assertSame( array(), $result['steps'][1]['fees'] );
			$this->assertSame( 0, $result['steps'][1]['session']['kiriof_cached_insurance_amt'] );
			$this->assertSame( 1, $result['steps'][1]['session']['kiriof_insurance'] );
			$this->assertSame( array( 'Insurance' => 1250 ), $result['steps'][2]['fees'] );
		}
	}

	public function test_disabled_express_pickup_and_virtual_cannot_reuse_insurance(): void {
		foreach ( array( array( 'disabled' => true ), array( 'virtual' => true ), array( 'steps' => array( array( 'post_data' => 'kiriof_insurance=1', 'method' => 'local_pickup:1' ) ) ) ) as $input ) {
			$result = $this->run_case( $input );
			$this->assertSame( array(), $result['steps'][0]['fees'] );
			$this->assertEquals( 0, $result['steps'][0]['session']['kiriof_cached_insurance_amt'] );
		}
	}

	public function test_only_verified_native_hooks_sync_and_final_checkout_uses_the_last_selection(): void {
		foreach ( array( array( 'rest' => true ), array( 'steps' => array( array( 'post_data' => 'kiriof_insurance=1', 'hook' => 'unrelated' ) ) ) ) as $input ) {
			$result = $this->run_case( $input );
			$this->assertSame( 0, $result['steps'][0]['session']['kiriof_insurance'] );
		}
		$result = $this->run_case( array( 'steps' => array( array( 'post_data' => 'kiriof_insurance=1' ), array( 'post_data' => 'kiriof_insurance=0', 'final' => true ) ) ) );
		$this->assertSame( 0, $result['steps'][1]['after_sync']['billing_insurance'] );
		$this->assertSame( array(), $result['steps'][1]['fees'] );
	}

	public function test_unchanged_selection_does_not_invalidate_rates_or_reprice(): void {
		$result = $this->run_case( array( 'session' => array( 'kiriof_insurance' => 1, 'billing_insurance' => 1 ), 'steps' => array( array( 'post_data' => 'kiriof_insurance=1' ), array( 'post_data' => 'kiriof_insurance=1&shipping_method[0]=local_pickup:1&payment_method=cod' ) ) ) );
		$this->assertSame( array( 'stale' ), $result['steps'][1]['after_sync']['shipping_for_package_0'] );
		$this->assertSame( 1, $result['calls'] );
		$this->assertSame( array( 'Insurance' => 1250 ), $result['steps'][1]['fees'] );
		$this->assertSame( 'bacs', $result['steps'][1]['after_sync']['chosen_payment_method'] );
		$this->assertSame( array( 'kiriminaja-official:jnt_EZ' ), $result['steps'][1]['after_sync']['chosen_shipping_methods'] );
	}

	public function test_classic_hooks_are_registered_before_native_totals_and_order_creation(): void {
		$source = file_get_contents( PLUGIN_DIR . '/inc/Controllers/CheckoutController.php' );
		$this->assertStringContainsString( "add_action( 'woocommerce_checkout_update_order_review', array( \$this, 'kiriof_sync_classic_review_insurance' ), 20, 1 );", $source );
		$this->assertStringContainsString( "add_action( 'woocommerce_checkout_process', array( \$this, 'kiriof_sync_classic_checkout_insurance' ), 5 );", $source );
	}

	public function test_failed_repricing_cannot_leave_previous_fee_amounts_cached(): void {
		$result = $this->run_case( array( 'pricing_failure' => true ) );
		$this->assertSame( array(), $result['steps'][0]['fees'] );
		$this->assertSame( 1000, $result['steps'][0]['total'] );
		$this->assertSame( 0, $result['steps'][0]['session']['kiriof_cached_insurance_amt'] );
		$this->assertNull( $result['steps'][0]['session']['kiriof_cached_fee_context'] );
	}
}
