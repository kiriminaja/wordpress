<?php
use PHPUnit\Framework\TestCase;

final class ClassicPinBackendRuntimeTest extends TestCase {
    public function test_coordinate_draft_ajax_is_nonce_protected_and_does_not_require_an_address(): void {
        $this->assertSame( array(), $this->runCase( 'draft-nonce' )['writes'] );
        $result = $this->runCase( 'draft-save' );
        $this->assertSame( array( 'success' => true, 'data' => array( 'coordinates_saved' => true ), 'status' => 200 ), $result['response'] );
        $this->assertSame( array( 'billing' => array( 'latitude' => '-6.2', 'longitude' => '106.8' ) ), $result['session']['kiriof_classic_pin_drafts'] );
        $this->assertSame( array( 'kiriof_classic_pin_drafts' ), $result['writes'] );
        $this->assertSame( array(), $result['lookups'] );
        $this->assertSame( array( 'legacy' ), $result['session']['chosen_shipping_methods'] );
    }

    public function test_coordinate_draft_ajax_clear_and_foreign_country_remove_the_bound_pin(): void {
        foreach ( array( 'draft-clear', 'draft-foreign-clear', 'draft-foreign', 'draft-foreign-omitted', 'draft-country-missing', 'draft-country-malformed' ) as $mode ) {
            $result = $this->runCase( $mode );
            $this->assertNull( $result['session']['kiriof_buyer_destination'] );
            $this->assertNull( $result['session']['kiriof_buyer_destination_coordinates'] );
            $this->assertSame( array(), $result['session']['kiriof_instant_checkout_quotes'] );
            $this->assertSame( array( 'legacy' ), $result['session']['chosen_shipping_methods'] );
            $this->assertSame( array(), $result['lookups'] );
            $success = in_array( $mode, array( 'draft-clear', 'draft-foreign-clear' ), true );
            $this->assertSame( $success, $result['response']['success'], $mode );
            if ( $success ) {
                $this->assertSame( array( 'coordinates_saved' => true ), $result['response']['data'] );
                $this->assertSame( 200, $result['response']['status'] );
            } else {
                $this->assertSame( 'kiriof_pin_address_mismatch', $result['response']['data']['code'], $mode );
            }
        }
    }

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
        $expectedContracts = [];
        $actualContracts = [];

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
            $case = ($mode) . ' #' . count($expectedContracts);
            $expectedContracts[$case] = array();
            $actualContracts[$case] = $result['writes'];
            $response = $result['response'];
            $case = ($mode) . ' #' . count($expectedContracts);
            $expectedContracts[$case] = [
                    'response.success' => false,
                    'response.status' => $status,
                    'response.data.code' => 'kiriof_pin_' . $reason,
                ];
            $actualContracts[$case] = [
                    'response.success' => $response['success'],
                    'response.status' => $response['status'],
                    'response.data.code' => $response['data']['code'],
                ];
            $this->assertNotEmpty( $response['data']['message'], $mode );
            $case = ($mode) . ' #' . count($expectedContracts);
            $expectedContracts[$case] = $response['data']['message'];
            $actualContracts[$case] = $response['data']['msg'];
            foreach ( array( '55581', '12345', 'Main street', 'Other street', 'secret', 'credentials', '<private>' ) as $private ) {
                $this->assertStringNotContainsString( $private, $response['data']['message'], $mode );
            }
        }
        $case = (__FUNCTION__) . ' #' . count($expectedContracts);
        $expectedContracts[$case] = [
                'fixture.lookups' => array( '55581' ),
                'fixture.lookups' => array(),
                'fixture.lookups' => array(),
            ];
        $actualContracts[$case] = [
                'fixture.lookups' => $this->runCase( 'not-mapped' )['lookups'],
                'fixture.lookups' => $this->runCase( 'cached-lookup-fails' )['lookups'],
                'fixture.lookups' => $this->runCase( 'nonce' )['lookups'],
            ];
    
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
}
    public function test_pin_bridge_only_writes_pin_and_quote_state(): void {
        $result = $this->runCase( 'pin' );
        $this->assertSame(
            [
                'response' => array( 'success' => true, 'data' => array( 'pin_saved' => true ), 'status' => 200 ),
                'writes' => array( 'kiriof_buyer_destination', 'kiriof_buyer_destination_coordinates', 'kiriof_instant_checkout_quotes', 'kiriof_instant_checkout_status' ),
                'session.kiriof_buyer_destination.district_label' => 'Authoritative district',
                'session.destination_id' => '123',
                'session.chosen_payment_method' => 'cod',
                'session.kiriof_insurance' => 1,
                'session.chosen_shipping_methods' => array( 'legacy' ),
            ],
            [
                'response' => $result['response'],
                'writes' => $result['writes'],
                'session.kiriof_buyer_destination.district_label' => $result['session']['kiriof_buyer_destination']['district_label'],
                'session.destination_id' => $result['session']['destination_id'],
                'session.chosen_payment_method' => $result['session']['chosen_payment_method'],
                'session.kiriof_insurance' => $result['session']['kiriof_insurance'],
                'session.chosen_shipping_methods' => $result['session']['chosen_shipping_methods'],
            ],
            __FUNCTION__
        );
    }
    public function test_explicit_clear_does_not_rewrite_legacy_selection(): void {
        $result = $this->runCase( 'clear' );
        $this->assertSame(
            [
                'session.kiriof_buyer_destination' => null,
                'session.kiriof_buyer_destination_coordinates' => null,
                'session.destination_id' => '123',
                'count(result.writes)' => 4,
            ],
            [
                'session.kiriof_buyer_destination' => $result['session']['kiriof_buyer_destination'],
                'session.kiriof_buyer_destination_coordinates' => $result['session']['kiriof_buyer_destination_coordinates'],
                'session.destination_id' => $result['session']['destination_id'],
                'count(result.writes)' => count($result['writes']),
            ],
            __FUNCTION__
        );
    }
    public function test_legacy_district_endpoint_preserves_same_and_clears_changed_pin(): void {
        $this->assertSame(
            [
                'fixture.session.kiriof_buyer_destination.version' => 2,
                'fixture.session.kiriof_buyer_destination' => null,
            ],
            [
                'fixture.session.kiriof_buyer_destination.version' => $this->runCase( 'district-same' )['session']['kiriof_buyer_destination']['version'],
                'fixture.session.kiriof_buyer_destination' => $this->runCase( 'district-changed' )['session']['kiriof_buyer_destination'],
            ],
            __FUNCTION__
        );
    }
    public function test_legacy_fee_updates_preserve_only_matching_valid_pins(): void {
        $this->assertSame(
            [
                'fixture.session.kiriof_buyer_destination.version' => 2,
                'fixture.session.kiriof_buyer_destination' => null,
            ],
            [
                'fixture.session.kiriof_buyer_destination.version' => $this->runCase( 'legacy-same' )['session']['kiriof_buyer_destination']['version'],
                'fixture.session.kiriof_buyer_destination' => $this->runCase( 'legacy-changed' )['session']['kiriof_buyer_destination'],
            ],
            __FUNCTION__
        );
    }

	public function test_pin_rejections_have_fixed_private_diagnostics_but_success_is_silent(): void {
		foreach ( array( 'mismatch' => 'kiriof_pin_district_mismatch', 'shipping-mismatch' => 'kiriof_pin_district_mismatch', 'lookup-fails' => 'kiriof_pin_lookup_unavailable' ) as $mode => $code ) {
			$result = $this->runCase( $mode );
			$this->assertSame( array( array(
				'level' => 'warning',
				'message' => 'Classic checkout pin save rejected.',
				'context' => array( 'operation' => 'sync_classic_pin', 'code' => $code, 'scope' => 'shipping-mismatch' === $mode ? 'shipping' : 'billing', 'http_status' => 'lookup-fails' === $mode ? 503 : 409, 'backtrace' => false ),
				'channel' => 'kiriminaja_checkout',
			) ), $result['logs'] );
			foreach ( array( 'Private', '12345', 'Main street', '106.8', 'credentials' ) as $secret ) {
				$this->assertStringNotContainsString( $secret, json_encode( $result['logs'] ) );
			}
		}
		$this->assertSame( array(), $this->runCase( 'valid' )['logs'] );
	}

	public function test_technical_log_download_includes_checkout_and_shipping_diagnostics(): void {
		$class = new ReflectionClass( \KiriminAjaOfficial\Controllers\SettingController::class );
		$controller = $class->newInstanceWithoutConstructor();
		$sources = $class->getMethod( 'getPluginLogSources' )->invoke( $controller );
		$this->assertContains( 'kiriminaja_checkout', $sources );
		$this->assertContains( 'kiriminaja_shipping', $sources );
		$this->assertContains( 'kiriminaja_api', $sources );
	}

	public function test_saved_classic_district_and_pin_invalidate_cached_rates_without_resetting_method(): void {
		foreach ( array( 'cached-rates', 'district-cached-rates' ) as $mode ) {
			$result = $this->runCase( $mode );
			foreach ( array( 0, 2, 7 ) as $package ) {
				$this->assertFalse( $result['session'][ 'shipping_for_package_' . $package ], $mode );
			}
			$this->assertSame( array( 'legacy' ), $result['session']['chosen_shipping_methods'] );
		}
		$result = $this->runCase( 'mismatch-cached-rates' );
		$this->assertSame( array(), $result['writes'] );
		$this->assertSame( array( 'rates' => array() ), $result['session']['shipping_for_package_0'] );
	}
}
