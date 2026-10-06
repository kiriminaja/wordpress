<?php

use PHPUnit\Framework\TestCase;

final class ClassicOrderReviewVisibilityRuntimeTest extends TestCase {
	private function render( string $scene = 'rates' ): array {
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/classic-order-review-runtime.php' ) . ' ' . escapeshellarg( json_encode( array( 'scene' => $scene ), JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_real_review_fragment_preserves_products_totals_native_rates_and_woocommerce_hooks(): void {
		$result = $this->render();
		$html = $result['html'];
		$this->assertSame( 1, substr_count( $html, '<table ' ) );
		$this->assertStringContainsString( 'shop_table woocommerce-checkout-review-order-table kiriof-classic-order-review', $html );
		foreach ( array( '<thead>', '<tbody>', '<tfoot>', 'Fixture coffee', 'product-quantity', 'cart-subtotal', 'order-total', 'Rp 120,000', 'Rp 135,000', 'Fixture JNE Express REG', 'Fixture GOSEND Instant', 'woocommerce-shipping-contents' ) as $part ) {
			$this->assertStringContainsString( $part, $html );
		}
		$this->assertSame( count($result['rates']), substr_count( $html, 'name="shipping_method[0]"' ) );
		$this->assertSame( 1, substr_count( $html, 'checked="checked"' ) );
		foreach ( $result['rates'] as $id ) { $this->assertStringContainsString( 'value="' . $id . '"', $html ); }
		$this->assertSame( array_merge(array('woocommerce_review_order_before_cart_contents','woocommerce_review_order_after_cart_contents','woocommerce_review_order_before_shipping'),array_fill(0,count($result['rates']),'woocommerce_after_shipping_rate'),array('woocommerce_review_order_after_shipping','woocommerce_review_order_before_order_total','woocommerce_review_order_after_order_total')), array_column( $result['hooks'], 'name' ) );
		$this->assertSame( $result['rates'], array_values( array_filter( array_column( $result['hooks'], 'rate' ) ) ) );
		foreach ( array( 'booking', 'pin required', 'place_order' ) as $extra ) { $this->assertStringNotContainsString( $extra, strtolower( $html ) ); }
	}

	public function test_missing_api_keeps_real_summary_with_native_no_rates_explanation_not_pin_validation(): void {
		$result = $this->render( 'missing-api' );
		$this->assertSame( array(), $result['rates'] );
		$this->assertStringContainsString( 'Shipping options are currently unavailable. Please contact us for assistance.', $result['html'] );
		$this->assertStringContainsString( 'order-total', $result['html'] );
		$this->assertStringContainsString( 'Rp 120,000', $result['html'] );
		$this->assertStringNotContainsString( 'shipping_method[', $result['html'] );
		$this->assertDoesNotMatchRegularExpression( '/\bpin\b/i', $result['html'] );
		$this->assertNotContains( 'woocommerce_after_shipping_rate', array_column( $result['hooks'], 'name' ) );
	}

	public function test_single_instant_rate_is_a_native_checked_radio_without_select(): void {
		$html = $this->render( 'single' )['html'];
		$this->assertStringContainsString( 'value="kiriminaja-instant:1:gosend:instant"', $html );
		$this->assertSame( 1, substr_count( $html, 'type="radio"' ) );
		$this->assertStringContainsString( 'checked="checked"', $html );
		$this->assertStringNotContainsString( '<select', $html );
		$this->assertStringContainsString( 'Rp 140,000', $html );
	}
}
