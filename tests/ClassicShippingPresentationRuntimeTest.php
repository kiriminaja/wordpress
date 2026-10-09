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
		$this->assertSame( array(
			'package marker' => true,
			'hidden row' => 1,
			'excludes woocommerce-shipping-totals' => false,
			'excludes input' => false,
			'excludes select' => false,
			'excludes name' => false,
		), array(
			'package marker' => str_contains( $row, 'data-kiriof-summary-package="' . $index . '"' ),
			'hidden row' => preg_match( '/<tr\b[^>]*\bhidden(?:\s|>)/', $row ),
			'excludes woocommerce-shipping-totals' => str_contains( $row, 'woocommerce-shipping-totals' ),
			'excludes input' => str_contains( $row, '<input' ),
			'excludes select' => str_contains( $row, '<select' ),
			'excludes name' => str_contains( $row, 'name=' ),
		) );
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
		$this->assertSame( array(
			'package dropdown id' => true,
			'enhanced dropdown classes' => true,
			'enhanced list' => true,
			'radio count' => 2,
			'native package identity' => true,
			'native label target' => true,
			'selected instant option' => 1,
			'checked count' => 1,
			'placeholder present' => false,
			'excludes kiriof-cart-shipment-row' => false,
		), array(
			'package dropdown id' => str_contains( $html, 'id="kiriof_shipping_method_select_2"' ),
			'enhanced dropdown classes' => str_contains( $html, 'wc-enhanced-select kiriof-classic-shipping-method-select' ),
			'enhanced list' => str_contains( $html, 'kiriof-shipping-methods-list--enhanced' ),
			'radio count' => substr_count( $html, 'type="radio"' ),
			'native package identity' => str_contains( $html, 'name="shipping_method[2]" data-index="2"' ),
			'native label target' => str_contains( $html, 'for="shipping_method_2_kiriminaja-official_gosend_instant"' ),
			'selected instant option' => preg_match( '/value="kiriminaja-official_gosend_instant"[^>]*selected="selected"/', $html ),
			'checked count' => substr_count( $html, 'checked="checked"' ),
			'placeholder present' => str_contains( $html, '<option value=""' ),
			'excludes kiriof-cart-shipment-row' => str_contains( $html, 'kiriof-cart-shipment-row' ),
		) );
		foreach ( array( 'kiriof-shipping-rate-eta', '1-2 hours', 'Instant service', 'Local delivery', 'Tomorrow' ) as $copy ) {
			$this->assertStringNotContainsString( $copy, $html, 'Legacy courier UI must not add service descriptions or ETA metadata.' );
		}
		$this->assertSame( array(
			'native flat rate option' => true,
			'escaped courier label' => true,
			'raw courier label' => false,
			'escaped promo badge' => true,
			'original price markup' => true,
			'discounted price markup' => true,
			'coupon savings' => true,
			'combined coupon label' => true,
			'hooks' => array( array( 'kiriminaja-official_gosend_instant', 2 ), array( 'flat_rate:4', 2 ) ),
		), array(
			'native flat rate option' => str_contains( $html, 'value="flat_rate:4"' ),
			'escaped courier label' => str_contains( $html, 'Local &lt;courier&gt;' ),
			'raw courier label' => str_contains( $html, 'Local <courier>' ),
			'escaped promo badge' => str_contains( $html, 'Promo &lt;offer&gt;' ),
			'original price markup' => str_contains( $html, '<del class="kiriof-shipping-rate-original"><span class="amount">Rp 25000</span></del>' ),
			'discounted price markup' => str_contains( $html, '<ins class="kiriof-shipping-rate-discounted"><span class="amount">Rp 20000</span></ins>' ),
			'coupon savings' => str_contains( $html, 'Save Rp 5000' ),
			'combined coupon label' => str_contains( $html, 'GOSEND Instant Promo &lt;offer&gt;Rp 25000Rp 20000Save Rp 5000' ),
			'hooks' => $result['hooks'],
		) );
	}

	public function test_single_rate_preserves_label_eta_without_adding_metadata_or_dropdown(): void {
		$result = $this->render( array( 'chosen' => 'legacy', 'rates' => array( array( 'id' => 'legacy', 'label' => 'JNE REG (1-2 days) <script>', 'meta' => array( 'kiriof_rate_eta' => '1-2 days', 'kiriof_rate_description' => 'Regular & safe <script>alert(1)</script>' ) ) ) ) );
		$this->assertSame( array(
			'label matched' => 1,
			'ETA occurrences' => 1,
		), array(
			'label matched' => preg_match( '/<label\b[^>]*class="kiriof-shipping-method-label"[^>]*>(.*?)<\/label>/s', $result['html'], $label ),
			'ETA occurrences' => substr_count( $label[1], '1-2 days' ),
		) );
		$options = $this->summary_row( $result['html'], 'options', 0 );
		$this->assertSame( array(
			'ETA occurrences' => 1,
			'raw script markup' => false,
			'escaped script text' => true,
			'excludes Regular' => false,
			'native radio present' => true,
			'native radio checked' => true,
			'excludes select' => false,
			'excludes kiriof-shipping-methods-list--enhanced' => false,
			'hooks' => array( array( 'legacy', 0 ) ),
		), array(
			'ETA occurrences' => substr_count( $options, '1-2 days' ),
			'raw script markup' => str_contains( $result['html'], '<script>' ),
			'escaped script text' => str_contains( $result['html'], '&lt;script&gt;' ),
			'excludes Regular' => str_contains( $result['html'], 'Regular' ),
			'native radio present' => str_contains( $result['html'], 'type="radio"' ),
			'native radio checked' => str_contains( $result['html'], 'checked="checked"' ),
			'excludes select' => str_contains( $result['html'], '<select' ),
			'excludes kiriof-shipping-methods-list--enhanced' => str_contains( $result['html'], 'kiriof-shipping-methods-list--enhanced' ),
			'hooks' => $result['hooks'],
		) );
	}

	public function test_cart_dropdown_requires_destination_and_explicit_buyer_selection(): void {
		$input = array( 'cart' => true, 'chosen' => 'b', 'session' => array( 'destination_id' => 123 ), 'rates' => array( array( 'id' => 'a', 'label' => 'A' ), array( 'id' => 'b', 'label' => 'B' ) ) );
		$result = $this->render( $input );
		$this->assertSame( array(
			'contains select' => true,
			'contains kiriof-cart-shipment-row' => true,
			'enhanced list' => true,
			'contains Select Option' => true,
			'excludes checked checked' => false,
			'hooks' => array( array( 'a', 0 ), array( 'b', 0 ) ),
		), array(
			'contains select' => str_contains( $result['html'], '<select' ),
			'contains kiriof-cart-shipment-row' => str_contains( $result['html'], 'kiriof-cart-shipment-row' ),
			'enhanced list' => str_contains( $result['html'], 'kiriof-shipping-methods-list--enhanced' ),
			'contains Select Option' => str_contains( $result['html'], '<option value="" selected="selected" disabled="disabled">Select Option</option>' ),
			'excludes checked checked' => str_contains( $result['html'], 'checked="checked"' ),
			'hooks' => $result['hooks'],
		) );
		$input['session']['kiriof_chosen_shipping_methods'] = array( 'b' );
		$selected = $this->render( $input );
		$this->assertSame( array(
			'selected buyer option' => 1,
			'native radio checked' => true,
			'placeholder present' => false,
		), array(
			'selected buyer option' => preg_match( '/value="b"[^>]*selected="selected"/', $selected['html'] ),
			'native radio checked' => str_contains( $selected['html'], 'checked="checked"' ),
			'placeholder present' => str_contains( $selected['html'], '<option value=""' ),
		) );
		unset( $input['session']['destination_id'] );
		$missing = $this->render( $input );
		$this->assertSame( array(
			'excludes select' => false,
			'excludes type radio' => false,
			'hooks' => array(),
		), array(
			'excludes select' => str_contains( $missing['html'], '<select' ),
			'excludes type radio' => str_contains( $missing['html'], 'type="radio"' ),
			'hooks' => $missing['hooks'],
		) );
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
		$this->assertSame( array(
			'html' => 2,
			'hooks' => array( array( 'instant', 0 ), array( 'other', 0 ) ),
		), array(
			'html' => substr_count( $result['html'], 'type="radio"' ),
			'hooks' => $result['hooks'],
		) );
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
			$this->assertSame( array(
				'contains Shipping Options' => true,
				'contains GoSend Instant' => true,
				'contains Shipping Cost' => true,
				'contains Rp 18000' => true,
				'radio count' => count( $rates ),
				'package input count' => count( $rates ),
				'checked count' => 1,
				'chosen radio checked' => 1,
				'hooks' => array_map( static function ( $rate ) use ( $index ) { return array( $rate['id'], $index ); }, $rates ),
				'dropdown count' => 3 === $index ? 1 : 0,
			), array(
				'contains Shipping Options' => str_contains( $options, '<th scope="row">Shipping Options</th>' ),
				'contains GoSend Instant' => str_contains( $options, '<td data-title="Shipping Options">GoSend Instant</td>' ),
				'contains Shipping Cost' => str_contains( $cost, '<th scope="row">Shipping Cost</th>' ),
				'contains Rp 18000' => str_contains( $cost, '<td data-title="Shipping Cost">Rp 18000</td>' ),
				'radio count' => substr_count( $result['html'], 'type="radio"' ),
				'package input count' => substr_count( $result['html'], 'name="shipping_method[' . $index . ']"' ),
				'checked count' => substr_count( $result['html'], 'checked="checked"' ),
				'chosen radio checked' => preg_match( '/<input[^>]*value="kiriminaja-official_gosend_instant"[^>]*checked="checked"/', $result['html'] ),
				'hooks' => $result['hooks'],
				'dropdown count' => substr_count( $result['html'], '<select' ),
			) );
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
			$this->assertSame( array(
				'chosen service summary' => true,
				'chosen cost summary' => true,
				'native package choice' => 1,
			), array(
				'chosen service summary' => str_contains( $options, 2 === $index ? '>GoSend Instant</td>' : '>GoSend Same Day</td>' ),
				'chosen cost summary' => str_contains( $cost, 2 === $index ? '>Rp 18000</td>' : '>Rp 10000</td>' ),
				'native package choice' => preg_match( '/<input[^>]*name="shipping_method\[' . $index . '\]"[^>]*value="' . $chosen . '"[^>]*checked="checked"/', $result['html'] ),
			) );
			$html .= $result['html'];
		}
		$this->assertSame( array(
			'options summary count' => 2,
			'cost summary count' => 2,
			'package two summaries' => 2,
			'package five summaries' => 2,
		), array(
			'options summary count' => substr_count( $html, 'kiriof-classic-shipping-summary-options' ),
			'cost summary count' => substr_count( $html, 'kiriof-classic-shipping-summary-cost' ),
			'package two summaries' => substr_count( $html, 'data-kiriof-summary-package="2"' ),
			'package five summaries' => substr_count( $html, 'data-kiriof-summary-package="5"' ),
		) );
	}

	public function test_summary_escapes_chosen_label_and_strips_price_markup(): void {
		$result = $this->render( array(
			'chosen' => 'gosend',
			'price_prefix_html' => '<em>Offer &amp; &lt;safe&gt;</em> ',
			'rates' => array( array( 'id' => 'gosend', 'label' => 'GoSend <script>alert("x")</script> & Express', 'cost' => 12000 ) ),
		) );
		$options = $this->summary_row( $result['html'], 'options', 0 );
		$cost = $this->summary_row( $result['html'], 'cost', 0 );
		$this->assertSame( array(
			'escaped malicious service label' => true,
			'raw script markup' => false,
			'escaped plain cost summary' => true,
			'excludes span' => false,
			'emphasis markup' => false,
		), array(
			'escaped malicious service label' => str_contains( $options, 'GoSend &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; Express</td>' ),
			'raw script markup' => str_contains( $options, '<script>' ),
			'escaped plain cost summary' => str_contains( $cost, '<td data-title="Shipping Cost">Offer &amp; &lt;safe&gt; Rp 12000</td>' ),
			'excludes span' => str_contains( $cost, '<span' ),
			'emphasis markup' => str_contains( $cost, '<em>' ),
		) );
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
