<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TrackingShortcodeLookupTest extends TestCase
{
    #[Test]
    public function tracking_lookup_accepts_awb_kiriminaja_order_id_and_wc_order_id(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Repositories/TransactionRepository.php');
        $start = strpos($content, 'function getTransactionByAWBforTracking');
        $this->assertNotFalse($start, 'Tracking transaction lookup method must exist');
        $methodBody = substr($content, $start, 1600);

        $this->assertStringContainsString('`awb` = %s', $methodBody, 'Tracking lookup should accept AWB/resi input');
        $this->assertStringContainsString("REPLACE(REPLACE(`awb`, '-', ''), ' ', '') = %s", $methodBody, 'Tracking lookup should accept AWB input with or without dashes/spaces');
        $this->assertStringContainsString('`order_id` = %s', $methodBody, 'Tracking lookup should accept KiriminAja order_id input');
        $this->assertStringContainsString("REPLACE(REPLACE(`order_id`, '-', ''), ' ', '') = %s", $methodBody, 'Tracking lookup should accept KiriminAja order_id input with or without dashes/spaces');
        $this->assertStringContainsString('`wp_wc_order_stat_order_id` = %d', $methodBody, 'Tracking lookup should accept WooCommerce order ID/order number input');
    }

    #[Test]
    public function tracking_service_uses_resolved_wc_order_id_for_fallback_order_details(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/KiriminAjaTrackingService.php');

        $this->assertStringContainsString(
            '$this->getDetailWcOrder($transactionRepo->wp_wc_order_stat_order_id)',
            $content,
            'Fallback WooCommerce details should use the resolved WC order ID, not the raw AWB/order_id input'
        );
    }

    #[Test]
    public function tracking_shortcode_uses_enqueued_script_for_frontend_behavior(): void
    {
        $enqueue = file_get_contents(PLUGIN_DIR . '/inc/Base/Enqueue.php');
        $template = file_get_contents(PLUGIN_DIR . '/templates/front/tracking.php');
        $scriptPath = PLUGIN_DIR . '/assets/wp/js/kj-tracking.js';
        $script = file_get_contents($scriptPath);

        $this->assertFileExists($scriptPath, 'Tracking page behavior should live in a real frontend script asset');
        $this->assertStringContainsString('assets/wp/js/kj-tracking.js', $enqueue, 'Tracking script should be enqueued before footer scripts print');
        $this->assertStringNotContainsString("wp_add_inline_script( 'kiriof-script'", $template, 'Tracking script should not be attached from shortcode render time');
        $this->assertStringContainsString('window.trackOrder = trackOrder', $script, 'Legacy inline onclick should still resolve trackOrder globally');
        $this->assertStringContainsString("urlParams.get('order_id')", $script, 'Tracking script should prefill from the order_id query parameter');
    }

    #[Test]
    public function tracking_page_is_configurable_from_woocommerce_advanced_settings(): void
    {
        $controller = file_get_contents(PLUGIN_DIR . '/inc/Controllers/SettingController.php');

        $this->assertStringContainsString(
            "add_filter( 'woocommerce_get_settings_advanced'",
            $controller,
            'Tracking page selector must be injected into WooCommerce Advanced settings'
        );

        $this->assertStringContainsString(
            "'id'       => 'kiriof_tracking_page_id'",
            $controller,
            'Tracking page selector must save to the KiriminAja tracking page option'
        );

        $this->assertStringContainsString(
            "filter_input( INPUT_GET, 'section'",
            $controller,
            'Tracking page selector must inspect the WooCommerce Advanced settings section'
        );

        $this->assertStringContainsString(
            'if ( ! empty( $section ) )',
            $controller,
            'Tracking page selector must only render in the root Advanced settings section'
        );

        $this->assertStringContainsString(
            "'type'     => 'kiriof_tracking_page_select'",
            $controller,
            'Tracking page selector must use a custom picker so only shortcode pages are selectable'
        );

        $this->assertStringContainsString(
            "add_action( 'woocommerce_admin_field_kiriof_tracking_page_select'",
            $controller,
            'Tracking page selector must render a custom WooCommerce settings field'
        );

        $this->assertStringContainsString(
            "add_action( 'woocommerce_update_options_advanced'",
            $controller,
            'Tracking page selector must save through the WooCommerce Advanced settings update hook'
        );

        $this->assertStringContainsString(
            'getTrackingShortcodePages',
            $controller,
            'Tracking page selector must query only pages that contain a tracking shortcode'
        );

        $this->assertStringContainsString(
            'pageHasTrackingShortcode',
            $controller,
            'Tracking page save must reject pages that do not contain a tracking shortcode'
        );

        $this->assertStringContainsString(
            'Page contents: [kiriminaja-tracking-front-page]',
            $controller,
            'Tracking page selector must explain the required shortcode'
        );
    }

    #[Test]
    public function activation_reuses_existing_tracking_shortcode_page(): void
    {
        $adminPost = file_get_contents(PLUGIN_DIR . '/inc/Pages/AdminPost.php');

        $this->assertStringContainsString(
            'getTrackingPageByShortcode',
            $adminPost,
            'Activation must search for an existing tracking shortcode page before creating a new one'
        );

        $this->assertStringContainsString(
            "post_content LIKE '%[kiriminaja-tracking-front-page%'",
            $adminPost,
            'Activation must detect the current KiriminAja tracking shortcode'
        );

        $this->assertStringContainsString(
            "post_content LIKE '%[wp-tracking-front-page%'",
            $adminPost,
            'Activation must detect the legacy tracking shortcode'
        );

        $this->assertStringContainsString(
            "update_option( 'kiriof_tracking_page_id'",
            $adminPost,
            'Activation must store the selected or created tracking page ID'
        );
    }

    #[Test]
    public function tracking_links_use_configured_tracking_page(): void
    {
        $plugin = file_get_contents(PLUGIN_DIR . '/kiriminaja.php');
        $editOrder = file_get_contents(PLUGIN_DIR . '/inc/Controllers/EditOrderController.php');
        $checkout = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $enqueue = file_get_contents(PLUGIN_DIR . '/inc/Base/Enqueue.php');

        $this->assertStringContainsString(
            'function kiriof_get_tracking_page_url',
            $plugin,
            'Plugin must expose a helper for tracking URLs'
        );

        $this->assertStringContainsString(
            "get_option( 'kiriof_tracking_page_id'",
            $plugin,
            'Tracking URL helper must read the configured tracking page option'
        );

        $this->assertStringContainsString(
            'function kiriof_find_tracking_shortcode_page_id',
            $plugin,
            'Tracking URL helper must fall back to an existing page containing the tracking shortcode'
        );

        $this->assertStringContainsString(
            'kiriof_get_tracking_page_url',
            $editOrder,
            'Order edit tracking links must use the configured tracking page URL'
        );

        $this->assertStringContainsString(
            'kiriof_get_tracking_page_url',
            $checkout,
            'Checkout/order received tracking links must use the configured tracking page URL'
        );

        $this->assertStringContainsString(
            'kiriof_get_tracking_page_id',
            $enqueue,
            'Frontend tracking assets must load on the configured tracking page'
        );

        $this->assertStringNotContainsString(
            '/tracking?order_id',
            $editOrder . $checkout,
            'Order tracking links must not hardcode /tracking; they must use the configured tracking page option'
        );
    }
}
