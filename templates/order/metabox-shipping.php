<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * KiriminAja Shipping metabox — rendered on the WooCommerce order edit screen.
 *
 * Variables available:
 * @var array  $data         Shipping info from ShippingInfoServices.
 * @var string $tracking_url Public tracking page URL.
 * @var string $detail_url   Admin KiriminAja transaction process page URL.
 */
?>
<style>
    #kiriminaja-shipping-info .kiriof-meta-table { width: 100%; border-collapse: collapse; }
    #kiriminaja-shipping-info .kiriof-meta-table th,
    #kiriminaja-shipping-info .kiriof-meta-table td { padding: 8px 0; font-size: 13px; vertical-align: top; }
    #kiriminaja-shipping-info .kiriof-meta-table th { text-align: left; font-weight: 600; width: 40%; color: #50575e; }
    #kiriminaja-shipping-info .kiriof-meta-table td { text-align: right; color: #1d2327; }
    #kiriminaja-shipping-info .kiriof-meta-table tr + tr { border-top: 1px solid #f0f0f1; }
    #kiriminaja-shipping-info .kiriof-awb-value { font-family: monospace; font-weight: 700; font-size: 14px; color: #2271b1; }
    #kiriminaja-shipping-info .kiriof-btn-group { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
    #kiriminaja-shipping-info .kiriof-btn-group .button { text-align: center; }
    #kiriminaja-shipping-info .kiriof-phone { font-size: 13px; }
    #kiriminaja-shipping-info .kiriof-phone a { color: #50575e; text-decoration: none; }
    #kiriminaja-shipping-info .kiriof-total-row td,
    #kiriminaja-shipping-info .kiriof-total-row th { font-weight: 700; color: #1d2327; }
    #kiriminaja-shipping-info .kiriof-discount td { color: #007017; }
</style>

<table class="kiriof-meta-table">
    <tbody>
        <?php if ( ! empty( $data['awb'] ) && '-' !== $data['awb'] ) : ?>
        <tr>
            <th><?php esc_html_e( 'AWB', 'kiriminaja-official' ); ?></th>
            <td><span class="kiriof-awb-value"><?php echo esc_html( $data['awb'] ); ?></span></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['status'] ) ) : ?>
        <tr>
            <th><?php esc_html_e( 'Status', 'kiriminaja-official' ); ?></th>
            <td><span class="<?php echo esc_attr( $data['status_classes'] ?? 'kj-badge' ); ?>"><?php echo esc_html( $data['status'] ); ?></span></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['service'] ) && '-' !== $data['service'] ) : ?>
        <tr>
            <th><?php esc_html_e( 'Expedition', 'kiriminaja-official' ); ?></th>
            <td><?php echo esc_html( $data['service'] ); ?></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['order_id'] ) && '-' !== $data['order_id'] ) : ?>
        <tr>
            <th><?php esc_html_e( 'KA Order ID', 'kiriminaja-official' ); ?></th>
            <td><?php echo esc_html( $data['order_id'] ); ?></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['pickup_id'] ) && '-' !== $data['pickup_id'] ) : ?>
        <tr>
            <th><?php esc_html_e( 'Pickup ID', 'kiriminaja-official' ); ?></th>
            <td><?php echo esc_html( $data['pickup_id'] ); ?></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['destination_phone'] ) ) : ?>
        <tr class="kiriof-phone">
            <th><?php esc_html_e( 'Destination Phone', 'kiriminaja-official' ); ?></th>
            <td><a href="tel:<?php echo esc_attr( $data['destination_phone'] ); ?>"><?php echo esc_html( $data['destination_phone'] ); ?></a></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['destination_address'] ) ) : ?>
        <tr>
            <th><?php esc_html_e( 'Destination', 'kiriminaja-official' ); ?></th>
            <td><?php echo esc_html( $data['destination_address'] ); ?></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['payment_type'] ) ) : ?>
        <tr>
            <th><?php esc_html_e( 'Payment', 'kiriminaja-official' ); ?></th>
            <td><?php echo esc_html( $data['payment_type'] ); ?></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['weight_grams'] ) && '-' !== $data['weight_grams'] ) : ?>
        <tr>
            <th><?php esc_html_e( 'Weight', 'kiriminaja-official' ); ?></th>
            <td><?php echo esc_html( $data['weight_grams'] ); ?></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['shipping_cost'] ) && '-' !== $data['shipping_cost'] ) : ?>
        <tr>
            <th><?php esc_html_e( 'Shipping Cost', 'kiriminaja-official' ); ?></th>
            <td><?php echo esc_html( $data['shipping_cost'] ); ?></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['cod_fee'] ) && '-' !== $data['cod_fee'] ) : ?>
        <tr>
            <th><?php esc_html_e( 'COD Fee', 'kiriminaja-official' ); ?></th>
            <td><?php echo esc_html( $data['cod_fee'] ); ?></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['insurance_fee'] ) && '-' !== $data['insurance_fee'] ) : ?>
        <tr>
            <th><?php esc_html_e( 'Insurance Fee', 'kiriminaja-official' ); ?></th>
            <td><?php echo esc_html( $data['insurance_fee'] ); ?></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['discount_amount'] ) && '-' !== $data['discount_amount'] ) : ?>
        <tr class="kiriof-discount">
            <th><?php esc_html_e( 'Discount', 'kiriminaja-official' ); ?></th>
            <td>-<?php echo esc_html( $data['discount_amount'] ); ?></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['total'] ) ) : ?>
        <tr class="kiriof-total-row">
            <th><?php esc_html_e( 'Total', 'kiriminaja-official' ); ?></th>
            <td><?php echo esc_html( $data['total'] ); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="kiriof-btn-group">
    <a href="<?php echo esc_url( $detail_url ); ?>" class="button button-primary">
        <?php esc_html_e( 'View in KiriminAja', 'kiriminaja-official' ); ?>
    </a>
    <a href="<?php echo esc_url( $tracking_url ); ?>" class="button" target="_blank">
        <?php esc_html_e( 'Track Shipment', 'kiriminaja-official' ); ?>
    </a>
</div>
