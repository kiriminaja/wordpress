<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$kiriof_repository = new \KiriminAjaOfficial\Repositories\ShipmentLocationRepository();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
$kiriof_edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$kiriof_edit    = $kiriof_edit_id ? $kiriof_repository->getById( $kiriof_edit_id ) : null;
$kiriof_rows    = $kiriof_repository->getAll();
$kiriof_base    = admin_url( 'admin.php?page=wc-settings&tab=shipping&section=kiriminaja_shipment_locations' );
$kiriof_default = $kiriof_repository->getDefault();
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Shipment Locations', 'kiriminaja-official' ); ?></h1>
    <?php if ( ! $kiriof_edit ) : ?>
        <a href="<?php echo esc_url( add_query_arg( 'edit', 'new', $kiriof_base ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'kiriminaja-official' ); ?></a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php if ( $kiriof_default ) : ?>
        <div class="notice notice-info inline"><p><strong><?php esc_html_e( 'Default shipment location:', 'kiriminaja-official' ); ?></strong> <?php echo esc_html( $kiriof_default->name ); ?><?php if ( ! empty( $kiriof_default->address ) ) : ?> — <?php echo esc_html( $kiriof_default->address ); ?><?php endif; ?>. <?php esc_html_e( 'Products without an assigned shipment location use this origin.', 'kiriminaja-official' ); ?></p></div>
    <?php endif; ?>

    <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only message. ?>
    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Shipment location saved.', 'kiriminaja-official' ); ?></p></div>
    <?php endif; ?>
    <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only message. ?>
    <?php if ( isset( $_GET['error'] ) ) : ?>
        <div class="notice notice-error"><p><?php esc_html_e( 'The shipment location could not be changed. A default location must remain active and cannot be deleted.', 'kiriminaja-official' ); ?></p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['edit'] ) ) : ?>
        <h2><?php echo esc_html( $kiriof_edit ? __( 'Edit Shipment Location', 'kiriminaja-official' ) : __( 'Add Shipment Location', 'kiriminaja-official' ) ); ?></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'kiriof_manage_shipment_location' ); ?>
            <input type="hidden" name="action" value="kiriof_save_shipment_location">
            <input type="hidden" name="location_id" value="<?php echo esc_attr( $kiriof_edit ? $kiriof_edit->id : 0 ); ?>">
            <table class="form-table" role="presentation"><tbody>
                <tr><th><label for="kiriof-location-name"><?php esc_html_e( 'Location name', 'kiriminaja-official' ); ?></label></th><td><input required class="regular-text" id="kiriof-location-name" name="name" value="<?php echo esc_attr( $kiriof_edit->name ?? '' ); ?>"><p class="description"><?php esc_html_e( 'For example: Jakarta warehouse.', 'kiriminaja-official' ); ?></p></td></tr>
                <tr><th><label for="kiriof-location-phone"><?php esc_html_e( 'Sender phone', 'kiriminaja-official' ); ?></label></th><td><input class="regular-text" id="kiriof-location-phone" name="phone" value="<?php echo esc_attr( $kiriof_edit->phone ?? '' ); ?>"></td></tr>
                <tr><th><label for="kiriof-location-address"><?php esc_html_e( 'Address', 'kiriminaja-official' ); ?></label></th><td><textarea class="large-text" rows="3" id="kiriof-location-address" name="address"><?php echo esc_textarea( $kiriof_edit->address ?? '' ); ?></textarea></td></tr>
                <tr><th><label for="kiriof-location-subdistrict"><?php esc_html_e( 'Sub-district ID', 'kiriminaja-official' ); ?></label></th><td><input type="number" min="1" class="small-text" id="kiriof-location-subdistrict" name="sub_district_id" value="<?php echo esc_attr( $kiriof_edit->sub_district_id ?? '' ); ?>"><p class="description"><?php esc_html_e( 'Use the KiriminAja sub-district ID for shipping-rate calculation.', 'kiriminaja-official' ); ?></p></td></tr>
                <tr><th><label for="kiriof-location-zip"><?php esc_html_e( 'Zip code', 'kiriminaja-official' ); ?></label></th><td><input class="regular-text" id="kiriof-location-zip" name="zip_code" value="<?php echo esc_attr( $kiriof_edit->zip_code ?? '' ); ?>"></td></tr>
                <tr><th><label for="kiriof-location-lat"><?php esc_html_e( 'Latitude', 'kiriminaja-official' ); ?></label></th><td><input class="regular-text" id="kiriof-location-lat" name="latitude" value="<?php echo esc_attr( $kiriof_edit->latitude ?? '' ); ?>"></td></tr>
                <tr><th><label for="kiriof-location-long"><?php esc_html_e( 'Longitude', 'kiriminaja-official' ); ?></label></th><td><input class="regular-text" id="kiriof-location-long" name="longitude" value="<?php echo esc_attr( $kiriof_edit->longitude ?? '' ); ?>"></td></tr>
                <tr><th><?php esc_html_e( 'Status', 'kiriminaja-official' ); ?></th><td><label><input type="checkbox" name="is_active" value="1" <?php checked( ! $kiriof_edit || $kiriof_edit->is_active ); ?> <?php disabled( $kiriof_edit && $kiriof_edit->is_default ); ?>> <?php esc_html_e( 'Active', 'kiriminaja-official' ); ?></label></td></tr>
            </tbody></table>
            <?php submit_button( __( 'Save Location', 'kiriminaja-official' ) ); ?>
            <a class="button button-secondary" href="<?php echo esc_url( $kiriof_base ); ?>"><?php esc_html_e( 'Cancel', 'kiriminaja-official' ); ?></a>
        </form>
    <?php else : ?>
        <p><?php esc_html_e( 'Manage the fulfillment origins used by your products. Products without an assigned location use the default location.', 'kiriminaja-official' ); ?></p>
        <table class="widefat fixed striped"><thead><tr><th><?php esc_html_e( 'Location', 'kiriminaja-official' ); ?></th><th><?php esc_html_e( 'Address', 'kiriminaja-official' ); ?></th><th><?php esc_html_e( 'Status', 'kiriminaja-official' ); ?></th><th><?php esc_html_e( 'Actions', 'kiriminaja-official' ); ?></th></tr></thead><tbody>
        <?php if ( empty( $kiriof_rows ) ) : ?><tr><td colspan="4"><?php esc_html_e( 'No shipment locations found.', 'kiriminaja-official' ); ?></td></tr><?php endif; ?>
        <?php foreach ( $kiriof_rows as $kiriof_row ) : ?>
            <tr><td><strong><?php echo esc_html( $kiriof_row->name ); ?></strong><?php if ( $kiriof_row->is_default ) : ?> <span class="description">— <?php esc_html_e( 'Default', 'kiriminaja-official' ); ?></span><?php endif; ?><br><span class="description"><?php echo esc_html( $kiriof_row->phone ); ?></span></td><td><?php echo esc_html( $kiriof_row->address ); ?><br><span class="description"><?php echo esc_html( $kiriof_row->zip_code ); ?></span></td><td><?php echo esc_html( $kiriof_row->is_active ? __( 'Active', 'kiriminaja-official' ) : __( 'Inactive', 'kiriminaja-official' ) ); ?></td><td><a href="<?php echo esc_url( add_query_arg( 'edit', $kiriof_row->id, $kiriof_base ) ); ?>"><?php esc_html_e( 'Edit', 'kiriminaja-official' ); ?></a> | <form style="display:inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'kiriof_manage_shipment_location' ); ?><input type="hidden" name="action" value="kiriof_shipment_location_action"><input type="hidden" name="location_id" value="<?php echo esc_attr( $kiriof_row->id ); ?>"><input type="hidden" name="location_action" value="default"><button class="button-link" <?php disabled( $kiriof_row->is_default ); ?>><?php esc_html_e( 'Make default', 'kiriminaja-official' ); ?></button></form> | <form style="display:inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'kiriof_manage_shipment_location' ); ?><input type="hidden" name="action" value="kiriof_shipment_location_action"><input type="hidden" name="location_id" value="<?php echo esc_attr( $kiriof_row->id ); ?>"><input type="hidden" name="location_action" value="toggle"><button class="button-link"><?php echo esc_html( $kiriof_row->is_active ? __( 'Deactivate', 'kiriminaja-official' ) : __( 'Activate', 'kiriminaja-official' ) ); ?></button></form><?php if ( ! $kiriof_row->is_default ) : ?> | <form style="display:inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'kiriof_manage_shipment_location' ); ?><input type="hidden" name="action" value="kiriof_shipment_location_action"><input type="hidden" name="location_id" value="<?php echo esc_attr( $kiriof_row->id ); ?>"><input type="hidden" name="location_action" value="delete"><button class="button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this shipment location?', 'kiriminaja-official' ) ); ?>');"><?php esc_html_e( 'Delete', 'kiriminaja-official' ); ?></button></form><?php endif; ?></td></tr>
        <?php endforeach; ?></tbody></table>
    <?php endif; ?>
</div>
