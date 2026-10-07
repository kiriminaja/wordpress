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
}
