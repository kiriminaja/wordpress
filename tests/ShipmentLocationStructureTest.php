<?php

use PHPUnit\Framework\TestCase;

class ShipmentLocationStructureTest extends TestCase {
    private function read( string $path ): string {
        $content = file_get_contents( $path );
        $this->assertIsString( $content );

        return $content;
    }

    public function testTransactionListDisplaysShipmentRoute(): void {
        $template = $this->read( __DIR__ . '/../templates/transaction-process/view/index.php' );

        $this->assertStringContainsString( "__('Shipment Route', 'kiriminaja-official')", $template );
        $this->assertStringContainsString( 'shipment_location_snapshot', $template );
        $this->assertStringContainsString( '$kiriof_originName', $template );
        $this->assertStringContainsString( "__('To', 'kiriminaja-official')", $template );
        $this->assertStringContainsString( '$kiriofShippingName', $template );
    }

    public function testProductEditorUsesShipmentLocationsMetaboxLinkingToGeneralSettings(): void {
        $source = $this->read( __DIR__ . '/../inc/Controllers/ProductController.php' );

        $this->assertStringNotContainsString( 'register_shipment_location_meta_box', $source );
        $this->assertStringNotContainsString( 'save_shipment_location_meta', $source );
        $this->assertStringNotContainsString( 'ShipmentLocationService', $source );
    }

    public function testShipmentLocationServiceHasNoCartSplittingOrProductBinding(): void {
        $source = $this->read( __DIR__ . '/../inc/Services/ShipmentLocationService.php' );

        $this->assertStringNotContainsString( 'splitCartShippingPackages', $source );
        $this->assertStringNotContainsString( 'splitPackageByOrigin', $source );
        $this->assertStringNotContainsString( 'isMultiOriginPackage', $source );
        $this->assertStringNotContainsString( 'resolveProductLocation', $source );
        $this->assertStringNotContainsString( 'storeOrderItemLocation', $source );
        $this->assertStringContainsString( 'getLocationOrDefault', $source );
        $this->assertStringContainsString( 'locationToOrigin', $source );
    }

    public function testPickupRequestSupportsShipFromLocation(): void {
        $service    = $this->read( __DIR__ . '/../inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php' );
        $controller = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );
        $modal      = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );
        $tpl        = $this->read( __DIR__ . '/../templates/transaction-process/view/index.php' );
        $rp_modal   = $this->read( __DIR__ . '/../templates/request-pickup/view/modal-request-pickup.php' );
        $rp_js      = $this->read( __DIR__ . '/../templates/request-pickup/view/index.php' );

        $this->assertStringContainsString( 'public function locationId(', $service );
        $this->assertStringContainsString( 'getLocationOrDefault(', $service );
        $this->assertStringContainsString( "'shipment_location_id' => (int) (\$originSnapshot['location_id'] ?? 0),", $service );
        $this->assertStringContainsString( "'shipment_location_snapshot' => wp_json_encode(\$originSnapshot),", $service );

        $this->assertStringContainsString( "\$_POST['data']['location_id']", $controller );
        $this->assertStringContainsString( '->locationId($location_id)', $controller );
        $this->assertStringContainsString( 'kiriof-shipment-location-select', $modal );
        $this->assertStringContainsString( 'Ship From', $modal );

        $this->assertStringContainsString( 'location_id', $tpl );
        $this->assertStringContainsString( 'select[name="location_id"]', $tpl );
        $this->assertStringContainsString( 'name="location_id"', $rp_modal );
        $this->assertStringContainsString( 'location_id', $rp_js );
    }

    public function testTransactionSchemaStoresShipmentLocationSnapshot(): void {
        $migration = $this->read( __DIR__ . '/../inc/Migration/SetupMigration.php' );

        $this->assertStringContainsString( '`shipment_location_id` int(11) DEFAULT NULL,', $migration );
        $this->assertStringContainsString( '`shipment_location_snapshot` text DEFAULT NULL,', $migration );
        $this->assertStringContainsString( "ADD shipment_location_id int(11) DEFAULT NULL", $migration );
        $this->assertStringContainsString( "ADD shipment_location_snapshot text DEFAULT NULL", $migration );
    }

    public function testGeneralSettingsStoreAddressRendersPerLocationTable(): void {
        $source = $this->read( __DIR__ . '/../inc/Controllers/SettingController.php' );

        $this->assertStringContainsString( "add_action( 'woocommerce_settings_kiriminaja_warehouses', array( \$this, 'renderWarehousesSettingsTab' ) );", $source );
        $this->assertStringContainsString( "add_filter( 'woocommerce_settings_tabs_array', array( \$this, 'registerWarehousesSettingsTab' ), 50 );", $source );
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
        $this->assertStringContainsString( "'id'       => 'kiriof_wc_origin_name',", $source );
        $this->assertStringContainsString( "'id'    => 'kiriof_wc_origin_pin_location',", $source );
    }

    public function testGeneralSettingsSavePersistsLocationsAndSyncsDefaultOrigin(): void {
        $source = $this->read( __DIR__ . '/../inc/Controllers/SettingController.php' );

        $this->assertStringContainsString( "\$posted     = isset( \$_POST['kiriof_locations'] ) ? wp_unslash( \$_POST['kiriof_locations'] ) : array();", $source );
        $this->assertStringContainsString( 'false === $repository->insert( $data )', $source );
        $this->assertStringContainsString( '! $repository->update( $location_id, $data )', $source );
        $this->assertStringContainsString( '! $repository->delete( $location_id )', $source );
        $this->assertStringContainsString( '! $repository->setDefault( $default_id )', $source );
        $this->assertStringContainsString( '! $default_location || ! $repository->setDefault( $default_id )', $source );
        $this->assertStringContainsString( '! $repository->ensureDefaultExists()', $source );
        $this->assertStringContainsString( 'isValidShipmentLocationData', $source );
        $this->assertStringContainsString( '-90 <= $latitude', $source );
        $this->assertStringContainsString( '180 >= $longitude', $source );
        $this->assertStringContainsString( 'Shipment location could not be saved.', $source );
        $this->assertStringContainsString( "update_option( 'woocommerce_store_address', \$payload['origin_address'] );", $source );
        $this->assertStringContainsString( "update_option( 'woocommerce_store_postcode', \$payload['origin_zip_code'] );", $source );
        $this->assertStringContainsString( 'storeOriginMirrorData( $payload );', $source );
		$this->assertStringContainsString( '$editing_key = $this->getCurrentShipmentLocationEditingKey();', $source );
		$this->assertStringContainsString( '$posted     = isset( $posted[ $posted_key ] ) ? array( $posted_key => $posted[ $posted_key ] ) : array();', $source );
		$this->assertStringContainsString( "wp_safe_redirect( \$this->getShipmentLocationDetailUrl( '' ) );", $source );
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
        $this->assertStringContainsString( 'admin.php?page=wc-settings&tab=kiriminaja_warehouses', $admin );
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
        $this->assertStringContainsString( "String(label).match(/\\b\\d{5}\\b/)", $controller );
        $this->assertStringContainsString( "\$zip.val(postcode).trigger('input').trigger('change')", $controller );
        $this->assertStringContainsString( "'Required fields'", $controller );
        $this->assertStringContainsString( "'(Optional)'", $controller );
        $this->assertStringContainsString( 'required aria-required="true"', $controller );
        $this->assertStringContainsString( 'country_state', $controller );
        $this->assertStringNotContainsString( 'name="<?php echo esc_attr( $prefix . \'[country_state]\' ); ?>" value="ID"', $controller );
        $this->assertStringContainsString( '$fields[\'country\'] . ( \'\' !== $fields[\'state\']', $controller );
        $this->assertStringContainsString( "update_option( 'woocommerce_store_address_2'", $controller );
        $this->assertStringContainsString( "update_option( 'woocommerce_store_city'", $controller );
        $this->assertStringContainsString( "update_option( 'woocommerce_default_country'", $controller );
        $this->assertStringContainsString( 'woocommerce_store_address_2', $controller );
        $this->assertStringContainsString( 'Warehouses', $controller );
        $this->assertStringContainsString( 'registerWarehousesSettingsTab', $controller );
		$this->assertStringContainsString( "data: {\n                                term: params.term,\n                                search: params.term", $controller );
    }

    public function testInitRegistersMigrationOnEveryLoadForSelfHealingSchema(): void {
        $init = $this->read( __DIR__ . '/../inc/Init.php' );

        $this->assertMatchesRegularExpression( '/SetupMigration.*register\(\)/s', $init );
    }
}
