<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Override default WooCommerce templates and template parts from plugin.
 * 
 * E.g.
 * Override template 'woocommerce/loop/result-count.php' with 'my-plugin/woocommerce/loop/result-count.php'.
 * Override template part 'woocommerce/content-product.php' with 'my-plugin/woocommerce/content-product.php'.
 *
 * Note: We used folder name 'woocommerce' in plugin to override all woocommerce templates and template parts.
 * You can change it as per your requirement.
 */
// Override Template Part's.
add_filter( 'wc_get_template_part', 'kiriof_override_woocommerce_template_part', 10, 3 );
// Override Template's.
add_filter( 'woocommerce_locate_template', 'kiriof_override_woocommerce_template', 10, 3 );

// Keep this list narrow. Overriding passive WooCommerce templates causes
// outdated-template warnings whenever WooCommerce bumps core templates.
function kiriof_allowed_woocommerce_template_overrides() {
    return array(
        'cart/cart.php',
        'cart/cart-shipping.php',
        'cart/cart-totals.php',
        'cart/shipping-calculator.php',
        'checkout/review-order.php',
    );
}
/**
 * Template Part's
 *
 * @param  string $template Default template file path.
 * @param  string $slug     Template file slug.
 * @param  string $name     Template file name.
 * @return string           Return the template part from plugin.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Required for WooCommerce filter callback
function kiriof_override_woocommerce_template_part( $template, $slug, $name ) {
    $template_name = $name ? "{$slug}-{$name}.php" : "{$slug}.php";
    if ( ! in_array( $template_name, kiriof_allowed_woocommerce_template_overrides(), true ) ) {
        return $template;
    }

    // Template directory.
    // E.g. /wp-content/plugins/my-plugin/templates/woocommerce/
    $template_directory = str_replace('\\','/',untrailingslashit( str_replace( 'wc/','',plugin_dir_path( __FILE__ ) ) ) . '/templates/woocommerce/');
    if ( $name ) {
        $path = $template_directory . "{$slug}-{$name}.php";
    } else {
        $path = $template_directory . "{$slug}.php";
    }
    return file_exists( $path ) ? $path : $template;
}
/**
 * Template File
 *
 * @param  string $template      Default template file  path.
 * @param  string $template_name Template file name.
 * @param  string $template_path Template file directory file path.
 * @return string                Return the template file from plugin.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Required for WooCommerce filter callback
function kiriof_override_woocommerce_template( $template, $template_name, $template_path ) {
    if ( ! in_array( $template_name, kiriof_allowed_woocommerce_template_overrides(), true ) ) {
        return $template;
    }

    // Respect a theme or another extension that already supplied this template.
    // Replacing a child-theme checkout template is a common source of broken
    // checkout markup and duplicate fields. The plugin override is only a
    // fallback when WooCommerce selected its own core template.
    if ( kiriof_is_non_core_template( $template, $template_name ) ) {
        return $template;
    }

    // Template directory.
    // E.g. /wp-content/plugins/my-plugin/woocommerce/
    $template_directory = str_replace('\\','/',untrailingslashit( str_replace( 'wc/','',plugin_dir_path( __FILE__ ) ) ) . '/templates/woocommerce/');
    $path = $template_directory . $template_name;
    return file_exists( $path ) ? $path : $template;
}

/**
 * Determine whether WooCommerce selected a theme/plugin template instead of core.
 *
 * @param string $template      Resolved template path.
 * @param string $template_name Template relative path.
 * @return bool
 */
function kiriof_is_non_core_template( $template, $template_name ) {
    if ( ! is_string( $template ) || '' === $template || ! file_exists( $template ) ) {
        return false;
    }

    $core_template = '';
    if ( defined( 'WC_TEMPLATE_PATH' ) ) {
        $core_template = trailingslashit( WC_TEMPLATE_PATH ) . $template_name;
    } elseif ( function_exists( 'WC' ) && WC() && method_exists( WC(), 'plugin_path' ) ) {
        $core_template = trailingslashit( WC()->plugin_path() ) . 'templates/' . $template_name;
    }

    if ( '' === $core_template || ! file_exists( $core_template ) ) {
        return false;
    }

    $resolved_template = realpath( $template );
    $resolved_core     = realpath( $core_template );

    return false !== $resolved_template && false !== $resolved_core && $resolved_template !== $resolved_core;
}
