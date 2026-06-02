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
    public function controller_exposes_all_discount_type_combination_options(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php');

        $this->assertStringContainsString("'fixed_cart'", $content);
        $this->assertStringContainsString("'percent'", $content);
        $this->assertStringContainsString("'fixed_product'", $content);
        $this->assertStringContainsString("self::COUPON_TYPE", $content);
        $this->assertStringContainsString('Fixed cart discount', $content);
        $this->assertStringContainsString('Percentage discount', $content);
        $this->assertStringContainsString('Fixed product discount', $content);
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