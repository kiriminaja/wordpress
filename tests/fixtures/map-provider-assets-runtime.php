<?php
/** Isolated registration harness running production map asset paths. */
namespace KiriminAjaOfficial\Base {
	function filter_input( $type, $name, $filter ) { return $GLOBALS['input'][ $name ] ?? null; }
}
namespace KiriminAjaOfficial\Services {
	function get_option( $name, $default = '' ) { return 'kiriof_google_maps_browser_key' === $name ? ( $GLOBALS['input']['key'] ?? '' ) : $default; }
	class InstantMapCoverageService {
		public function defaultCoverage() { return array(); }
		public function checkoutCoverage() { return array(); }
	}
	class CustomerShippingDestinationService {
		public function forCheckout( $session ) { return null; }
	}
}
namespace {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	define( 'KIRIOF_DIR', ABSPATH ); define( 'KIRIOF_VERSION', 'test' ); define( 'KIRIOF_NONCE', 'test' );
	$GLOBALS['input'] = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
	$GLOBALS['assets'] = array( 'scripts' => array(), 'styles' => array(), 'enqueued_scripts' => array(), 'enqueued_styles' => array() );
	function plugin_dir_path( $path ) { return ABSPATH; }
	function plugin_dir_url( $path ) { return 'https://shop.example/plugin/'; }
	function plugin_basename( $path ) { return basename( $path ); }
	function wp_script_is( $handle, $status = 'registered' ) { return isset( $GLOBALS['assets']['scripts'][$handle] ); }
	function wp_style_is( $handle, $status = 'registered' ) { return isset( $GLOBALS['assets']['styles'][$handle] ); }
	function wp_register_script( $handle, $src, $deps = array(), $version = false, $args = false ) { $GLOBALS['assets']['scripts'][$handle] = compact( 'src', 'deps' ); }
	function wp_register_style( $handle, $src, $deps = array(), $version = false ) { $GLOBALS['assets']['styles'][$handle] = compact( 'src', 'deps' ); }
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $version = false, $args = false ) { if ( $src ) { wp_register_script( $handle, $src, $deps ); } $GLOBALS['assets']['enqueued_scripts'][] = $handle; }
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false, $media = '' ) { if ( $src ) { wp_register_style( $handle, $src, $deps ); } $GLOBALS['assets']['enqueued_styles'][] = $handle; }
	function wp_script_add_data( ...$args ) {}
	function wp_localize_script( ...$args ) {}
	function wp_set_script_translations( ...$args ) {}
	function wp_add_inline_style( ...$args ) {}
	function add_filter( ...$args ) {}
	function apply_filters( $name, $value ) { return $value; }
	function wp_kses_post( $value ) { return $value; }
	function __( $value, $domain = '' ) { return $value; }
	function admin_url( $path ) { return 'https://shop.example/wp-admin/' . $path; }
	function wp_create_nonce( $action ) { return 'test'; }
	function get_current_user_id() { return 7; }
	function is_account_page() { return 'account' === $GLOBALS['input']['context']; }
	function is_wc_endpoint_url( $endpoint ) { return true; }
	function get_query_var( $key ) { return 'shipping'; }
	function get_current_screen() { return (object) array( 'id' => $GLOBALS['input']['screen'] ?? '' ); }
	spl_autoload_register( static function( $class ) {
		$prefix = 'KiriminAjaOfficial\\';
		if ( 0 === strpos( $class, $prefix ) ) {
			$path = ABSPATH . 'inc/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( file_exists( $path ) ) { require_once $path; }
		}
	} );
	$enqueue = new \KiriminAjaOfficial\Base\Enqueue();
	switch ( $GLOBALS['input']['context'] ) {
		case 'blocks': $enqueue->register_buyer_checkout_assets(); break;
		case 'classic': $enqueue->register_classic_checkout_assets(); break;
		case 'account': ( new \KiriminAjaOfficial\Controllers\AccountShippingDestinationController() )->assets(); break;
		default: $enqueue->enqueueAdmin();
	}
	$GLOBALS['assets']['map'] = $enqueue->map_checkout_config();
	$GLOBALS['assets']['detailMap'] = \KiriminAjaOfficial\Services\InstantDetailMapData::mapConfig();
	echo json_encode( $GLOBALS['assets'], JSON_THROW_ON_ERROR );
}
