<?php
/** Isolated legacy settings hooks, asset routing, and Woo General rendering. */
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'KIRIOF_DIR', ABSPATH );
define( 'KIRIOF_URL', 'https://example.test/plugin/' );
define( 'KIRIOF_VERSION', 'test' );
define( 'KIRIOF_NONCE', 'test' );
require_once ABSPATH . 'vendor/autoload.php';
$input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
$_GET = $input['query'] ?? array();
$GLOBALS['hooks'] = $GLOBALS['scripts'] = $GLOBALS['styles'] = array();
function add_action( $hook, $callback, ...$args ) { $GLOBALS['hooks'][$hook] = $callback[1]; }
function add_filter( $hook, $callback, ...$args ) { $GLOBALS['hooks'][$hook] = $callback[1]; }
function get_current_screen() { return (object) array( 'id' => $GLOBALS['input']['screen'] ?? '' ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function __( $value, $domain = '' ) { return $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return $value; }
function wp_kses( $value, $allowed ) { return $value; }
function wp_kses_post( $value ) { return $value; }
function wc_help_tip( $value ) { return '<span>' . esc_html( $value ) . '</span>'; }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function wp_create_nonce( $action ) { return 'nonce'; }
function get_option( $key, $default = false ) { return $default; }
function wp_enqueue_script( $handle, ...$args ) { $GLOBALS['scripts'][] = $handle; }
function wp_enqueue_style( $handle, ...$args ) { $GLOBALS['styles'][] = $handle; }
function wp_localize_script( ...$args ) {}
function wp_script_add_data( ...$args ) {}
$repository = new class extends \KiriminAjaOfficial\Repositories\SettingRepository {
	public function __construct() {}
	public function getSettingByArray( $keys ) {
		return array(
			(object) array( 'key' => 'origin_name', 'value' => 'Store Sender' ),
			(object) array( 'key' => 'origin_sub_district_id', 'value' => '123' ),
			(object) array( 'key' => 'origin_sub_district_name', 'value' => 'Store Area' ),
			(object) array( 'key' => 'origin_latitude', 'value' => '-6.2' ),
			(object) array( 'key' => 'origin_longitude', 'value' => '106.8' ),
		);
	}
};
$tracking = new class implements \KiriminAjaOfficial\Contracts\TrackingPageRepositoryInterface {
	public function hasPublishedTrackingPage(): bool { return false; }
	public function findPublishedTrackingContent(): array { return array(); }
	public function findTrackingShortcodePages(): array { return array(); }
	public function findPreferredTrackingShortcodePage() { return null; }
};
$controller = new \KiriminAjaOfficial\Controllers\SettingController( $tracking, $repository );
$controller->register();
$controller->enqueueSettingsScript();
$fields = $controller->injectWooCommerceGeneralSettings( array(
	array( 'id' => 'woocommerce_store_address' ),
	array( 'id' => 'woocommerce_default_country' ),
) );
ob_start();
$controller->renderWooCommerceAreaSelectField( array( 'title' => 'Area' ) );
$controller->renderWooCommercePinLocationField( array( 'title' => 'Pin' ) );
$html = ob_get_clean();
echo json_encode( array( 'hooks' => $GLOBALS['hooks'], 'scripts' => $GLOBALS['scripts'], 'styles' => $GLOBALS['styles'], 'fields' => $fields, 'html' => $html ), JSON_THROW_ON_ERROR );
