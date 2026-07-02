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
        $this->assertStringContainsString("post('/api/mitra/province')", $content);
        $this->assertStringContainsString("get('/api/mitra/province')", $content);
        $this->assertStringContainsString('function getCitiesByProvinceId', $content);
        $this->assertStringContainsString("post('/api/mitra/city'", $content);
        $this->assertStringContainsString("'provinsi_id' => (int) \$provinceId", $content);
        $this->assertStringContainsString("'province_id' => \$provinceId", $content);
        $this->assertStringContainsString("get('/api/mitra/city?provinsi_id='", $content);
        $this->assertStringContainsString("get('/api/mitra/city?province_id='", $content);
    }

    #[Test]
    public function region_cache_service_normalizes_documented_region_fields(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/ShippingDiscountRegionCacheService.php');

        $this->assertStringContainsString('provinsi_name', $content);
        $this->assertStringContainsString('kabupaten_name', $content);
        $this->assertStringContainsString('could not be normalized', $content);
    }

    #[Test]
    public function coupon_controller_registers_required_hooks_and_meta_keys(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php');

        $this->assertStringContainsString('woocommerce_coupon_discount_types', $content);
        $this->assertStringContainsString('woocommerce_coupon_data_tabs', $content);
        $this->assertStringContainsString('woocommerce_coupon_data_panels', $content);
        $this->assertStringContainsString('woocommerce_coupon_options_usage_restriction', $content);
        $this->assertStringContainsString('woocommerce_coupon_options_save', $content);
        $this->assertStringContainsString('wp_ajax_kiriof_refresh_coupon_regions', $content);
        $this->assertStringContainsString('wp_ajax_kiriof_get_coupon_region_cities', $content);
        $this->assertStringContainsString('refreshRegionCacheCron', $content);
        $this->assertStringContainsString('Area Restrictions', $content);
        $this->assertStringContainsString('Courier Restrictions', $content);
        $this->assertStringContainsString('Usage Combinations', $content);
        $this->assertStringContainsString('Fixed shipping discount', $content);
        $this->assertStringContainsString('Percentage shipping discount', $content);
        $this->assertStringContainsString('All Indonesian Regions', $content);
        $this->assertStringContainsString('Selected Regions', $content);
        $this->assertStringContainsString('Search region, province, or city', $content);
        $this->assertStringContainsString('Powered by KiriminAja Discount Extension', $content);
        $this->assertStringContainsString('_kiriof_coupon_regions', $content);
        $this->assertStringContainsString('_kiriof_coupon_couriers', $content);
        $this->assertStringContainsString('_kiriof_coupon_combinations', $content);
        $this->assertStringContainsString('normalizeCouponAmount', $content);
        $this->assertStringContainsString('normalizeCouponAmountValue', $content);
        $this->assertStringContainsString("ltrim( (string) ( \$parts[0] ?? '' ), '0' )", $content);
    }

    #[Test]
    public function activation_schedules_region_cache_warmup(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Base/Activate.php');
        $serviceContent = file_get_contents(PLUGIN_DIR . '/inc/Services/ShippingDiscountRegionCacheService.php');

        $this->assertStringContainsString('scheduleRefresh', $content);
        $this->assertStringContainsString('kiriof_refresh_coupon_regions_cache', $serviceContent);
    }

    #[Test]
    public function activation_registers_kiriminaja_shipping_method_without_deleting_zones(): void
    {
        $activation = file_get_contents(PLUGIN_DIR . '/inc/Base/Activate.php');
        $plugin = file_get_contents(PLUGIN_DIR . '/kiriminaja.php');
        $service = file_get_contents(PLUGIN_DIR . '/inc/Services/WooCommerceShippingMethodRegistrationService.php');

        $this->assertStringContainsString('WooCommerceShippingMethodRegistrationService', $activation);
        $this->assertStringContainsString('add_shipping_method( self::METHOD_ID )', $service);
        $this->assertStringContainsString("add_location( 'ID', 'country' )", $service);
        $this->assertStringContainsString("'enabled'] = 'yes'", $service);
        $this->assertStringContainsString('woocommerce_shipping_zone_methods', $service);
        $this->assertStringContainsString("'is_enabled' => 1", $service);
        $this->assertStringNotContainsString('kiriof_delete_shipping_zone();', $plugin);
    }

    #[Test]
    public function coupon_admin_assets_exist(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php');

        $this->assertStringContainsString('assets/admin/js/kj-coupon-admin.js', $content);
        $this->assertStringContainsString('wp_script_add_data( \'kiriof-coupon-admin-script\', \'type\', \'module\' )', $content);
        $this->assertFileExists(PLUGIN_DIR . '/assets/admin/css/kj-coupon-admin.css');
    }
}
