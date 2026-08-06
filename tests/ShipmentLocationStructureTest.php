<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ShipmentLocationStructureTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__);
    }

    public function test_location_schema_and_service_are_packaged_source(): void
    {
        $migration = (string) file_get_contents($this->root . '/inc/Migration/SetupMigration.php');
        $service = (string) file_get_contents($this->root . '/inc/Services/ShipmentLocationService.php');

        self::assertStringContainsString('kiriminaja_shipment_location', $migration);
        self::assertStringContainsString('seedDefaultFromGlobalOrigin', $service);
        self::assertStringContainsString('resolveProductLocation', $service);
        self::assertStringContainsString('splitPackageByOrigin', $service);
    }

    public function test_variation_assignment_and_cart_package_hooks_are_registered(): void
    {
        $product = (string) file_get_contents($this->root . '/inc/Controllers/ProductController.php');
        $service = (string) file_get_contents($this->root . '/inc/Services/ShipmentLocationService.php');

        self::assertStringContainsString('woocommerce_product_after_variable_attributes', $product);
        self::assertStringContainsString('woocommerce_save_product_variation', $product);
        self::assertStringContainsString('woocommerce_cart_shipping_packages', $service);
        self::assertStringContainsString('woocommerce_checkout_create_order_line_item', $service);
    }

    public function test_product_assignment_uses_a_dedicated_shipment_locations_meta_box(): void
    {
        $product = (string) file_get_contents($this->root . '/inc/Controllers/ProductController.php');

        self::assertStringContainsString('add_meta_boxes_product', $product);
        self::assertStringContainsString("'kiriof-shipment-locations'", $product);
        self::assertStringContainsString("'Shipment Locations'", $product);
        self::assertStringContainsString('This option is managed by the KiriminAja plugin', $product);
        self::assertStringContainsString('page=wc-settings&tab=shipping&section=kiriminaja_shipment_locations', $product);
    }

    public function test_shipping_method_uses_the_origin_bound_to_each_package(): void
    {
        $shippingMethod = (string) file_get_contents($this->root . '/wc/KiriminajaShippingMethod.php');

        self::assertStringContainsString('isset($package[\'origin\'])', $shippingMethod);
        self::assertStringContainsString('[\'origin_sub_district_id\']', $shippingMethod);
        self::assertStringContainsString('\'wc_cart_contents\' => isset($package[\'contents\'])', $shippingMethod);
    }

    public function test_locations_management_screen_is_registered_and_secured(): void
    {
        $admin      = (string) file_get_contents($this->root . '/inc/Pages/Admin.php');
        $controller = (string) file_get_contents($this->root . '/inc/Controllers/ShipmentLocationController.php');
        $template   = (string) file_get_contents($this->root . '/templates/shipment-location/index.php');

        self::assertStringContainsString("'menu_slug'=>'kiriminaja-shipment-locations'", $admin);
        self::assertStringContainsString('redirectLegacyPage', $admin);
        self::assertStringContainsString('current_user_can', $controller);
        self::assertStringContainsString('check_admin_referer', $controller);
        self::assertStringContainsString('woocommerce_get_sections_shipping', $controller);
        self::assertStringContainsString('woocommerce_settings_shipping', $controller);
        self::assertStringContainsString('KIRIOF_DIR . \'templates/shipment-location/index.php\'', $controller);
        self::assertStringNotContainsString('KIRIOF_PLUGIN_PATH', $controller);
        self::assertStringContainsString('kiriminaja_shipment_locations', $controller);
        self::assertStringContainsString('Shipment Locations', $template);
        self::assertStringContainsString('Default shipment location:', $template);
        self::assertStringContainsString('kiriof_save_shipment_location', $template);
    }
}
