<?php

use PHPUnit\Framework\TestCase;

class ShipmentLocationStructureTest extends TestCase {
    private function read( string $path ): string {
        $content = file_get_contents( $path );
        $this->assertIsString( $content );

        return $content;
    }

    public function testProductEditorUsesShipmentLocationsMetaboxLinkingToGeneralSettings(): void {
        $source = $this->read( __DIR__ . '/../inc/Controllers/ProductController.php' );

        $this->assertStringContainsString( "add_action( 'add_meta_boxes_product', array( \$this, 'register_shipment_location_meta_box' ) );", $source );
        $this->assertStringContainsString( 'Shipment Locations', $source );
        $this->assertStringContainsString( 'This option is managed by the KiriminAja plugin', $source );
        $this->assertStringContainsString( 'admin.php?page=wc-settings&tab=general#kiriof-shipment-locations', $source );
        $this->assertStringContainsString( '$this->save_shipment_location_meta($post_id, $shipment_location);', $source );
        $this->assertStringContainsString( '$this->save_shipment_location_meta($variation_id, is_array($locations) && isset($locations[$variation_id]) ? $locations[$variation_id] : \'\');', $source );
    }

    public function testGeneralSettingsStoreAddressRendersPerLocationTable(): void {
        $source = $this->read( __DIR__ . '/../inc/Controllers/SettingController.php' );

        $this->assertStringContainsString( "add_action( 'woocommerce_admin_field_kiriof_shipment_locations', array( \$this, 'renderWooCommerceShipmentLocationsField' ) );", $source );
        $this->assertStringContainsString( "'type'  => 'kiriof_shipment_locations',", $source );
        $this->assertStringContainsString( 'kiriof_locations[', $source );
        $this->assertStringContainsString( 'kiriof_default_location_id', $source );
        $this->assertStringContainsString( 'kiriof-wc-location-card__body', $source );
        $this->assertStringContainsString( 'Add Shipment Location', $source );
        $this->assertStringContainsString( 'kiriof-wc-locations-table', $source );
        $this->assertStringContainsString( 'wc-shipping-zones widefat', $source );
        $this->assertStringContainsString( 'kiriof-wc-location-summary', $source );
        $this->assertStringContainsString( 'kiriof-wc-location-page', $source );
        $this->assertStringContainsString( 'kiriof-wc-origin-area-select', $source );
        $this->assertStringContainsString( 'kiriof-wc-origin-map', $source );
        $this->assertStringContainsString( "renderShipmentLocationCard( \$id, \$location, \$use_store_fallback )", $source );
        $this->assertStringNotContainsString( 'kiriof-wc-locations-cards', $source );
        $this->assertStringNotContainsString( '<dialog', $source );
        $this->assertStringContainsString( "get_option( 'woocommerce_store_address' )", $source );
        $this->assertStringNotContainsString( "'id'       => 'kiriof_wc_origin_name',", $source );
        $this->assertStringNotContainsString( "'id'      => 'kiriof_wc_origin_pin_location',", $source );
    }

    public function testGeneralSettingsSavePersistsLocationsAndSyncsDefaultOrigin(): void {
        $source = $this->read( __DIR__ . '/../inc/Controllers/SettingController.php' );

        $this->assertStringContainsString( "\$posted     = isset( \$_POST['kiriof_locations'] ) ? wp_unslash( \$_POST['kiriof_locations'] ) : array();", $source );
        $this->assertStringContainsString( '$repository->insert( $data );', $source );
        $this->assertStringContainsString( '$repository->update( $location_id, $data );', $source );
        $this->assertStringContainsString( '$repository->delete( $location_id );', $source );
        $this->assertStringContainsString( '$repository->setDefault( $default_id );', $source );
        $this->assertStringContainsString( '$repository->ensureDefaultExists();', $source );
        $this->assertStringContainsString( "update_option( 'woocommerce_store_address', \$payload['origin_address'] );", $source );
        $this->assertStringContainsString( "update_option( 'woocommerce_store_postcode', \$payload['origin_zip_code'] );", $source );
        $this->assertStringContainsString( 'storeOriginMirrorData( $payload );', $source );
    }

    public function testSeedFallsBackToNativeWooCommerceStoreAddress(): void {
        $source = $this->read( __DIR__ . '/../inc/Services/ShipmentLocationService.php' );

        $this->assertStringContainsString( "get_option( 'woocommerce_store_address', '' )", $source );
        $this->assertStringContainsString( "get_option( 'woocommerce_store_postcode', '' )", $source );
    }

    public function testStandaloneShipmentLocationPagesAreRemoved(): void {
        $init  = $this->read( __DIR__ . '/../inc/Init.php' );
        $admin = $this->read( __DIR__ . '/../templates/setting/setuped/index.php' );
        $pages = $this->read( __DIR__ . '/../inc/Pages/Admin.php' );

        $this->assertFileDoesNotExist( __DIR__ . '/../inc/Controllers/ShipmentLocationController.php' );
        $this->assertDirectoryDoesNotExist( __DIR__ . '/../templates/shipment-location' );
        $this->assertFileDoesNotExist( __DIR__ . '/../templates/setting/setuped/section-address.php' );

        $this->assertStringNotContainsString( 'ShipmentLocationController', $init );
        $this->assertStringNotContainsString( 'kiriminaja-shipment-locations', $pages );
        $this->assertStringNotContainsString( "\$kiriof_base_url . '&section=address'", $admin );
        $this->assertStringContainsString( 'admin.php?page=wc-settings&tab=general#kiriof-shipment-locations', $admin );
    }

    public function testLocationSchemaStoresSubDistrictName(): void {
        $migration  = $this->read( __DIR__ . '/../inc/Migration/SetupMigration.php' );
        $repository = $this->read( __DIR__ . '/../inc/Repositories/ShipmentLocationRepository.php' );

        $this->assertStringContainsString( '`sub_district_name` varchar(191) NOT NULL DEFAULT \'\',', $migration );
        $this->assertStringContainsString( "'sub_district_name' => isset(\$data['sub_district_name']) ? sanitize_text_field(\$data['sub_district_name']) : '',", $repository );
        $this->assertStringContainsString( "'sub_district_name' => '%s',", $repository );

        foreach ( array( 'address_2', 'city', 'state', 'country' ) as $column ) {
            $this->assertStringContainsString( '`' . $column . '`', $migration );
            $this->assertMatchesRegularExpression( "/'" . $column . "'\s*=>/", $repository );
        }
    }

    public function testGeneralSettingsStoreAddressHoldsFullNativeFieldsPerLocation(): void {
        $controller = $this->read( __DIR__ . '/../inc/Controllers/SettingController.php' );

        $this->assertStringContainsString( 'Address line 2', $controller );
        $this->assertStringContainsString( 'Country / State', $controller );
        $this->assertStringContainsString( 'kiriof-wc-location-country', $controller );
        $this->assertStringContainsString( 'renderCountryStateOptions', $controller );
        $this->assertStringContainsString( "'woocommerce_store_city', 'woocommerce_default_country'", $controller );
        $this->assertStringContainsString( "update_option( 'woocommerce_store_address_2'", $controller );
        $this->assertStringContainsString( "update_option( 'woocommerce_store_city'", $controller );
        $this->assertStringContainsString( "update_option( 'woocommerce_default_country'", $controller );
        $this->assertStringContainsString( 'woocommerce_store_address_2', $controller );
    }

    public function testInitRegistersMigrationOnEveryLoadForSelfHealingSchema(): void {
        $init = $this->read( __DIR__ . '/../inc/Init.php' );

        $this->assertMatchesRegularExpression( '/SetupMigration.*register\(\)/s', $init );
    }
}
