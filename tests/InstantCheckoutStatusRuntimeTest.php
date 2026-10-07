<?php
use PHPUnit\Framework\TestCase;

/** Production Cart status and Store API refresh, isolated from WP/network/autoload. */
final class InstantCheckoutStatusRuntimeTest extends TestCase {
    private function fixture(): array {
        $output = array();
        $status = 0;
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/instant-checkout-status-runtime.php' ) . ' 2>&1', $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
    }

    public function test_canonical_package_and_currency_guards_override_valid_cached_native_rates(): void {
        $result = $this->fixture();
        $this->assertTrue( $result['available']['eligible'] );
        $this->assertSame( array( 'eligible' => false, 'code' => 'packages_invalid', 'message' => 'Instant delivery is available only for a single shipping package. Please choose another shipping method.', 'expires_at' => 0 ), $result['multiple_packages'] );
        $this->assertSame( array( 'eligible' => false, 'code' => 'currency_unsupported', 'message' => 'Instant delivery supports IDR checkout currency only. Please choose another shipping method.', 'expires_at' => 0 ), $result['unsupported_currency'] );
    }

    public function test_disabled_or_removed_methods_suppress_cached_rates_and_previous_lookup_messages(): void {
        $result = $this->fixture();
        $empty = array( 'eligible' => false, 'code' => 'inactive', 'message' => '', 'expires_at' => 0 );
        foreach ( array( 'disabled_method', 'removed_method', 'disabled_method_diagnostic', 'removed_method_diagnostic', 'without_wc', 'without_session' ) as $scenario ) {
            $this->assertSame( $empty, $result[$scenario], $scenario );
        }
    }

    public function test_current_native_rates_use_earliest_valid_instant_expiry_without_private_metadata(): void {
        $result = $this->fixture();
        $this->assertSame( array( 'eligible' => true, 'code' => 'available', 'message' => '', 'expires_at' => $result['now'] + 300 ), $result['available'] );
        foreach ( array( 'inactive', 'matching', 'stale', 'changed_contents', 'unknown_code', 'expired', 'available', 'multiple_packages', 'unsupported_currency', 'disabled_method', 'removed_method', 'disabled_method_diagnostic', 'removed_method_diagnostic', 'without_wc', 'without_session' ) as $scenario ) {
            $this->assertSame( array( 'eligible', 'code', 'message', 'expires_at' ), array_keys( $result[$scenario] ), $scenario );
            $this->assertStringNotContainsString( 'PRIVATE', json_encode( $result[$scenario] ), $scenario );
        }
    }

    public function test_session_diagnostics_require_current_contents_and_recent_known_codes(): void {
        $result = $this->fixture();
        $empty = array( 'eligible' => false, 'code' => 'inactive', 'message' => '', 'expires_at' => 0 );
        foreach ( array( 'inactive', 'stale', 'changed_contents', 'unknown_code' ) as $scenario ) {
            $this->assertSame( $empty, $result[$scenario], $scenario );
        }
        $this->assertSame( array( 'eligible' => false, 'code' => 'quote_failed', 'message' => 'Instant delivery rates are temporarily unavailable. Please try again.', 'expires_at' => 0 ), $result['matching'] );
        $this->assertSame( array( 'eligible' => false, 'code' => 'available', 'message' => 'Instant prices have expired. Refresh your shipping quote before placing the order.', 'expires_at' => 0 ), $result['expired'] );
    }

    public function test_real_controller_registers_readonly_cart_status_allowlist(): void {
        $result = $this->fixture();
        $schema = $result['schema'];
        $this->assertSame( 'cart', $schema['endpoint'] );
        $this->assertSame( 'kiriminaja-official-instant-checkout', $schema['namespace'] );
        $this->assertSame( 'ARRAY_A', $schema['schema_type'] );
        $this->assertSame( array(
            'eligible' => array( 'type' => 'boolean', 'readonly' => true ),
            'code' => array( 'type' => 'string', 'readonly' => true ),
            'message' => array( 'type' => 'string', 'readonly' => true ),
            'expires_at' => array( 'type' => 'integer', 'readonly' => true ),
        ), $schema['fields'] );
        $this->assertSame( $result['available'], $schema['data'] );
        $this->assertStringNotContainsString( 'PRIVATE', json_encode( $schema ) );
    }

    public function test_refresh_invalidates_fresh_request_cart_package_before_one_shipping_recalculation(): void {
        $result = $this->fixture();
        $this->assertSame( array(), $result['fresh_before']['initialized_packages'] );
        $this->assertSame( 'old-token', $result['fresh_before']['rate']['token'] );
        $session = $result['fresh_invalidated'];
        $this->assertFalse( $session['shipping_for_package_7'] );
        $this->assertSame( array(), $session['kiriof_instant_checkout_quotes'] );
        $this->assertSame( array(), $session['kiriof_instant_checkout_status'] );
        $this->assertSame( array( 'preserved-rate' ), $session['shipping_for_package_0'] );
        $this->assertSame( 'unrelated', $session['shipping_for_package_99'] );
        $this->assertSame( 0, $result['calculations_during_refresh'] );
        $fresh = $result['fresh_recalculated'];
        $this->assertSame( 1, $fresh['calculations'] );
        $rate = array( 'token' => 'updated-token', 'fee' => 25000 );
        $this->assertSame( array( $rate ), $fresh['packages'][7]['rates'] );
        $this->assertSame( array( $rate ), $fresh['session']['shipping_for_package_7']['rates'] );
        $this->assertSame( $rate, $fresh['session']['kiriof_instant_checkout_quotes']['current'] );
        foreach ( array( 'chosen_shipping_methods', 'kiriof_chosen_shipping_methods' ) as $key ) {
            $this->assertSame( array( 'kiriminaja-instant:11:gosend:instant' ), $fresh['session'][$key] );
        }
    }

    public function test_registered_sync_callback_refreshes_package_caches_and_preserves_native_selection_and_recipient(): void {
        $result = $this->fixture();
        $this->assertSame( 'kiriminaja-official', $result['callback_namespace'] );
        $session = $result['refresh'];
        $this->assertSame( array(), $session['kiriof_instant_checkout_quotes'] );
        $this->assertSame( array(), $session['kiriof_instant_checkout_status'] );
        $this->assertFalse( $session['shipping_for_package_0'] );
        $this->assertFalse( $session['shipping_for_package_4'] );
        $this->assertSame( 'unrelated', $session['shipping_for_package_99'] );
        foreach ( array( 'chosen_shipping_methods', 'kiriof_chosen_shipping_methods' ) as $key ) {
            $this->assertSame( array( 'kiriminaja-instant:11:gosend:instant' ), $session[$key] );
        }
        $this->assertSame( array( '12345' ), $result['lookup_calls'] );
        $this->assertSame( '222', $session['kiriof_buyer_destination']['district_id'] );
        $this->assertSame( 'Canonical district', $session['kiriof_buyer_destination']['district_label'] );
        $this->assertSame( array( 'latitude' => '-6.2', 'longitude' => '106.8' ), $session['kiriof_buyer_destination_coordinates'] );
        $this->assertSame( array( 'Native recipient', 'Unchanged', 'native-phone' ), $result['native_recipient'] );
        $this->assertSame( 0, $session['kiriof_cached_insurance_amt'] );
        $this->assertSame( 0, $session['kiriof_cached_cod_amt'] );
        $this->assertNull( $session['kiriof_cached_fee_context'] );
        $this->assertSame( array( 'preserved-quote' ), $result['without_refresh']['kiriof_instant_checkout_quotes'] );
        $this->assertSame( array( 'preserved-status' ), $result['without_refresh']['kiriof_instant_checkout_status'] );
        $this->assertSame( array( 'preserved-rate' ), $result['without_refresh']['shipping_for_package_0'] );
        $this->assertSame( $session['kiriof_buyer_destination'], $result['without_refresh']['kiriof_buyer_destination'] );
    }
}
