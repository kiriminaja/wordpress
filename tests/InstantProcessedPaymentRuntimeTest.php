<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InstantProcessedPaymentRuntimeTest extends TestCase {
	public function test_matched_payment_evidence_settles_without_a_new_awb(): void {
		foreach ( array(
			array( 'status_code' => 0 ),
			array( 'status_code' => '0' ),
			array( 'payment_status' => 'success' ),
			array( 'payment_status' => 'settlement' ),
			array( 'status' => ' PAID ' ),
			array( 'status_code' => 9, 'paid_at' => '2025-01-01T17:00:00.123+07:00' ),
			array( 'status_code' => 999, 'payment_status' => 'paid' ),
		) as $evidence ) {
			foreach ( array( 'webhook', 'tracking' ) as $mode ) {
				$result = $this->execute( array(
					'mode' => $mode,
					'package' => array( 'awb' => null ),
					'omit_package_awb' => true,
					'payment' => array_merge( array( 'payment_id' => 'PAY-1' ), $evidence ),
					'repeat' => 2,
					'poll_unpaid' => true,
				) );
				$this->assertSame( 'paid', $result['row']['instant_payment_status'], json_encode( $evidence ) );
				$this->assertNull( $result['row']['awb'] );
				$this->assertCount( 1, $result['writes'] );
				$this->assertSame( array_fill( 0, 2, 'webhook' === $mode ? 200 : 'reconciled' ), $result['responses'] );
			}
		}
	}

	public function test_qr_creation_time_and_invalid_settlement_dates_do_not_settle_payment(): void {
		foreach ( array(
			array( 'status_code' => 9, 'pay_time' => '2025-01-01T10:00:00Z' ),
			array( 'status_code' => 9, 'paid_at' => '2025-02-30T10:00:00Z' ),
			array( 'status_code' => 9, 'paid_at' => true ),
			array( 'status_code' => 9, 'paid_at' => array() ),
		) as $evidence ) {
			$result = $this->execute( array( 'package' => array( 'awb' => null ), 'payment' => array_merge( array( 'payment_id' => 'PAY-1' ), $evidence ) ) );
			$this->assertSame( 'unpaid', $result['row']['instant_payment_status'] );
		}
		$result = $this->execute( array( 'package' => array( 'awb' => null ), 'payment' => array( 'payment_id' => 'PAY-1', 'status_code' => 0, 'payment_status' => 'refunded', 'paid_at' => '2025-01-01T10:00:00Z' ) ) );
		$this->assertSame( 'refunded', $result['row']['instant_payment_status'] );
	}

	public function test_processed_webhook_tracks_url_availability_independently_of_payment_awb_and_route(): void {
		// Synthetic identities and the public encoded-polyline example, never a real webhook log.
		$package = array(
			'service' => 'gosend',
			'service_type' => 'Instant',
			'live_tracking_url' => null,
			'poly_line' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@',
			'driver' => array( 'name' => null, 'phone' => null, 'image' => null ),
		);
		$payment = array( 'payment_id' => 'PAY-1', 'status_code' => 0, 'qr_content' => null, 'pay_time' => null );
		foreach ( array(
			array( '', null, '' ),
			array( '', 'https://courier.example.test/live/AWB-1', 'https://courier.example.test/live/AWB-1' ),
			array( 'https://courier.example.test/live/AWB-1', null, 'https://courier.example.test/live/AWB-1' ),
		) as [ $saved_url, $incoming_url, $expected_url ] ) {
			$result = $this->execute( array(
				'row' => array( 'live_tracking_url' => $saved_url, 'shipping_info' => '{}' ),
				'event' => array( 'awb' => 'AWB-1', 'date' => '2025-01-01T10:00:00Z', 'shipped_at' => null, 'finished_at' => null ),
				'package' => array_replace( $package, array( 'live_tracking_url' => $incoming_url ) ),
				'payment' => $payment,
				'repeat' => 2,
			) );
			$this->assertSame( array( 200, 200 ), $result['responses'] );
			$this->assertSame( array( 'request_pickup', 105, 'AWB-1', 'paid', $expected_url ), array(
				$result['row']['status'], $result['row']['instant_status_code'], $result['row']['awb'], $result['row']['instant_payment_status'], $result['row']['live_tracking_url'],
			) );
			$this->assertCount( 1, $result['writes'] );
			$this->assertSame( array( array( 38.5, -120.2 ), array( 40.7, -120.95 ), array( 43.252, -126.453 ) ), json_decode( $result['row']['shipping_info'], true )['instant_route_points'] );
			if ( null === $incoming_url ) {
				$this->assertArrayNotHasKey( 'live_tracking_url', $result['writes'][0]['changes'] );
			}
		}
	}

	private function execute( array $input ): array {
		$command = escapeshellarg( PHP_BINARY ) . ' -d error_reporting=22527 ' . escapeshellarg( __DIR__ . '/fixtures/instant-processed-payment-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) );
		exec( $command, $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public static function confirmed_packages(): array {
		return array(
			'webhook fills AWB' => array( array() ),
			'webhook matches AWB' => array( array( 'row' => array( 'awb' => 'AWB-1' ) ) ),
			'nullable payment' => array( array( 'payment' => null ) ),
			'pending payment' => array( array( 'row' => array( 'instant_payment_status' => 'pending' ) ) ),
			'matched payment identity' => array( array( 'payment' => array( 'payment_id' => 'PAY-1' ) ) ),
			'delayed unpaid payment metadata' => array( array( 'payment' => array( 'payment_id' => 'PAY-1', 'status_code' => 9 ) ) ),
			'matched new payment identity' => array( array( 'row' => array( 'instant_payment_id' => null ), 'payment' => array( 'payment_id' => 'PAY-1' ) ) ),
			'authenticated API fills AWB' => array( array( 'mode' => 'tracking' ) ),
			'authenticated API matches AWB' => array( array( 'mode' => 'tracking', 'row' => array( 'awb' => 'AWB-1' ) ) ),
		);
	}

	#[DataProvider( 'confirmed_packages' )]
	public function test_processed_qris_confirms_payment_and_awb_atomically( array $input ): void {
		$result = $this->execute( $input + array( 'repeat' => 2, 'poll_unpaid' => true ) );
		$this->assertSame( array( 'request_pickup', 105, 'paid', 'AWB-1', 'PAY-1' ), array(
			$result['row']['status'], $result['row']['instant_status_code'], $result['row']['instant_payment_status'], $result['row']['awb'], $result['row']['instant_payment_id'],
		) );
		$this->assertCount( 1, $result['writes'], 'Duplicate events and stale payment polls must not write again.' );
		$this->assertSame( 'paid', $result['writes'][0]['changes']['instant_payment_status'] );
		$this->assertSame( $input['row']['awb'] ?? null, $result['writes'][0]['condition']['awb'] );
		$this->assertSame( $input['row']['instant_payment_status'] ?? 'unpaid', $result['writes'][0]['condition']['instant_payment_status'] );
		if ( 'tracking' !== ( $input['mode'] ?? '' ) ) {
			$this->assertSame( array( 200, 200 ), $result['responses'] );
		} else {
			$this->assertSame( array( 'reconciled', 'reconciled' ), $result['responses'] );
		}
	}

	public static function insufficient_evidence(): array {
		return array(
			'no supplied AWB' => array( array( 'package' => array( 'awb' => null ) ), 'unpaid' ),
			'local AWB alone' => array( array( 'package' => array( 'awb' => null ), 'row' => array( 'awb' => 'AWB-1' ) ), 'unpaid' ),
			'booking acknowledgement' => array( array( 'mode' => 'booking' ), 'unpaid' ),
			'tracking metadata only' => array( array( 'mode' => 'metadata' ), 'unpaid' ),
			'new package 100' => array( array( 'mode' => 'tracking', 'package' => array( 'status' => 100 ) ), 'unpaid' ),
			'pickup request 101' => array( array( 'mode' => 'tracking', 'package' => array( 'status' => 101 ) ), 'unpaid' ),
			'pending direct pickup 110' => array( array( 'mode' => 'tracking', 'package' => array( 'status' => 110 ) ), 'unpaid' ),
			'no payment identity' => array( array( 'row' => array( 'instant_payment_id' => null ) ), 'unpaid' ),
			'credit is not QRIS' => array( array( 'row' => array( 'instant_payment_method' => 'credit' ) ), 'unpaid' ),
			'refund remains terminal' => array( array( 'row' => array( 'instant_payment_status' => 'refunded' ) ), 'refunded' ),
			'remote refund wins' => array( array( 'payment' => array( 'payment_id' => 'PAY-1', 'status' => 'refunded' ) ), 'refunded' ),
			'refund beats unpaid code' => array( array( 'payment' => array( 'payment_id' => 'PAY-1', 'status_code' => 9, 'status' => 'refunded' ) ), 'refunded' ),
			'refund beats paid code' => array( array( 'payment' => array( 'payment_id' => 'PAY-1', 'status_code' => 0, 'status' => 'refunded' ) ), 'refunded' ),
			'stale canceled package' => array( array( 'row' => array( 'status' => 'canceled', 'instant_status_code' => 300 ), 'mode' => 'tracking' ), 'unpaid' ),
		);
	}

	#[DataProvider( 'insufficient_evidence' )]
	public function test_other_evidence_never_invents_paid_qris( array $input, string $expected ): void {
		$result = $this->execute( $input );
		$this->assertSame( $expected, $result['row']['instant_payment_status'] );
	}

	public static function invalid_callbacks(): array {
		return array(
			'wrong bearer' => array( array( 'token' => 'wrong' ), 401 ),
			'wrong AWB' => array( array( 'row' => array( 'awb' => 'OTHER' ) ), 400 ),
			'malformed AWB' => array( array( 'package' => array( 'awb' => array( 'AWB-1' ) ) ), 400 ),
			'wrong payment' => array( array( 'payment' => array( 'payment_id' => 'OTHER' ) ), 400 ),
			'wrong order' => array( array( 'package' => array( 'order_id' => 'OTHER' ) ), 400 ),
			'wrong courier' => array( array( 'package' => array( 'service' => 'grab_express' ) ), 400 ),
			'contradictory package status' => array( array( 'package' => array( 'status' => 110 ) ), 400 ),
		);
	}

	#[DataProvider( 'invalid_callbacks' )]
	public function test_invalid_callbacks_cannot_change_payment_or_awb( array $input, int $expected ): void {
		$result = $this->execute( $input );
		$this->assertSame( array( $expected ), $result['responses'] );
		$this->assertSame( array(), $result['writes'] );
		$this->assertSame( 'unpaid', $result['row']['instant_payment_status'] );
	}

	public function test_persistence_failure_is_retryable_and_does_not_confirm_payment(): void {
		$result = $this->execute( array( 'write_failure' => true ) );
		$this->assertSame( array( 503 ), $result['responses'] );
		$this->assertSame( array( 'unpaid', null ), array( $result['row']['instant_payment_status'], $result['row']['awb'] ) );
	}

	public function test_concurrent_refund_wins_over_processed_payment_confirmation(): void {
		$result = $this->execute( array( 'race' => array( 'instant_payment_status' => 'refunded' ) ) );
		$this->assertSame( array( 200 ), $result['responses'] );
		$this->assertSame( array( 'refunded', 'AWB-1', 105 ), array( $result['row']['instant_payment_status'], $result['row']['awb'], $result['row']['instant_status_code'] ) );
		$this->assertCount( 2, $result['writes'] );
		$this->assertSame( 'refunded', $result['writes'][1]['condition']['instant_payment_status'] );
		$this->assertArrayNotHasKey( 'instant_payment_status', $result['writes'][1]['changes'] );
	}
}
