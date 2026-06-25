<?php
/**
 * Plugin Name:     KiriminAja Official
 * Plugin URI:      https://wordpress.org/plugins/kiriminaja-official/
 * Description:     Ship smarter with KiriminAja — real-time rates from multiple couriers, COD support, one-click pickup scheduling, label printing, and package tracking, all from your WooCommerce dashboard. Built for online sellers across Indonesia.
 * Version:         2.2.4
 * Author:          KiriminAja
 * Author URI:      https://kiriminaja.com
 * License:         GPL-2.0-or-later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html 
 * Text Domain:     kiriminaja-official
 * Domain Path:     /lang
 * Requires Plugins: woocommerce
 * Requires at least: 6.8
 * Requires PHP: 8.0
 * WC requires at least: 8.0.0
 * WC tested up to: 10.6
 */

/** prevent unauthorized access other than wordpress */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'KIRIOF_DIR', plugin_dir_path( __FILE__ ) );
define( 'KIRIOF_URL', plugin_dir_url( __FILE__ ) );
define( 'KIRIOF_NONCE', 'kiriof-nonce' );
define( 'KIRIOF_SLUG', plugin_basename( __DIR__ ) );
define( 'KIRIOF_SLUG_FILE', plugin_basename( __FILE__ ) );
define( 'KIRIOF_VERSION', '2.2.4' );
define( 'KIRIOF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'KIRIOF_MAX_COD_AMOUNT', 3000000 );

if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
    require_once dirname( __FILE__ ) . '/vendor/autoload.php';
}

/** Helper functions */
if ( ! function_exists( 'kiriof_check_woocommerce' ) ) {
    function kiriof_check_woocommerce() {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress hook
        return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
    }
}
if ( ! function_exists( 'kiriof_money_format' ) ) {
    function kiriof_money_format( $val ) {
        return number_format( $val, 0, ',', '.' );
    }
}
if ( ! function_exists( 'kiriof_helper' ) ) {
    function kiriof_helper() {
        return ( new \KiriminAjaOfficial\Base\Helper() );
    }
}
if ( ! function_exists( 'kiriof_get_tracking_page_id' ) ) {
    function kiriof_get_tracking_page_id() {
        $page_id = absint( get_option( 'kiriof_tracking_page_id', 0 ) );
        if ( $page_id > 0 && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
            return $page_id;
        }

        $shortcode_page_id = kiriof_find_tracking_shortcode_page_id();
        if ( $shortcode_page_id > 0 ) {
            return $shortcode_page_id;
        }

        $tracking_page = get_page_by_path( 'tracking' );
        if ( $tracking_page instanceof \WP_Post ) {
            return (int) $tracking_page->ID;
        }

        return 0;
    }
}
if ( ! function_exists( 'kiriof_find_tracking_shortcode_page_id' ) ) {
    function kiriof_find_tracking_shortcode_page_id() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'page'
               AND post_status NOT IN ('trash', 'auto-draft')
               AND (
                   post_content LIKE '%[kiriminaja-tracking-front-page%'
                   OR post_content LIKE '%[wp-tracking-front-page%'
               )
             ORDER BY post_status = 'publish' DESC, ID ASC
             LIMIT 1"
        );
    }
}
if ( ! function_exists( 'kiriof_get_tracking_page_url' ) ) {
    function kiriof_get_tracking_page_url( $query_args = array() ) {
        $page_id = kiriof_get_tracking_page_id();
        $url     = $page_id > 0 ? get_permalink( $page_id ) : home_url( '/tracking' );

        if ( ! is_array( $query_args ) || empty( $query_args ) ) {
            return $url;
        }

        return add_query_arg( array_map( 'sanitize_text_field', $query_args ), $url );
    }
}
/**
 * Recursively sanitize a value coming from a request superglobal.
 *
 * - Arrays are walked recursively.
 * - Scalars are passed through sanitize_text_field().
 * - Anything else is cast to an empty string.
 */
if ( ! function_exists( 'kiriof_sanitize_recursive' ) ) {
    /**
     * @param mixed $value Raw value from $_POST/$_GET/$_REQUEST (already wp_unslash'd).
     * @return mixed Sanitized value with the same shape as the input.
     */
    function kiriof_sanitize_recursive( $value ) {
        if ( is_array( $value ) ) {
            $clean = array();
            foreach ( $value as $key => $item ) {
                $clean_key           = is_string( $key ) ? sanitize_key( $key ) : $key;
                $clean[ $clean_key ] = kiriof_sanitize_recursive( $item );
            }
            return $clean;
        }
        if ( is_object( $value ) ) {
            $clean = new \stdClass();
            foreach ( get_object_vars( $value ) as $key => $item ) {
                $clean_key           = is_string( $key ) ? sanitize_key( $key ) : $key;
                $clean->{$clean_key} = kiriof_sanitize_recursive( $item );
            }
            return $clean;
        }
        if ( is_scalar( $value ) ) {
            return sanitize_text_field( (string) $value );
        }
        return null;
    }
}

if ( ! function_exists( 'kiriof_log' ) ) {
    /**
     * Helper to log messages via the WooCommerce logger.
     *
     * @param string       $level   emergency|alert|critical|error|warning|notice|info|debug.
     * @param string       $message Log message in English, single line.
     * @param array<mixed> $context Additional structured data.
     * @param string|null  $source  Optional source override.
     * @return bool
     */
    function kiriof_log( $level, $message, $context = array(), $source = null ) {
        $level = strtolower( sanitize_key( (string) $level ) );

        if ( ! is_array( $context ) ) {
            $context = array(
                'context_value' => $context,
            );
        }

        if ( null !== $source && '' !== $source ) {
            $context['source'] = $source;
        }

        if ( ! \KiriminAjaOfficial\Utils\Logger::isValidLevel( $level ) ) {
            return false;
        }

        \KiriminAjaOfficial\Utils\Logger::{$level}( (string) $message, $context );
        return true;
    }
}

add_action( 'plugins_loaded', 'kiriof_bootstrap_logger', 5 );
function kiriof_bootstrap_logger() {
    add_filter( 'woocommerce_log_directory', 'kiriof_filter_woocommerce_log_directory' );
    add_filter( 'woocommerce_logger_log_message', 'kiriof_filter_woocommerce_logger_message', 10, 4 );
}

function kiriof_filter_woocommerce_log_directory( $directory ) {
    $custom_directory = apply_filters( 'kiriof_log_directory', '', $directory );

    if ( ! is_string( $custom_directory ) || '' === trim( $custom_directory ) ) {
        return $directory;
    }

    return trailingslashit( $custom_directory );
}

function kiriof_filter_woocommerce_logger_message( $message, $level, $context, $handler ) {
    $source   = is_array( $context ) ? (string) ( $context['source'] ?? '' ) : '';
    $patterns = apply_filters( 'kiriof_logger_suppressed_messages', array(), $level, $context, $handler );

    if ( empty( $patterns ) || 0 !== strpos( $source, 'kiriminaja' ) ) {
        return $message;
    }

    foreach ( (array) $patterns as $pattern ) {
        if ( is_string( $pattern ) && '' !== $pattern && false !== strpos( (string) $message, $pattern ) ) {
            return null;
        }
    }

    return $message;
}

add_action( 'plugins_loaded', 'kiriof_load_textdomain' );
function kiriof_load_textdomain() {
    // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Kept for compatibility outside wp.org language-pack loading.
    load_plugin_textdomain(
        'kiriminaja-official',
        false,
        dirname( KIRIOF_PLUGIN_BASENAME ) . '/lang'
    );
}

add_action( 'admin_notices', 'kiriof_woocommerce_notice' );
function kiriof_woocommerce_notice() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if ( ! class_exists( 'WooCommerce' ) && is_plugin_active( plugin_basename( __FILE__ ) ) ) {

        $message = sprintf(
            wp_kses(
                /* translators: %1$s: Plugin name, %2$s: WooCommerce. */
                __( '<strong>%1$s</strong> requires <strong>%2$s</strong> to be installed and activated. Please install and activate WooCommerce to continue using this plugin.', 'kiriminaja-official' ),
                [ 'strong' => [] ]
            ),
            __( 'Plugin Kiriminaja', 'kiriminaja-official' ),
            __( 'WooCommerce', 'kiriminaja-official' )
        );

        echo '<div class="notice notice-error"><p>' . wp_kses_post($message) . '</p></div>';

        deactivate_plugins( plugin_basename( __FILE__ ) );
    }
}

/** Activation*/
function kiriof_activate_plugin() {

    if ( ! class_exists( 'WooCommerce' ) ) {
        // Deactivate the plugin
        deactivate_plugins( plugin_basename( __FILE__ ) );

        // Display admin notice
        $message = sprintf(
            wp_kses(
                /* translators: %1$s: Plugin name, %2$s: WooCommerce. */
                __(
                    '%1$s requires %2$s to be installed and activated. Please install and activate WooCommerce before activating this plugin.',
                    'kiriminaja-official'
                ),
                [] // No HTML allowed in the translatable string
            ),
            '<strong>Plugin Kiriminaja</strong>',
            '<strong>WooCommerce</strong>'
        );

        $message .= '<p><a href="' . esc_url(admin_url('plugins.php')) . '">&laquo; ' . esc_html__('Return to Plugins', 'kiriminaja-official') . '</a></p>';

        // Output the error message
        wp_die(
            wp_kses_post('<p>' . $message . '</p>'),
            esc_html__('Plugin Activation Error', 'kiriminaja-official')
        );

    }

    (new \KiriminAjaOfficial\Migration\SetupMigration())->register();
    (new \KiriminAjaOfficial\Base\Activate())->activate();
    (new \KiriminAjaOfficial\Pages\AdminPost())->register();

}
/** Deactivation*/
/** Deactivation */
function kiriof_deactivate_plugin() {
    ( new \KiriminAjaOfficial\Base\Deactivate() )->deactivate();
}

/** activation*/
register_activation_hook( __FILE__, 'kiriof_activate_plugin' );
/** deactivation*/
register_deactivation_hook( __FILE__, 'kiriof_deactivate_plugin' );

/** Run migration on plugin update */
add_action( 'upgrader_process_complete', 'kiriof_plugin_update_migration', 10, 2 );
function kiriof_plugin_update_migration( $upgrader_object, $options ) {
    // Check if this is a plugin update
    if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
        // Check if our plugin is being updated
        if (isset($options['plugins'])) {
            foreach ($options['plugins'] as $plugin) {
                if ($plugin == plugin_basename(__FILE__)) {
                    // Load autoloader if not loaded yet
                    if ( file_exists(dirname(__FILE__) . '/vendor/autoload.php')){
                        require_once dirname(__FILE__) . '/vendor/autoload.php';
                    }
                    
                    // Run migration only if class exists
                    if (class_exists('\KiriminAjaOfficial\Migration\SetupMigration')) {
                        (new \KiriminAjaOfficial\Migration\SetupMigration())->register();
                    }
                    break;
                }
            }
        }
    }
}

/** Services */
if ( class_exists( 'KiriminAjaOfficial\\Init' ) ) {
    // Defer to plugins_loaded so WooCommerce (and its textdomain) are fully
    // available before any of our controllers hook into WC APIs. Running at
    // file-load time can trigger WC translations before `init`, which emits
    // a "_load_textdomain_just_in_time was called incorrectly" notice.
    add_action( 'plugins_loaded', [ 'KiriminAjaOfficial\\Init', 'register_services' ] );
}

/**
 * load 
 * function hook folder wc
 */
$kiriof_woo_files = [
    'KiriminajaShippingMethod',
    'OverwriteWoocommercePlugin',
    'AdminWoocommerceSetting',
];
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Internal loop variable
foreach ( $kiriof_woo_files as $namefile ) {
    include_once KIRIOF_DIR . '/wc/' . $namefile . '.php';
}

/** 
 * WooCommerce Init
 * compatibility HPOS version
*/
add_action( 'before_woocommerce_init', 'kiriof_before_woocommerce_init' );
function kiriof_before_woocommerce_init() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
}

/**
 * Delete shipping zone utility
 */
function kiriof_delete_shipping_zone() {
    $data_store = WC_Data_Store::load( 'shipping-zone' );
    $raw_zones  = $data_store->get_zones();
    
    foreach ( $raw_zones as $raw_zone ) {
        $data_methods = empty( $data_store->get_methods( $raw_zone->zone_id, false ) ) ? $data_store->get_methods( $raw_zone->zone_id, true ) : $data_store->get_methods( $raw_zone->zone_id, false );
        foreach ( $data_methods as $methode ) {
            $data_store->delete_method( (int) $methode->instance_id );
        }
    }
}

/** 
 * Add filter to disable sslverify
 * set true to enable sslverify
 * set false to disable sslverify
 */
add_filter( 'http_request_args', 'kiriof_set_ssl_verify', 10, 2 );
function kiriof_set_ssl_verify( $args, $url ) {
    $args['sslverify'] = true; 
    return $args;
}
