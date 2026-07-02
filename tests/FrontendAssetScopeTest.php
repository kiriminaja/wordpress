<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FrontendAssetScopeTest extends TestCase
{
    #[Test]
    public function frontend_assets_are_scoped_to_cart_checkout_tracking_and_filter_override(): void
    {
        $enqueue = file_get_contents(PLUGIN_DIR . '/inc/Base/Enqueue.php');
        $start = strpos($enqueue, 'private function shouldEnqueueFront()');

        $this->assertNotFalse($start, 'Frontend enqueue guard must exist');

        $method = substr($enqueue, $start, 1800);

        $this->assertStringContainsString("function_exists( 'is_cart' ) && is_cart()", $method);
        $this->assertStringContainsString("function_exists( 'is_checkout' ) && is_checkout()", $method);
        $this->assertStringContainsString('$this->isTrackingPage()', $method);
        $this->assertStringContainsString("apply_filters( 'kiriof_enqueue_frontend_assets', false )", $method);
        $this->assertStringNotContainsString('is_woocommerce()', $method);
        $this->assertStringNotContainsString('is_account_page()', $method);
    }
}
