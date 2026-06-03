<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @var string $locale
 * @var bool $isOriginShippingDataReady
 * @var object|null $approvedSetupKey
 * @var array $inputValueArr
 */

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only section navigation
$kiriof_section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
$kiriof_base_url = admin_url( 'admin.php?page=kiriminaja-konfigurasi' );

// If a section is requested, show the detail page
if ( '' !== $kiriof_section ) {
    $kiriof_section_file = __DIR__ . '/section-' . $kiriof_section . '.php';
    if ( file_exists( $kiriof_section_file ) ) {
        include $kiriof_section_file;
    } else {
        echo '<div class="wrap"><p>' . esc_html__( 'Section not found.', 'kiriminaja-official' ) . '</p></div>';
    }
    return;
}

// Load COD state for the list page switch
$kiriof_cod_settings = get_option( 'woocommerce_cod_settings', array() );
$kiriof_cod_enabled  = isset( $kiriof_cod_settings['enabled'] ) ? $kiriof_cod_settings['enabled'] : 'yes';
$kiriof_insurance_setting = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('enable_insurance');
$kiriof_insurance_enabled = ( $kiriof_insurance_setting && 'yes' === $kiriof_insurance_setting->value ) ? 'yes' : 'no';
$kiriof_ship_to_countries = get_option( 'woocommerce_ship_to_countries', '' );
$kiriof_shipping_countries = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_shipping_countries() : array();
$kiriof_shipping_locations_ready = ( 'disabled' !== $kiriof_ship_to_countries && ! empty( $kiriof_shipping_countries ) );
$kiriof_wc_general_url = admin_url( 'admin.php?page=wc-settings' );

global $wpdb;
$kiriof_product_volumetric_from_sql = "
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->posts} child_variation
        ON child_variation.post_parent = p.ID
       AND child_variation.post_type = 'product_variation'
       AND child_variation.post_status IN ('publish','private')";
$kiriof_product_volumetric_where_sql = "
    WHERE (
          (p.post_type = 'product_variation' AND p.post_status IN ('publish','private'))
          OR (p.post_type = 'product' AND p.post_status = 'publish' AND child_variation.ID IS NULL)
      )";
$kiriof_product_volumetric_ready_sql = "
    (
        CAST(
            CASE
                WHEN p.post_type = 'product_variation'
                    THEN COALESCE(NULLIF(weight_meta.meta_value, ''), parent_weight_meta.meta_value, '0')
                ELSE COALESCE(weight_meta.meta_value, '0')
            END
            AS DECIMAL(10,2)
        ) > 0
        AND CAST(
            CASE
                WHEN p.post_type = 'product_variation'
                    THEN COALESCE(NULLIF(length_meta.meta_value, ''), parent_length_meta.meta_value, '0')
                ELSE COALESCE(length_meta.meta_value, '0')
            END
            AS DECIMAL(10,2)
        ) > 0
        AND CAST(
            CASE
                WHEN p.post_type = 'product_variation'
                    THEN COALESCE(NULLIF(width_meta.meta_value, ''), parent_width_meta.meta_value, '0')
                ELSE COALESCE(width_meta.meta_value, '0')
            END
            AS DECIMAL(10,2)
        ) > 0
        AND CAST(
            CASE
                WHEN p.post_type = 'product_variation'
                    THEN COALESCE(NULLIF(height_meta.meta_value, ''), parent_height_meta.meta_value, '0')
                ELSE COALESCE(height_meta.meta_value, '0')
            END
            AS DECIMAL(10,2)
        ) > 0
    )";

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query fragments are fully internal/static SQL snippets.
$kiriof_product_volumetric_total = (int) $wpdb->get_var(
    "SELECT COUNT(DISTINCT p.ID)
     {$kiriof_product_volumetric_from_sql}
     {$kiriof_product_volumetric_where_sql}"
);
$kiriof_product_volumetric_configured = (int) $wpdb->get_var(
    "SELECT COUNT(DISTINCT p.ID)
     {$kiriof_product_volumetric_from_sql}
     LEFT JOIN {$wpdb->postmeta} weight_meta ON weight_meta.post_id = p.ID AND weight_meta.meta_key = '_weight'
     LEFT JOIN {$wpdb->postmeta} length_meta ON length_meta.post_id = p.ID AND length_meta.meta_key = '_length'
     LEFT JOIN {$wpdb->postmeta} width_meta ON width_meta.post_id = p.ID AND width_meta.meta_key = '_width'
     LEFT JOIN {$wpdb->postmeta} height_meta ON height_meta.post_id = p.ID AND height_meta.meta_key = '_height'
     LEFT JOIN {$wpdb->postmeta} parent_weight_meta ON parent_weight_meta.post_id = p.post_parent AND parent_weight_meta.meta_key = '_weight'
     LEFT JOIN {$wpdb->postmeta} parent_length_meta ON parent_length_meta.post_id = p.post_parent AND parent_length_meta.meta_key = '_length'
     LEFT JOIN {$wpdb->postmeta} parent_width_meta ON parent_width_meta.post_id = p.post_parent AND parent_width_meta.meta_key = '_width'
     LEFT JOIN {$wpdb->postmeta} parent_height_meta ON parent_height_meta.post_id = p.post_parent AND parent_height_meta.meta_key = '_height'
     {$kiriof_product_volumetric_where_sql}
       AND {$kiriof_product_volumetric_ready_sql}"
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
$kiriof_product_volumetric_ready = ( $kiriof_product_volumetric_total > 0 && $kiriof_product_volumetric_configured >= $kiriof_product_volumetric_total );
$kiriof_product_volumetric_status = $kiriof_product_volumetric_ready
    ? __( 'All Product Configured', 'kiriminaja-official' )
    : sprintf(
        /* translators: %1$d: configured products, %2$d: total products */
        __( '%1$d / %2$d Product Volumetric Configurations', 'kiriminaja-official' ),
        $kiriof_product_volumetric_configured,
        $kiriof_product_volumetric_total
    );
$kiriof_products_url = admin_url( 'edit.php?post_type=product' );
?>
<div class="wrap kj-wrap">

    <?php $kiriof_title = __( 'Settings', 'kiriminaja-official' ); include KIRIOF_DIR . 'templates/_header.php'; ?>
    <hr class="wp-header-end">

    <?php if (!kiriof_check_woocommerce() || kiriof_helper()->devForceTrue()): ?>
    <div class="kj-notice kj-notice-warning">
        <div><?php echo esc_html( __( 'WooCommerce is not yet installed or activated. This plugin only supports WooCommerce features.', 'kiriminaja-official' ) ); ?></div>
    </div>
    <?php endif; ?>

    <div class="kj-settings">

        <!-- Configuration -->
        <div class="kj-group-header"><?php echo esc_html( __( 'Configuration', 'kiriminaja-official' ) ); ?></div>

        <a href="<?php echo esc_url( $kiriof_base_url . '&section=account' ); ?>" class="kj-setting-row">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="#50575e" stroke-width="1.5"/><path d="M5 20c0-4 3.1-7 7-7s7 3 7 7" stroke="#50575e" stroke-width="1.5" stroke-linecap="round"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( __( 'Account Configuration', 'kiriminaja-official' ) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( __( 'Manage your KiriminAja account connection and profile.', 'kiriminaja-official' ) ); ?></span>
                </div>
                <svg class="kj-chevron" width="12" height="12" viewBox="0 0 12 12"><path d="M4 2l4 4-4 4" fill="none" stroke="#8c8f94" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>

        <!-- Online Store -->
        <div class="kj-group-header"><?php echo esc_html( __( 'Online Store', 'kiriminaja-official' ) ); ?></div>

        <a href="<?php echo esc_url( $kiriof_base_url . '&section=tracking' ); ?>" class="kj-setting-row">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="2" stroke="#50575e" stroke-width="1.5"/><path d="M3 9h18M9 21V9" stroke="#50575e" stroke-width="1.5"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( __( 'Tracking Page', 'kiriminaja-official' ) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( __( 'Configure the order tracking page for your customers.', 'kiriminaja-official' ) ); ?></span>
                </div>
                <svg class="kj-chevron" width="12" height="12" viewBox="0 0 12 12"><path d="M4 2l4 4-4 4" fill="none" stroke="#8c8f94" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>

        <!-- Shipping -->
        <div class="kj-group-header"><?php echo esc_html( __( 'Shipping', 'kiriminaja-official' ) ); ?></div>

        <a href="<?php echo esc_url( $kiriof_products_url ); ?>" class="kj-setting-row">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2L3 6.5v11L12 22l9-4.5v-11L12 2z" stroke="#50575e" stroke-width="1.5" stroke-linejoin="round"/><path d="M3 6.5l9 4.5 9-4.5M12 11v11" stroke="#50575e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( __( 'Product Volumetric Configurations', 'kiriminaja-official' ) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( __( 'Set weight, length, width, and height for every product and variation.', 'kiriminaja-official' ) ); ?></span>
                </div>
                <span class="kj-status-pill <?php echo $kiriof_product_volumetric_ready ? 'is-ready' : 'is-warning'; ?>">
                    <?php echo esc_html( $kiriof_product_volumetric_status ); ?>
                </span>
                <svg class="kj-chevron" width="12" height="12" viewBox="0 0 12 12"><path d="M4 2l4 4-4 4" fill="none" stroke="#8c8f94" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>

        <a href="<?php echo esc_url( $kiriof_wc_general_url ); ?>" class="kj-setting-row">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 21s7-6.12 7-12A7 7 0 1 0 5 9c0 5.88 7 12 7 12z" stroke="#50575e" stroke-width="1.5"/><path d="M9 9.5l2 2 4-4" stroke="#50575e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( __( 'WooCommerce Shipping Locations', 'kiriminaja-official' ) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( __( 'Set Shipping location(s) so WooCommerce can offer KiriminAja rates at checkout.', 'kiriminaja-official' ) ); ?></span>
                </div>
                <span class="kj-status-pill <?php echo $kiriof_shipping_locations_ready ? 'is-ready' : 'is-warning'; ?>">
                    <?php echo esc_html( $kiriof_shipping_locations_ready ? __( 'Ready', 'kiriminaja-official' ) : __( 'Action needed', 'kiriminaja-official' ) ); ?>
                </span>
                <svg class="kj-chevron" width="12" height="12" viewBox="0 0 12 12"><path d="M4 2l4 4-4 4" fill="none" stroke="#8c8f94" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>

        <a href="<?php echo esc_url( $kiriof_base_url . '&section=couriers' ); ?>" class="kj-setting-row">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M4 7h12l3 4-3 4H4V7zM4 7l-2 3m16 1h4M4 15l-2-3" stroke="#50575e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="8" cy="11" r="1.5" fill="#50575e"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( __( 'Courier List', 'kiriminaja-official' ) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( __( 'Choose which couriers are available at checkout.', 'kiriminaja-official' ) ); ?></span>
                </div>
                <svg class="kj-chevron" width="12" height="12" viewBox="0 0 12 12"><path d="M4 2l4 4-4 4" fill="none" stroke="#8c8f94" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>

        <div class="kj-setting-row">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="#50575e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( __( 'Shipping Insurance', 'kiriminaja-official' ) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( __( 'Require shipping insurance on all orders.', 'kiriminaja-official' ) ); ?></span>
                </div>
                <label class="kj-ios-toggle">
                    <input type="checkbox" id="kiriof_insurance_toggle" value="yes" <?php checked( 'yes', $kiriof_insurance_enabled ); ?>>
                    <span class="kj-ios-toggle-track"><span class="kj-ios-toggle-thumb"></span></span>
                </label>
            </div>
        </div>

        <div class="kj-setting-row">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#50575e" stroke-width="1.5"/><path d="M7 10h10M7 14h6" stroke="#50575e" stroke-width="1.5" stroke-linecap="round"/><circle cx="12" cy="12" r="3" fill="#50575e"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( __( 'Cash on Delivery', 'kiriminaja-official' ) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( __( 'Allow customers to pay when they receive their order.', 'kiriminaja-official' ) ); ?></span>
                </div>
                <label class="kj-ios-toggle">
                    <input type="checkbox" id="kiriof_cod_toggle" value="yes" <?php checked( 'yes', $kiriof_cod_enabled ); ?>>
                    <span class="kj-ios-toggle-track"><span class="kj-ios-toggle-thumb"></span></span>
                </label>
            </div>
        </div>

        <a href="<?php echo esc_url( $kiriof_base_url . '&section=address' ); ?>" class="kj-setting-row">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z" stroke="#50575e" stroke-width="1.5"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( __( 'Manage Locations', 'kiriminaja-official' ) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( __( 'Set your business location for accurate shipping rates.', 'kiriminaja-official' ) ); ?></span>
                </div>
                <svg class="kj-chevron" width="12" height="12" viewBox="0 0 12 12"><path d="M4 2l4 4-4 4" fill="none" stroke="#8c8f94" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>

        <!-- Others -->
        <div class="kj-group-header"><?php echo esc_html( __( 'Others', 'kiriminaja-official' ) ); ?></div>

        <a href="<?php echo esc_url( $kiriof_base_url . '&section=webhooks' ); ?>" class="kj-setting-row">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" stroke="#50575e" stroke-width="1.5" stroke-linecap="round"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.72-1.71" stroke="#50575e" stroke-width="1.5" stroke-linecap="round"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( __( 'Webhooks', 'kiriminaja-official' ) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( __( 'Configure callback URL for shipment status updates.', 'kiriminaja-official' ) ); ?></span>
                </div>
                <svg class="kj-chevron" width="12" height="12" viewBox="0 0 12 12"><path d="M4 2l4 4-4 4" fill="none" stroke="#8c8f94" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>

        <a href="<?php echo esc_url( $kiriof_base_url . '&section=cache' ); ?>" class="kj-setting-row">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><ellipse cx="12" cy="6" rx="8" ry="3" stroke="#50575e" stroke-width="1.5"/><path d="M4 6v6c0 1.66 3.58 3 8 3s8-1.34 8-3V6" stroke="#50575e" stroke-width="1.5"/><path d="M4 12v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6" stroke="#50575e" stroke-width="1.5"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( __( 'Cache', 'kiriminaja-official' ) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( __( 'Manage cached data such as region coverage used for coupon restrictions.', 'kiriminaja-official' ) ); ?></span>
                </div>
                <svg class="kj-chevron" width="12" height="12" viewBox="0 0 12 12"><path d="M4 2l4 4-4 4" fill="none" stroke="#8c8f94" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>

    </div>
</div>

<style>
<?php include '_section-css-shared.php'; ?>
</style>

<?php ob_start(); ?>
    <?php include '_section-js-shared.php'; ?>
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>

<!-- COD Toggle (list page only) -->
<?php ob_start(); ?>
    jQuery(document).ready(function($){
        var $cod=$('#kiriof_cod_toggle'), $ins=$('#kiriof_insurance_toggle');
        function saveToggle(action, val, $el){
            $el.prop('disabled',true);
            jQuery.ajax({type:'post',url:kiriofAjaxRoute(),data:{action:action,data:$.extend({nonce:kiriofAjax.nonce},val)},error:function(){$el.prop('disabled',false).prop('checked',!$el.is(':checked'))},complete:function(r){$el.prop('disabled',false);var p=kiriofParseAjaxResponse(r);if(!(p&&p.status===200))$el.prop('checked',!$el.is(':checked'))}});
        }
        $cod.on('change',function(){saveToggle('kiriof_store_config_data',{enable_cod:$(this).is(':checked')?'yes':'no'},$(this))});
        $ins.on('change',function(){saveToggle('kiriof_store_insurance_data',{enable_insurance:$(this).is(':checked')?'yes':'no'},$(this))});
    });
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
