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
    #kiriminaja-shipping-info .kiriof-meta-table td { padding: 6px 0; font-size: 13px; vertical-align: top; }
    #kiriminaja-shipping-info .kiriof-meta-table th { text-align: left; font-weight: 600; width: 45%; color: #646970; }
    #kiriminaja-shipping-info .kiriof-meta-table td { text-align: right; color: #1d2327; }
    #kiriminaja-shipping-info .kiriof-meta-table tr + tr { border-top: 1px solid #f0f0f1; }
    #kiriminaja-shipping-info .kiriof-awb-value { font-family: monospace; font-weight: 600; font-size: 13px; color: #2271b1; }
    #kiriminaja-shipping-info .kiriof-status-badge {
        display: inline-block; padding: 2px 8px; border-radius: 3px;
        font-size: 12px; font-weight: 600; background: #f0f0f1; color: #50575e;
    }
    #kiriminaja-shipping-info .kiriof-btn-group { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
    #kiriminaja-shipping-info .kiriof-btn-group .button { text-align: center; }
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
            <td><span class="kiriof-status-badge"><?php echo esc_html( $data['status'] ); ?></span></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['service'] ) && '-' !== $data['service'] ) : ?>
        <tr>
            <th><?php esc_html_e( 'Service', 'kiriminaja-official' ); ?></th>
            <td><?php echo esc_html( $data['service'] ); ?></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['payment_type'] ) ) : ?>
        <tr>
            <th><?php esc_html_e( 'Payment', 'kiriminaja-official' ); ?></th>
            <td><?php echo esc_html( $data['payment_type'] ); ?></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['order_id'] ) && '-' !== $data['order_id'] ) : ?>
        <tr>
            <th><?php esc_html_e( 'Order ID', 'kiriminaja-official' ); ?></th>
            <td><?php echo esc_html( $data['order_id'] ); ?></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['pickup_id'] ) && '-' !== $data['pickup_id'] ) : ?>
        <tr>
            <th><?php esc_html_e( 'Pickup ID', 'kiriminaja-official' ); ?></th>
            <td><?php echo esc_html( $data['pickup_id'] ); ?></td>
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
            <th>
                <?php esc_html_e( 'COD Fee', 'kiriminaja-official' ); ?>
                <br><em style="font-weight:400;font-size:11px;"><?php esc_html_e( '(Include 11% VAT)', 'kiriminaja-official' ); ?></em>
            </th>
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
        <tr>
            <th><?php esc_html_e( 'Discount', 'kiriminaja-official' ); ?></th>
            <td><?php echo esc_html( $data['discount_amount'] ); ?></td>
        </tr>
        <?php endif; ?>

        <?php if ( ! empty( $data['total'] ) ) : ?>
        <tr>
            <th><strong><?php esc_html_e( 'Total', 'kiriminaja-official' ); ?></strong></th>
            <td><strong><?php echo esc_html( $data['total'] ); ?></strong></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="kiriof-btn-group">
    <a href="<?php echo esc_url( $detail_url ); ?>" class="button button button-primary">
        <?php esc_html_e( 'View in KiriminAja', 'kiriminaja-official' ); ?>
    </a>
    <a href="<?php echo esc_url( $tracking_url ); ?>" class="button" target="_blank">
        <?php esc_html_e( 'Track Shipment', 'kiriminaja-official' ); ?>
    </a>
</div>
