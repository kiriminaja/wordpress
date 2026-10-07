<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheckoutShippingSelectionGuardRuntimeTest extends TestCase {
	public static function cases(): iterable {
		foreach ( array( false, true ) as $classic ) {
			foreach ( array( 'instant', 'express', 'legacy', 'multi', 'outside', 'changed', 'terms', 'into-plugin', 'away-plugin', 'missing', 'malformed', 'duplicate', 'missing-rate', 'service', 'case', 'instance', 'order-route', 'control', 'overflow', 'negative', 'outside-malformed', 'patch' ) as $case ) {
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
		$success = in_array( $case, array( 'instant', 'express', 'legacy', 'multi', 'outside' ), true ) || ( 'patch' === $case && ! $classic );
		$final = $result['attempts'][count( $result['attempts'] ) - 1];
		$this->assertSame( $success ? array( 'express-validation', 'instant-validation' ) : array(), $final['writes'] );
		$this->assertSame( $success || $classic ? 0 : 409, $final['status'] );
		$this->assertSame( $success ? '' : 'Shipping options changed. Please review and select your courier again before placing the order.', $final['message'] );
		$this->assertCount( 'terms' === $case ? 2 : 1, $result['attempts'] );
		if ( 'terms' === $case ) {
			$this->assertSame( array( 'status' => 'terms', 'writes' => array() ), $result['attempts'][0] );
		}
		if ( 'missing-rate' === $case ) { $this->assertSame( array( 3 => 'kiriminaja-instant:7:gosend:instant' ), $result['chosen'] ); }
	}
}
