<?php

use PHPUnit\Framework\TestCase;

final class ClassicCartQuantityRuntimeTest extends TestCase {
	private function render( array $scenario = array() ): array {
		$output = array();
		$status = 0;
		exec( kiriof_test_fixture_php_shell( array( __DIR__ . '/fixtures/classic-cart-quantity-runtime.php', json_encode( $scenario, JSON_THROW_ON_ERROR ) ) ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	private function quantity_input( string $html ): DOMElement {
		$document = new DOMDocument();
		$document->loadHTML( $html );
		$xpath = new DOMXPath( $document );
		$inputs = $xpath->query( '//td[@class="product-quantity"]//input[@name="cart[fixture-shirt][qty]"]' );
		$this->assertSame( 1, $inputs->length, 'The quantity input must survive rendering inside its cart cell.' );
		return $inputs->item( 0 );
	}

	public function test_editable_quantity_preserves_native_name_value_and_limits(): void {
		$result = $this->render();
		$input = $this->quantity_input( $result['html'] );
		foreach ( array( 'type' => 'number', 'value' => '2', 'min' => '0', 'max' => '8', 'step' => '1' ) as $attribute => $value ) {
			$this->assertSame( $value, $input->getAttribute( $attribute ) );
		}
		$this->assertFalse( $input->hasAttribute( 'disabled' ) );
		$this->assertFalse( $input->hasAttribute( 'readonly' ) );
		$this->assertSame( array( array( 'args' => array( 'input_name' => 'cart[fixture-shirt][qty]', 'input_value' => 2, 'max_value' => 8, 'min_value' => 0, 'product_name' => 'Fixture shirt' ), 'echo' => false ) ), $result['calls'] );
		$this->assertStringContainsString( 'name="update_cart"', $result['html'] );
	}

	public function test_unlimited_stock_keeps_quantity_editable(): void {
		$result = $this->render( array( 'max_quantity' => -1 ) );
		$input = $this->quantity_input( $result['html'] );
		$this->assertSame( 'number', $input->getAttribute( 'type' ) );
		$this->assertFalse( $input->hasAttribute( 'max' ) );
		$this->assertSame( -1, $result['calls'][0]['args']['max_value'] );
	}

	public function test_sold_individually_preserves_hidden_submission_with_fixed_limits(): void {
		$result = $this->render( array( 'sold_individually' => true, 'quantity' => 1 ) );
		$input = $this->quantity_input( $result['html'] );
		$this->assertSame( 'hidden', $input->getAttribute( 'type' ) );
		$this->assertSame( '1', $input->getAttribute( 'value' ) );
		$this->assertSame( 1, $result['calls'][0]['args']['min_value'] );
		$this->assertSame( 1, $result['calls'][0]['args']['max_value'] );
	}

	public function test_quantity_filter_preserves_theme_controls_and_receives_cart_context(): void {
		$result = $this->render( array( 'filtered' => true ) );
		$this->quantity_input( $result['html'] );
		$this->assertSame( array( array( 'key' => 'fixture-shirt', 'quantity' => 2 ) ), $result['filters'] );
		foreach ( array( 'class="theme-quantity"', 'class="minus"', 'class="plus"', 'name="theme_quantity_token"' ) as $markup ) {
			$this->assertStringContainsString( $markup, $result['html'] );
		}
	}
}
