<?php
/**
 * ParaTest bootstrap for unit, runtime, and plugin validation tests.
 *
 * Loads plugin classes and test doubles without booting WordPress.
 */

define('PLUGIN_DIR', dirname(__DIR__));
define('PLUGIN_SLUG', 'kiriminaja-official');
define('PLUGIN_PREFIX', 'kiriof_');
define('PLUGIN_DEFINE_PREFIX', 'KIRIOF_');

require_once PLUGIN_DIR . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', PLUGIN_DIR . '/');
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value ) {
        $GLOBALS['tracking_page_test_options'][ $option ]  = $value;
        $GLOBALS['shipping_method_test_options'][ $option ] = $value;

        return true;
    }
}
