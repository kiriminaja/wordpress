<?php

use PHPUnit\Framework\TestCase;

final class RateChoicePresentationRuntimeTest extends TestCase {
	private function render( array $input ): array {
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/classic-shipping-presentation-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
		$document = new DOMDocument();
		@$document->loadHTML( '<?xml encoding="UTF-8">' . $result['html'] );
		$options = array();
		foreach ( $document->getElementsByTagName( 'option' ) as $option ) {
			if ( $option->getAttribute( 'value' ) ) {
				$options[ $option->getAttribute( 'value' ) ] = $option;
			}
		}
		return array( $result, $options );
	}

	public function test_actual_template_emits_escaped_server_prices_and_keeps_native_fallback_and_hooks(): void {
		list( $result, $options ) = $this->render( array(
			'chosen' => 'flat_rate:2',
			'rates' => array(
				array( 'id' => 'flat_rate:2', 'label' => 'Local <courier> " & service', 'cost' => 12345 ),
				array( 'id' => 'kiriminaja-official_jne_REG', 'label' => 'JNE REG', 'cost' => 20000 ),
			),
		) );
		$this->assertSame( 'Local <courier> " & service', $options['flat_rate:2']->getAttribute( 'data-label' ) );
		$this->assertSame( 'Rp 12345', $options['flat_rate:2']->getAttribute( 'data-price' ) );
		$this->assertSame( '', $options['flat_rate:2']->getAttribute( 'data-courier' ) );
		$this->assertSame( 'Local <courier> " & service: Rp 12345', trim( $options['flat_rate:2']->textContent ) );
		$this->assertFalse( $options['flat_rate:2']->hasAttribute( 'data-original-price' ) );
		$this->assertSame( 'selected', $options['flat_rate:2']->getAttribute( 'selected' ) );
		$this->assertStringContainsString( 'data-label="Local &lt;courier&gt; &quot; &amp; service"', $result['html'] );
		$this->assertStringContainsString( '<label class="kiriof-shipping-method-label" for="shipping_method_0_flat_rate-2">Local &lt;courier&gt; &quot; &amp; service: <span class="amount">Rp 12345</span></label>', $result['html'] );
		$this->assertSame( array( array( 'flat_rate:2', 0 ), array( 'kiriminaja-official_jne_REG', 0 ) ), $result['hooks'] );
	}

	public function test_multiple_shipping_taxes_and_cart_tax_display_use_numeric_rate_getters(): void {
		$input = array( 'including_tax' => true, 'rates' => array(
			array( 'id' => 'taxed', 'label' => 'Taxed', 'getter_cost' => 100, 'cost' => 999, 'taxes' => array( 5, 7.5 ) ),
			array( 'id' => 'other', 'label' => 'Other', 'cost' => 20 ),
		) );
		list( , $options ) = $this->render( $input );
		$this->assertSame( 'Rp 112.5 (incl. VAT)', $options['taxed']->getAttribute( 'data-price' ) );
		$input['including_tax'] = false;
		$input['prices_include_tax'] = true;
		list( , $options ) = $this->render( $input );
		$this->assertSame( 'Rp 100 (excl. VAT)', $options['taxed']->getAttribute( 'data-price' ) );
		$input['prices_include_tax'] = false;
		list( , $options ) = $this->render( $input );
		$this->assertSame( 'Rp 100', $options['taxed']->getAttribute( 'data-price' ) );
	}

	public function test_only_real_session_discounts_emit_exact_plain_comparison_metadata(): void {
		list( , $options ) = $this->render( array(
			'including_tax' => true,
			'session' => array( 'kiriof_shipping_coupon_rate_meta' => array(
				'promo' => array( 'original_cost' => 25000, 'discount_amount' => 5000, 'badge' => '<b>Promo</b> &amp; safe' ),
				'no-discount' => array( 'original_cost' => 30000, 'discount_amount' => 0 ),
				'not-lower' => array( 'original_cost' => 10000, 'discount_amount' => 5000, 'notice' => '<em>Not eligible</em>' ),
			) ),
			'rates' => array(
				array( 'id' => 'promo', 'label' => 'Promo courier', 'cost' => 20000, 'taxes' => array( 100, 200 ) ),
				array( 'id' => 'no-discount', 'label' => 'No discount', 'cost' => 20000 ),
				array( 'id' => 'not-lower', 'label' => 'Not lower', 'cost' => 20000 ),
				array( 'id' => 'rate-only', 'label' => 'Legacy', 'cost' => 20000, 'meta' => array( 'kiriof_shipping_coupon_original_cost' => 25000, 'kiriof_shipping_coupon_discount_amount' => 5000 ) ),
			),
		) );
		$this->assertSame( 'Rp 20300 (incl. VAT)', $options['promo']->getAttribute( 'data-price' ) );
		$this->assertSame( 'Rp 25300 (incl. VAT)', $options['promo']->getAttribute( 'data-original-price' ) );
		$this->assertSame( 'Save Rp 5000', $options['promo']->getAttribute( 'data-savings' ) );
		$this->assertSame( 'Promo & safe', $options['promo']->getAttribute( 'data-note' ) );
		foreach ( array( 'no-discount', 'not-lower', 'rate-only' ) as $id ) {
			$this->assertFalse( $options[ $id ]->hasAttribute( 'data-original-price' ) );
			$this->assertFalse( $options[ $id ]->hasAttribute( 'data-savings' ) );
		}
		$this->assertSame( 'Not eligible', $options['not-lower']->getAttribute( 'data-note' ) );
	}

	public function test_zero_prices_are_explicit_and_negative_costs_never_emit_invalid_discounts(): void {
		list( , $options ) = $this->render( array(
			'session' => array( 'kiriof_shipping_coupon_rate_meta' => array(
				'free' => array( 'original_cost' => 5000, 'discount_amount' => 5000 ),
				'negative' => array( 'original_cost' => 5000, 'discount_amount' => 6000 ),
			) ),
			'rates' => array(
				array( 'id' => 'free', 'label' => 'Free', 'cost' => 0 ),
				array( 'id' => 'negative', 'label' => 'Invalid', 'cost' => -1000 ),
			),
		) );
		$this->assertSame( 'Rp 0', $options['free']->getAttribute( 'data-price' ) );
		$this->assertSame( 'Rp 5000', $options['free']->getAttribute( 'data-original-price' ) );
		$this->assertSame( 'Save Rp 5000', $options['free']->getAttribute( 'data-savings' ) );
		$this->assertSame( '', $options['negative']->getAttribute( 'data-price' ) );
		$this->assertFalse( $options['negative']->hasAttribute( 'data-original-price' ) );
	}

	public function test_price_filter_markup_is_never_trusted_in_plain_attributes(): void {
		list( $result, $options ) = $this->render( array(
			'price_prefix_html' => '<img src="bad" onerror="bad()"><b>Currency</b> &amp; ',
			'rates' => array(
				array( 'id' => 'filtered', 'label' => 'Filtered', 'cost' => 100 ),
				array( 'id' => 'other', 'label' => 'Other', 'cost' => 200 ),
			),
		) );
		$this->assertSame( 'Currency & Rp 100', $options['filtered']->getAttribute( 'data-price' ) );
		$this->assertStringContainsString( 'data-price="Currency &amp; Rp 100"', $result['html'] );
		$this->assertFalse( $options['filtered']->hasAttribute( 'onerror' ) );
	}
}
