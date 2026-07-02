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
            'private const STALE_TTL_SECONDS = 900',
            $cacheService,
            'Checkout should have a bounded stale-cache window for slow pricing API fallback'
        );
        $this->assertStringContainsString(
            'bool $allow_stale = false',
            $cacheService,
            'Pricing cache reads should opt into stale fallback explicitly'
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
            "empty( \$kiriofPricing['status'] ) || empty( \$kiriofPricing['data'] )",
            $shippingMethod,
            'calculate_shipping should exit cleanly when pricing is unavailable instead of breaking shipment option rendering'
        );
        $this->assertStringContainsString(
            'PricingCacheService::get( $pricingPayload )',
            $checkoutCalculation,
            'Selected-courier fee calculation should check the cached shipping_price response before calling the API'
        );
        $this->assertStringContainsString(
            'PricingCacheService::get( $pricingPayload, true )',
            $checkoutCalculation,
            'Selected-courier fee calculation should fall back to stale pricing when the API is slow or unavailable'
        );
        $this->assertStringContainsString(
            "empty( \$kiriofPricing['status'] ) || empty( \$kiriofPricing['data'] )",
            $checkoutCalculation,
            'Selected-courier fee calculation must treat raw pricing API status as boolean, not compare it to service status 200'
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
    public function classic_update_order_review_renders_fees_without_second_ajax_refresh(): void
    {
        $controller = file_get_contents( PLUGIN_DIR . '/inc/Controllers/CheckoutController.php' );
        $script = file_get_contents( PLUGIN_DIR . '/assets/wp/js/form-billing-address.js' );

        $this->assertStringContainsString(
            'kiriof_sync_classic_checkout_context_from_post',
            $controller,
            'Woo update_order_review should sync posted payment and insurance state before native fee calculation'
        );
        $this->assertStringContainsString(
            "WC()->session->set( 'chosen_payment_method', \$payment_method );",
            $controller,
            'Posted payment method must be available to native checkout fee calculation in the same request'
        );
        $this->assertStringContainsString(
            "WC()->session->set( 'kiriof_insurance', \$insurance );",
            $controller,
            'Posted insurance state must be available to native checkout fee calculation in the same request'
        );
        $this->assertStringContainsString(
            "WC()->session->set( 'kiriof_force_insurance', \$force_insurance );",
            $controller,
            'Selected courier force-insurance state should be retained from native fee calculation'
        );
        $refreshHandlerStart = strpos( $script, 'function kiriofHandleCodInsurance()' );
        $this->assertNotFalse( $refreshHandlerStart );
        $refreshHandlerBody = substr( $script, $refreshHandlerStart, 700 );
        $this->assertStringContainsString(
            "jQuery( document.body ).trigger( 'update_checkout',{update_shipping_method:true} );",
            $refreshHandlerBody,
            'Classic checkout state changes should trigger one native WooCommerce checkout refresh'
        );
        $feeFunctionStart = strpos( $script, 'function kiriofCodInsurance()' );
        $this->assertNotFalse( $feeFunctionStart );
        $feeFunctionBody = substr( $script, $feeFunctionStart, 3000 );
        $this->assertStringContainsString(
            'if (!isBlockCheckout) {',
            $feeFunctionBody,
            'Classic checkout must skip the separate plugin fee AJAX path'
        );
        $this->assertStringNotContainsString(
            "jQuery(document.body).trigger('update_checkout', { update_shipping_method: true });",
            $feeFunctionBody,
            'kiriofCodInsurance must not start another native checkout refresh on classic checkout'
        );
        $this->assertStringNotContainsString(
            "jQuery(document.body).trigger('update_checkout', { update_shipping_method: false });",
            $feeFunctionBody,
            'Classic checkout should not trigger a second update_checkout after fee AJAX'
        );

        $this->assertStringContainsString(
            'if (kiriofIsBlockCheckoutContext())',
            $script,
            'Block checkout still needs the Store API fee/rate follow-up after a destination update'
        );
        $this->assertStringContainsString(
            'window.setTimeout(kiriofCodInsurance, 150);',
            $script,
            'Block checkout destination updates should schedule Store API refresh without adding a second classic update_checkout'
        );
    }

    #[Test]
    public function pricing_api_uses_checkout_specific_timeout(): void
    {
        $api = file_get_contents( PLUGIN_DIR . '/inc/Base/KiriminAjaApi.php' );

        $this->assertStringContainsString(
            'build_request_args',
            $api,
            'KiriminAja API requests should resolve per-operation request arguments'
        );
        $this->assertStringContainsString(
            "'get_pricing' === ( \$request_meta['operation'] ?? '' )",
            $api,
            'shipping_price requests should have checkout-specific timeout handling'
        );
        $this->assertStringContainsString(
            '? 10',
            $api,
            'shipping_price timeout should allow valid slow pricing responses instead of failing at an aggressive 4 seconds'
        );
        $this->assertStringContainsString(
            "apply_filters( 'kiriof_api_request_timeout'",
            $api,
            'Pricing timeout should remain overridable for production tuning'
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
}
