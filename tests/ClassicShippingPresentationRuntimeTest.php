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

	private function summary_row( string $html, string $kind, int $index ): string {
		$this->assertSame( 1, preg_match( '/<tr\b[^>]*class="[^"]*\bkiriof-classic-shipping-summary-' . preg_quote( $kind, '/' ) . '\b[^"]*"[^>]*>.*?<\/tr>/s', $html, $matches ) );
		$row = $matches[0];
		$this->assertStringContainsString( 'data-kiriof-summary-package="' . $index . '"', $row );
		$this->assertMatchesRegularExpression( '/<tr\b[^>]*\bhidden(?:\s|>)/', $row );
		$this->assertStringNotContainsString( 'woocommerce-shipping-totals', $row );
		$this->assertStringNotContainsString( '<input', $row );
		$this->assertStringNotContainsString( '<select', $row );
		$this->assertStringNotContainsString( 'name=', $row );
		$this->assertGreaterThan( strpos( $html, '</tr>' ), strpos( $html, $row ), 'Read-only summaries follow the native shipping row.' );
		return $row;
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
		$this->assertSame( 1, preg_match( '/<label\b[^>]*class="kiriof-shipping-method-label"[^>]*>(.*?)<\/label>/s', $result['html'], $label ) );
		$this->assertSame( 1, substr_count( $label[1], '1-2 days' ), 'Native label preserves its ETA without appending metadata.' );
		$options = $this->summary_row( $result['html'], 'options', 0 );
		$this->assertSame( 1, substr_count( $options, '1-2 days' ), 'Read-only summary intentionally repeats the chosen label.' );
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

	public function test_chosen_gosend_summary_preserves_native_controls_for_each_package(): void {
		foreach ( array( 0, 3 ) as $index ) {
			$rates = array( array( 'id' => 'kiriminaja-official_gosend_instant', 'label' => 'GoSend Instant', 'cost' => 18000 ) );
			if ( 3 === $index ) {
				array_unshift( $rates, array( 'id' => 'flat_rate:4', 'label' => 'Other courier', 'cost' => 7000 ) );
			}
			$result = $this->render( array( 'index' => $index, 'chosen' => 'kiriminaja-official_gosend_instant', 'rates' => $rates ) );
			$options = $this->summary_row( $result['html'], 'options', $index );
			$cost = $this->summary_row( $result['html'], 'cost', $index );
			$this->assertStringContainsString( '<th scope="row">Shipping Options</th>', $options );
			$this->assertStringContainsString( '<td data-title="Shipping Options">GoSend Instant</td>', $options );
			$this->assertStringContainsString( '<th scope="row">Shipping Cost</th>', $cost );
			$this->assertStringContainsString( '<td data-title="Shipping Cost">Rp 18000</td>', $cost );
			$this->assertSame( count( $rates ), substr_count( $result['html'], 'type="radio"' ) );
			$this->assertSame( count( $rates ), substr_count( $result['html'], 'name="shipping_method[' . $index . ']"' ) );
			$this->assertSame( 1, substr_count( $result['html'], 'checked="checked"' ) );
			$this->assertMatchesRegularExpression( '/<input[^>]*value="kiriminaja-official_gosend_instant"[^>]*checked="checked"/', $result['html'] );
			$this->assertSame( array_map( static function ( $rate ) use ( $index ) { return array( $rate['id'], $index ); }, $rates ), $result['hooks'] );
			$this->assertSame( 3 === $index ? 1 : 0, substr_count( $result['html'], '<select' ) );
		}
	}

	public function test_multiple_package_summaries_keep_nonzero_package_keys_and_independent_choices(): void {
		$html = '';
		foreach ( array( 2 => 'gosend_instant', 5 => 'gosend_sameday' ) as $index => $chosen ) {
			$result = $this->render( array(
				'index' => $index,
				'chosen' => $chosen,
				'rates' => array(
					array( 'id' => 'gosend_instant', 'label' => 'GoSend Instant', 'cost' => 18000 ),
					array( 'id' => 'gosend_sameday', 'label' => 'GoSend Same Day', 'cost' => 10000 ),
				),
			) );
			$options = $this->summary_row( $result['html'], 'options', $index );
			$cost = $this->summary_row( $result['html'], 'cost', $index );
			$this->assertStringContainsString( 2 === $index ? '>GoSend Instant</td>' : '>GoSend Same Day</td>', $options );
			$this->assertStringContainsString( 2 === $index ? '>Rp 18000</td>' : '>Rp 10000</td>', $cost );
			$this->assertMatchesRegularExpression( '/<input[^>]*name="shipping_method\[' . $index . '\]"[^>]*value="' . $chosen . '"[^>]*checked="checked"/', $result['html'] );
			$html .= $result['html'];
		}
		$this->assertSame( 2, substr_count( $html, 'kiriof-classic-shipping-summary-options' ) );
		$this->assertSame( 2, substr_count( $html, 'kiriof-classic-shipping-summary-cost' ) );
		$this->assertSame( 2, substr_count( $html, 'data-kiriof-summary-package="2"' ) );
		$this->assertSame( 2, substr_count( $html, 'data-kiriof-summary-package="5"' ) );
	}

	public function test_summary_escapes_chosen_label_and_strips_price_markup(): void {
		$result = $this->render( array(
			'chosen' => 'gosend',
			'price_prefix_html' => '<em>Offer &amp; &lt;safe&gt;</em> ',
			'rates' => array( array( 'id' => 'gosend', 'label' => 'GoSend <script>alert("x")</script> & Express', 'cost' => 12000 ) ),
		) );
		$options = $this->summary_row( $result['html'], 'options', 0 );
		$cost = $this->summary_row( $result['html'], 'cost', 0 );
		$this->assertStringContainsString( 'GoSend &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; Express</td>', $options );
		$this->assertStringNotContainsString( '<script>', $options );
		$this->assertStringContainsString( '<td data-title="Shipping Cost">Offer &amp; &lt;safe&gt; Rp 12000</td>', $cost );
		$this->assertStringNotContainsString( '<span', $cost );
		$this->assertStringNotContainsString( '<em>', $cost );
	}

	public function test_summary_uses_server_tax_display_and_actual_discounted_or_zero_cost(): void {
		foreach ( array(
			array( 'cost' => 0, 'expected' => 'Rp 0' ),
			array( 'cost' => 20000, 'getter_cost' => 15000, 'expected' => 'Rp 15000' ),
			array( 'cost' => 20000, 'getter_cost' => 15000, 'taxes' => array( 1000, 500 ), 'including_tax' => true, 'expected' => 'Rp 16500 (incl. VAT)' ),
			array( 'cost' => 20000, 'getter_cost' => 15000, 'taxes' => array( 1000, 500 ), 'prices_include_tax' => true, 'expected' => 'Rp 15000 (excl. VAT)' ),
		) as $case ) {
			$result = $this->render( array(
				'chosen' => 'gosend',
				'including_tax' => $case['including_tax'] ?? false,
				'prices_include_tax' => $case['prices_include_tax'] ?? false,
				'session' => array( 'kiriof_shipping_coupon_rate_meta' => array( 'gosend' => array( 'original_cost' => 25000, 'discount_amount' => 10000, 'badge' => 'Promo' ) ) ),
				'rates' => array( array_merge( array( 'id' => 'gosend', 'label' => 'GoSend Instant' ), $case ) ),
			) );
			$cost = $this->summary_row( $result['html'], 'cost', 0 );
			$this->assertStringContainsString( '<td data-title="Shipping Cost">' . $case['expected'] . '</td>', $cost );
			foreach ( array( 'Rp 25000', 'Save', 'Promo', '<del', '<ins', '<span' ) as $comparison ) {
				$this->assertStringNotContainsString( $comparison, $cost, 'Summary shows only the actual current server-formatted cost.' );
			}
		}
	}

	public function test_summary_is_absent_on_cart_or_without_an_available_chosen_rate(): void {
		$rates = array( array( 'id' => 'gosend', 'label' => 'GoSend Instant' ) );
		foreach ( array(
			array( 'cart' => true, 'chosen' => 'gosend', 'session' => array( 'destination_id' => 123, 'kiriof_chosen_shipping_methods' => array( 'gosend' ) ), 'rates' => $rates ),
			array( 'cart' => true, 'chosen' => 'gosend', 'rates' => $rates ),
			array( 'rates' => $rates ),
			array( 'chosen' => 'unavailable', 'rates' => $rates ),
			array( 'chosen' => 'gosend', 'rates' => array() ),
		) as $input ) {
			$result = $this->render( $input );
			$this->assertStringNotContainsString( 'kiriof-classic-shipping-summary', $result['html'] );
		}
	}
}
