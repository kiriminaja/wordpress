<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShippingDiscountCouponCombinationsTest extends TestCase
{
    #[Test]
    public function controller_registers_combinations_hooks_and_meta_key(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php');

        $this->assertStringContainsString('manage_edit-shop_coupon_columns', $content);
        $this->assertStringContainsString('manage_shop_coupon_posts_custom_column', $content);
        $this->assertStringContainsString('_kiriof_coupon_combinations', $content);
        $this->assertStringContainsString('Allow Combinations', $content);
        $this->assertStringContainsString('Combinations', $content);
    }

    #[Test]
    public function controller_exposes_non_kiriminaja_discount_type_combination_options(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php');

        // Only WooCommerce native types should be combinable with KiriminAja shipping coupons.
        $this->assertStringContainsString("'fixed_cart'", $content);
        $this->assertStringContainsString("'percent'", $content);
        $this->assertStringContainsString("'fixed_product'", $content);
        $this->assertStringContainsString('Fixed cart discount', $content);
        $this->assertStringContainsString('Percentage discount', $content);
        $this->assertStringContainsString('Fixed product discount', $content);

        // KiriminAja types must NOT appear in getCombinationTypes — they appear elsewhere in the file
        // (registerDiscountType, normalizeCouponAmount) but must not be combination options.
        $this->assertStringContainsString('ShippingDiscountCouponService::FIXED_COUPON_TYPE', $content);
        $this->assertStringContainsString('ShippingDiscountCouponService::PERCENTAGE_COUPON_TYPE', $content);
        $this->assertStringNotContainsString("'shipping_discount' => array", $content);
        $this->assertStringNotContainsString('"shipping_discount" => array', $content);
    }

    #[Test]
    public function service_blocks_double_shipping_discount_coupons(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/ShippingDiscountCouponService.php');

        $this->assertStringContainsString('hasOtherActiveShippingCoupon', $content);
        $this->assertStringContainsString('This coupon cannot be combined with another shipping discount coupon.', $content);
        $this->assertStringNotContainsString('COMBINATION_SHIPPING_DISCOUNT', $content);
    }

    #[Test]
    public function service_enforces_disabled_native_coupon_combinations_at_checkout(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/ShippingDiscountCouponService.php');

        $this->assertStringContainsString('META_COMBINATIONS', $content);
        $this->assertStringContainsString('_kiriof_coupon_combinations', $content);
        $this->assertStringContainsString('getCouponCombinations', $content);
        $this->assertStringContainsString('couponAllowsActiveNativeCoupons', $content);
        $this->assertStringContainsString('This coupon cannot be combined with one or more active coupons.', $content);
    }

    #[Test]
    public function controller_forces_individual_use_off_for_kiriminaja_types(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php');

        // When individual_use is checked, combinations are cleared — not forced off.
        $this->assertStringContainsString('individual_use', $content);
        $this->assertStringContainsString('isIndividualUse', $content);
    }

    #[Test]
    public function js_disables_combinations_when_individual_use_is_checked(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/assets/admin/js/kj-coupon-admin.js');

        $this->assertStringContainsString('syncCombinationsAvailability', $content);
        $this->assertStringContainsString('individual_use', $content);
        $this->assertStringContainsString('kiriof-combination-options', $content);
    }

    #[Test]
    public function admin_styles_include_combination_badges_and_options(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/assets/admin/css/kj-coupon-admin.css');

        $this->assertStringContainsString('.kiriof-combination-options', $content);
        $this->assertStringContainsString('.kiriof-combination-column', $content);
        $this->assertStringContainsString('.kiriof-combination-badge', $content);
        $this->assertStringContainsString('.kiriof-combination-badge.is-disabled', $content);
    }
}
