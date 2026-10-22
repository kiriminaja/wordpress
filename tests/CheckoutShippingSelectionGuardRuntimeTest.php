<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheckoutShippingSelectionGuardRuntimeTest extends TestCase {
	public function test_available_selection_mismatch_logs_only_private_fingerprints_and_order_diagnostic(): void {
		$expectedCases = $actualCases = [];
		$reviewed_id = 'kiriminaja-instant:7:gosend:instant:private-reviewed-rate';
		$selected_id = 'kiriminaja-official_jne_REG:private-selected-rate';
		$fingerprints = null;
		foreach ( array( false, true ) as $order_matches_review ) {
			$payload = array( 'case' => 'available-mismatch', 'classic' => false, 'order_lines_review' => $order_matches_review );
			exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/checkout-shipping-selection-guard-runtime.php' ) . ' ' . escapeshellarg( json_encode( $payload, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
			$contractCase = implode( "\n", $output );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = 0;
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $status;
			$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: result[attempts]' => array( array( 'status' => 409, 'message' => 'Shipping options changed. Please review and select your courier again before placing the order.', 'writes' => array() ) ),
			    '2: result[chosen]' => array( 3 => $selected_id ),
			    '3: result[logs]' => array( array(
				'level' => 'warning',
				'message' => 'Checkout shipping review rejected.',
				'context' => array(
					'reason' => 'selected_rate_mismatch',
					'route' => 'blocks',
					'backtrace' => false,
					'reviewed_kind' => 'instant',
					'selected_kind' => 'express',
					'reviewed_fingerprint' => substr( hash( 'sha256', $reviewed_id ), 0, 16 ),
					'selected_fingerprint' => substr( hash( 'sha256', $selected_id ), 0, 16 ),
					'order_matches_review' => $order_matches_review,
				),
				'channel' => 'kiriminaja_checkout',
			) ),
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: result[attempts]' => $result['attempts'],
			    '2: result[chosen]' => $result['chosen'],
			    '3: result[logs]' => $result['logs'],
			    ];
			$context = $result['logs'][0]['context'];
			$current_fingerprints = array( $context['reviewed_fingerprint'], $context['selected_fingerprint'] );
			foreach ( $current_fingerprints as $fingerprint ) { $this->assertMatchesRegularExpression( '/^[a-f0-9]{16}$/', $fingerprint ); }
			$this->assertNotSame( $current_fingerprints[0], $current_fingerprints[1] );
			if ( null !== $fingerprints ) { $contractCase = 'Fingerprints depend only on exact rate IDs, not draft order state.';
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = $fingerprints;
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $current_fingerprints; }
			$fingerprints = $current_fingerprints;
			$logs = json_encode( $result['logs'], JSON_THROW_ON_ERROR );
			foreach ( array( $reviewed_id, $selected_id, 'private-', 'address', 'pin"', 'api_key', 'quote_key', 'package_id', 'rate_id' ) as $private ) {
				$this->assertStringNotContainsString( $private, $logs );
			}
			unset( $output );
		}
		$this->assertSame( $expectedCases, $actualCases );
	}

	public function test_current_package_identity_is_authoritative_when_woo_retains_obsolete_session_keys(): void {
		$expectedCases = $actualCases = [];
		foreach ( array( array( 'case' => 'instant', 'classic' => false, 'allowed' => true ), array( 'case' => 'outside', 'classic' => false, 'allowed' => true ), array( 'case' => 'changed', 'classic' => false, 'allowed' => false ), array( 'case' => 'instant', 'classic' => true, 'allowed' => true ) ) as $input ) {
			$payload = $input + array( 'stale_session_keys' => true );
			exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/checkout-shipping-selection-guard-runtime.php' ) . ' ' . escapeshellarg( json_encode( $payload, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
			$contractCase = implode( "\n", $output );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = 0;
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $status;
			$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: result[attempts][0][writes]' => $input['allowed'] ? array( 'express-validation', 'instant-validation' ) : array(),
			    '2: result[attempts][0][message]' => $input['allowed'] ? '' : 'Shipping options changed. Please review and select your courier again before placing the order.',
			    '3: result[logs]' => $input['allowed'] ? array() : array( array( 'level' => 'warning', 'message' => 'Checkout shipping review rejected.', 'context' => array( 'reason' => 'reviewed_rate_unavailable', 'route' => 'blocks', 'backtrace' => false ), 'channel' => 'kiriminaja_checkout' ) ),
			    '4: result[chosen]' => array( 3 => $input['case'] === 'outside' ? 'flat_rate:8' : ( $input['case'] === 'changed' ? 'kiriminaja-official_jne_REG' : 'kiriminaja-instant:7:gosend:instant' ), 0 => 'kiriminaja-instant:7:gosend:instant', 12 => 'flat_rate:obsolete' ),
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: result[attempts][0][writes]' => $result['attempts'][0]['writes'],
			    '2: result[attempts][0][message]' => $result['attempts'][0]['message'],
			    '3: result[logs]' => $result['logs'],
			    '4: result[chosen]' => $result['chosen'],
			    ];
			unset( $output );
		}
		$this->assertSame( $expectedCases, $actualCases );
	}

	public static function cases(): iterable {
		foreach ( array( false, true ) as $classic ) {
			foreach ( array( 'instant', 'express', 'legacy', 'multi', 'outside', 'changed', 'terms', 'into-plugin', 'away-plugin', 'missing', 'malformed', 'duplicate', 'missing-rate', 'service', 'case', 'instance', 'order-route', 'control', 'overflow', 'negative', 'outside-malformed', 'patch', 'opaque', 'translated' ) as $case ) {
				yield ( $classic ? 'Classic ' : 'StoreAPI ' ) . $case => array( $classic, $case );
			}
		}
	}

	#[DataProvider( 'cases' )]
	public function test_review_gate_precedes_route_writes_and_never_reselects( bool $classic, string $case ): void {
		$expectedCases = $actualCases = [];
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/checkout-shipping-selection-guard-runtime.php' ) . ' ' . escapeshellarg( json_encode( compact( 'classic', 'case' ), JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
		$contractCase = implode( "\n", $output );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = 0;
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $status;
		$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: result[registered]' => true,
		    '2: result[priorities]' => array( 5, 10, 20 ),
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: result[registered]' => $result['registered'],
		    '2: result[priorities]' => $result['priorities'],
		    ];
		$success = in_array( $case, array( 'instant', 'express', 'legacy', 'multi', 'outside', 'opaque' ), true ) || ( 'patch' === $case && ! $classic );
		$final = $result['attempts'][count( $result['attempts'] ) - 1];
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: final[writes]' => $success ? array( 'express-validation', 'instant-validation' ) : array(),
		    '2: final[status]' => $success || $classic ? 0 : 409,
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: final[writes]' => $final['writes'],
		    '2: final[status]' => $final['status'],
		    ];
		$expected_message = 'translated' === $case ? '&lt;b&gt;Shipping changed &amp; retry&lt;/b&gt;' : 'Shipping options changed. Please review and select your courier again before placing the order.';
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: final[message]' => $success ? '' : $expected_message,
		    '2: count( result[attempts] )' => 'terms' === $case ? 2 : 1,
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: final[message]' => $final['message'],
		    '2: count( result[attempts] )' => count( $result['attempts'] ),
		    ];
		if ( 'terms' === $case ) {
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = array( 'status' => 'terms', 'writes' => array() );
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $result['attempts'][0];
		}
		if ( 'missing-rate' === $case ) { $contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = array( 3 => 'kiriminaja-instant:7:gosend:instant' );
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $result['chosen']; }
		$this->assertSame( $expectedCases, $actualCases );
	}
}
