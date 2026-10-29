<?php
use PHPUnit\Framework\TestCase;

final class ClassicCheckoutRecipientSyncRuntimeTest extends TestCase {
	private function runFixture( array $input ): array {
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/classic-checkout-recipient-sync-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_funnelkit_current_contact_clears_do_not_reuse_prior_review_or_billing_names(): void {
		$form = array( '_wfacp_post_id' => '123', 'wfacp_billing_same_as_shipping' => '0', 'shipping_country' => 'ID', 'shipping_first_name' => '', 'billing_first_name' => 'Laksana', 'billing_last_name' => 'Adi', 'billing_phone' => '81229917900', 'wfacp_input_phone_field' => '{"billing":{"code":"62","number":"81229917900","hidden":"no"},"shipping":{"code":"","number":"","hidden":""}}' );
		$empty = array_replace( $form, array( 'billing_phone' => '', 'wfacp_billing_same_as_shipping' => '1' ) );
		$result = $this->runFixture( array( 'forms' => array( $form, $empty ) ) );
		$this->assertSame( 'Laksana', $result['steps'][0]['recipient']['first_name'] );
		$this->assertSame( '6281229917900', $result['steps'][0]['recipient']['phone'] );
		$this->assertSame( '', $result['steps'][1]['recipient']['first_name'] );
		$this->assertSame( '', $result['steps'][1]['recipient']['phone'] );
		$this->assertSame( '', $result['steps'][1]['customer']['shipping_phone'] );
	}

	private function billing(): array {
		return array( 'billing_first_name' => 'Fresh', 'billing_last_name' => "O'Neil", 'billing_phone' => '081234567890' );
	}

	public function test_fresh_guest_contact_reaches_recipient_and_real_shipping_method(): void {
		$result = $this->runFixture( array( 'forms' => array( $this->billing() ) ) );
		$this->assertSame( 'Fresh', $result['steps'][0]['recipient']['first_name'] );
		$this->assertSame( "O'Neil", $result['calls'][0]['last_name'] );
		$this->assertSame( '081234567890', $result['calls'][0]['phone'] );
		$this->assertSame( array( 'kiriminaja-instant:11:gosend:instant' ), $result['rates'] );
		$this->assertContains( 'woocommerce_checkout_update_order_review', $result['hooks'] );
		$this->assertSame( 10, $result['registration'] );
		$this->assertSame( 0, $result['saves'] );
	}

	public function test_unchecked_shipping_overrides_stale_contact_without_changing_other_context(): void {
		$initial = array( 'shipping_first_name' => 'Stale', 'shipping_last_name' => 'Recipient', 'shipping_phone' => '089999999999', 'shipping_country' => 'US', 'shipping_address_1' => 'Saved address', 'billing_email' => 'saved@example.test' );
		$form = $this->billing() + array( 'shipping_first_name' => 'Ignored', 'shipping_last_name' => 'Ignored', 'shipping_phone' => '088888888888', 'shipping_country' => 'ID', 'billing_email' => 'new@example.test', 'payment_method' => 'cod', 'ship_to_different_address' => '0' );
		$result = $this->runFixture( array( 'initial' => $initial, 'forms' => array( $form ) ) );
		$customer = $result['steps'][0]['customer'];
		$this->assertSame( 'Fresh', $customer['shipping_first_name'] );
		$this->assertSame( '081234567890', $customer['shipping_phone'] );
		foreach ( array( 'shipping_country', 'shipping_address_1', 'billing_email' ) as $key ) { $this->assertSame( $initial[ $key ], $customer[ $key ] ); }
		$this->assertSame( array( 'flat_rate:1' ), $result['session']['chosen_shipping_methods'] );
		$this->assertSame( 'bacs', $result['session']['chosen_payment_method'] );
		$this->assertSame( 'unchanged', $result['session']['pin_context'] );
		$this->assertSame( 'keep', $result['session']['shipping_for_package_fake'] );
	}

	public function test_separate_contact_and_missing_phone_fallback(): void {
		$form = $this->billing() + array( 'ship_to_different_address' => '1', 'shipping_first_name' => 'Separate', 'shipping_last_name' => 'Name' );
		$result = $this->runFixture( array( 'initial' => array( 'shipping_phone' => '089999999999' ), 'forms' => array( $form, $form + array( 'shipping_phone' => '087777777777' ) ) ) );
		$this->assertSame( 'Separate', $result['steps'][0]['recipient']['first_name'] );
		$this->assertSame( '081234567890', $result['steps'][0]['customer']['shipping_phone'] );
		$this->assertSame( '087777777777', $result['calls'][0]['phone'] );
	}

	public function test_present_empty_and_array_values_clear_stale_contact_without_salvaging(): void {
		foreach ( array( '', array( 'Valid looking value' ) ) as $value ) {
			$form = array( 'ship_to_different_address' => '1' );
			$initial = array();
			foreach ( array( 'billing', 'shipping' ) as $type ) {
				foreach ( array( 'first_name', 'last_name', 'phone' ) as $field ) { $form[ $type . '_' . $field ] = $value; $initial[ $type . '_' . $field ] = 'Stale'; }
			}
			$result = $this->runFixture( array( 'initial' => $initial, 'forms' => array( $form ) ) );
			foreach ( $initial as $key => $_ ) { $this->assertSame( '', $result['steps'][0]['customer'][ $key ] ); }
			$this->assertSame( array(), $result['rates'] );
		}
		$form = $this->billing() + array( 'ship_to_different_address' => '1', 'shipping_phone' => '' );
		$result = $this->runFixture( array( 'initial' => array( 'shipping_phone' => 'Stale' ), 'forms' => array( $form ) ) );
		$this->assertSame( '', $result['steps'][0]['customer']['shipping_phone'] );
	}

	public function test_contact_corrections_invalidate_once_and_repeated_refresh_is_idempotent(): void {
		$form = $this->billing();
		$corrected = array_replace( $form, array( 'billing_first_name' => '<b>Corrected</b>', 'billing_phone' => '087777777777' ) );
		$result = $this->runFixture( array( 'forms' => array( $form, $form, $corrected, $corrected ) ) );
		foreach ( array( 0, 2 ) as $index ) { $this->assertSame( array( 'shipping_for_package_0', 'shipping_for_package_4' ), $result['steps'][ $index ]['invalidations'] ); }
		foreach ( array( 1, 3 ) as $index ) { $this->assertSame( array(), $result['steps'][ $index ]['invalidations'] ); $this->assertSame( array(), $result['steps'][ $index ]['writes'] ); }
		$this->assertSame( 'Corrected', $result['calls'][0]['first_name'] );
		$this->assertSame( 0, $result['saves'] );
	}

	public function test_outside_classic_lifecycle_virtual_cart_and_missing_form_do_not_mutate(): void {
		foreach ( array( array( 'rest' => true ), array( 'hook' => 'woocommerce_store_api_checkout_update_order_from_request' ), array( 'physical' => false ), array( 'forms' => array( array( 'billing_email' => 'new@example.test' ) ) ), array( 'forms' => array( null ) ) ) as $scope ) {
			$result = $this->runFixture( $scope + array( 'initial' => array( 'billing_first_name' => 'Saved' ), 'forms' => array( $this->billing() ) ) );
			$this->assertSame( array(), $result['steps'][0]['writes'] );
			$this->assertSame( array(), $result['steps'][0]['invalidations'] );
		}
	}
}
