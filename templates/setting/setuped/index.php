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
?>
<div class="wrap kj-wrap">

    <h1 class="wp-heading-inline"><?php echo esc_html( kiriof_helper()->tlThis('Settings',$locale) ); ?></h1>
    <hr class="wp-header-end">

    <?php if (!kiriof_check_woocommerce() || kiriof_helper()->devForceTrue()): ?>
    <div class="kj-notice kj-notice-warning">
        <div><?php echo esc_html( kiriof_helper()->tlThis('WooCommerce is not yet installed or activated. This plugin only supports WooCommerce features.',$locale) ); ?></div>
    </div>
    <?php endif; ?>

    <?php if ( ! empty( $isOriginShippingDataReady ) || kiriof_helper()->devForceTrue() ): ?>
    <div class="kj-notice kj-notice-success">
        <div><?php echo esc_html( kiriof_helper()->tlThis('All Setup! Now you\'re connected with KiriminAja.',$locale) ); ?></div>
    </div>
    <?php endif; ?>

    <div class="kj-settings">

        <!-- Configuration -->
        <div class="kj-group-header"><?php echo esc_html( kiriof_helper()->tlThis('Configuration',$locale) ); ?></div>

        <a href="<?php echo esc_url( $kiriof_base_url . '&section=account' ); ?>" class="kj-setting-row">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="#50575e" stroke-width="1.5"/><path d="M5 20c0-4 3.1-7 7-7s7 3 7 7" stroke="#50575e" stroke-width="1.5" stroke-linecap="round"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( kiriof_helper()->tlThis('Account Configuration',$locale) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( kiriof_helper()->tlThis('Manage your KiriminAja account connection and profile.',$locale) ); ?></span>
                </div>
                <svg class="kj-chevron" width="12" height="12" viewBox="0 0 12 12"><path d="M4 2l4 4-4 4" fill="none" stroke="#8c8f94" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>

        <!-- Online Store -->
        <div class="kj-group-header"><?php echo esc_html( kiriof_helper()->tlThis('Online Store',$locale) ); ?></div>

        <a href="<?php echo esc_url( $kiriof_base_url . '&section=tracking' ); ?>" class="kj-setting-row">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="2" stroke="#50575e" stroke-width="1.5"/><path d="M3 9h18M9 21V9" stroke="#50575e" stroke-width="1.5"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( kiriof_helper()->tlThis('Tracking Page',$locale) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( kiriof_helper()->tlThis('Configure the order tracking page for your customers.',$locale) ); ?></span>
                </div>
                <svg class="kj-chevron" width="12" height="12" viewBox="0 0 12 12"><path d="M4 2l4 4-4 4" fill="none" stroke="#8c8f94" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>

        <!-- Shipping -->
        <div class="kj-group-header"><?php echo esc_html( kiriof_helper()->tlThis('Shipping',$locale) ); ?></div>

        <a href="<?php echo esc_url( $kiriof_base_url . '&section=couriers' ); ?>" class="kj-setting-row">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M4 7h12l3 4-3 4H4V7zM4 7l-2 3m16 1h4M4 15l-2-3" stroke="#50575e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="8" cy="11" r="1.5" fill="#50575e"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( kiriof_helper()->tlThis('Courier List',$locale) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( kiriof_helper()->tlThis('Choose which couriers are available at checkout.',$locale) ); ?></span>
                </div>
                <svg class="kj-chevron" width="12" height="12" viewBox="0 0 12 12"><path d="M4 2l4 4-4 4" fill="none" stroke="#8c8f94" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>

        <div class="kj-setting-row" style="opacity:0.5;">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="#50575e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( kiriof_helper()->tlThis('Shipping Insurance',$locale) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( kiriof_helper()->tlThis('This feature is not available yet, coming soon.',$locale) ); ?></span>
                </div>
                <label class="kj-ios-toggle">
                    <input type="checkbox" disabled>
                    <span class="kj-ios-toggle-track"><span class="kj-ios-toggle-thumb"></span></span>
                </label>
            </div>
        </div>

        <div class="kj-setting-row">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#50575e" stroke-width="1.5"/><path d="M7 10h10M7 14h6" stroke="#50575e" stroke-width="1.5" stroke-linecap="round"/><circle cx="12" cy="12" r="3" fill="#50575e"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( kiriof_helper()->tlThis('Cash on Delivery',$locale) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( kiriof_helper()->tlThis('Allow customers to pay when they receive their order.',$locale) ); ?></span>
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
                    <span class="kj-setting-row-label"><?php echo esc_html( kiriof_helper()->tlThis('Manage Locations',$locale) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( kiriof_helper()->tlThis('Set your business location for accurate shipping rates.',$locale) ); ?></span>
                </div>
                <svg class="kj-chevron" width="12" height="12" viewBox="0 0 12 12"><path d="M4 2l4 4-4 4" fill="none" stroke="#8c8f94" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </a>

        <!-- Others -->
        <div class="kj-group-header"><?php echo esc_html( kiriof_helper()->tlThis('Others',$locale) ); ?></div>

        <a href="<?php echo esc_url( $kiriof_base_url . '&section=webhooks' ); ?>" class="kj-setting-row">
            <div class="kj-setting-row-inner">
                <svg class="kj-row-icon" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" stroke="#50575e" stroke-width="1.5" stroke-linecap="round"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.72-1.71" stroke="#50575e" stroke-width="1.5" stroke-linecap="round"/></svg>
                <div class="kj-setting-row-text">
                    <span class="kj-setting-row-label"><?php echo esc_html( kiriof_helper()->tlThis('Webhooks',$locale) ); ?></span>
                    <span class="kj-setting-row-desc"><?php echo esc_html( kiriof_helper()->tlThis('Configure callback URL for shipment status updates.',$locale) ); ?></span>
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
    jQuery(document).ready(function($){var $t=$('#kiriof_cod_toggle');$t.on('change',function(){var e=$(this).is(':checked')?'yes':'no';$(this).prop('disabled',true);jQuery.ajax({type:'post',url:kiriofAjaxRoute(),data:{action:'kiriof_store_config_data',data:{enable_cod:e,nonce:kiriofAjax.nonce}},error:function(){$t.prop('disabled',false).prop('checked',e==='no')},complete:function(r){$t.prop('disabled',false);var p=kiriofParseAjaxResponse(r);if(!(p&&p.status===200))$t.prop('checked',e==='no')}})})});
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
