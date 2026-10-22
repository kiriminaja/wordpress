<?php

use KiriminAjaOfficial\Controllers\SettingController;
use PHPUnit\Framework\TestCase;

final class LegacyWarehouseAssetsRuntimeTest extends TestCase {
	private function runSettings( array $query, string $screen = '' ): array {
		$output = shell_exec( kiriof_test_fixture_php_shell( array(
			PLUGIN_DIR . '/tests/fixtures/legacy-warehouse-assets-runtime.php',
			json_encode( array( 'query' => $query, 'screen' => $screen ), JSON_THROW_ON_ERROR ),
		) ) );

		return json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_managed_warehouses_do_not_load_legacy_settings_assets_or_register_legacy_views(): void {
		$result = $this->runSettings( array( 'page' => 'kiriminaja-setting', 'section' => 'warehouses' ) );

		self::assertContains( 'kiriof-admin-workspace', $result['scripts'] );
		self::assertNotContains( 'kiriof-settings', $result['scripts'] );
		self::assertArrayNotHasKey( 'woocommerce_settings_tabs_array', $result['hooks'] );
		self::assertArrayNotHasKey( 'woocommerce_settings_kiriminaja_warehouses', $result['hooks'] );
		// Old nonce-protected POST callers remain supported independently of UI rendering.
		self::assertSame( 'syncWooCommerceGeneralSettings', $result['hooks']['woocommerce_update_options_kiriminaja_warehouses'] );
		self::assertSame( 'syncWooCommerceDefaultLocation', $result['hooks']['woocommerce_update_options_general'] );
	}

	public function test_woo_general_retains_origin_fields_and_the_live_map_and_area_script(): void {
		$result = $this->runSettings( array( 'page' => 'wc-settings', 'tab' => 'general' ), 'woocommerce_page_wc-settings' );

		self::assertContains( 'kiriof-settings', $result['scripts'] );
		self::assertNotContains( 'kiriof-admin-workspace', $result['scripts'] );
		self::assertSame( array(
			'kiriof_wc_shipment_locations_notice',
			'kiriof_wc_origin_name',
			'kiriof_wc_origin_phone',
			'woocommerce_store_address',
			'kiriof_wc_origin_pin_location',
			'woocommerce_default_country',
			'kiriof_wc_origin_area',
		), array_column( $result['fields'], 'id' ) );
		self::assertStringContainsString( 'page=kiriminaja-setting&section=warehouses', $result['fields'][0]['text'] );
		foreach ( array( 'id="kiriof_wc_origin_area"', 'Store Area', 'id="kiriof-wc-origin-map"', 'id="kiriof_wc_origin_latitude"', 'id="kiriof_wc_origin_longitude"', 'id="kiriof-wc-use-my-location"' ) as $markup ) {
			self::assertStringContainsString( $markup, $result['html'] );
		}
	}

	public function test_dead_warehouse_rendering_and_handlers_are_removed_but_general_handlers_remain(): void {
		foreach ( array(
			'registerWarehousesSettingsTab', 'renderWarehousesSettingsTab', 'renderShipmentLocationDetailPage',
			'renderShipmentLocationListPage', 'renderShipmentLocationListStyles', 'renderShipmentLocationDetailStyles',
			'resolveShipmentLocationFields', 'renderShipmentLocationRow', 'renderShipmentLocationCard',
			'renderCountryStateOptions', 'renderShipmentLocationsInlineScript',
		) as $method ) {
			self::assertFalse( method_exists( SettingController::class, $method ), $method );
		}
		$script = file_get_contents( PLUGIN_DIR . '/assets/admin/js/kj-settings.js' );
		foreach ( array( '.kiriof-wc-location-card__body', '.kiriof-wc-location-delete', 'kiriof_locations[' ) as $selector ) {
			self::assertStringNotContainsString( $selector, $script );
		}
		foreach ( array( '#kiriof_wc_origin_area', 'kiriof-wc-origin-map', '#kiriof-wc-use-my-location', 'extractPostcode', 'kiriminaja_subdistrict_search' ) as $consumer ) {
			self::assertStringContainsString( $consumer, $script );
		}
	}
}
