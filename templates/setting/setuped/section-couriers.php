<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Section: Courier List (detail page)
 *
 * @var string $locale
 * @var string $kiriof_base_url
 */
$kiriof_settings_bootstrap = $settingsPageData->prepareCouriersBootstrap();
?>
<div class="wrap kj-wrap" data-kiriof-settings-page>
    <style><?php include '_section-css-shared.php'; ?></style>

    <?php $kiriof_title = __( 'Courier List', 'kiriminaja-official' ); $kiriof_parent_url = $kiriof_base_url; $kiriof_parent_title = __( 'Settings', 'kiriminaja-official' ); include KIRIOF_DIR . 'templates/_header.php'; ?>
    <hr class="wp-header-end">

    <div data-kiriof-settings-root></div>
    <script type="application/json" data-kiriof-settings-payload><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is hex-escaped for a non-executable data block. ?><?php echo wp_json_encode( $kiriof_settings_bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>

    <div class="kj-detail" data-kiriof-settings-fallback>
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
            <div style="margin-bottom:0.75rem;">
                <button type="button" class="button kj-courier-enable-all"><?php echo esc_html( __( 'Enable All', 'kiriminaja-official' ) ); ?></button>
                <button type="button" class="button kj-courier-disable-all" style="margin-left:0.5rem"><?php echo esc_html( __( 'Disable All', 'kiriminaja-official' ) ); ?></button>
                <span class="kj-courier-status" style="margin-left:0.75rem;vertical-align:middle;font-size:12px;color:#50575e"></span>
            </div>
            <div id="kiriof-courier-list" class="kj-courier-grid">
                <div style="padding:0.5rem;text-align:center;color:#50575e"><span class="spinner is-active" style="float:none;margin:0 8px 0 0"></span><?php echo esc_html( __( 'Loading couriers…', 'kiriminaja-official' ) ); ?></div>
            </div>
        </div>
    </div>
</div>
