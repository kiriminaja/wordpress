<?php
namespace KiriminAjaOfficial\Controllers {
	function header( $header ) { $GLOBALS['download_headers'][] = $header; }
	function header_remove( $header ) { $GLOBALS['removed_headers'][] = $header; }
	function headers_sent() { return false; }
	function time() { return strtotime( '2026-10-28 12:00:00 UTC' ); }
}
namespace {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
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
