<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckoutLayoutRecoveryRuntimeTest extends TestCase {
    private function runFixture( string $mode = '' ): array {
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/checkout-layout-recovery-runtime.php' ) . ' ' . escapeshellarg( $mode ), $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
    }

    private function assertRecoveryContract( array $data ): void {
        // The legacy expression matches a child, then str_replace expands BOTH
        // identical shipping placeholders, injecting full checkouts into a layout.
        $this->assertSame( 2, substr_count( $data['legacyExpanded'], 'data-default-checkout="true"' ) );
        $this->assertNotSame( $data['legacyLayout'], $data['legacyExpanded'] );
        $this->assertSame( 0, $data['normalizedMatches'] );
        $this->assertSame( 0, $data['shellMatches'] );
        $this->assertSame( $data['normalizedLayout'], $data['normalizedExpanded'] );
        $this->assertSame( 0, substr_count( $data['normalizedExpanded'], 'data-default-checkout=' ) );
        $this->assertSame( 2, substr_count( $data['normalizedLayout'], 'data-block-name="woocommerce/checkout-shipping-address-block"' ) );
        $this->assertSame( 1, substr_count( $data['normalizedLayout'], 'data-block-name="woocommerce/checkout-order-summary-block"' ) );
        $this->assertSame( '<div data-default-checkout="true">FULL DEFAULT CHECKOUT</div>', $data['rootExpanded'], 'Genuine empty checkout roots must remain eligible for Woo recovery' );

        $cases = $data['cases'];
        foreach ( $cases as $case ) {
            $this->assertSame( $case['output'], $case['twice'], 'Identity annotation must be idempotent' );
        }
        foreach ( array( 'existing', 'emptyIdentity', 'booleanIdentity', 'root', 'nonWoo', 'missingName', 'invalidName', 'unknown', 'noDiv', 'substringClass', 'district', 'map' ) as $key ) {
            $this->assertSame( $cases[$key]['input'], $cases[$key]['output'], $key . ' must be byte-for-byte unchanged' );
        }
        foreach ( array( 'shipping' => 'shipping-address', 'summary' => 'order-summary' ) as $key => $suffix ) {
            $output = $cases[$key]['output'];
            $this->assertStringContainsString( 'data-block-name="woocommerce/checkout-' . $suffix . '-block"', $output );
            $this->assertSame( 1, substr_count( $output, '<div ' ) );
            $this->assertSame( 1, substr_count( $output, '</div>' ) );
        }
        foreach ( array( 'attributes', 'singleQuoted', 'unrelated' ) as $key ) {
            $this->assertSame( $cases[$key]['input'], preg_replace( '/\sdata-block-name="woocommerce\/checkout-shipping-address-block"/', '', $cases[$key]['output'] ), 'Only the missing identity may be added: ' . $key );
            $this->assertSame( 1, substr_count( $cases[$key]['output'], 'data-block-name=' ) );
        }
        $this->assertStringContainsString( '<div class="wp-block-woocommerce-checkout-order-summary-block"></div>', $cases['unrelated']['output'], 'Unrelated checkout wrappers must not be annotated with another child identity' );
    }

    #[Test]
    public function legacy_expansion_is_prevented_without_rebuilding_the_saved_layout(): void {
        $data = $this->runFixture();
        $this->assertSame( 'limited test double', $data['processor'] );
        $this->assertRecoveryContract( $data );
    }

    #[Test]
    public function actual_wordpress_html_api_preserves_attributes_and_prevents_expansion(): void {
        $data = $this->runFixture( 'wordpress' );
        if ( ! $data['available'] ) {
            $this->markTestSkipped( 'Set KIRIOF_WP_HTML_API_DIR to an actual WordPress wp-includes/html-api directory to run the real tokenizer regression.' );
        }
        $this->assertSame( 'WordPress HTML API', $data['processor'] );
        $this->assertRecoveryContract( $data );
    }

    #[Test]
    public function official_woocommerce_renderer_does_not_multiply_identified_children(): void {
        $data = $this->runFixture( 'real-woocommerce' );
        if ( ! $data['available'] ) {
            $this->markTestSkipped( 'Set KIRIOF_WOO_CHECKOUT_RENDERER to official WooCommerce Checkout.php and KIRIOF_WP_HTML_API_DIR to the WordPress HTML API directory.' );
        }
        $this->assertSame( 'WordPress HTML API', $data['processor'] );
        $this->assertSame( 'Automattic\\WooCommerce\\Blocks\\BlockTypes\\Checkout', $data['renderer'] );
        $this->assertSame( realpath( getenv( 'KIRIOF_WOO_CHECKOUT_RENDERER' ) ), realpath( $data['rendererFile'] ) );
        $this->assertSame( hash_file( 'sha256', getenv( 'KIRIOF_WOO_CHECKOUT_RENDERER' ) ), $data['rendererSha256'] );
        $single = array( 'root' => 1, 'contact' => 1, 'shipping' => 1, 'actions' => 1 );
        $this->assertSame( $single, $data['inputCounts'] );
        $this->assertNotSame( $data['legacyLayout'], $data['legacyRendered'] );
        foreach ( array( 'contact', 'shipping', 'actions' ) as $key ) {
            $this->assertGreaterThan( 1, $data['legacyCounts'][$key], 'Actual Woo legacy recovery multiplies ' . $key );
        }
        $this->assertSame( 1, $data['legacyCounts']['root'] );
        $this->assertSame( $data['normalizedLayout'], $data['normalizedRendered'], 'Current layout must survive actual Woo migrations byte-for-byte' );
        $this->assertSame( $single, $data['normalizedCounts'] );
        $this->assertSame( $data['shell'], $data['shellRendered'], 'Extension fallback shells must not trigger Woo recovery' );
        $this->assertSame( array( 'root' => 0, 'contact' => 0, 'shipping' => 0, 'actions' => 0 ), $data['shellCounts'] );
        $this->assertSame( $single, $data['rootCounts'], 'A genuine empty root still recovers one checkout' );
    }

    #[Test]
    public function older_wordpress_without_html_processor_is_a_noop(): void {
        $data = $this->runFixture( 'unavailable' );
        $this->assertSame( $data['input'], $data['output'] );
    }
}
