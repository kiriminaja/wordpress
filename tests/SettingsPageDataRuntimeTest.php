<?php

use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Repositories\ShippingDiscountRegionRepository;
use KiriminAjaOfficial\Services\KiriminajaApiService;
use KiriminAjaOfficial\Services\ProductVolumetricReadinessService;
use KiriminAjaOfficial\Services\SettingsPageData;
use KiriminAjaOfficial\Services\ShippingDiscountRegionCacheService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', PLUGIN_DIR . '/' );
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test/' . ltrim( (string) $path, '/' );
	}
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $key, $value, $url ) {
		$separator = str_contains( $url, '?' ) ? '&' : '?';
		return $url . $separator . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
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
require_once PLUGIN_DIR . '/inc/Services/GoogleMapsSettings.php';
require_once PLUGIN_DIR . '/inc/Services/CourierServiceUiData.php';
require_once PLUGIN_DIR . '/inc/Services/RevampAnnouncementService.php';
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		unset( $capability );
		return $GLOBALS['kiriof_settings_page_allowed'] ?? false;
	}
}
if ( ! defined( 'KIRIOF_DIR' ) ) {
	define( 'KIRIOF_DIR', PLUGIN_DIR . '/' );
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return stripslashes( $value );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $value ) {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $value ) {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
}
if ( ! function_exists( 'kiriof_get_published_tracking_content' ) ) {
	function kiriof_get_published_tracking_content() {
		return array();
	}
}
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
		return htmlspecialchars( $url . '&_wpnonce=' . wp_create_nonce( $action ), ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp ) {
		return gmdate( $format, $timestamp );
	}
}

final class SettingsPageDataRuntimeTest extends TestCase {
	public static function settings_sections(): array {
		$cases = array();
		foreach ( array( '', 'unknown', 'account', 'couriers', 'tracking', 'technical', 'cache', 'webhooks', 'TECHNICAL' ) as $section ) {
			$cases[ 'connected-' . $section ] = array( $section, true );
			$cases[ 'disconnected-' . $section ] = array( $section, false );
		}
		return $cases;
	}

	#[Test]
	#[DataProvider( 'settings_sections' )]
	public function settings_dispatch_only_loads_readiness_for_connected_root( string $section, bool $connected ): void {
		$settings = $this->createMock( SettingRepository::class );
		$settings->method( 'getSettingByArray' )->willReturn( array() );
		$settings->method( 'getSettingByKey' )->willReturnCallback(
			static function ( $key ) use ( $connected ) {
				return (object) array( 'value' => 'setup_key' === $key && $connected ? 'setup-123' : '' );
			}
		);
		$is_root = $connected && in_array( $section, array( '', 'unknown' ), true );
		$is_account = $connected && 'account' === $section;
		$is_technical = $connected && in_array( $section, array( 'technical', 'cache', 'webhooks', 'TECHNICAL' ), true );
		$readiness = $this->createMock( ProductVolumetricReadinessService::class );
		$readiness->expects( $this->exactly( $is_root ? 1 : 0 ) )->method( 'getReadiness' )
			->willReturn( array( 'total' => 4, 'configured' => 3, 'ready' => false ) );
		$api = $this->createMock( KiriminajaApiService::class );
		$api->expects( $this->exactly( $is_account ? 1 : 0 ) )->method( 'getProfile' )
			->willReturn( (object) array( 'status' => 200, 'data' => (object) array( 'name' => 'Merchant' ) ) );
		$api->expects( $this->exactly( $is_technical ? 1 : 0 ) )->method( 'get_couriers' )
			->willReturn( (object) array( 'status' => 200, 'data' => array() ) );
		$cache = $this->createMock( ShippingDiscountRegionCacheService::class );
		$cache->method( 'getStatus' )->willReturn( array( 'state' => 'ready' ) );
		$settingsPageData = new SettingsPageData(
			$settings,
			$readiness,
			$api,
			$this->createMock( ShippingDiscountRegionRepository::class ),
			$cache
		);
		$shared = $settingsPageData->prepare( false );
		$productVolumetricReadiness = $shared['productVolumetricReadiness'];
		$this->assertSame( array(), $productVolumetricReadiness );
		$this->assertStringContainsString( '->prepare( false )', file_get_contents( PLUGIN_DIR . '/templates/setting/index.php' ) );

		$previous_get = $_GET;
		$_GET['section'] = $section;
		$GLOBALS['kiriof_settings_page_allowed'] = true;
		ob_start();
		try {
			include PLUGIN_DIR . '/templates/setting/' . ( $connected ? 'setuped' : 'unsetuped' ) . '/index.php';
		} finally {
			ob_end_clean();
			$_GET = $previous_get;
			unset( $GLOBALS['kiriof_settings_page_allowed'] );
		}
		$this->assertSame( $connected ? 'setup-123' : '', $shared['approvedSetupKey']->value );
		if ( $is_root ) {
			$this->assertSame( '3 / 4 Need Action', $kiriof_settings_bootstrap['productAlert']['status'] );
		} elseif ( ! $connected ) {
			$this->assertSame( 'unconfigured', $kiriof_settings_bootstrap['mode'] );
		} else {
			$this->assertSame( $is_technical ? 'technical' : $section, $kiriof_settings_bootstrap['view'] );
		}
	}

	#[Test]
	public function courier_bootstrap_uses_shared_count_translation_without_context(): void {
		$provider = new SettingsPageData(
			$this->createMock( SettingRepository::class ),
			$this->createMock( ProductVolumetricReadinessService::class ),
			$this->createMock( KiriminajaApiService::class ),
			$this->createMock( ShippingDiscountRegionRepository::class ),
			$this->createMock( ShippingDiscountRegionCacheService::class )
		);
		$bootstrap = $provider->prepareCouriersBootstrap();

		$this->assertSame( '%1$s of %2$s enabled', $bootstrap['i18n']['count'] );
		$this->assertSame( $bootstrap['i18n']['count'], $bootstrap['i18n']['serviceCount'] );
	}

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
		$readiness->expects( $this->once() )->method( 'getReadiness' )->willReturn( array( 'total' => 4, 'configured' => 3, 'ready' => false ) );
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
		$root     = $provider->prepareRootBootstrap(
			true,
			array(
				'kiriof_cod_enabled'              => 'yes',
				'kiriof_insurance_enabled'        => 'yes',
				'kiriof_shipping_locations_ready' => true,
				'kiriof_default_address_ready'    => true,
				'kiriof_enabled_courier_count'    => 4,
			),
			$shared['productVolumetricReadiness']
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
		$this->assertSame( '3 / 4 Need Action', $root['productAlert']['status'] );
		$this->assertSame( 'warning', $root['productAlert']['tone'] );
		$this->assertSame( 'insurance', $root['groups'][2]['items'][1]['toggle'] );
		$this->assertSame( '4 Enabled', $root['groups'][2]['items'][0]['status'] );
		$this->assertNotContains( 'products', array_column( $root['groups'][2]['items'], 'key' ) );
		$this->assertNotContains( 'shipping-locations', array_column( $root['groups'][2]['items'], 'key' ) );
		$this->assertSame( 'Default address ready', $root['groups'][2]['items'][3]['status'] );
		$this->assertNotContains( 'webhooks', array_column( $root['groups'][3]['items'], 'key' ) );
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
		$settings->method( 'getSettingByKey' )->willReturnMap(
			array(
				array( 'enable_insurance', (object) array( 'value' => 'yes' ) ),
				array( 'callback_url', (object) array( 'value' => 'https://example.test/?feed=kiriminaja-callback' ) ),
				array( 'origin_whitelist_expedition_id', (object) array( 'value' => 'jne,jnt,jne,spx' ) ),
			)
		);
		$settings->method( 'getSettingByArray' )->willReturn( array() );
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
		parse_str( parse_url( $technical['downloadLogUrl'], PHP_URL_QUERY ), $download_query );
		$this->assertSame( array( 'action' => 'kiriof_download_plugin_logs', '_wpnonce' => wp_create_nonce( 'kiriof_download_plugin_logs' ) ), $download_query );
		$this->assertStringNotContainsString( '&amp;', $technical['downloadLogUrl'] );
		$this->assertSame( $technical['downloadLogUrl'], $technical_bootstrap['downloadLogUrl'] );

		$this->assertSame( 'no', $list['kiriof_cod_enabled'] );
		$this->assertSame( 'yes', $list['kiriof_insurance_enabled'] );
		$this->assertFalse( $list['kiriof_default_address_ready'] );
		$this->assertSame( 3, $list['kiriof_enabled_courier_count'] );
		$this->assertSame( 38, $technical['provinceCount'] );
		$this->assertSame( 514, $technical['cityCount'] );
		$this->assertSame( 2, $technical['courierCount'] );
		$this->assertSame( 'Cached', $technical['courierBadgeTxt'] );
		$this->assertSame( 'Manual refresh only', $technical['regionValidUntil'] );
		$this->assertSame( 'technical', $technical_bootstrap['view'] );
		$maps = $technical_bootstrap['googleMaps'];
		$this->assertSame( array( 'configured', 'maskedKey', 'nonce', 'browserCheckUrl', 'i18n' ), array_keys( $maps ) );
		$check_url = parse_url( $maps['browserCheckUrl'] );
		$this->assertSame( home_url( '/' ), strtok( $maps['browserCheckUrl'], '?' ) );
		parse_str( $check_url['query'], $check_query );
		$this->assertSame( array( 'kiriof_google_maps_browser_check' => '1', '_wpnonce' => 'nonce-kiriof_google_maps_settings' ), $check_query );
		$this->assertStringNotContainsString( '&amp;', $maps['browserCheckUrl'] );
		$this->assertSame( 38, $technical_bootstrap['region']['provinceCount'] );
		$this->assertTrue( $technical_bootstrap['couriers']['cached'] );
		$this->assertContains( 'https://example.test/?feed=kiriminaja-callback', $technical_bootstrap['callbacks'] );
		$this->assertStringContainsString( 'icon-128x128.png', $technical_bootstrap['toolbar']['logoUrl'] );
	}

}
