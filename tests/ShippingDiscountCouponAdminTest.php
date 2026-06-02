<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShippingDiscountCouponAdminTest extends TestCase
{
    #[Test]
    public function init_registers_shipping_discount_coupon_controller(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Init.php');

        $this->assertStringContainsString(
            'Controllers\\ShippingDiscountCouponController::class',
            $content,
            'Init must register the shipping discount coupon controller'
        );
    }

    #[Test]
    public function migration_creates_region_cache_tables(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Migration/SetupMigration.php');

        $this->assertStringContainsString('kiriminaja_provinces', $content);
        $this->assertStringContainsString('kiriminaja_cities', $content);
        $this->assertStringContainsString('regionCacheTables', $content);
    }

    #[Test]
    public function api_repository_supports_province_and_city_endpoints(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Repositories/KiriminajaApiRepository.php');

        $this->assertStringContainsString('function getProvinces', $content);
        $this->assertStringContainsString('/api/mitra/province', $content);
        $this->assertStringContainsString('function getCitiesByProvinceId', $content);
        $this->assertStringContainsString('/api/mitra/city?province_id=', $content);
    }

    #[Test]
    public function coupon_controller_registers_required_hooks_and_meta_keys(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php');

        $this->assertStringContainsString('woocommerce_coupon_discount_types', $content);
        $this->assertStringContainsString('woocommerce_coupon_options_usage_restriction', $content);
        $this->assertStringContainsString('woocommerce_coupon_options_save', $content);
        $this->assertStringContainsString('wp_ajax_kiriof_refresh_coupon_regions', $content);
        $this->assertStringContainsString('wp_ajax_kiriof_get_coupon_region_cities', $content);
        $this->assertStringContainsString('_kiriof_coupon_regions', $content);
        $this->assertStringContainsString('_kiriof_coupon_couriers', $content);
    }

    #[Test]
    public function coupon_admin_assets_exist(): void
    {
        $this->assertFileExists(PLUGIN_DIR . '/assets/admin/js/kj-coupon-admin.js');
        $this->assertFileExists(PLUGIN_DIR . '/assets/admin/css/kj-coupon-admin.css');
    }
}