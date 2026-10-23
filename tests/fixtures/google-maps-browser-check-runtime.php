<?php
/** Standalone fixture avoids collisions with other controller namespace doubles. */
namespace KiriminAjaOfficial\Controllers {
	function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['result']['hooks'][] = $hook; }
	function current_user_can( $capability ) {
		$GLOBALS['result']['capability'] = $capability;
		return $GLOBALS['scenario']['authorized'] ?? true;
	}
	function wp_verify_nonce( $nonce, $action ) { return 'dedicated' === $nonce && 'kiriof_google_maps_settings' === $action; }
	function wp_unslash( $value ) { return is_array( $value ) ? array_map( __FUNCTION__, $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value ); }
	function __( $text, $domain ) { return $text; }
	function esc_html__( $text, $domain ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
	function nocache_headers() { $GLOBALS['result']['nocache'] = true; }
	function status_header( $status ) { $GLOBALS['result']['status'] = $status; }
	function header( $value, $replace = true ) { $GLOBALS['result']['headers'][] = $value; }
	function header_remove( $name ) { $GLOBALS['result']['removed_headers'][] = $name; }
	function wp_die( $message, $title, $args ) {
		$GLOBALS['result']['status'] = $args['response'];
		$GLOBALS['result']['error'] = $message;
		throw new \RuntimeException( 'fixture-stop' );
	}
	function admin_url( $path ) { return $GLOBALS['scenario']['admin_url'] ?? 'https://shop.example:8443/wp-admin/'; }
	function home_url( $path ) { return $GLOBALS['scenario']['home_url'] ?? 'https://shop.example:8443/'; }
	function wp_parse_url( $url ) { return parse_url( $url ); }
	function sanitize_text_field( $value ) { return is_string( $value ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( $value ) ) ) : ''; }
}
namespace KiriminAjaOfficial\Services {
	function get_option( $name, $default = '' ) {
		++$GLOBALS['result']['key_reads'];
		return $GLOBALS['scenario']['key'] ?? 'AIza-browser_saved123';
	}
}
namespace {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	define( 'KIRIOF_DIR', ABSPATH );
	define( 'KIRIOF_URL', 'https://shop.example/wp-content/plugins/kiriminaja/' );
	define( 'KIRIOF_VERSION', 'test' );
	function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_html_e( $text, $domain ) { echo esc_html( $text ); }
	function esc_attr_e( $text, $domain ) { echo esc_html( $text ); }
	function esc_url( $text ) { return esc_html( $text ); }
	function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
	function absint( $value ) { return abs( (int) $value ); }
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) { $GLOBALS['result']['scripts'][ $handle ] = array( 'src' => $src, 'ver' => $ver, 'args' => $args ); }
	function wp_print_scripts( $handles = false ) {
		$handles = false === $handles ? array_keys( $GLOBALS['result']['scripts'] ) : (array) $handles;
		foreach ( $handles as $handle ) {
			$script = $GLOBALS['result']['scripts'][ $handle ] ?? null;
			if ( ! is_array( $script ) ) { continue; }
			echo '<script src="' . esc_url( $script['src'] . '?ver=' . rawurlencode( (string) $script['ver'] ) ) . '" defer></script>';
		}
	}
	$GLOBALS['scenario'] = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
	$GLOBALS['result'] = array( 'key_reads' => 0, 'headers' => array(), 'hooks' => array(), 'nocache' => false, 'scripts' => array() );
	$_SERVER['REQUEST_METHOD'] = $GLOBALS['scenario']['method'] ?? 'GET';
	$_GET = $GLOBALS['scenario']['query'] ?? array( '_wpnonce' => 'dedicated', 'channel' => 'random_channel_123456' );
	require ABSPATH . 'inc/Services/GoogleMapsSettings.php';
	require ABSPATH . 'inc/Controllers/GoogleMapsSettingsController.php';
	$settings = new \KiriminAjaOfficial\Services\GoogleMapsSettings();
	if ( isset( $GLOBALS['scenario']['unsafe_config'] ) ) {
		$settings = new class() extends \KiriminAjaOfficial\Services\GoogleMapsSettings {
			public function config(): array {
				++$GLOBALS['result']['key_reads'];
				return array( 'apiKey' => '</script><img src=x onerror="alert(1)">&\'', 'provider' => 'google' );
			}
		};
	}
	$controller = new \KiriminAjaOfficial\Controllers\GoogleMapsSettingsController( $settings );
	$controller->register();
	ob_start();
	register_shutdown_function( static function () use ( $settings ) {
		$GLOBALS['result']['html'] = ob_get_clean();
		$result = $GLOBALS['result'];
		$result['summary'] = $settings->settingsSummary();
		echo json_encode( $result, JSON_THROW_ON_ERROR );
	} );
	try {
		if ( $GLOBALS['scenario']['dispatch'] ?? false ) {
			$controller->maybeBrowserCheck();
		} else {
			$controller->browserCheck();
		}
	} catch ( \RuntimeException $error ) {
		if ( 'fixture-stop' !== $error->getMessage() ) { throw $error; }
	}
}
