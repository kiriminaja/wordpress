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
}
