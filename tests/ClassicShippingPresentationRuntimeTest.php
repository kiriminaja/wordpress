<?php

use PHPUnit\Framework\TestCase;

final class ClassicShippingPresentationRuntimeTest extends TestCase {
	private function render( array $input ): array {
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/classic-shipping-presentation-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_checkout_renders_native_package_radios_service_eta_and_coupon_prices(): void {
		$result = $this->render( array(
			'index' => 2,
			'chosen' => 'kiriminaja-official_gosend_instant',
			'session' => array( 'kiriof_shipping_coupon_rate_meta' => array( 'kiriminaja-official_gosend_instant' => array( 'original_cost' => 25000, 'discount_amount' => 5000, 'badge' => 'Promo <offer>' ) ) ),
			'rates' => array(
				array( 'id' => 'kiriminaja-official_gosend_instant', 'label' => 'GOSEND Instant', 'meta' => array( 'kiriof_rate_description' => 'Instant service • No Insurance Support', 'kiriof_rate_eta' => '1-2 hours' ) ),
				array( 'id' => 'flat_rate:4', 'label' => 'Local <courier>', 'description' => 'Local delivery', 'delivery_time' => 'Tomorrow' ),
			),
		) );
		$html = $result['html'];
		$this->assertStringNotContainsString( '<select', $html );
		$this->assertStringNotContainsString( 'shipping-methods-list--enhanced', $html );
		$this->assertSame( 2, substr_count( $html, 'type="radio"' ) );
		$this->assertStringContainsString( 'name="shipping_method[2]" data-index="2"', $html );
		$this->assertStringContainsString( 'for="shipping_method_2_kiriminaja-official_gosend_instant"', $html );
		$this->assertStringContainsString( 'checked="checked"', $html );
		$this->assertStringContainsString( 'kiriof-shipping-rate-eta">1-2 hours', $html );
		$this->assertStringContainsString( 'Instant service • No Insurance Support', $html );
		$this->assertStringContainsString( 'Local &lt;courier&gt;', $html );
		$this->assertStringContainsString( 'Local delivery', $html );
		$this->assertStringContainsString( 'Tomorrow', $html );
		$this->assertStringContainsString( 'Promo &lt;offer&gt;', $html );
		$this->assertStringContainsString( '<del class="kiriof-shipping-rate-original">', $html );
		$this->assertStringContainsString( '<ins class="kiriof-shipping-rate-discounted">', $html );
		$this->assertCount( 2, $result['hooks'] );
	}

	public function test_single_rate_is_visible_and_legacy_eta_is_not_duplicated(): void {
		$result = $this->render( array( 'chosen' => 'legacy', 'rates' => array( array( 'id' => 'legacy', 'label' => 'JNE REG (1-2 days)', 'meta' => array( 'kiriof_rate_eta' => '1-2 days', 'kiriof_rate_description' => 'Regular & safe <script>alert(1)</script>' ) ) ) ) );
		$this->assertSame( 1, substr_count( $result['html'], '1-2 days' ) );
		$this->assertStringNotContainsString( '<script>', $result['html'] );
		$this->assertStringContainsString( 'Regular &amp; safe', $result['html'] );
		$this->assertStringContainsString( 'type="radio"', $result['html'] );
		$this->assertStringNotContainsString( '<select', $result['html'] );
	}

	public function test_cart_dropdown_compatibility_stays_scoped_to_cart_shipment_row(): void {
		$result = $this->render( array( 'cart' => true, 'session' => array( 'destination_id' => 123 ), 'rates' => array( array( 'id' => 'a', 'label' => 'A' ), array( 'id' => 'b', 'label' => 'B' ) ) ) );
		$this->assertStringContainsString( '<select', $result['html'] );
		$this->assertStringContainsString( 'kiriof-cart-shipment-row', $result['html'] );
		$this->assertStringContainsString( 'kiriof-shipping-methods-list--enhanced', $result['html'] );
		$styles = file_get_contents( PLUGIN_DIR . '/assets/wp/css/kj-wp-style.css' );
		$this->assertStringContainsString( '.kiriof-cart-shipment-row .kiriof-shipping-methods-list--enhanced {', $styles );
		$this->assertStringNotContainsString( '.woocommerce-checkout .kiriof-classic-shipping-method-select-wrap', $styles );
	}

	public function test_meta_wins_over_native_copy_and_eta_inside_description_is_not_repeated(): void {
		$result = $this->render( array( 'rates' => array( array(
			'id' => 'instant',
			'label' => 'GRAB Instant',
			'description' => 'Native fallback should not appear',
			'delivery_time' => 'Days must not appear',
			'meta' => array( 'kiriof_rate_description' => 'Instant delivery in 1-2 hours', 'kiriof_rate_eta' => '1-2 hours' ),
		) ) ) );
		$this->assertSame( 1, substr_count( $result['html'], '1-2 hours' ) );
		$this->assertStringNotContainsString( 'Native fallback', $result['html'] );
		$this->assertStringNotContainsString( 'Days must not appear', $result['html'] );
		$this->assertStringNotContainsString( 'checked="checked"', $result['html'] );
	}
}
