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
        $this->assertStringContainsString('validateShippingCouponForCart', $content);
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
        $this->assertStringContainsString('kiriof_shipping_coupon_discount_amount', $serviceContent);
        $this->assertStringContainsString('Shipping Discount (%s)', $serviceContent);
        $this->assertStringContainsString("'original_cost'", $serviceContent);
        $this->assertStringContainsString("'rate_label'", $serviceContent);
    }

    #[Test]
    public function cart_and_checkout_templates_render_shipping_coupon_rows_as_shipping_scoped(): void
    {
        $cartTotals = file_get_contents(PLUGIN_DIR . '/templates/woocommerce/cart/cart-totals.php');
        $reviewOrder = file_get_contents(PLUGIN_DIR . '/templates/woocommerce/checkout/review-order.php');
        $cartShipping = file_get_contents(PLUGIN_DIR . '/templates/woocommerce/cart/cart-shipping.php');
        $blockCheckout = file_get_contents(PLUGIN_DIR . '/assets/wp/js/kiriof-block-checkout.js');
        $couponController = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php');

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
        $this->assertStringNotContainsString('getShippingRateMetaAjax', $couponController);
    }
}
