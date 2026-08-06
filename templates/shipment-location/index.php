<?php
/**
 * Pointer notice for the Shipment Locations shipping section.
 *
 * The authoritative manager lives in WooCommerce > Settings > General,
 * inside the Store Address section.
 *
 * @package KiriminAjaOfficial
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$kiriof_locations_settings_url = admin_url( 'admin.php?page=wc-settings&tab=general#kiriof-shipment-locations' );
?>
<div class="notice notice-info inline">
    <p>
        <?php esc_html_e( 'Shipment locations are managed in WooCommerce General settings under Store Address.', 'kiriminaja-official' ); ?>
    </p>
    <p>
        <a class="button button-primary" href="<?php echo esc_url( $kiriof_locations_settings_url ); ?>">
            <?php esc_html_e( 'Open Store Address settings', 'kiriminaja-official' ); ?>
        </a>
    </p>
</div>
