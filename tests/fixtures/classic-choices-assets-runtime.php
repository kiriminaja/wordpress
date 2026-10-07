<?php
/** Isolated WP registration harness; executes the real enqueue and config paths. */
namespace KiriminAjaOfficial\Services {
	class ShipmentLocationService {}
	class InstantCheckoutQuoteService {
		public function __construct( $settings, $locations ) {}
		public function enabledInstant() { return array(); }
	}
}
namespace KiriminAjaOfficial\Repositories { class SettingRepository {} }
namespace {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	require ABSPATH . 'inc/Services/CourierLogoAssets.php';
	define( 'KIRIOF_DIR', ABSPATH );
	define( 'KIRIOF_URL', 'https://shop.example/wp-content/plugins/kiriminaja/' );
	define( 'KIRIOF_VERSION', 'test' );
	define( 'KIRIOF_NONCE', 'choices-assets-test' );
	$input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
	$GLOBALS['choices_page'] = $input['page'] ?? 'classic';
	$GLOBALS['choices_assets'] = array( 'scripts' => array(), 'styles' => array(), 'enqueued_styles' => array(), 'localized' => array() );
	$GLOBALS['post'] = (object) array();
	function is_checkout() { return in_array( $GLOBALS['choices_page'], array( 'classic', 'blocks', 'received' ), true ); }
	function is_cart() { return 'cart' === $GLOBALS['choices_page']; }
	function is_account_page() { return 'account' === $GLOBALS['choices_page']; }
	function is_woocommerce() { return false; }
	function is_wc_endpoint_url( $endpoint ) { return 'received' === $GLOBALS['choices_page'] && 'order-received' === $endpoint; }
	function has_block( $block, $post ) { return 'blocks' === $GLOBALS['choices_page'] && 'woocommerce/checkout' === $block; }
	function apply_filters( $hook, $value ) { return $value; }
	function plugin_dir_path( $path ) { return ABSPATH; }
	function plugin_dir_url( $path ) { return 'https://shop.example/wp-content/plugins/kiriminaja/'; }
	function plugin_basename( $path ) { return basename( $path ); }
	function admin_url( $path ) { return 'https://shop.example/wp-admin/' . $path; }
	function rest_url( $path ) { return 'https://shop.example/wp-json/' . $path; }
	function wp_create_nonce( $action ) { return 'nonce:' . $action; }
	function __( $text, $domain ) { return $text; }
	function wp_register_script( $handle, $src, $deps = array(), $version = false, $args = false ) {
		$GLOBALS['choices_assets']['scripts'][ $handle ] = compact( 'src', 'deps', 'version', 'args' );
	}
	function wp_script_is( $handle, $status = 'registered' ) { return isset( $GLOBALS['choices_assets']['scripts'][$handle] ); }
	function wp_style_is( $handle, $status = 'registered' ) { return isset( $GLOBALS['choices_assets']['styles'][$handle] ); }
	function wp_register_style( $handle, $src, $deps = array(), $version = false ) {
		$GLOBALS['choices_assets']['styles'][ $handle ] = compact( 'src', 'deps', 'version' );
	}
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $version = false, $args = false ) {
		if ( $src ) { wp_register_script( $handle, $src, $deps, $version, $args ); }
	}
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false, $media = '' ) {
		if ( $src ) { wp_register_style( $handle, $src, $deps, $version ); }
		$GLOBALS['choices_assets']['enqueued_styles'][] = $handle;
	}
	function wp_localize_script( $handle, $name, $data ) { $GLOBALS['choices_assets']['localized'][ $name ] = $data; }
	require ABSPATH . 'inc/Base/BaseInit.php';
	require ABSPATH . 'inc/Base/Enqueue.php';
	// Block adapter configuration is unrelated to Choices; leave its own asset suite responsible for it.
	class ChoicesAssetsEnqueue extends \KiriminAjaOfficial\Base\Enqueue {
		public function register_buyer_checkout_assets( bool $localize = false ): void {}
	}
	( new ChoicesAssetsEnqueue() )->enqueueWp();
	$field_key = 'kiriof_destination_area';
	$kiriof_global_insurance = false;
	$kiriof_saved_destination_map = array();
	$kiriof_saved_checkout_postcode = '';
	$destination_id = '101';
	$destination_name = 'Billing village';
	$shipping_destination_id = '202';
	$shipping_destination_name = 'Shipping village';
	$GLOBALS['choices_assets']['config'] = require ABSPATH . 'templates/front/partials/form-billing-address-config.php';
	echo json_encode( $GLOBALS['choices_assets'], JSON_THROW_ON_ERROR );
}
