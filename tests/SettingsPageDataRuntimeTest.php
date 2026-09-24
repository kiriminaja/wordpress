<?php

use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Repositories\ShippingDiscountRegionRepository;
use KiriminAjaOfficial\Services\KiriminajaApiService;
use KiriminAjaOfficial\Services\ProductVolumetricReadinessService;
use KiriminAjaOfficial\Services\SettingsPageData;
use KiriminAjaOfficial\Services\ShippingDiscountRegionCacheService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', PLUGIN_DIR . '/' );
}

require_once PLUGIN_DIR . '/inc/Base/BaseService.php';
require_once PLUGIN_DIR . '/inc/Contracts/ProductVolumetricReadinessRepositoryInterface.php';
require_once PLUGIN_DIR . '/inc/Repositories/SettingRepository.php';
require_once PLUGIN_DIR . '/inc/Repositories/ProductVolumetricReadinessRepository.php';
require_once PLUGIN_DIR . '/inc/Repositories/ShippingDiscountRegionRepository.php';
require_once PLUGIN_DIR . '/inc/Services/KiriminajaApiService.php';
require_once PLUGIN_DIR . '/inc/Services/ProductVolumetricReadinessService.php';
require_once PLUGIN_DIR . '/inc/Services/ShippingDiscountRegionCacheService.php';
require_once PLUGIN_DIR . '/inc/Services/SettingsPageData.php';
if ( ! defined( 'KIRIOF_NONCE' ) ) {
	define( 'KIRIOF_NONCE', 'kiriof-nonce' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! function_exists( 'get_locale' ) ) {
	function get_locale() {
		return 'id_ID';
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $GLOBALS['kiriof_settings_page_options'][ $key ] ?? $default;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return $GLOBALS['kiriof_settings_page_transients'][ $key ] ?? false;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action ) {
		return 'nonce-' . $action;
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . $path;
	}
}
if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $url, $action ) {
		return $url . '&_wpnonce=' . $action;
	}
}
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp ) {
		return gmdate( $format, $timestamp );
	}
}

final class SettingsPageDataRuntimeTest extends TestCase {
	#[Test]
	public function prepares_existing_setting_shapes_and_whitelist_names(): void {
		$setup_key = (object) array( 'key' => 'setup_key', 'value' => 'setup-123' );
		$shipping  = array(
			(object) array( 'key' => 'origin_name', 'value' => 'Warehouse' ),
			(object) array( 'key' => 'origin_phone', 'value' => '0812' ),
			(object) array( 'key' => 'origin_whitelist_expedition_id', 'value' => 'jne,jnt' ),
			(object) array( 'key' => 'origin_whitelist_expedition_name', 'value' => 'JNE,' ),
		);
		$callback = array( (object) array( 'key' => 'callback_url', 'value' => 'https://example.test/callback' ) );

		$settings = $this->createMock( SettingRepository::class );
		$settings->method( 'getSettingByKey' )->willReturnMap(
			array(
				array( 'setup_key', $setup_key ),
				array( 'enable_insurance', (object) array( 'value' => 'yes' ) ),
			)
		);
		$settings->method( 'getSettingByArray' )->willReturnCallback(
			static function ( $keys ) use ( $shipping, $callback ) {
				return array( 'callback_url' ) === $keys ? $callback : $shipping;
			}
		);

		$readiness = $this->createMock( ProductVolumetricReadinessService::class );
		$readiness->method( 'getReadiness' )->willReturn( array( 'total' => 4, 'configured' => 3, 'ready' => false ) );
		$api = $this->createMock( KiriminajaApiService::class );
		$api->method( 'getProfile' )->willReturn( (object) array( 'status' => 200, 'data' => (object) array( 'name' => 'Merchant' ) ) );
		$api->method( 'get_couriers' )->willReturn(
			(object) array(
				'status' => 200,
				'data'   => array(
					(object) array( 'code' => 'jne', 'name' => 'JNE API' ),
					(object) array( 'code' => 'jnt', 'name' => 'J&T Express' ),
				),
			)
		);
		$region = $this->createMock( ShippingDiscountRegionRepository::class );
		$cache  = $this->createMock( ShippingDiscountRegionCacheService::class );

		$provider = new SettingsPageData( $settings, $readiness, $api, $region, $cache );
		$shared   = $provider->prepare();
		$account  = $provider->prepareAccount();
		$webhooks = $provider->prepareWebhooksBootstrap( 'https://example.test/hook' );
		$root     = $provider->prepareRootBootstrap(
			true,
			array(
				'kiriof_cod_enabled'              => 'yes',
				'kiriof_insurance_enabled'        => 'yes',
				'kiriof_shipping_locations_ready' => true,
			),
			array( 'total' => 4, 'configured' => 3, 'ready' => false )
		);

		$this->assertSame( $setup_key, $shared['approvedSetupKey'] );
		$this->assertSame( 'https://example.test/callback', $shared['inputValueArr']['callback_url'] );
		$this->assertSame( 'Warehouse', $shared['inputValueArr']['origin_name'] );
		$this->assertTrue( $shared['isOriginShippingDataReady'] );
		$this->assertSame( array( 'total' => 4, 'configured' => 3, 'ready' => false ), $shared['productVolumetricReadiness'] );
		$this->assertTrue( $account['kiriof_is_connected'] );
		$this->assertSame( 'JNE', $account['kiriof_wl_map']['jne'] );
		$this->assertSame( 'J&T Express', $account['kiriof_wl_map']['jnt'] );
		$this->assertSame( 'configured', $root['mode'] );
		$this->assertTrue( $root['toggles']['insurance'] );
		$this->assertSame( '3 / 4 Need Action', $root['groups'][2]['items'][0]['status'] );
		$this->assertSame( 'insurance', $root['groups'][2]['items'][3]['toggle'] );
		$this->assertSame( 'webhooks', $webhooks['view'] );
		$this->assertSame( 'https://example.test/hook', $webhooks['callbackUrl'] );
	}

	#[Test]
	public function prepares_insurance_and_region_cache_diagnostics(): void {
		$GLOBALS['kiriof_settings_page_options'] = array(
			'woocommerce_cod_settings'                    => array( 'enabled' => 'no' ),
			'woocommerce_ship_to_countries'               => 'all',
			'_transient_timeout_kiriof_couriers_list_v2' => DAY_IN_SECONDS + 3600,
		);
		$GLOBALS['kiriof_settings_page_transients'] = array( 'kiriof_couriers_list_v2' => array( 'cached' ) );

		$settings = $this->createMock( SettingRepository::class );
		$settings->method( 'getSettingByKey' )->with( 'enable_insurance' )->willReturn( (object) array( 'value' => 'yes' ) );
		$readiness = $this->createMock( ProductVolumetricReadinessService::class );
		$api = $this->createMock( KiriminajaApiService::class );
		$api->method( 'get_couriers' )->willReturn( (object) array( 'status' => 200, 'data' => array( 'a', 'b' ) ) );
		$region = $this->createMock( ShippingDiscountRegionRepository::class );
		$region->method( 'getProvinceCount' )->willReturn( 38 );
		$region->method( 'getCityCount' )->willReturn( 514 );
		$cache = $this->createMock( ShippingDiscountRegionCacheService::class );
		$cache->method( 'getStatus' )->willReturn( array( 'state' => 'ready', 'last_error' => '', 'last_completed_at' => '2026-01-01 00:00:00' ) );

		$provider  = new SettingsPageData( $settings, $readiness, $api, $region, $cache );
		$list      = $provider->prepareList();
		$technical = $provider->prepareTechnical();
		$technical_bootstrap = $provider->prepareTechnicalBootstrap( $technical );

		$this->assertSame( 'no', $list['kiriof_cod_enabled'] );
		$this->assertSame( 'yes', $list['kiriof_insurance_enabled'] );
		$this->assertSame( 38, $technical['provinceCount'] );
		$this->assertSame( 514, $technical['cityCount'] );
		$this->assertSame( 2, $technical['courierCount'] );
		$this->assertSame( 'Cached', $technical['courierBadgeTxt'] );
		$this->assertSame( 'Manual refresh only', $technical['regionValidUntil'] );
		$this->assertSame( 'technical', $technical_bootstrap['view'] );
		$this->assertSame( 38, $technical_bootstrap['region']['provinceCount'] );
		$this->assertTrue( $technical_bootstrap['couriers']['cached'] );
		$this->assertStringContainsString( 'icon-128x128.png', $technical_bootstrap['toolbar']['logoUrl'] );
	}

	#[Test]
	public function target_templates_never_instantiate_repositories(): void {
		$templates = array(
			'templates/setting/index.php',
			'templates/setting/setuped/index.php',
			'templates/setting/unsetuped/index.php',
			'templates/setting/app.php',
		);

		foreach ( $templates as $template ) {
			$content = file_get_contents( PLUGIN_DIR . '/' . $template );
			$this->assertDoesNotMatchRegularExpression( '/new\s+[^;\n]*Repository\s*\(/', $content, $template );
			$this->assertStringNotContainsString( 'Repositories\\', $content, $template );
		}
		$this->assertStringContainsString( 'SettingsPageData', file_get_contents( PLUGIN_DIR . '/templates/setting/index.php' ) );
	}
}
