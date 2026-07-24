<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckoutShippingPerformanceTest extends TestCase
{
    #[Test]
    public function checkout_shipping_rate_cache_uses_stable_context_key(): void
    {
        $content = file_get_contents( PLUGIN_DIR . '/inc/Controllers/CheckoutController.php' );
        $methodStart = strpos( $content, 'public function kiriof_shipping_rate_cache_invalidation' );
        $this->assertNotFalse( $methodStart, 'Shipping rate cache hook must exist' );

        $methodBody = substr( $content, $methodStart, 2600 );

        $this->assertStringContainsString(
            'kiriof_get_shipping_rate_cache_key',
            $methodBody,
            'Checkout shipping cache should be keyed by checkout context instead of always being busted'
        );
        $this->assertStringNotContainsString(
            'wp_rand',
            $methodBody,
            'update_order_review must not force a shipping-rate cache miss on every request'
        );
        $this->assertStringContainsString(
            'get_cart_hash',
            $methodBody,
            'The stable rate-cache key must include the Woo cart hash'
        );
        $this->assertStringContainsString(
            'getWhitelistExpeditionIds',
            $methodBody,
            'The stable rate-cache key must change when the courier whitelist changes'
        );
    }

    #[Test]
    public function pricing_requests_include_enabled_courier_whitelist(): void
    {
        $repository = file_get_contents( PLUGIN_DIR . '/inc/Repositories/SettingRepository.php' );
        $shippingMethod = file_get_contents( PLUGIN_DIR . '/wc/KiriminajaShippingMethod.php' );
        $ongkirService = file_get_contents( PLUGIN_DIR . '/inc/Services/CheckoutServices/OngkirPricingService.php' );
        $checkoutCalculation = file_get_contents( PLUGIN_DIR . '/inc/Services/CheckoutServices/CheckoutCalculationService.php' );

        $this->assertStringContainsString(
            'public function getWhitelistExpeditionIds',
            $repository,
            'Courier whitelist IDs should be exposed as a reusable sanitized array'
        );
        $this->assertStringContainsString(
            'private static array $setting_cache',
            $repository,
            'Repeated checkout setting reads should use a request-level cache instead of querying the same row repeatedly'
        );
        $this->assertStringContainsString(
            'private static array $whitelist_expedition_ids_cache',
            $repository,
            'Parsed courier whitelist IDs should be cached for the request'
        );
        $this->assertStringContainsString(
            "'courier' => ! empty( \$courier_filter ) ? \$courier_filter : \"\"",
            $shippingMethod,
            'calculate_shipping must send the enabled whitelist to shipping_price instead of an always-empty courier value'
        );
        $this->assertStringContainsString(
            "'courier'                   => ! empty( \$courier_filter ) ? \$courier_filter : null",
            $ongkirService,
            'Legacy pricing service must also respect the enabled courier whitelist'
        );
        $this->assertStringContainsString(
            "'courier'                   => [\$courier]",
            $checkoutCalculation,
            'Selected-courier fee calculation must keep requesting the exact courier chosen by the buyer'
        );
    }

    #[Test]
    public function shipping_price_response_is_reused_for_selected_courier_fee_calculation(): void
    {
        $cacheService = file_get_contents( PLUGIN_DIR . '/inc/Services/CheckoutServices/PricingCacheService.php' );
        $shippingMethod = file_get_contents( PLUGIN_DIR . '/wc/KiriminajaShippingMethod.php' );
        $checkoutCalculation = file_get_contents( PLUGIN_DIR . '/inc/Services/CheckoutServices/CheckoutCalculationService.php' );

        $this->assertStringContainsString(
            "private const SESSION_KEY = 'kiriof_shipping_price_cache'",
            $cacheService,
            'shipping_price responses should be cached in the Woo session for the active checkout context'
        );
        $this->assertStringContainsString(
            "private const TRANSIENT_PREFIX = 'kiriof_ship_price_'",
            $cacheService,
            'shipping_price responses should also use a short-lived persistent cache for repeated identical payloads'
        );
        $this->assertStringContainsString(
            'set_transient( self::transientKey( $key ), $entry, self::TTL_SECONDS )',
            $cacheService,
            'Cached shipping_price responses should survive beyond a single Woo session write'
        );
        $this->assertStringContainsString(
            'get_transient( self::transientKey( $key ) )',
            $cacheService,
            'Checkout should check the persistent cache before waiting for shipping_price again'
        );
        $this->assertStringContainsString(
            'couriersAreCompatible',
            $cacheService,
            'A whitelist/all-courier cache entry must be reusable by a later selected-courier calculation'
        );
        $this->assertStringContainsString(
            'PricingCacheService::put( $payload, $kiriofPricing[\'data\'] )',
            $shippingMethod,
            'calculate_shipping should cache the full/whitelist shipping_price response'
        );
        $this->assertStringContainsString(
            'PricingCacheService::get( $pricingPayload )',
            $checkoutCalculation,
            'Selected-courier fee calculation should check the cached shipping_price response before calling the API'
        );
        $cacheLookupPosition = strpos( $checkoutCalculation, 'PricingCacheService::get( $pricingPayload )' );
        $apiCallPosition = strpos( $checkoutCalculation, 'KiriminajaApiRepository())->getPricing($pricingPayload)' );
        $this->assertNotFalse( $cacheLookupPosition );
        $this->assertNotFalse( $apiCallPosition );
        $this->assertLessThan(
            $apiCallPosition,
            $cacheLookupPosition,
            'Fee calculation must attempt cache reuse before making a second shipping_price API call'
        );
    }

    #[Test]
    public function plugin_ajax_fee_cache_context_matches_checkout_fee_context(): void
    {
        $ajax = file_get_contents( PLUGIN_DIR . '/inc/Controllers/GeneralAjaxController.php' );

        $this->assertStringContainsString(
            'kiriof_get_fee_cache_context',
            $ajax,
            'AJAX fee calculation should store the same cache context consumed by checkout fee rendering'
        );
        $this->assertStringContainsString(
            "'coupon_codes'    => \$discount_context['coupon_codes']",
            $ajax,
            'AJAX fee cache context must include coupon codes'
        );
        $this->assertStringContainsString(
            "'discount_total'  => \$discount_context['discount_total']",
            $ajax,
            'AJAX fee cache context must include discount totals'
        );
        $this->assertStringContainsString(
            "'discount_tax'    => \$discount_context['discount_tax']",
            $ajax,
            'AJAX fee cache context must include discount tax'
        );
    }

    #[Test]
    public function plugin_ajax_handlers_defer_totals_recalculation_to_woocommerce_update_checkout(): void
    {
        $ajax = file_get_contents( PLUGIN_DIR . '/inc/Controllers/GeneralAjaxController.php' );
        $destinationStart = strpos( $ajax, 'function kiriof_getDestinationArea' );
        $feeStart = strpos( $ajax, 'public function kiriof_getDataAfterUpdateCheckout' );

        $this->assertNotFalse( $destinationStart, 'Destination AJAX handler must exist' );
        $this->assertNotFalse( $feeStart, 'Fee AJAX handler must exist' );

        $destinationBody = substr( $ajax, $destinationStart, $feeStart - $destinationStart );
        $feeBody = substr( $ajax, $feeStart, strpos( $ajax, 'private function kiriof_get_fee_cache_context' ) - $feeStart );

        $this->assertStringNotContainsString(
            'calculate_totals',
            $destinationBody,
            'Destination AJAX should only save session state; the following update_checkout request recalculates totals'
        );
        $this->assertStringNotContainsString(
            'calculate_totals',
            $feeBody,
            'Fee AJAX should only cache calculated fees; the following update_checkout request renders them'
        );
    }

    #[Test]
    public function pricing_api_uses_a_short_filterable_timeout(): void
    {
        $repository = file_get_contents( PLUGIN_DIR . '/inc/Repositories/KiriminajaApiRepository.php' );
        $api = file_get_contents( PLUGIN_DIR . '/inc/Base/KiriminAjaApi.php' );

        $this->assertStringContainsString("apply_filters( 'kiriof_pricing_api_timeout', 8 )", $repository);
        $this->assertStringContainsString("'httpversion' => '1.1'", $repository);
        $this->assertStringContainsString('$request_args = array()', $api);
        $this->assertStringContainsString('array_merge( $request_args', $api);
    }

    #[Test]
    public function block_district_persistence_uses_ajax_only_as_fallback(): void
    {
        $script = file_get_contents( PLUGIN_DIR . '/assets/wp/js/form-billing-address.js' );
        $start = strpos( $script, 'function kiriofPersistBlockDistrictSelection' );
        $end = strpos( $script, 'var kiriofLastDistrictResults', $start );
        $body = substr( $script, $start, $end - $start );

        $this->assertStringContainsString('function kiriofPersistBlockDistrictFallback()', $body);
        $this->assertStringContainsString('result.then(function()', $body);
        $this->assertStringContainsString('kiriofAfterBlockDistrictPersist();', $body);
        $this->assertStringContainsString('kiriofPersistBlockDistrictFallback();', $body);
        $this->assertLessThan(
            strpos( $body, 'kiriofPersistBlockDistrictFallback();' ),
            strpos( $body, 'kiriofAfterBlockDistrictPersist();' ),
            'Successful extensionCartUpdate should finish without the fallback AJAX and raw Store API update chain'
        );
    }
}
