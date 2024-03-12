<?php

/**
 * Plugin Name:     KiriminAja
 * Plugin URI:      https://developer.kiriminaja.com
 * Description:     Integrate to all best delivery services across the nusantara
 * Version:         1.0
 * Author:          KiriminAja
 * Author URI:      https://kiriminaja.com
 * License:         GPL
 * Text Domain:     kiriminaja
 * Domain Path:     /languages
 * WC requires at least: 5.0.0
 * WC tested up to: 7.1
 */

/** prevent unauthorized access othe than wordpress */

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
    Inc\Base\Activate::activate();
    (new \Inc\Migration\SetupMigration())->register();
}

/** Deactivation*/
function deactivate_kj_plugin(){
    Inc\Base\Deactivate::deactivate();
}

/** Services*/
if (class_exists('Inc\\Init')){
    Inc\Init::register_services();
}

/** activation*/
register_activation_hook(__FILE__, 'activate_kj_plugin');
/** deactivation*/
register_deactivation_hook(__FILE__, 'deactivate_kj_plugin');


