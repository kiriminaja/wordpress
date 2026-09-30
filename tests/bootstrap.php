<?php
/**
 * ParaTest bootstrap file for plugin validation tests.
 *
 * These tests validate the plugin structure, coding standards,
 * and WordPress.org review requirements WITHOUT loading WordPress.
 */

define('PLUGIN_DIR', dirname(__DIR__));
define('PLUGIN_SLUG', 'kiriminaja-official');
define('PLUGIN_PREFIX', 'kiriof_');
define('PLUGIN_DEFINE_PREFIX', 'KIRIOF_');

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value ) {
        $GLOBALS['tracking_page_test_options'][ $option ]  = $value;
        $GLOBALS['shipping_method_test_options'][ $option ] = $value;

        return true;
    }
}
