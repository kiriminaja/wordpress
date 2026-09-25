<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LegacyAdminCssAssetsTest extends TestCase
{
    #[Test]
    public function badge_css_remains_for_non_svelte_badge_consumers(): void
    {
        $badges = file_get_contents( PLUGIN_DIR . '/assets/admin/css/kj-badge.css' );
        $helper = file_get_contents( PLUGIN_DIR . '/inc/Base/Helper.php' );
        $coupon = file_get_contents( PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php' );
        $cart = file_get_contents( PLUGIN_DIR . '/templates/woocommerce/cart/cart-shipping.php' );

        $this->assertStringContainsString( '.kiriof-badge', $badges );
        $this->assertStringContainsString( 'kiriof-badge kiriof-badge--', $helper );
        $this->assertStringContainsString( 'kiriof-combination-badge', $coupon );
        $this->assertStringContainsString( 'kiriof-shipping-rate-badge', $cart );
    }

    #[Test]
    public function admin_compatibility_css_remains_but_bootstrap_grid_is_retired(): void
    {
        $enqueue = file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' );
        $admin_css = file_get_contents( PLUGIN_DIR . '/assets/admin/css/kj-admin-style.css' );

        $this->assertFileExists( PLUGIN_DIR . '/assets/admin/css/kj-admin-style.css' );
        $this->assertStringContainsString( '.wc-backbone-modal.kiriof-change-origin-modal', $admin_css );
        $this->assertStringNotContainsString( '.kiriof-payment-summary-row', $admin_css );
        $this->assertFileDoesNotExist( PLUGIN_DIR . '/assets/admin/css/bootstrap-grid.css' );
        $this->assertStringNotContainsString( 'kiriof-grid-style', $enqueue );
        $this->assertFileDoesNotExist( PLUGIN_DIR . '/templates/request-pickup/view/modal-request-pickup.php' );
    }
}
