<?php
use PHPUnit\Framework\TestCase;

final class ClassicInstantStatusRuntimeTest extends TestCase {
	private static ?array $result = null;

	private function fixture(): array {
		if ( null === self::$result ) {
			$output = array();
			exec( kiriof_test_fixture_php_shell( array( __DIR__ . '/fixtures/classic-instant-status-runtime.php' ) ) . ' 2>&1', $output, $status );
			$this->assertSame( 0, $status, implode( "\n", $output ) );
			self::$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
		}
		return self::$result;
	}

	public function test_registers_last_review_fragment_filter_and_preserves_other_fragments(): void {
		$result = $this->fixture();
		$this->assertTrue( $result['registered'] );
		$this->assertSame( array( array( 'woocommerce_update_order_review_fragments', 'classic_fragments', PHP_INT_MAX ) ), $result['hooks'] );
		foreach ( $result['states'] as $fragments ) {
			$this->assertSame( $result['existing'], array_diff_key( $fragments, array( '.kiriof-classic-instant-status' => true ) ) );
			$this->assertCount( count( $result['existing'] ) + 1, $fragments );
			$this->assertStringNotContainsString( 'PRIVATE', implode( '', $fragments ) );
		}
	}

	public function test_inactive_unknown_disabled_stale_and_available_rates_clear_the_message(): void {
		$result = $this->fixture();
		$empty = '<div class="kiriof-classic-instant-status" role="status" aria-live="polite" data-code="" hidden></div>';
		$this->assertSame( $empty, $result['states']['inactive']['.kiriof-classic-instant-status'] );
		$this->assertSame( $empty, $result['states']['PRIVATE\"><script>alert(1)</script>']['.kiriof-classic-instant-status'] );
		foreach ( array( 'native_available', 'disabled', 'stale' ) as $state ) {
			$this->assertSame( $empty, $result[ $state ]['.kiriof-classic-instant-status'] );
		}
	}

	public function test_cod_and_non_cod_failures_use_only_fixed_safe_messages(): void {
		$result = $this->fixture();
		$messages = array(
			'destination_required' => 'Choose a shipping address and map pin for Instant delivery.',
			'recipient_invalid' => 'Complete the shipping recipient name and phone number for Instant delivery.',
			'cod_not_supported' => 'Instant delivery does not support COD.',
			'cod_unsupported' => 'Instant delivery does not support COD.',
			'quote_failed' => 'Instant delivery rates are temporarily unavailable. Please try again.',
			'outside_instant_radius' => 'Instant delivery is available only within 40 km of the pickup origin. You can use Express delivery for this address.',
			'origin_invalid' => 'The store pickup address is not ready for Instant delivery.',
			'no_rates' => 'No Instant delivery rates are available for this address and cart.',
			'services_disabled' => 'No Instant courier services are enabled.',
			'quote_unavailable' => 'Instant delivery rates are temporarily unavailable. Please try again.',
			'available' => 'Instant prices have expired. Refresh your shipping quote before placing the order.',
		);
		foreach ( $messages as $code => $message ) {
			$this->assertSame( '<div class="kiriof-classic-instant-status" role="status" aria-live="polite" data-code="' . $code . '">' . $message . '</div>', $result['states'][ $code ]['.kiriof-classic-instant-status'] );
		}
		$this->assertStringContainsString( '&lt;script&gt;&quot; &amp; unsafe&lt;/script&gt;', $result['escaped']['.kiriof-classic-instant-status'] );
		$this->assertStringNotContainsString( '<script>', $result['escaped']['.kiriof-classic-instant-status'] );
	}
}
