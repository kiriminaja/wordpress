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

	public function test_checkout_preserves_legacy_dropdown_radio_sync_coupon_prices_and_hooks(): void {
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
		$this->assertStringContainsString( 'id="kiriof_shipping_method_select_2"', $html );
		$this->assertStringContainsString( 'wc-enhanced-select kiriof-classic-shipping-method-select', $html );
		$this->assertStringContainsString( 'kiriof-shipping-methods-list--enhanced', $html );
		$this->assertSame( 2, substr_count( $html, 'type="radio"' ) );
		$this->assertStringContainsString( 'name="shipping_method[2]" data-index="2"', $html );
		$this->assertStringContainsString( 'for="shipping_method_2_kiriminaja-official_gosend_instant"', $html );
		$this->assertMatchesRegularExpression( '/value="kiriminaja-official_gosend_instant"[^>]*selected="selected"/', $html );
		$this->assertSame( 1, substr_count( $html, 'checked="checked"' ) );
		$this->assertStringNotContainsString( '<option value=""', $html );
		$this->assertStringNotContainsString( 'kiriof-cart-shipment-row', $html );
		foreach ( array( 'kiriof-shipping-rate-eta', '1-2 hours', 'Instant service', 'Local delivery', 'Tomorrow' ) as $copy ) {
			$this->assertStringNotContainsString( $copy, $html, 'Legacy courier UI must not add service descriptions or ETA metadata.' );
		}
		$this->assertStringContainsString( 'value="flat_rate:4"', $html );
		$this->assertStringContainsString( 'Local &lt;courier&gt;', $html );
		$this->assertStringNotContainsString( 'Local <courier>', $html );
		$this->assertStringContainsString( 'Promo &lt;offer&gt;', $html );
		$this->assertStringContainsString( '<del class="kiriof-shipping-rate-original"><span class="amount">Rp 25000</span></del>', $html );
		$this->assertStringContainsString( '<ins class="kiriof-shipping-rate-discounted"><span class="amount">Rp 20000</span></ins>', $html );
		$this->assertStringContainsString( 'Save Rp 5000', $html );
		$this->assertStringContainsString( 'GOSEND Instant Promo &lt;offer&gt;Rp 25000Rp 20000Save Rp 5000', $html );
		$this->assertSame( array( array( 'kiriminaja-official_gosend_instant', 2 ), array( 'flat_rate:4', 2 ) ), $result['hooks'] );
	}

	public function test_single_rate_preserves_label_eta_without_adding_metadata_or_dropdown(): void {
		$result = $this->render( array( 'chosen' => 'legacy', 'rates' => array( array( 'id' => 'legacy', 'label' => 'JNE REG (1-2 days) <script>', 'meta' => array( 'kiriof_rate_eta' => '1-2 days', 'kiriof_rate_description' => 'Regular & safe <script>alert(1)</script>' ) ) ) ) );
		$this->assertSame( 1, substr_count( $result['html'], '1-2 days' ) );
		$this->assertStringNotContainsString( '<script>', $result['html'] );
		$this->assertStringContainsString( '&lt;script&gt;', $result['html'] );
		$this->assertStringNotContainsString( 'Regular', $result['html'] );
		$this->assertStringContainsString( 'type="radio"', $result['html'] );
		$this->assertStringContainsString( 'checked="checked"', $result['html'] );
		$this->assertStringNotContainsString( '<select', $result['html'] );
		$this->assertStringNotContainsString( 'kiriof-shipping-methods-list--enhanced', $result['html'] );
		$this->assertSame( array( array( 'legacy', 0 ) ), $result['hooks'] );
	}

	public function test_cart_dropdown_requires_destination_and_explicit_buyer_selection(): void {
		$input = array( 'cart' => true, 'chosen' => 'b', 'session' => array( 'destination_id' => 123 ), 'rates' => array( array( 'id' => 'a', 'label' => 'A' ), array( 'id' => 'b', 'label' => 'B' ) ) );
		$result = $this->render( $input );
		$this->assertStringContainsString( '<select', $result['html'] );
		$this->assertStringContainsString( 'kiriof-cart-shipment-row', $result['html'] );
		$this->assertStringContainsString( 'kiriof-shipping-methods-list--enhanced', $result['html'] );
		$this->assertStringContainsString( '<option value="" selected="selected" disabled="disabled">Select Option</option>', $result['html'] );
		$this->assertStringNotContainsString( 'checked="checked"', $result['html'] );
		$this->assertSame( array( array( 'a', 0 ), array( 'b', 0 ) ), $result['hooks'] );
		$input['session']['kiriof_chosen_shipping_methods'] = array( 'b' );
		$selected = $this->render( $input );
		$this->assertMatchesRegularExpression( '/value="b"[^>]*selected="selected"/', $selected['html'] );
		$this->assertStringContainsString( 'checked="checked"', $selected['html'] );
		$this->assertStringNotContainsString( '<option value=""', $selected['html'] );
		unset( $input['session']['destination_id'] );
		$missing = $this->render( $input );
		$this->assertStringNotContainsString( '<select', $missing['html'] );
		$this->assertStringNotContainsString( 'type="radio"', $missing['html'] );
		$this->assertSame( array(), $missing['hooks'] );
		$styles = file_get_contents( PLUGIN_DIR . '/assets/wp/css/kj-wp-style.css' );
		$this->assertStringContainsString( '.kiriof-shipping-methods-list--enhanced,', $styles );
		$this->assertStringContainsString( '.woocommerce-checkout .kiriof-classic-shipping-method-select-wrap,', $styles );
	}

	public function test_unselected_checkout_preserves_placeholder_and_ignores_description_eta_metadata(): void {
		$result = $this->render( array( 'rates' => array(
			array( 'id' => 'instant', 'label' => 'GRAB Instant', 'description' => 'Native fallback should not appear', 'delivery_time' => 'Days must not appear', 'meta' => array( 'kiriof_rate_description' => 'Instant delivery in 1-2 hours', 'kiriof_rate_eta' => '1-2 hours' ) ),
			array( 'id' => 'other', 'label' => 'Other courier' ),
		) ) );
		$this->assertStringContainsString( '<option value="" selected="selected" disabled="disabled">Select Option</option>', $result['html'] );
		foreach ( array( '1-2 hours', 'Native fallback', 'Days must not appear', 'checked="checked"' ) as $copy ) {
			$this->assertStringNotContainsString( $copy, $result['html'] );
		}
		$this->assertSame( 2, substr_count( $result['html'], 'type="radio"' ) );
		$this->assertSame( array( array( 'instant', 0 ), array( 'other', 0 ) ), $result['hooks'] );
	}
}
