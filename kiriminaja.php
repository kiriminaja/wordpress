<?php

/**
 * Plugin Name:     KiriminAja
 * Plugin URI:      https://developer.kiriminaja.com
 * Description:     Integrate to all best delivery services across the nusantara
 * Version:         2.0.0
 * Author:          KiriminAja
 * Author URI:      https://kiriminaja.com
 * License:         GPL-2.0-or-later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html 
 * Text Domain:     kiriminaja
 * Domain Path:     /lang
 * WC requires at least: 5.0.0
 * WC tested up to: 7.1
 */

/** prevent unauthorized access othe than wordpress */
if ( defined( 'XMLRPC_REQUEST' ) || defined( 'REST_REQUEST' ) || ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) || wp_doing_ajax() ) {
    @ini_set( 'display_errors', 1 );
}

date_default_timezone_set('Asia/Jakarta'); // Atur timezone ke GMT+7

define( 'KJ_PLUGIN_VERSION', rand(0,999));
define( 'KJ_DIR', plugin_dir_path( __FILE__ ));
define( 'KJ_URL', plugin_dir_url( __FILE__ ));
define( 'KJ_NONCE', 'kj-nonce');
define('KIRIMINAJA_VERSION', '2.0.0');
define('KJ_SLUG' ,plugin_basename(__DIR__));
define('KJ_SLUG_FILE',plugin_basename(__FILE__) );

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

/** Activation*/
function activate_kj_plugin(){
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


