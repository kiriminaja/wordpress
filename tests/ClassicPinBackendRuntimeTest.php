<?php
use PHPUnit\Framework\TestCase;

final class ClassicPinBackendRuntimeTest extends TestCase {
    private function runCase( string $mode ): array {
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/classic-pin-backend-runtime.php' ) . ' ' . escapeshellarg( $mode ) . ' 2>&1', $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
    }
    public function test_rejected_requests_do_not_write_any_session_state(): void {
        foreach ( array( 'nonce', 'disabled', 'mismatch', 'shipping-mismatch', 'tamper' ) as $mode ) {
            $this->assertSame( array(), $this->runCase( $mode )['writes'], $mode );
        }
    }
    public function test_pin_failures_return_specific_safe_diagnostics_and_write_nothing(): void {
        $cases = array(
            'disabled' => array( 'instant_disabled', 409 ),
            'bad-shape' => array( 'invalid_destination', 422 ),
            'bad-version' => array( 'invalid_destination', 422 ),
            'bad-effective-address' => array( 'invalid_destination', 422 ),
            'bad-scope' => array( 'invalid_scope', 422 ),
            'postcode-mismatch' => array( 'address_mismatch', 409 ),
            'country-mismatch' => array( 'address_mismatch', 409 ),
            'tamper' => array( 'snapshot_mismatch', 409 ),
            'bad-coordinates' => array( 'invalid_coordinates', 422 ),
            'missing-coordinates' => array( 'invalid_coordinates', 422 ),
            'bad-snapshot' => array( 'invalid_coordinates', 422 ),
            'snapshot-postcode' => array( 'invalid_coordinates', 422 ),
            'mismatch' => array( 'district_mismatch', 409 ),
            'shipping-mismatch' => array( 'district_mismatch', 409 ),
            'lookup-fails' => array( 'lookup_unavailable', 503 ),
            'lookup-status' => array( 'lookup_unavailable', 503 ),
            'cached-lookup-fails' => array( 'lookup_unavailable', 503 ),
            'malformed-label' => array( 'lookup_unavailable', 503 ),
            'not-mapped' => array( 'district_not_mapped', 422 ),
            'changed-after-lookup' => array( 'district_changed', 409 ),
            'no-session' => array( 'checkout_unavailable', 409 ),
        );
        foreach ( $cases as $mode => list( $reason, $status ) ) {
            $result = $this->runCase( $mode );
            $this->assertSame( array(), $result['writes'], $mode );
            $response = $result['response'];
            $this->assertFalse( $response['success'], $mode );
            $this->assertSame( $status, $response['status'], $mode );
            $this->assertSame( 'kiriof_pin_' . $reason, $response['data']['code'], $mode );
            $this->assertNotEmpty( $response['data']['message'], $mode );
            $this->assertSame( $response['data']['message'], $response['data']['msg'], $mode );
            foreach ( array( '55581', '12345', 'Main street', 'Other street', 'secret', 'credentials', '<private>' ) as $private ) {
                $this->assertStringNotContainsString( $private, $response['data']['message'], $mode );
            }
        }
        $this->assertSame( array( '55581' ), $this->runCase( 'not-mapped' )['lookups'] );
        $this->assertSame( array(), $this->runCase( 'cached-lookup-fails' )['lookups'] );
        $this->assertSame( array(), $this->runCase( 'nonce' )['lookups'] );
    }
    public function test_pin_bridge_only_writes_pin_and_quote_state(): void {
        $result = $this->runCase( 'pin' );
        $this->assertSame( array( 'success' => true, 'data' => array( 'pin_saved' => true ), 'status' => 200 ), $result['response'] );
        $this->assertSame( array( 'kiriof_buyer_destination', 'kiriof_buyer_destination_coordinates', 'kiriof_instant_checkout_quotes', 'kiriof_instant_checkout_status' ), $result['writes'] );
        $this->assertSame( 'Authoritative district', $result['session']['kiriof_buyer_destination']['district_label'] );
        $this->assertSame( '123', $result['session']['destination_id'] );
        $this->assertSame( 'cod', $result['session']['chosen_payment_method'] );
        $this->assertSame( 1, $result['session']['kiriof_insurance'] );
        $this->assertSame( array( 'legacy' ), $result['session']['chosen_shipping_methods'] );
    }
    public function test_explicit_clear_does_not_rewrite_legacy_selection(): void {
        $result = $this->runCase( 'clear' );
        $this->assertNull( $result['session']['kiriof_buyer_destination'] );
        $this->assertNull( $result['session']['kiriof_buyer_destination_coordinates'] );
        $this->assertSame( '123', $result['session']['destination_id'] );
        $this->assertCount( 4, $result['writes'] );
    }
    public function test_legacy_district_endpoint_preserves_same_and_clears_changed_pin(): void {
        $this->assertSame( 2, $this->runCase( 'district-same' )['session']['kiriof_buyer_destination']['version'] );
        $this->assertNull( $this->runCase( 'district-changed' )['session']['kiriof_buyer_destination'] );
    }
    public function test_legacy_fee_updates_preserve_only_matching_valid_pins(): void {
        $this->assertSame( 2, $this->runCase( 'legacy-same' )['session']['kiriof_buyer_destination']['version'] );
        $this->assertNull( $this->runCase( 'legacy-changed' )['session']['kiriof_buyer_destination'] );
    }
}
