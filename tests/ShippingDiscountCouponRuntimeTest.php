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
        $this->assertStringContainsString('woocommerce_cart_shipping_method_full_label', $content);
        $this->assertStringContainsString('validateShippingCouponForCart', $content);
        $this->assertStringContainsString('filterShippingMethodLabel', $content);
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
        $this->assertStringContainsString('getCurrentShippingDiscountTotal', $serviceContent);
        $this->assertStringContainsString('kiriof_shipping_coupon_discount_amount', $serviceContent);
    }

    #[Test]
    public function cart_and_checkout_templates_render_shipping_coupon_rows_as_shipping_scoped(): void
    {
        $cartTotals = file_get_contents(PLUGIN_DIR . '/templates/woocommerce/cart/cart-totals.php');
        $reviewOrder = file_get_contents(PLUGIN_DIR . '/templates/woocommerce/checkout/review-order.php');
        $cartShipping = file_get_contents(PLUGIN_DIR . '/templates/woocommerce/cart/cart-shipping.php');

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
    }
}
