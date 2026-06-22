<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShippingDiscountCouponRuntimeTest extends TestCase
{
    #[Test]
    public function coupon_controller_registers_runtime_shipping_coupon_hooks(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php');

        $this->assertStringContainsString('woocommerce_coupon_is_valid_for_cart', $content);
        $this->assertStringContainsString('woocommerce_cart_coupon_types', $content);
        $this->assertStringContainsString('woocommerce_coupon_is_valid_for_product', $content);
        $this->assertStringContainsString('woocommerce_coupon_get_discount_amount', $content);
        $this->assertStringContainsString('woocommerce_applied_coupon', $content);
        $this->assertStringContainsString('woocommerce_removed_coupon', $content);
        $this->assertStringContainsString('woocommerce_before_calculate_totals', $content);
        $this->assertStringContainsString('validateShippingCouponForCart', $content);
        $this->assertStringContainsString('registerRuntimeCartCouponTypes', $content);
        $this->assertStringContainsString('validateShippingCouponForProduct', $content);
        $this->assertStringContainsString('zeroItemDiscountForShippingCoupon', $content);
        $this->assertStringContainsString('invalidateShippingRatesAfterCouponChange', $content);
        $this->assertStringContainsString('handleAppliedShippingCoupon', $content);
        $this->assertStringContainsString('enforceShippingCouponRestrictions', $content);
        // Shipping label injection removed — janky on block checkout themes (ShopVerse)
        $this->assertStringNotContainsString('woocommerce_cart_shipping_method_full_label', $content);
        $this->assertStringNotContainsString('filterShippingMethodLabel', $content);
    }

    #[Test]
    public function shipping_method_applies_runtime_shipping_coupon_pricing_metadata(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/wc/KiriminajaShippingMethod.php');
        $serviceContent = file_get_contents(PLUGIN_DIR . '/inc/Services/ShippingDiscountCouponService.php');

        $this->assertStringContainsString('ShippingDiscountCouponService', $content);
        $this->assertStringContainsString('getAdjustedRatePricing', $content);
        $this->assertStringContainsString('kiriof_shipping_coupon_original_cost', $content);
        $this->assertStringContainsString('kiriof_shipping_coupon_notice', $content);
        $this->assertStringContainsString('kiriof_shipping_coupon_rate_meta', $content);
        $this->assertStringContainsString('kiriof_rate_eta', $content);
        $this->assertStringContainsString('kiriof_rate_description', $content);
        $this->assertStringContainsString('getCurrentShippingDiscountTotal', $serviceContent);
        $this->assertStringContainsString('getCurrentShippingDiscountSummary', $serviceContent);
        $this->assertStringContainsString('validateCouponForCart( $coupon, false, false )', $serviceContent);
        $this->assertStringContainsString('couponAllowsSelectedCourier', $serviceContent);
        $this->assertStringContainsString('getChosenShippingMethods', $serviceContent);
        $this->assertStringContainsString('getAvailableShippingRateIds', $serviceContent);
        $this->assertStringContainsString("'kiriof_chosen_shipping_methods'", $serviceContent);
        $this->assertStringContainsString("isset( \$_POST['shipping_method'] )", $serviceContent);
        $this->assertStringContainsString('getChosenKiriminAjaCourierCode', $serviceContent);
        $this->assertStringContainsString('extractCourierCodeFromMethodId', $serviceContent);
        $this->assertStringContainsString('normalizeCourierCode', $serviceContent);
        $this->assertStringContainsString('splitCouponCodesByScope', $serviceContent);
        $this->assertStringContainsString('getPostedDestinationId', $serviceContent);
        $this->assertStringContainsString('getPostedDestinationName', $serviceContent);
        $this->assertStringContainsString('clearValidationNotices', $serviceContent);
        $this->assertStringContainsString('hasActiveShippingCouponInCart', $serviceContent);
        $this->assertStringContainsString('getValidationMessages', $serviceContent);
        $this->assertStringContainsString('wc_set_notices', $serviceContent);
        $this->assertStringContainsString("'kiriof_shipping_destination_area'", $serviceContent);
        $this->assertStringContainsString("'kiriof_destination_area_name'", $serviceContent);
        $this->assertStringContainsString('This coupon is not valid for the selected courier.', $serviceContent);
        $this->assertStringContainsString('kiriof_shipping_coupon_discount_amount', $serviceContent);
        $this->assertStringContainsString('Shipping Discount (%s)', $serviceContent);
        $this->assertStringContainsString("'original_cost'", $serviceContent);
        $this->assertStringContainsString("'rate_label'", $serviceContent);
    }

    #[Test]
    public function kiriminaja_shipping_method_is_available_in_shipping_zones(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/wc/KiriminajaShippingMethod.php');

        $this->assertStringContainsString(
            '$this->instance_id = absint( $instance_id );',
            $content,
            'KiriminAja shipping method must accept a WooCommerce zone instance ID'
        );

        $this->assertStringContainsString(
            "'shipping-zones'",
            $content,
            'KiriminAja shipping method must support WooCommerce shipping zones'
        );

        $this->assertStringContainsString(
            "'instance-settings'",
            $content,
            'KiriminAja shipping method must expose instance settings for shipping zones'
        );

        $this->assertStringContainsString(
            "'instance-settings-modal'",
            $content,
            'KiriminAja shipping method must be addable from the zone shipping-method modal'
        );

        $this->assertStringContainsString(
            'function initInstanceFormFields',
            $content,
            'KiriminAja shipping method must define instance form fields for zone configuration'
        );

        $this->assertStringContainsString(
            "\$methods['kiriminaja-official'] = 'Kiriof_Shipping_Method_Controller';",
            $content,
            'KiriminAja shipping method must be registered by method ID so WooCommerce can list it in zones'
        );
    }

    #[Test]
    public function shipping_coupon_physical_product_check_ignores_virtual_items_and_discounted_totals(): void
    {
        $serviceContent = file_get_contents(PLUGIN_DIR . '/inc/Services/ShippingDiscountCouponService.php');
        $controllerContent = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php');

        $this->assertStringContainsString(
            'private function cartHasShippableProduct',
            $serviceContent,
            'Shipping discount eligibility must inspect cart products directly'
        );
        $this->assertStringContainsString(
            "isset( \$cartItem['data'] )",
            $serviceContent,
            'Mixed carts need eligibility based on each cart item product object'
        );
        $this->assertStringContainsString(
            '$product->needs_shipping()',
            $serviceContent,
            'A cart with one non-virtual item must remain eligible even when another virtual item is fully discounted'
        );

        $validationStart = strpos($serviceContent, 'public function validateCouponForCart');
        $this->assertNotFalse($validationStart, 'Shipping coupon cart validation must exist');
        $validationBody = substr($serviceContent, $validationStart, 1200);
        $this->assertStringContainsString(
            '! $this->cartHasShippableProduct()',
            $validationBody,
            'Physical-product validation should use cartHasShippableProduct instead of Woo shipping state'
        );
        $this->assertStringNotContainsString(
            '! WC()->cart->needs_shipping()',
            $validationBody,
            'Woo can temporarily report no shipping during coupon recalculation with mixed virtual/physical carts and 100% item discounts'
        );
        $this->assertStringNotContainsString(
            'Shipping discount coupons are not available for your account type.',
            $serviceContent,
            'Shipping coupons should remain usable for TOP/published-rate merchants when the merchant explicitly creates a shipping discount coupon'
        );
        $this->assertStringContainsString(
            'return true;',
            substr($controllerContent, strpos($controllerContent, 'public function validateShippingCouponForProduct'), 500),
            'KiriminAja shipping coupons discount shipping rates, so Woo product-scope coupon validation must not reject them when the cart also contains virtual products'
        );
    }

    #[Test]
    public function shipping_coupon_combinations_accept_percentage_like_smart_coupon_types(): void
    {
        $serviceContent = file_get_contents(PLUGIN_DIR . '/inc/Services/ShippingDiscountCouponService.php');

        $this->assertStringContainsString(
            'getNativeCouponCombinationAliases',
            $serviceContent,
            'Shipping coupon combinations must normalize third-party coupon discount type aliases'
        );
        $this->assertStringContainsString(
            "str_contains( \$type, 'percent' )",
            $serviceContent,
            'Smart Coupon percentage-like types should match the Percentage discount combination checkbox'
        );
        $this->assertStringContainsString(
            'allNativeCouponCombinationsAllowed',
            $serviceContent,
            'When all native combination boxes are checked, unknown non-shipping coupon types should not be rejected automatically'
        );
    }

    #[Test]
    public function store_api_shipping_coupon_validation_allows_all_region_coupons_without_posted_district(): void
    {
        $serviceContent = file_get_contents(PLUGIN_DIR . '/inc/Services/ShippingDiscountCouponService.php');
        $validationStart = strpos($serviceContent, 'public function validateCouponForCart');
        $this->assertNotFalse($validationStart, 'Shipping coupon cart validation must exist');
        $validationBody = substr($serviceContent, $validationStart, 2600);

        $this->assertStringContainsString(
            'couponHasRegionRestrictions',
            $validationBody,
            'All-region shipping coupons should not require a posted District during Store API apply-coupon'
        );
        $this->assertStringContainsString(
            'getAvailableShippingRateIds',
            $serviceContent,
            'Store API coupon validation should recognize KiriminAja from available package rates when chosen_shipping_methods is temporarily empty'
        );
        $this->assertStringContainsString(
            "strpos( \$method, 'kiriminaja-official' ) === 0",
            $serviceContent,
            'KiriminAja shipping detection must still use the KiriminAja method prefix'
        );
    }

    #[Test]
    public function shipping_coupon_registers_as_runtime_cart_coupon_without_item_discount(): void
    {
        $controllerContent = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php');

        $this->assertStringContainsString(
            'registerRuntimeCartCouponTypes',
            $controllerContent,
            'Store API apply-coupon must treat KiriminAja shipping coupon types as runtime cart coupon types so they can stay applied'
        );
        $this->assertStringContainsString(
            'array_merge( (array) $types, $this->getShippingCouponTypes() )',
            $controllerContent,
            'Runtime cart coupon type registration must include fixed and percentage shipping coupons'
        );
        $this->assertStringContainsString(
            'zeroItemDiscountForShippingCoupon',
            $controllerContent,
            'Shipping coupons must not discount product line items after being registered as cart coupon types'
        );
        $this->assertStringContainsString(
            'return 0;',
            substr($controllerContent, strpos($controllerContent, 'public function zeroItemDiscountForShippingCoupon'), 600),
            'KiriminAja shipping coupon amount should be applied only to shipping rates, not Woo item/cart discount totals'
        );
    }

    #[Test]
    public function coupon_changes_invalidate_cached_shipping_rates_for_store_api(): void
    {
        $controllerContent = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php');

        $this->assertStringContainsString(
            'invalidateShippingRatesAfterCouponChange',
            $controllerContent,
            'Store API coupon apply/remove must invalidate shipping rates because zero-item shipping coupons may not appear in the Blocks coupon signature'
        );
        $this->assertStringContainsString(
            "add_action( 'woocommerce_applied_coupon', array( \$this, 'invalidateShippingRatesAfterCouponChange' ), 5, 1 );",
            $controllerContent,
            'Shipping cache must be invalidated before WooCommerce recalculates totals on coupon apply'
        );
        $this->assertStringContainsString(
            "add_action( 'woocommerce_removed_coupon', array( \$this, 'invalidateShippingRatesAfterCouponChange' ), 5, 1 );",
            $controllerContent,
            'Shipping cache must be invalidated before WooCommerce recalculates totals on coupon removal'
        );
        $this->assertStringContainsString(
            "WC()->session->set( 'kiriof_shipping_coupon_rate_meta', array() );",
            $controllerContent,
            'Cached KiriminAja shipping coupon metadata must be cleared when coupons change'
        );
        $this->assertStringContainsString(
            "'shipping_for_package_' . \$package_index",
            $controllerContent,
            'WooCommerce shipping package cache must be cleared so rates recalculate with the new shipping coupon'
        );
        $this->assertStringContainsString(
            'reset_shipping',
            $controllerContent,
            'WooCommerce shipping object should be reset after coupon changes'
        );
    }

    #[Test]
    public function cart_and_checkout_templates_render_shipping_coupon_rows_as_shipping_scoped(): void
    {
        $cartTotals = file_get_contents(PLUGIN_DIR . '/templates/woocommerce/cart/cart-totals.php');
        $reviewOrder = file_get_contents(PLUGIN_DIR . '/templates/woocommerce/checkout/review-order.php');
        $cartShipping = file_get_contents(PLUGIN_DIR . '/templates/woocommerce/cart/cart-shipping.php');
        $blockCheckout = file_get_contents(PLUGIN_DIR . '/assets/wp/js/kiriof-block-checkout.js');
        $couponController = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php');
        $metabox = file_get_contents(PLUGIN_DIR . '/templates/order/metabox-shipping.php');
        $transactionProcess = file_get_contents(PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php');
        $transactionProcessView = file_get_contents(PLUGIN_DIR . '/templates/transaction-process/view/index.php');

        $this->assertStringContainsString('Applied to shipping', $cartTotals);
        $this->assertStringContainsString('Applied to shipping', $reviewOrder);
        $this->assertStringContainsString('isShippingCoupon', $cartTotals);
        $this->assertStringContainsString('isShippingCoupon', $reviewOrder);
        $this->assertStringContainsString('Shipping Discount', $cartTotals);
        $this->assertStringContainsString('Shipping Discount', $reviewOrder);
        $this->assertStringContainsString('getCurrentShippingDiscountTotal', $cartTotals);
        $this->assertStringContainsString('getCurrentShippingDiscountTotal', $reviewOrder);
        $this->assertStringContainsString('Save %s', $cartShipping);
        $this->assertStringContainsString('kiriof-shipping-rate-savings', $cartShipping);
        // kiriof_get_current_shipping_discount fetched to show strikethrough in Order Summary totals row
        $this->assertStringContainsString('kiriof_get_current_shipping_discount', $blockCheckout);
        // Shipping rate decoration (ETA/description injection, strikethrough pricing) removed —
        // block themes render rate meta_data as visible sub-lines causing janky display.
        $this->assertStringNotContainsString('kiriof_get_shipping_rate_meta', $blockCheckout);
        $this->assertStringNotContainsString('scheduleShippingDecorationRefresh', $blockCheckout);
        $this->assertStringNotContainsString('syncShippingSummaryLine', $blockCheckout);
        $this->assertStringNotContainsString('getShippingOptionLayoutHost', $blockCheckout);
        $this->assertStringNotContainsString('decorateShippingOptions', $blockCheckout);
        $this->assertStringNotContainsString('kiriof-block-shipping-option-selected', $blockCheckout);
        $this->assertStringNotContainsString('kiriof-block-shipping-option-meta', $blockCheckout);
        $this->assertStringNotContainsString('kiriof-block-shipping-rate-details', $blockCheckout);
        $this->assertStringNotContainsString('kiriof-block-shipping-rate-badge', $blockCheckout);
        $this->assertStringContainsString('invalidateBlockShippingRates', $blockCheckout);
        $this->assertStringContainsString('previousCouponsRef', $blockCheckout);
        $this->assertStringContainsString('getCurrentShippingDiscountAjax', $couponController);
        $this->assertStringContainsString('clearValidationNotices', $couponController);
        $this->assertStringContainsString('hasActiveShippingCouponInCart', $couponController);
        $this->assertStringContainsString('splitCouponCodesByScope', $metabox);
        $this->assertStringContainsString('splitCouponCodesByScope', $transactionProcess);
        $this->assertStringContainsString('splitCouponCodesByScope', $transactionProcessView);
        $this->assertStringNotContainsString('getShippingRateMetaAjax', $couponController);
        $this->assertStringContainsString('wc_clear_notices', $couponController);
        $this->assertStringContainsString('remove_coupon', $couponController);
    }
}
