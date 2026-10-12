<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckoutLogisticsSessionRuntimeTest extends TestCase {
	#[Test]
	#[DataProvider( 'shippableCarts' )]
	public function temporary_false_shipping_flag_preserves_logistics( array $products ): void {
		$session = $this->logisticsSession();
		foreach ( array( 'shipping_filter', 'fees', 'fields' ) as $action ) {
			$result = $this->runFixture( array(
				'action'         => $action,
				'needs_shipping' => false,
				'products'       => $products,
				'session'        => $session,
			) );
			$this->assertSame( $session, $result['session'], $action );
			if ( 'shipping_filter' === $action ) {
				$this->assertFalse( $result['needs_shipping'], 'Do not override another plugin shipping decision.' );
				$this->assertSame( 1, $result['shipping_reads'], 'The filter must not recurse into cart needs_shipping().' );
			}
		}
	}

	#[Test]
	public function suppressed_shipping_does_not_consume_stale_courier_fees(): void {
		$session = $this->logisticsSession();
		$session['chosen_shipping_methods'] = array( 'kiriminaja-official:jne_REG' );
		$result = $this->runFixture( array(
			'action'         => 'fees',
			'needs_shipping' => false,
			'products'       => array( true ),
			'session'        => $session,
		) );
		$this->assertSame( $session, $result['session'] );
	}

	#[Test]
	public function uninitialized_cart_is_not_treated_as_virtual(): void {
		foreach ( array( array( 'cart_unavailable' => true ), array( 'wp_loaded' => false, 'products' => array() ) ) as $state ) {
			foreach ( array( 'shipping_filter', 'fees' ) as $action ) {
				$result = $this->runFixture( $state + array(
					'action'         => $action,
					'needs_shipping' => false,
					'session'        => $this->logisticsSession(),
				) );
				if ( 'shipping_filter' === $action ) {
					$this->assertFalse( $result['needs_shipping'] );
				}
				$this->assertSame( $this->logisticsSession(), $result['session'] );
			}
		}
	}

	public static function shippableCarts(): array {
		return array(
			'physical' => array( array( true ) ),
			'mixed'    => array( array( false, true ) ),
		);
	}

	#[Test]
	#[DataProvider( 'nonShippableCarts' )]
	public function genuinely_non_shipping_carts_clear_logistics( array $products ): void {
		foreach ( array( 'shipping_filter', 'fees', 'fields', 'store_update' ) as $action ) {
			$result = $this->runFixture( array(
				'action'         => $action,
				'needs_shipping' => false,
				'products'       => $products,
				'session'        => $this->logisticsSession(),
			) );
			foreach ( array_keys( $this->logisticsSession() ) as $key ) {
				$this->assertNull( $result['session'][ $key ], $action . ':' . $key );
			}
		}
	}

	public static function nonShippableCarts(): array {
		return array(
			'virtual only' => array( array( false, false ) ),
			'empty'        => array( array() ),
		);
	}

	#[Test]
	public function district_change_and_explicit_reset_still_work_during_false_shipping_flag(): void {
		$session = $this->logisticsSession();
		foreach ( array( 17634, 41146, 0 ) as $destination ) {
			$result = $this->runFixture( array(
				'action'         => 'store_update',
				'needs_shipping' => false,
				'products'       => array( true ),
				'session'        => $session,
				'data'           => array( 'destination_id' => $destination ),
			) );
			$this->assertSame( $destination ?: '', $result['session']['destination_id'] );
			$this->assertSame( $destination ?: '', $result['session']['shipping_destination_id'] );
			$session = $result['session'];
		}
	}

	#[Test]
	#[DataProvider( 'pickupMethods' )]
	public function pickup_does_not_require_district_or_courier_calculation( string $method ): void {
		foreach ( array( 'validate', 'field_validation', 'fields' ) as $action ) {
			$result = $this->runFixture( array(
				'action'   => $action,
				'products' => array( true ),
				'post'     => array(
					'checkout_kiriminaja_nonce_field' => 'valid-country-nonce',
					'billing_country'                => 'ID',
					'kiriof_destination_area'        => '',
					'shipping_method'                => array( $method ),
				),
				'session'  => array( 'chosen_shipping_methods' => array( 'kiriminaja-official:stale' ) ),
			) );
			$this->assertSame( array(), $result['notices'], $action );
			$this->assertSame( array( 'kiriminaja-official:stale' ), $result['session']['chosen_shipping_methods'] );
			if ( 'fields' === $action ) {
				$this->assertFalse( $result['fields']['billing']['kiriof_destination_area']['required'] );
				$this->assertFalse( $result['fields']['shipping']['kiriof_shipping_destination_area']['required'] );
			}
		}
	}

	public static function pickupMethods(): array {
		return array(
			'classic' => array( 'local_pickup:7' ),
			'blocks'  => array( 'pickup_location:0' ),
		);
	}

	#[Test]
	public function posted_courier_overrides_stale_pickup_and_mixed_packages_require_district(): void {
		foreach ( array( array( 'kiriminaja-official:jne_REG' ), array( 'local_pickup:7', 'kiriminaja-official:jne_REG' ) ) as $methods ) {
			$result = $this->runFixture( array(
				'action'  => 'validate',
				'post'    => array(
					'checkout_kiriminaja_nonce_field' => 'valid-country-nonce',
					'billing_country'                => 'ID',
					'shipping_method'                => $methods,
				),
				'session' => array( 'chosen_shipping_methods' => array( 'local_pickup:7' ) ),
			) );
			$this->assertContains( '<strong>District</strong> is a required field', array_column( $result['notices'], 'message' ) );
			$this->assertContains( '<strong>Checkout Calculation</strong> is not finished yet', array_column( $result['notices'], 'message' ) );
		}
	}

	private function logisticsSession(): array {
		return array(
			'destination_id'                => '41146',
			'shipping_destination_id'       => '41146',
			'destination_name'              => 'Medan',
			'shipping_destination_name'     => 'Medan',
			'kiriof_destination_area'        => '41146',
			'kiriof_destination_area_name'   => 'Medan',
			'chosen_shipping_methods'       => array( 'local_pickup:7' ),
			'kiriof_chosen_shipping_methods' => array( 'local_pickup:7' ),
			'kiriof_cached_insurance_amt'    => 1500,
			'kiriof_cached_cod_amt'          => 2500,
		);
	}

	private function runFixture( array $input ): array {
		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/checkout-country-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1';
		exec( $command, $output, $exit_code );
		$text = implode( "\n", $output );
		$this->assertSame( 0, $exit_code, 'Runtime fixture failed: ' . $text );
		return json_decode( $text, true, 512, JSON_THROW_ON_ERROR );
	}
}
