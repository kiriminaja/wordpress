<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheckoutShippingSelectionGuardRuntimeTest extends TestCase {
	public function test_current_package_identity_is_authoritative_when_woo_retains_obsolete_session_keys(): void {
		foreach ( array( array( 'case' => 'instant', 'classic' => false, 'allowed' => true ), array( 'case' => 'outside', 'classic' => false, 'allowed' => true ), array( 'case' => 'changed', 'classic' => false, 'allowed' => false ), array( 'case' => 'instant', 'classic' => true, 'allowed' => true ) ) as $input ) {
			$payload = $input + array( 'stale_session_keys' => true );
			exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/checkout-shipping-selection-guard-runtime.php' ) . ' ' . escapeshellarg( json_encode( $payload, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
			$this->assertSame( 0, $status, implode( "\n", $output ) );
			$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
			$this->assertSame( $input['allowed'] ? array( 'express-validation', 'instant-validation' ) : array(), $result['attempts'][0]['writes'], $input['case'] );
			$this->assertSame( $input['allowed'] ? '' : 'Shipping options changed. Please review and select your courier again before placing the order.', $result['attempts'][0]['message'] );
			$this->assertSame( $input['allowed'] ? array() : array( array( 'level' => 'warning', 'message' => 'Checkout shipping review rejected.', 'context' => array( 'reason' => 'reviewed_rate_unavailable', 'route' => 'blocks', 'backtrace' => false ), 'channel' => 'kiriminaja_checkout' ) ), $result['logs'] );
			$this->assertSame( array( 3 => $input['case'] === 'outside' ? 'flat_rate:8' : ( $input['case'] === 'changed' ? 'kiriminaja-official_jne_REG' : 'kiriminaja-instant:7:gosend:instant' ), 0 => 'kiriminaja-instant:7:gosend:instant', 12 => 'flat_rate:obsolete' ), $result['chosen'], 'Read-only guard must not rewrite Woo session choices.' );
			unset( $output );
		}
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
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/checkout-shipping-selection-guard-runtime.php' ) . ' ' . escapeshellarg( json_encode( compact( 'classic', 'case' ), JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
		$this->assertTrue( $result['registered'] );
		$this->assertSame( array( 5, 10, 20 ), $result['priorities'] );
		$success = in_array( $case, array( 'instant', 'express', 'legacy', 'multi', 'outside', 'opaque' ), true ) || ( 'patch' === $case && ! $classic );
		$final = $result['attempts'][count( $result['attempts'] ) - 1];
		$this->assertSame( $success ? array( 'express-validation', 'instant-validation' ) : array(), $final['writes'] );
		$this->assertSame( $success || $classic ? 0 : 409, $final['status'] );
		$expected_message = 'translated' === $case ? '&lt;b&gt;Shipping changed &amp; retry&lt;/b&gt;' : 'Shipping options changed. Please review and select your courier again before placing the order.';
		$this->assertSame( $success ? '' : $expected_message, $final['message'] );
		$this->assertCount( 'terms' === $case ? 2 : 1, $result['attempts'] );
		if ( 'terms' === $case ) {
			$this->assertSame( array( 'status' => 'terms', 'writes' => array() ), $result['attempts'][0] );
		}
		if ( 'missing-rate' === $case ) { $this->assertSame( array( 3 => 'kiriminaja-instant:7:gosend:instant' ), $result['chosen'] ); }
	}
}
