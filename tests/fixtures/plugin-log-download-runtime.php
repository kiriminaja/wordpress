<?php
namespace KiriminAjaOfficial\Controllers {
	function header( $header ) { $GLOBALS['download_headers'][] = $header; }
	function header_remove( $header ) { $GLOBALS['removed_headers'][] = $header; }
	function headers_sent() { return false; }
	function time() { return strtotime( '2026-10-28 12:00:00 UTC' ); }
}
namespace {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	define( 'KIRIOF_VERSION', '3.0.11' );
	require ABSPATH . 'vendor/autoload.php';
	$scenario = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
	define( 'WC_LOG_DIR', $scenario['directory'] . '/logs/' );
	$GLOBALS['download_headers'] = array();
	$GLOBALS['removed_headers'] = array();
	register_shutdown_function( static function () use ( $scenario ) {
		file_put_contents( $scenario['directory'] . '/result.json', json_encode( array( 'headers' => $GLOBALS['download_headers'], 'removed_headers' => $GLOBALS['removed_headers'], 'peak_memory' => memory_get_peak_usage( true ), 'error' => error_get_last() ), JSON_THROW_ON_ERROR ) );
	} );
	function current_user_can( $capability ) { return $GLOBALS['scenario']['permission'] ?? true; }
	function check_admin_referer( $action ) { if ( ! ( $GLOBALS['scenario']['nonce'] ?? true ) ) { wp_die( 'Invalid nonce', 403 ); } }
	function wp_die( $message, $status = null ) { echo 'DOWNLOAD_DENIED'; exit( 3 ); }
	function wp_upload_dir() { return array( 'basedir' => $GLOBALS['scenario']['directory'] ); }
	function trailingslashit( $value ) { return rtrim( $value, '/\\' ) . '/'; }
	function nocache_headers() {}
	function current_time( $type ) { return '2026-10-28 12:00:00'; }
	function sanitize_text_field( $value ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) ); }
	function get_bloginfo( $field ) { return '6.9.1'; }
	function wp_remote_get( $url, $args ) { throw new \RuntimeException( 'Log exports must not make HTTP requests.' ); }
	function get_plugins() {
		return array(
			'z-inactive/main.php' => array( 'Name' => 'Inactive Plugin', 'Version' => '1.2.3', 'Description' => 'PRIVATE_DESCRIPTION', 'PluginURI' => 'https://PRIVATE_URL.test' ),
			'a-active/main.php' => array( 'Name' => "<b>Active Plugin</b>\nForged header", 'Version' => '2.3.4' ),
			'n-network/main.php' => array( 'Name' => 'Network Plugin', 'Version' => '3.4.5' ),
		);
	}
	function is_plugin_active_for_network( $file ) { return 'n-network/main.php' === $file; }
	function is_plugin_active( $file ) { return 'a-active/main.php' === $file; }
	function get_mu_plugins() { return empty( $GLOBALS['scenario']['no_mu'] ) ? array( 'mu.php' => array( 'Name' => 'Hosting Helper', 'Version' => '4.5.6' ) ) : array(); }
	function wp_get_theme() {
		return new class {
			public function get( $field ) { return 'Name' === $field ? 'Shop Child & Theme' : ( 'Version' === $field ? '1.2' : '' ); }
			public function get_stylesheet() { return 'shop-child'; }
			public function parent() {
				return empty( $GLOBALS['scenario']['no_parent'] ) ? new class {
					public function get( $field ) { return 'Name' === $field ? 'Shop Parent' : ( 'Version' === $field ? '5.6' : '' ); }
					public function get_stylesheet() { return 'shop-parent'; }
				} : false;
			}
		};
	}
	function wp_check_invalid_utf8( $value, $strip = false ) {
		$value = (string) $value;
		return 1 === preg_match( '//u', $value ) ? $value : '';
	}
	function esc_html( $value ) { return htmlspecialchars( wp_check_invalid_utf8( $value ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
	function esc_html__( $value, $domain ) { return esc_html( $value ); }
	function __( $value, $domain ) { return $value; }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	if ( ! empty( $scenario['buffered'] ) ) {
		ob_start();
		echo 'UNRELATED_BUFFERED_OUTPUT';
	}
	$controller = ( new ReflectionClass( \KiriminAjaOfficial\Controllers\SettingController::class ) )->newInstanceWithoutConstructor();
	if ( ! empty( $scenario['swap'] ) ) {
		$discover = new ReflectionMethod( $controller, 'getPluginLogFiles' );
		$discover->setAccessible( true );
		$files = $discover->invoke( $controller );
		$path = $files[0]['path'];
		rename( $path, $scenario['directory'] . '/original.log' );
		if ( 'symlink' === $scenario['swap'] ) {
			symlink( $scenario['directory'] . '/private.log', $path );
		} else {
			file_put_contents( $path, 'REPLACEMENT_PRIVATE_DATA' );
		}
		$prepare = new ReflectionMethod( $controller, 'preparePluginLogExport' );
		$prepare->setAccessible( true );
		$stream = $prepare->invoke( $controller, $files );
		fpassthru( $stream );
		fclose( $stream );
		exit;
	}
	$controller->downloadPluginLogs();
}
