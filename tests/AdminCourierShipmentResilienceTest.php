<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AdminCourierShipmentResilienceTest extends TestCase
{
    #[Test]
    public function courier_service_preserves_last_successful_list_during_api_failures(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/KiriminajaApiService.php');

        $this->assertStringContainsString('KIRIOF_COURIERS_LAST_SUCCESS_CACHE_KEY', $content);
        $this->assertStringContainsString(
            'get_transient( self::KIRIOF_COURIERS_LAST_SUCCESS_CACHE_KEY )',
            $content
        );
        $this->assertStringContainsString(
            'set_transient( self::KIRIOF_COURIERS_LAST_SUCCESS_CACHE_KEY, $data, WEEK_IN_SECONDS )',
            $content
        );
        $this->assertStringContainsString("'courier_cache_fallback'", $content);
    }

    #[Test]
    public function whitelist_save_does_not_delete_remote_courier_catalog_cache(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/SettingController.php');
        $start = strpos($content, 'function storeCourierWhitelist()');
        $end = strpos($content, 'function storeInsuranceData()', $start);
        $method = substr($content, $start, $end - $start);

        $this->assertStringNotContainsString(
            'invalidateCouriersCache',
            $method,
            'Saving a local whitelist must not force a remote API cache miss'
        );
    }

    #[Test]
    public function manual_courier_refresh_keeps_fallback_data_until_live_fetch_succeeds(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php');

        $this->assertStringContainsString('invalidateCouriersCache( false )', $content);
        $this->assertStringContainsString("'courier_cache_fallback' === \$fresh->customCode", $content);
        $this->assertStringContainsString('The last successful courier list remains available.', $content);
    }

    #[Test]
    public function courier_screen_displays_ajax_errors_instead_of_an_empty_state(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/setting/setuped/section-couriers.php');

        $this->assertStringContainsString('function renderError(message)', $content);
        $this->assertStringContainsString('.fail(function(request)', $content);
        $this->assertStringContainsString('Could not load couriers. Reload this page and try again.', $content);
    }

    #[Test]
    public function transaction_list_bounds_page_size_and_requeries_the_last_valid_page(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/transaction-process/index.php');

        $this->assertStringContainsString('min( $kiriof_per_page, 100 )', $content);
        $this->assertStringContainsString('min( $kiriof_per_page_get, 100 )', $content);
        $this->assertStringContainsString(
            '$kiriof_current_page > $kiriof_total_pages && $kiriof_total_pages > 0',
            $content
        );
        $this->assertStringContainsString(
            '$this->pageQuery( $kiriof_per_page, $kiriof_current_page )',
            $content
        );
    }

    #[Test]
    public function payment_list_clamps_page_and_whitelists_status_before_querying(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/request-pickup/index.php');

        $this->assertStringContainsString("in_array( \$status, array( 'unpaid', 'paid' ), true )", $content);
        $this->assertStringContainsString('$page > $total_pages && $total_pages > 0', $content);
        $this->assertStringContainsString('$offset = ( $page - 1 ) * $items_per_page;', $content);
        $this->assertLessThan(
            strpos($content, '/** Main Query*/'),
            strpos($content, '$total_pages = (int) ceil'),
            'Payment total and valid page must be resolved before the paginated query runs'
        );
    }
}
