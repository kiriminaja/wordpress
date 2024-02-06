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

/** Post Types*/
if (!class_exists('KiriminAjaV2')){
    class KiriminAjaV2 {
        function __construct(){
            add_action('init', array($this, 'custom_post_type'));
        }
        function custom_post_type() {
            register_post_type('package', ['public' => true, 'label' => 'Packages']);
        }
    }
    if ( class_exists('KiriminAjaV2') ){
        $KiriminAjaV2 =  new KiriminAjaV2();
    }
}


/** AJAX */
/** Test */
add_action('wp_ajax_my_action', 'my_function');
add_action('wp_ajax_nopriv_my_action', 'my_function');
function my_function() {
    $data = $_POST['data'];
    wp_send_json_success($data);
}

add_action('wp_ajax_my_action_2', 'my_function_2');
add_action('wp_ajax_nopriv_my_action_2', 'my_function_2');
function my_function_2() {

    global $wpdb;

    /** Settings Table*/
    $table_name = $wpdb->prefix.'kiriminaja_settings';
    /** Delete if table exist */
    if($wpdb->get_var( "show tables like '$table_name'" ) == $table_name ){
        $sql = "DROP TABLE IF EXISTS $table_name";
        $wpdb->query($sql);
        delete_option("my_plugin_db_version");
    }
    $sql = "CREATE TABLE ".$table_name."(
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            `key` varchar(255) NULL,
            `value` varchar(255) NULL,
            UNIQUE KEY id (id)
            );";
    require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
    dbDelta($sql);

    /** Settings Table Value*/
    $wpdb->query("INSERT INTO ".$table_name."
            (`key`, `value`)
            VALUES
            ('api_key', null),
            ('setup_key', null),
            ('oid_prefix', null),
            ('origin_name', null),
            ('origin_phone', null),
            ('origin_address', null),
            ('origin_sub_district_id', null),
            ('origin_sub_district_name', null),
            ('origin_latitude', null),
            ('origin_longitude', null),
            ('link_callback', null)
            ");

    /** Transactions Table*/
    $table_name = $wpdb->prefix.'kiriminaja_transactions';
    /** Delete if table exist */
    if($wpdb->get_var( "show tables like '$table_name'" ) == $table_name ){
        $sql = "DROP TABLE IF EXISTS $table_name";
        $wpdb->query($sql);
        delete_option("my_plugin_db_version");
    }
    $sql = "CREATE TABLE ".$table_name."(
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id varchar(100) NULL ,
            pickup_number varchar(100) NULL ,
            awb varchar(100) NULL ,
            rejected_reason varchar(255) NULL,
            shipping_cost double NULL,
            insurance_cost double NULL,
            cod_fee double NULL,
            transaction_value double NULL,
            shipped_at timestamp NULL,
            return_finished_at timestamp NULL,
            finished_at timestamp NULL,
            rejected_at timestamp NULL,
            returned_at timestamp NULL,
            UNIQUE KEY id (id)
            );";
    require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
    dbDelta($sql);

    /** Payments Table*/
    $table_name = $wpdb->prefix.'kiriminaja_payments';
    /** Delete if table exist */
    if($wpdb->get_var( "show tables like '$table_name'" ) == $table_name ){
        $sql = "DROP TABLE IF EXISTS $table_name";
        $wpdb->query($sql);
        delete_option("my_plugin_db_version");
    }
    $sql = "CREATE TABLE ".$table_name."(
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            pickup_number varchar(100) NULL,
            status varchar(50) NULL,
            `method` varchar(50) NULL,
            UNIQUE KEY id (id)
            );";
    require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    $data = $_POST['data'];
    wp_send_json_success($data);
}

if (! function_exists('localMoneyFormat')) {
    function localMoneyFormat($val)
    {
        return number_format($val, 0,',','.');
    }
}

//function register(){
//    add_action('wp_ajax_get_integration_data', 'getIntegrationData');
//    add_action('wp_ajax_nopriv_get_integration_data', 'getIntegrationData');
//}
//function getIntegrationData() {
//    try {
//        $response = [
//            'setup_key' => 'loremipsupdolor',
//            'oid_prefix' => 'LRM-'
//        ];
//        wp_send_json_success($response);
//    }catch (Throwable $e){
//        wp_send_json_success([]);
//    }
//}
//register();
//
//add_action( 'init', 'wpse_50841_register_extra_page' );
//function wpse_50841_register_extra_page()
//{
//    add_feed( 'wpse50841', 'wpse_50841_callback' );
//}
//function wpse_50841_callback()
//{
//    wp_send_json_success([
//        'status'=>true,
//        'data'=>[]
//    ]);
//}
