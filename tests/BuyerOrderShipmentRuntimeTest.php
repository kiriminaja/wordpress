<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BuyerOrderShipmentRuntimeTest extends TestCase {
	private function render( array $input ): string {
		$output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/buyer-order-shipment-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) );
		return json_decode( $output, true, 512, JSON_THROW_ON_ERROR )['html'];
	}

	public function test_saved_instant_and_express_courier_titles_render_before_booking(): void {
		foreach ( array( 'kiriminaja-instant', 'kiriminaja-instant:3:gosend:instant', 'kiriminaja-official', 'kiriminaja-official_jne_REG', 'kiriminaja-official:jne_REG' ) as $method ) {
			$html = $this->render( array( 'shipping' => array( array( 'method' => $method, 'name' => 'Chosen courier Instant' ) ) ) );
			$this->assertStringContainsString( 'Chosen courier Instant', $html );
			$this->assertStringContainsString( 'Shipping Method', $html );
			if ( 0 === strpos( $method, 'kiriminaja-instant' ) ) {
				$this->assertStringNotContainsString( 'Track Shipment', $html );
				$this->assertStringNotContainsString( '/tracking', $html );
			} else {
				$this->assertStringContainsString( 'Track Shipment', $html );
				$this->assertStringContainsString( '/tracking?order_id=504', $html );
			}
		}
	}

	public function test_generic_saved_labels_use_only_public_courier_service_metadata(): void {
		foreach ( array( 'kiriminaja-instant' => array( 'kiriof_instant_courier' => 'gosend', 'kiriof_instant_service' => 'Instant' ), 'kiriminaja-official' => array( 'kiriof_rate_service' => 'jne', 'kiriof_rate_service_type' => 'REG' ) ) as $method => $meta ) {
			$html = $this->render( array( 'shipping' => array( array( 'method' => $method, 'name' => 'KiriminAja', 'meta' => $meta + array( 'kiriof_instant_quote_token' => 'secret-token', 'qr_content' => 'secret-qr' ) ) ) ) );
			$this->assertStringContainsString( 'kiriminaja-instant' === $method ? 'GOSEND Instant' : 'JNE REG', $html );
			$this->assertStringNotContainsString( 'secret-', $html );
		}
	}

	public function test_all_saved_plugin_items_are_included_without_unrelated_methods_or_duplicates(): void {
		$html = $this->render( array( 'shipping' => array(
			array( 'method' => 'flat_rate', 'name' => 'Other shipping' ),
			array( 'method' => 'kiriminaja-instant', 'name' => 'GoSend Instant' ),
			array( 'method' => 'kiriminaja-official', 'name' => 'JNE REG' ),
			array( 'method' => 'kiriminaja-instant', 'name' => 'GoSend Instant' ),
		) ) );
		$this->assertStringContainsString( 'GoSend Instant, JNE REG', $html );
		$this->assertSame( 1, substr_count( $html, 'GoSend Instant' ) );
		$this->assertStringNotContainsString( 'Other shipping', $html );
	}

	public function test_unrelated_empty_and_virtual_orders_do_not_render_stale_shipment_information(): void {
		foreach ( array(
			array(),
			array( 'shipping' => array( array( 'method' => 'flat_rate', 'name' => 'Other' ) ) ),
			array( 'shipping' => array( array( 'method' => 'kiriminaja-instant-malicious', 'name' => 'Other' ) ) ),
			array( 'physical' => false, 'shipping' => array( array( 'method' => 'kiriminaja-instant', 'name' => 'GoSend Instant' ) ) ),
		) as $input ) {
			$this->assertSame( '', $this->render( $input ) );
		}
	}

	public function test_saved_courier_title_is_escaped_not_rendered_as_html(): void {
		$html = $this->render( array( 'shipping' => array( array( 'method' => 'kiriminaja-instant', 'name' => '<script>bad()</script> GoSend & Instant' ) ) ) );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;bad()&lt;/script&gt; GoSend &amp; Instant', $html );
	}
}
