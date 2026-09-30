<?php

use PHPUnit\Framework\TestCase;

class ShipmentLocationStructureTest extends TestCase {
    private function read( string $path ): string {
        $content = file_get_contents( $path );
        $this->assertIsString( $content );

        return $content;
    }

    public function testDbDeltaSchemasUseParseableCreateTableSpacing(): void {
        $migration = $this->read( __DIR__ . '/../inc/Migration/SetupMigration.php' );
        $region_start = strpos( $migration, 'private function regionCacheTables()' );
        $shipment_start = strpos( $migration, 'private function shipmentLocationTable()' );
        $this->assertIsInt( $region_start );
        $this->assertIsInt( $shipment_start );
        $region = substr( $migration, $region_start, $shipment_start - $region_start );
        $shipment = substr( $migration, $shipment_start );

        $this->assertStringContainsString( '$provinces_sql = "CREATE TABLE `" . $provinces_table . "` (', $region );
        $this->assertStringContainsString( '$cities_sql = "CREATE TABLE `" . $cities_table . "` (', $region );
        $this->assertStringContainsString( '$sql = "CREATE TABLE `" . $table_name . "` (', $shipment );
        $this->assertStringNotContainsString( '`(', $region );
        $this->assertStringNotContainsString( '`(', $shipment );
        $this->assertStringContainsString( 'PRIMARY KEY  (`id`)', $region );
        $this->assertStringContainsString( 'PRIMARY KEY  (`id`)', $shipment );
    }

    public function testShipmentLocationQueriesPrepareTableIdentifiers(): void {
        $repository = $this->read( __DIR__ . '/../inc/Repositories/ShipmentLocationRepository.php' );

        $this->assertStringContainsString( 'FROM %i WHERE is_default = 0', $repository );
        $this->assertStringContainsString( 'FROM %i WHERE id = %d', $repository );
        $this->assertStringContainsString( 'UPDATE %i SET is_default = 0 WHERE id != %d', $repository );
        $this->assertStringNotContainsString( 'FROM {$table}', $repository );
        $this->assertStringNotContainsString( 'UPDATE {$table}', $repository );
        $this->assertStringNotContainsString( '$where =', $repository );
        $this->assertStringNotContainsString( '$wpdb->get_results($query)', $repository );
    }

    public function testCheckoutFreezesDefaultOriginOnTheTransaction(): void {
        $service    = $this->read( __DIR__ . '/../inc/Services/CheckoutServices/CreateTransactionService.php' );
        $repository = $this->read( __DIR__ . '/../inc/Repositories/TransactionRepository.php' );
        $template   = $this->read( __DIR__ . '/../templates/transaction-process/view/index.php' );
        $pickup     = $this->read( __DIR__ . '/../inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php' );

        $this->assertStringContainsString( '$checkoutOriginLocation  = $shipmentLocationService->getDefaultLocation();', $service );
        $this->assertStringContainsString( '$checkoutOriginSnapshot  = $shipmentLocationService->locationToOrigin( $checkoutOriginLocation );', $service );
        $this->assertStringContainsString( "'shipment_location_id'          => (int) ( \$checkoutOriginSnapshot['location_id'] ?? 0 )", $service );
        $this->assertStringContainsString( "'shipment_location_snapshot'    => ! empty( \$checkoutOriginSnapshot ) ? wp_json_encode( \$checkoutOriginSnapshot )", $service );
        $this->assertStringContainsString( '`shipment_location_id`,', $repository );
        $this->assertStringContainsString( '`shipment_location_snapshot`', $repository );
        $this->assertStringContainsString( '$kiriof_origin_snapshot[\'location_id\']', $template );
        $this->assertStringNotContainsString( '$kiriof_origin_location  = ! empty( $kiriof_row->shipment_location_id )\n                        ? $kiriof_location_service->repository()->getById( (int) $kiriof_row->shipment_location_id )\n                        : $kiriof_location_service->getDefaultLocation();', $template );
        $this->assertStringContainsString( '$snapshotLocationId', $pickup );
        $this->assertStringContainsString( '$effectiveLocationId = $snapshotLocationId;', $pickup );
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

    public function testPickupRequestUsesOriginsAlreadyStoredOnTransactions(): void {
        $service    = $this->read( __DIR__ . '/../inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php' );
        $controller = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );
        $modal      = $this->read( __DIR__ . '/../inc/Controllers/TransactionProcessController.php' );
        $tpl        = $this->read( __DIR__ . '/../templates/transaction-process/view/index.php' );
        $rp_modal   = $this->read( __DIR__ . '/../templates/request-pickup/view/modal-request-pickup.php' );
        $rp_js      = $this->read( __DIR__ . '/../templates/request-pickup/view/index.php' );

        $this->assertStringNotContainsString( 'public function locationId(', $service );
        $this->assertStringNotContainsString( 'locationIdCache', $service );
        $this->assertStringContainsString( 'getLocationOrDefault(', $service );
        $this->assertStringContainsString( '$transaction->shipment_location_id', $service );
        $this->assertStringContainsString( '$transaction->shipment_location_snapshot', $service );
        $this->assertStringContainsString( '$this->originDataCache = $originSnapshot;', $service );
        $this->assertStringContainsString( 'getDefaultLocation()', $service );
        $this->assertStringContainsString( 'array_unique($savedLocationIds)', $service );
        $this->assertStringContainsString( 'count($savedLocationIds) > 1', $service );
        $this->assertStringContainsString( 'Selected transactions use different shipment origins. Request pickup separately for each origin.', $service );
        $this->assertStringNotContainsString( "'shipment_location_id' => (int) (\$originSnapshot['location_id'] ?? 0),", $service );
        $this->assertStringNotContainsString( "'shipment_location_snapshot' => wp_json_encode(\$originSnapshot),", $service );

        $this->assertStringNotContainsString( "\$_POST['data']['location_id']", $controller );
        $this->assertStringNotContainsString( '->locationId($location_id)', $controller );
        $this->assertStringNotContainsString( 'kiriof-shipment-location-select', $modal );
        $this->assertStringNotContainsString( "esc_html_e('Ship From'", $modal );

        $this->assertStringNotContainsString( 'select[name="location_id"]', $tpl );
        $this->assertStringNotContainsString( 'name="location_id"', $rp_modal );
        $this->assertStringNotContainsString( "select[name=\"location_id\"]", $rp_js );
        $this->assertStringNotContainsString( '$itemsPayload', $service );
        $this->assertStringNotContainsString( '$result[\'items\']', $service );
        $this->assertStringContainsString( '"weight"                    => (int) $helper->minAmount($transaction->weight)', $service );
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

        $this->assertStringContainsString( "\$posted     = isset( \$_POST['kiriof_locations'] ) ? map_deep( wp_unslash( \$_POST['kiriof_locations'] ), 'sanitize_textarea_field' ) : array();", $source );
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
        $script     = $this->read( __DIR__ . '/../assets/admin/js/kj-settings.js' );

        $this->assertStringContainsString( 'Address line 2', $controller );
        $this->assertStringContainsString( '.match(', $script );
        $this->assertStringContainsString( '/\\b\\d{5}\\b/', $script );
        $this->assertStringContainsString( '$zip.val(postcode).trigger("input").trigger("change")', $script );
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
		$this->assertStringContainsString( 'term: params.term', $script );
		$this->assertStringContainsString( 'search: params.term', $script );
    }

    public function testInitRegistersMigrationOnEveryLoadForSelfHealingSchema(): void {
        $init = $this->read( __DIR__ . '/../inc/Init.php' );

        $this->assertMatchesRegularExpression( '/SetupMigration.*register\(\)/s', $init );
    }

    public function testCustomShipmentAddressLimitIsConfigurableAndExcludesDefault(): void {
        $plugin     = $this->read( __DIR__ . '/../kiriminaja.php' );
        $env        = $this->read( __DIR__ . '/../.env.example' );
        $makefile   = $this->read( __DIR__ . '/../Makefile' );
        $injector   = $this->read( __DIR__ . '/../scripts/inject-api-url.php' );
        $repository = $this->read( __DIR__ . '/../inc/Repositories/ShipmentLocationRepository.php' );
        $controller = $this->read( __DIR__ . '/../inc/Controllers/SettingController.php' );

        $this->assertStringContainsString( "define( 'KIRIOF_MAX_CUSTOM_SHIPMENT_LOCATIONS', 5 );", $plugin );
        $this->assertStringContainsString( 'MAX_CUSTOM_SHIPMENT_LOCATIONS=5', $env );
        $this->assertStringContainsString( "grep '^MAX_CUSTOM_SHIPMENT_LOCATIONS=' .env", $makefile );
        $this->assertStringContainsString( "KIRIOF_MAX_CUSTOM_SHIPMENT_LOCATIONS", $makefile );
        $this->assertStringContainsString( "define( 'KIRIOF_ENV'", $injector );
        $this->assertStringContainsString( 'WHERE is_default = 0', $repository );
        $this->assertStringContainsString( 'public function canCreateCustomLocation()', $repository );
        $this->assertStringContainsString( "empty(\$data['is_default']) && ! \$this->canCreateCustomLocation()", $repository );
        $this->assertStringContainsString( '! $repository->canCreateCustomLocation()', $controller );
        $this->assertStringContainsString( 'The default origin is not counted.', $controller );
        $this->assertStringContainsString( 'The custom shipment address limit has been reached.', $controller );
    }
}
