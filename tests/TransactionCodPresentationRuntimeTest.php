<?php
use PHPUnit\Framework\TestCase;

final class TransactionCodPresentationRuntimeTest extends TestCase {
	private function render( string $mode, array $overrides = array() ): array {
		$payload = array_replace( array(
			'mode' => $mode,
			'delivery_type' => 'express',
			'service' => 'lion',
			'transaction_value' => 200000,
			'shipping_cost' => 7700,
			'discount_amount' => 385,
			'insurance_cost' => 1200,
			'cod_fee' => 6945,
			'wc_order' => array( 'payment_method' => 'cod', 'shipping_total' => 7315, 'total' => 215460, 'subtotal' => 200000 ),
		), $overrides );
		exec( kiriof_test_fixture_php_shell( array( PLUGIN_DIR . '/tests/fixtures/shipment-detail-amounts-runtime.php', json_encode( $payload, JSON_THROW_ON_ERROR ) ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_list_and_both_detail_paths_match_discounted_cod_wire_amount(): void {
		$list = $this->render( 'list' );
		$this->assertSame( 215460, $list['package']['codValue'] );
		$this->assertSame( 7700, $list['package']['actualShipping'] );
		$this->assertSame( 385, $list['package']['shippingDiscount'] );
		$this->assertSame( 7315, $list['package']['paidShipping'] );
		foreach ( array( 'detail', 'fallback' ) as $mode ) {
			$shipment = $this->render( $mode )['transaction']['shipment'];
			$this->assertSame( 215460, $shipment['codValue'], $mode );
			$this->assertSame( 215460, $shipment['costs']['orderTotal'], $mode );
			$this->assertSame( 15460, $shipment['costs']['totalShipping'], $mode );
			$this->assertSame( $shipment['costs']['shipping'] + $shipment['costs']['insurance'] + $shipment['costs']['codFee'], $shipment['costs']['totalShipping'] );
		}
	}

	public function test_orderless_cod_uses_persisted_discount_and_woo_total_does_not_rewrite_pickup_amount(): void {
		foreach ( array( 'list', 'detail', 'fallback' ) as $mode ) {
			$data = $this->render( $mode, array( 'wc_order' => false ) );
			$this->assertSame( 215460, 'list' === $mode ? $data['package']['codValue'] : $data['transaction']['shipment']['codValue'] );
		}
		$data = $this->render( 'detail', array( 'wc_order' => array( 'payment_method' => 'cod', 'shipping_total' => 7000, 'total' => 215145, 'subtotal' => 200000 ) ) )['transaction']['shipment'];
		$this->assertSame( 215460, $data['codValue'] );
		$this->assertSame( 215145, $data['costs']['orderTotal'] );
		$this->assertSame( 15145, $data['costs']['totalShipping'] );
	}

	public function test_instant_and_non_cod_have_no_collectible_cod(): void {
		foreach ( array( array( 'cod_fee' => 0 ), array( 'delivery_type' => 'instant', 'service' => 'gosend' ) ) as $overrides ) {
			foreach ( array( 'list', 'detail', 'fallback' ) as $mode ) {
				$data = $this->render( $mode, $overrides );
				$this->assertSame( 0, 'list' === $mode ? $data['package']['codValue'] : $data['transaction']['shipment']['codValue'] );
			}
		}
	}
}
