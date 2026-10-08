<?php

namespace KiriminAjaOfficial\Services {
	function get_option( $name, $default = '' ) {
		if ( 'kiriof_google_maps_browser_key' !== $name ) { return \get_option( $name, $default ); }
		return \GoogleMapsSettingsRuntimeTest::$options[ $name ] ?? $default;
	}
	function update_option( $name, $value, $autoload = null ) {
		\GoogleMapsSettingsRuntimeTest::$options[ $name ] = $value;
		\GoogleMapsSettingsRuntimeTest::$autoload = $autoload;
		return true;
	}
	function delete_option( $name ) { unset( \GoogleMapsSettingsRuntimeTest::$options[ $name ] ); }
}
namespace KiriminAjaOfficial\Controllers {
	function current_user_can( $capability ) {
		\GoogleMapsSettingsRuntimeTest::$capability = $capability;
		return \GoogleMapsSettingsRuntimeTest::$authorized;
	}
	function wp_verify_nonce( $nonce, $action ) { return 'dedicated' === $nonce && 'kiriof_google_maps_settings' === $action; }
	function wp_unslash( $value ) { \GoogleMapsSettingsRuntimeTest::$unslashed[] = $value; return stripslashes( $value ); }
	function __( $text, $domain ) { return $text; }
	function wp_send_json_error( $data, $status ) { \GoogleMapsSettingsRuntimeTest::$response = array( 'success' => false, 'data' => $data, 'status' => $status ); }
	function wp_send_json_success( $data ) { \GoogleMapsSettingsRuntimeTest::$response = array( 'success' => true, 'data' => $data ); }
}
namespace {
	require_once PLUGIN_DIR . '/inc/Services/GoogleMapsSettings.php';
	require_once PLUGIN_DIR . '/inc/Controllers/GoogleMapsSettingsController.php';

	final class GoogleMapsSettingsRuntimeTest extends \PHPUnit\Framework\TestCase {
		public static array $options = array();
		public static $autoload = null;
		public static bool $authorized = true;
		public static array $response = array();
		public static array $unslashed = array();
		public static string $capability = '';

		protected function setUp(): void {
			self::$options = array(); self::$response = array(); self::$unslashed = array();
			self::$authorized = true; self::$autoload = null;
			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_POST = array( 'data' => array( 'nonce' => 'dedicated', 'mode' => 'save', 'key' => 'AIza-test_key123' ) );
		}

		public function testExactValidationMaskingAndExplicitRemoval(): void {
			$service = new \KiriminAjaOfficial\Services\GoogleMapsSettings();
			self::assertSame( 'leaflet', $service->config()['provider'] );
			self::assertTrue( $service->save( 'AIza-test_key123' ) );
			self::assertFalse( self::$autoload );
			self::assertSame( array( 'provider' => 'google', 'apiKey' => 'AIza-test_key123' ), $service->config() );
			self::assertSame( 'AIza*********123', $service->settingsSummary()['maskedKey'] );
			foreach ( array( array(), 123, 'short', ' padded_key ', 'AIza****123', "AIza-test\n", str_repeat( 'a', 257 ), 'AIza<bad>key' ) as $invalid ) {
				self::assertFalse( $service->save( $invalid ) );
				self::assertSame( 'AIza-test_key123', $service->config()['apiKey'] );
			}
			self::assertTrue( $service->save( '' ) );
			self::assertTrue( $service->settingsSummary()['configured'] );
			self::assertTrue( $service->remove() );
			self::assertSame( array( 'configured' => false, 'maskedKey' => '' ), $service->settingsSummary() );
			self::assertTrue( $service->save( str_repeat( 'a', 256 ) ) );
		}

		public function testAuthorizationAndNonceBeforeParsingKey(): void {
			$controller = new \KiriminAjaOfficial\Controllers\GoogleMapsSettingsController();
			$_SERVER['REQUEST_METHOD'] = 'GET'; $controller->save();
			self::assertSame( 405, self::$response['status'] );
			self::assertSame( array(), self::$unslashed );
			$_SERVER['REQUEST_METHOD'] = 'POST'; self::$authorized = false; $controller->save();
			self::assertSame( 403, self::$response['status'] );
			self::assertSame( 'manage_woocommerce', self::$capability );
			self::assertSame( array(), self::$unslashed );
			self::$authorized = true; $_POST['data']['nonce'] = 'general'; $controller->save();
			self::assertSame( 403, self::$response['status'] );
			self::assertSame( array( 'general' ), self::$unslashed );
			self::assertSame( array(), self::$options );
		}

		public function testSaveResponseIsMaskedAndUnknownOrStructuredInputFailsSafely(): void {
			$controller = new \KiriminAjaOfficial\Controllers\GoogleMapsSettingsController();
			$controller->save();
			self::assertTrue( self::$response['success'] );
			self::assertStringNotContainsString( 'AIza-test_key123', json_encode( self::$response ) );
			self::assertSame( array( 'dedicated', 'save', 'AIza-test_key123' ), self::$unslashed );
			$_POST['data']['key'] = array( 'secret' ); $controller->save();
			self::assertSame( 400, self::$response['status'] );
			$_POST['data']['mode'] = 'unknown-secret'; $controller->save();
			self::assertSame( 400, self::$response['status'] );
			self::assertStringNotContainsString( 'secret', json_encode( self::$response ) );
			$_POST['data']['mode'] = 'remove'; $controller->save();
			self::assertFalse( self::$response['data']['data']['configured'] );
		}
	}
}
