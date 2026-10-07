<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ClassicShippingOptionsAssetsTest extends TestCase {
    public function test_shipping_section_module_is_classic_only_and_uses_existing_native_nodes(): void {
        $enqueue = file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' );
        $start = strpos( $enqueue, 'if ( $this->isClassicCheckoutPage() ) {' );
        $end = strpos( $enqueue, "wp_register_script(\n            'kiriof-form-billing-address'", $start );
        $this->assertStringContainsString( "'kiriof-classic-shipping-options'", substr( $enqueue, $start, $end - $start ) );
        $source = file_get_contents( PLUGIN_DIR . '/assets/buyer/js/checkout/shipping-options.js' );
        $this->assertStringContainsString( "!document.querySelector('.wc-block-checkout')", $source );
        $this->assertStringContainsString( "table.closest('#order_review')", $source );
        $this->assertStringContainsString( 'while (cell.firstChild) { content.append(cell.firstChild); }', $source );
        $this->assertStringContainsString( 'insuranceArea.append(insurance)', $source );
        $this->assertStringNotContainsString( 'cloneNode', $source );
        $this->assertStringNotContainsString( 'fetch(', $source );
        $this->assertStringContainsString( 'packages.delete(key)', $source );
        $this->assertStringContainsString( 'observer.disconnect()', $source );
        $this->assertStringContainsString( "'shippingOptions' => __( 'Shipping options'", file_get_contents( PLUGIN_DIR . '/templates/front/partials/form-billing-address-config.php' ) );
    }

    public function test_checkout_summary_rows_are_server_owned_readonly_and_revealed_only_by_relocation(): void {
        $template = file_get_contents( PLUGIN_DIR . '/templates/woocommerce/cart/cart-shipping.php' );
        $source   = file_get_contents( PLUGIN_DIR . '/assets/buyer/js/checkout/shipping-options.js' );
        $css      = file_get_contents( PLUGIN_DIR . '/assets/buyer/css/kiriof-classic-choices.css' );
        $summary  = substr( $template, strpos( $template, '// Read-only checkout totals' ) );

        $this->assertStringContainsString( '! $kiriof_is_cart_totals_shipping && ! empty( $available_methods )', $summary );
        $this->assertStringContainsString( '$kiriof_summary_rate->id === $chosen_method', $summary );
        $this->assertStringContainsString( 'RateChoicePresentation::forRate', $summary );
        $this->assertStringContainsString( "esc_html( \$kiriof_summary_presentation['label'] )", $summary );
        $this->assertStringContainsString( "esc_html( \$kiriof_summary_presentation['price'] )", $summary );
        foreach ( array( 'options', 'cost' ) as $kind ) {
            $this->assertMatchesRegularExpression( '/<tr class="kiriof-classic-shipping-summary kiriof-classic-shipping-summary-' . $kind . '" data-kiriof-summary-package="[^\n]+" hidden>/', $summary );
        }
        $this->assertDoesNotMatchRegularExpression( '/<(?:input|select|button)\b/i', $summary );
        $this->assertStringContainsString( "summary.getAttribute('data-kiriof-summary-package') === key && summary.hidden", $source );
        $this->assertStringContainsString( 'summary.hidden = false', $source );
        $this->assertStringContainsString( 'tr.kiriof-classic-shipping-summary[hidden] { display: none !important; }', $css );
        $this->assertStringContainsString( 'tr.kiriof-classic-shipping-summary:not([hidden]) { display: table-row !important; }', $css );
    }
}
