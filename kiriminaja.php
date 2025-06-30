<?php
/**
 * Plugin Name:     KiriminAja
 * Plugin URI:      https://developer.kiriminaja.com
 * Description:     Integrate to all best delivery services across the nusantara
 * Version:         2.0.6
 * Author:          KiriminAja
 * Author URI:      https://kiriminaja.com
 * License:         GPL-2.0-or-later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html 
 * Text Domain:     plugin-wp
 * Domain Path:     /lang
 * WC requires at least: 5.0.0
 * WC tested up to: 7.1
 */

/** prevent unauthorized access othe than wordpress */
if ( defined( 'XMLRPC_REQUEST' ) || defined( 'REST_REQUEST' ) || ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) || wp_doing_ajax() ) {
    @ini_set( 'display_errors', 1 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
}

// Atur timezone ke GMT+7
date_default_timezone_set('Asia/Jakarta'); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set

define( 'KJ_PLUGIN_VERSION', rand(0,999)); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand
define( 'KJ_DIR', plugin_dir_path( __FILE__ ));
define( 'KJ_URL', plugin_dir_url( __FILE__ ));
define( 'KJ_NONCE', 'kj-nonce');
define('KJ_SLUG' ,plugin_basename(__DIR__));
define('KJ_SLUG_FILE',plugin_basename(__FILE__) );
define('KJ_VERSION_PLUGIN', sanitize_text_field('2.0.6') );

/** opt 1 */
if ( ! defined( 'ABSPATH' ) ) { die; }

/** opt 2 */
defined('ABSPATH') or die('die !!!');

/** opt 3 */
if (!function_exists('add_action')){
    echo 'die !!!';
    exit;
}

if ( file_exists(dirname(__FILE__) . '/vendor/autoload.php')){
    require_once dirname(__FILE__) . '/vendor/autoload.php';
}

/** Define constants*/
if ( ! defined( 'KJ_PLUGIN_BASENAME' ) ) {
    define ('KJ_PLUGIN_BASENAME', plugin_basename(__FILE__));
}
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}


/** Helper*/
if (!function_exists('KJ_GENERATE_BARCODE')) {
    function KJ_GENERATE_BARCODE() {
        return new \Picqer\Barcode\BarcodeGeneratorPNG();
    }
}
if (!function_exists('KJ_CHECK_WOOCOMMERCE')) {
    function KJ_CHECK_WOOCOMMERCE() {
        return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
    }
}
if (! function_exists('localMoneyFormat')) {
    function localMoneyFormat($val)
    {
        return number_format($val, 0,',','.');
    }
}
if (! function_exists('kjHelper')) {
    function kjHelper()
    {
        return (new \Inc\Base\Helper());
    }
}

add_action( 'admin_notices', 'kj_shipping_plugin_woocommerce_notice' );
function kj_shipping_plugin_woocommerce_notice() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if ( ! class_exists( 'WooCommerce' ) && is_plugin_active( plugin_basename( __FILE__ ) ) ) {

        $message = sprintf(
            wp_kses(
                /* translators: %1$s: Plugin name, %2$s: WooCommerce. */
                __( '<strong>%1$s</strong> requires <strong>%2$s</strong> to be installed and activated. Please install and activate WooCommerce to continue using this plugin.', 'plugin-wp' ),
                [ 'strong' => [] ]
            ),
            __( 'Plugin Kiriminaja', 'plugin-wp' ),
            __( 'WooCommerce', 'plugin-wp' )
        );

        echo '<div class="notice notice-error"><p>' . wp_kses_post($message) . '</p></div>';

        deactivate_plugins( plugin_basename( __FILE__ ) );
    }
}

/** Activation*/
function activate_kj_plugin(){

    if ( ! class_exists( 'WooCommerce' ) ) {
        // Deactivate the plugin
        deactivate_plugins( plugin_basename( __FILE__ ) );

        // Display admin notice
        $message = sprintf(
            wp_kses(
                /* translators: %1$s: Plugin name, %2$s: WooCommerce. */
                __(
                    '%1$s requires %2$s to be installed and activated. Please install and activate WooCommerce before activating this plugin.',
                    'plugin-wp'
                ),
                [] // No HTML allowed in the translatable string
            ),
            '<strong>Plugin Kiriminaja</strong>',
            '<strong>WooCommerce</strong>'
        );

        $message .= '<p><a href="' . esc_url(admin_url('plugins.php')) . '">&laquo; ' . esc_html__('Return to Plugins', 'plugin-wp') . '</a></p>';

        // Output the error message
        wp_die(
            wp_kses_post('<p>' . $message . '</p>'),
            esc_html__('Plugin Activation Error', 'plugin-wp')
        );

    }

    (new \Inc\Migration\SetupMigration())->register();
    (new \Inc\Base\Activate())->activate();
    (new \Inc\Pages\AdminPost())->register();
    deleteShippingZone();

}
/** Deactivation*/
function deactivate_kj_plugin(){
    
    (new \Inc\Base\Deactivate())->deactivate();
}
/** activation*/
register_activation_hook(__FILE__, 'activate_kj_plugin');
/** deactivation*/
register_deactivation_hook(__FILE__, 'deactivate_kj_plugin');


/** Services*/
if (class_exists('Inc\\Init')){
    Inc\Init::register_services();
}

/**
 * load 
 * function hook folder wc
 */
$woo_files = [
    'KiriminajaShippingMethod',
    'OverwriteWoocommercePlugin',
    'AdminWoocommerceSetting'
];
foreach($woo_files as $namefile){
    include_once KJ_DIR .'/wc/'.$namefile.'.php';
}

/** 
 * WooCommerce Init
 * compatibility HPOS version
*/
add_action('before_woocommerce_init', 'kj_before_woocommerce_init');
function kj_before_woocommerce_init(){
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
}

function deleteShippingZone(){
    $data_store = WC_Data_Store::load( 'shipping-zone' );
    $raw_zones = $data_store->get_zones();
    
    $instance_id = [];
    foreach ( $raw_zones as $raw_zone ) {
        $data_methods = empty( $data_store->get_methods($raw_zone->zone_id,false) ) ? $data_store->get_methods($raw_zone->zone_id,true) : $data_store->get_methods($raw_zone->zone_id,false) ;
        foreach( $data_methods as $methode ){
            $data_store->delete_method((int) $methode->instance_id);
        }
    }
}

/** 
 * Add filter to disable sslverify
 * set true to enable sslverify
 * set false to disable sslverify
 * */
add_filter('http_request_args', 'setSSLVerifyWordpress',10, 2);
function setSSLVerifyWordpress($args, $url) {
    $args['sslverify'] = true; 
    return $args;
}