<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @var array $kiriof_payment_data
 * @var array $kiriof_transactions_data
 * @var string $kiriof_back_url
 */
?>
<div class="kj-wrapper kj-wrap">

    <div class="wrap">
        <div id="root">
            <div class="woocommerce-layout">
                <div class="woocommerce-layout__header is-scrolled">
                    <div class="woocommerce-layout__header-wrapper">
                        <h1 data-wp-c16t="true" data-wp-component="Text" class="components-truncate components-text woocommerce-layout__header-heading css-wv5nn e19lxcc00">
                            <?php esc_html_e('Request Pickup Detail','kiriminaja-official'); ?> - <?php echo esc_html( $kiriof_payment_data['pickup_number'] ); ?>
                        </h1>
                    </div>
                </div>
                <div class="woocommerce-layout__primary" id="woocommerce-layout__primary">
                    <div id="woocommerce-layout__notice-list" class="woocommerce-layout__notice-list"></div>
                    <div class="woocommerce-layout__main">

                        <div class="woocommerce-homescreen">
                            <div class="woocommerce-homescreen-column" style="position: static;width: 100%">

                                <!--BACK BUTTON-->
                                <div style="margin-bottom: .75rem; display: flex; justify-content: space-between; align-items: center;">
                                    <a href="<?php echo esc_url( $kiriof_back_url ); ?>" class="button button-primary-secondary">
                                        &larr; <?php esc_html_e('Back to Payments','kiriminaja-official'); ?>
                                    </a>
                                    <?php
                                    $kiriof_print_all_ids = array();
                                    foreach ( $kiriof_transactions_data as $kiriof_txn ) {
                                        if ( ! empty( $kiriof_txn->awb ) ) {
                                            $kiriof_print_all_ids[] = $kiriof_txn->order_id;
                                        }
                                    }
                                    if ( ! empty( $kiriof_print_all_ids ) ) :
                                        $kiriof_print_all_url = admin_url( 'admin-post.php?action=kiriof_resi_print&oids=' . implode( ',', array_map( 'urlencode', $kiriof_print_all_ids ) ) . '&_wpnonce=' . wp_create_nonce( 'kiriof_resi_print' ) );
                                    ?>
                                    <a href="<?php echo esc_url( $kiriof_print_all_url ); ?>" target="_blank" class="button button-primary" style="border-radius: 4px;">
                                        <div style="display: flex">
                                            <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                <div style="position: relative; top: 1px">
                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M9.59961 8.79998H5.59961V9.59998H9.59961V8.79998ZM10.3996 12H5.59961V12.8H10.3996V12ZM7.99961 10.4H5.59961V11.2H7.99961V10.4ZM13.5996 4.79998H11.9996V1.59998H3.99961V4.79998H2.39961C1.91961 4.79998 1.59961 5.11998 1.59961 5.59998V9.59998C1.59961 10.08 1.91961 10.4 2.39961 10.4H3.99961V14.4H11.9996V10.4H13.5996C14.0796 10.4 14.3996 10.08 14.3996 9.59998V5.59998C14.3996 5.11998 14.0796 4.79998 13.5996 4.79998ZM11.1996 13.6H4.79961V7.99998H11.1996V13.6ZM11.1996 4.79998H4.79961V2.39998H11.1996V4.79998ZM12.7996 7.19998H11.9996V6.39998H12.7996V7.19998Z" fill="white"/>
                                                    </svg>
                                                </div>
                                                <span style="margin-left: 6px">Print All</span>
                                            </div>
                                        </div>
                                    </a>
                                    <?php endif; ?>
                                </div>

                                <!--SUMMARY CARDS-->
                                <div class="row gx-2">
                                    <div class="col">
                                        <div style="border:1px solid #dadadc;padding: .5rem .75rem; background-color: #ffffff">
                                            <div style="font-weight: 600;"><?php echo esc_html( number_format_i18n( $kiriof_payment_data['package_count'] ) ); ?></div>
                                            <div class="row-divider" style="margin-top: .5rem"></div>
                                            <div><?php esc_html_e('Total Paket','kiriminaja-official'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div style="border:1px solid #dadadc;padding: .5rem .75rem; background-color: #ffffff">
                                            <div style="font-weight: 600;"><?php echo esc_html( number_format_i18n( $kiriof_payment_data['cod_count'] ) ); ?></div>
                                            <div class="row-divider" style="margin-top: .5rem"></div>
                                            <div><?php esc_html_e('Paket Cash on Delivery','kiriminaja-official'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div style="border:1px solid #dadadc;padding: .5rem .75rem; background-color: #ffffff">
                                            <div style="font-weight: 600;"><?php echo esc_html( number_format_i18n( $kiriof_payment_data['non_cod_count'] ) ); ?></div>
                                            <div class="row-divider" style="margin-top: .5rem"></div>
                                            <div><?php esc_html_e('Paket Non-COD','kiriminaja-official'); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!--TABLE-->
                                <div class="row-divider" style="margin-top: .75rem"></div>
                                <table class="wp-list-table widefat fixed striped table-view-list posts">
                                    <thead>
                                    <tr>
                                        <th style="width: 4rem;" scope="col" class="manage-column column-thumb">No</th>
                                        <th scope="col" class="manage-column column-thumb"><?php esc_html_e('Package','kiriminaja-official'); ?></th>
                                        <th scope="col" class="manage-column column-thumb"><?php esc_html_e('Shipment','kiriminaja-official'); ?></th>
                                        <th scope="col" class="manage-column column-thumb"><?php esc_html_e('Item Value','kiriminaja-official'); ?></th>
                                        <th scope="col" class="manage-column column-thumb"><?php esc_html_e('Fees','kiriminaja-official'); ?></th>
                                        <th scope="col" class="manage-column column-thumb"><?php esc_html_e('COD Value','kiriminaja-official'); ?></th>
                                        <th scope="col" class="manage-column column-thumb"><?php esc_html_e('Status','kiriminaja-official'); ?></th>
                                        <th scope="col" class="manage-column column-thumb"><span style="float: right"><?php esc_html_e('Action','kiriminaja-official'); ?></span></th>
                                    </tr>
                                    </thead>
                                    <tbody id="the-list">
                                    <?php
                                    if ( ! empty( $kiriof_transactions_data ) ) :
                                        foreach ( $kiriof_transactions_data as $kiriof_idx => $kiriof_txn ) :
                                            $kiriof_shipping_info = json_decode( $kiriof_txn->shipping_info, true );

                                            // Calculate COD value
                                            $kiriof_cod_value = (float) ( $kiriof_txn->shipping_cost ?? 0 ) + (float) ( $kiriof_txn->insurance_cost ?? 0 );
                                            if ( (float) ( $kiriof_txn->cod_fee ?? 0 ) > 0 ) {
                                                $kiriof_cod_value += (float) ( $kiriof_txn->cod_fee ?? 0 ) + (float) ( $kiriof_txn->transaction_value ?? 0 );
                                            }

                                            // Build fees display
                                            $kiriof_fees_html = '';
                                            if ( (float) ( $kiriof_txn->shipping_cost ?? 0 ) > 0 ) {
                                                $kiriof_fees_html .= '<div style="font-size: 12px;">Shipping: Rp' . esc_html( kiriof_money_format( $kiriof_txn->shipping_cost ) ) . '</div>';
                                            }
                                            if ( (float) ( $kiriof_txn->insurance_cost ?? 0 ) > 0 ) {
                                                $kiriof_fees_html .= '<div style="font-size: 12px;">Insurance: Rp' . esc_html( kiriof_money_format( $kiriof_txn->insurance_cost ) ) . '</div>';
                                            }
                                            if ( (float) ( $kiriof_txn->cod_fee ?? 0 ) > 0 ) {
                                                $kiriof_fees_html .= '<div style="font-size: 12px;">COD Fee: Rp' . esc_html( kiriof_money_format( $kiriof_txn->cod_fee ) ) . '</div>';
                                            }
                                            if ( empty( $kiriof_fees_html ) ) {
                                                $kiriof_fees_html = '-';
                                            }

                                            // Build action buttons
                                            $kiriof_order_edit_url = admin_url( 'post.php?post=' . absint( $kiriof_txn->wp_wc_order_stat_order_id ) . '&action=edit' );
                                            $kiriof_resi_nonce     = wp_create_nonce( 'kiriof_resi_print' );
                                            $kiriof_print_resi_url = admin_url( 'admin-post.php?action=kiriof_resi_print&oids=' . urlencode( $kiriof_txn->order_id ) . '&_wpnonce=' . $kiriof_resi_nonce );

                                            $kiriof_allowed_fees_html = [
                                                'div' => [ 'style' => [] ],
                                            ];
                                    ?>
                                    <tr>
                                        <td style="font-weight: 700;" class="thumb column-thumb"><?php echo esc_html( $kiriof_idx + 1 ); ?></td>
                                        <td class="manage-column column-thumb">
                                            <div style="display: flex">
                                                <div style="font-weight: 700;padding: 0.2rem 0.5rem;color: #3c82ba;border: 2px solid #3c82ba;border-radius: 5px;">
                                                    <?php echo (float) ( $kiriof_txn->cod_fee ?? 0 ) > 0 ? 'COD' : 'Non-COD'; ?>
                                                </div>
                                            </div>
                                            <div class="row-divider" style="margin-top: .25rem"></div>
                                            <div style="font-weight: 700">
                                                <a target="_blank" href="<?php echo esc_url( $kiriof_order_edit_url ); ?>"><?php echo esc_html( $kiriof_txn->order_id ); ?></a>
                                            </div>
                                            <div style="font-size: 12px;"><?php echo esc_html( $kiriof_shipping_info['_billing_first_name'] ?? '' ); ?></div>
                                        </td>
                                        <td class="manage-column column-thumb">
                                            <div style="font-weight: 700"><?php echo esc_html( ! empty( $kiriof_txn->awb ) ? $kiriof_txn->awb : '-' ); ?></div>
                                            <div style="font-weight: 700"><?php echo esc_html( strtoupper( $kiriof_txn->service ) . ' – ' . strtoupper( $kiriof_txn->service_name ) ); ?></div>
                                            <div style="font-size: 12px;">Pickup Schedule: <?php echo esc_html( $kiriof_payment_data['schedule'] ); ?></div>
                                        </td>
                                        <td class="manage-column column-thumb">
                                            <div style="font-weight: 700">Rp<?php echo esc_html( kiriof_money_format( $kiriof_txn->transaction_value ?? 0 ) ); ?></div>
                                        </td>
                                        <td class="manage-column column-thumb">
                                            <?php echo wp_kses( $kiriof_fees_html, $kiriof_allowed_fees_html ); ?>
                                        </td>
                                        <td class="manage-column column-thumb">
                                            <div style="font-weight: 700">Rp<?php echo esc_html( kiriof_money_format( $kiriof_cod_value ) ); ?></div>
                                        </td>
                                        <td class="manage-column column-thumb">
                                            <div style="text-transform: capitalize" class="kj-badge <?php echo esc_attr( $kiriof_txn->status_classes ); ?>">
                                                <span><?php echo esc_html( $kiriof_txn->status ); ?></span>
                                            </div>
                                        </td>
                                        <td class="manage-column column-thumb">
                                            <div style="display: flex;justify-content: end;gap: 4px; flex-wrap: wrap">
                                                <?php if ( ! empty( $kiriof_txn->awb ) ) : ?>
                                                <a href="<?php echo esc_url( $kiriof_print_resi_url ); ?>" target="_blank" class="button button-primary" style="border-radius: 4px;">
                                                    <div style="display: flex">
                                                        <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                            <div style="position: relative; top: 1px">
                                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                    <path d="M9.59961 8.79998H5.59961V9.59998H9.59961V8.79998ZM10.3996 12H5.59961V12.8H10.3996V12ZM7.99961 10.4H5.59961V11.2H7.99961V10.4ZM13.5996 4.79998H11.9996V1.59998H3.99961V4.79998H2.39961C1.91961 4.79998 1.59961 5.11998 1.59961 5.59998V9.59998C1.59961 10.08 1.91961 10.4 2.39961 10.4H3.99961V14.4H11.9996V10.4H13.5996C14.0796 10.4 14.3996 10.08 14.3996 9.59998V5.59998C14.3996 5.11998 14.0796 4.79998 13.5996 4.79998ZM11.1996 13.6H4.79961V7.99998H11.1996V13.6ZM11.1996 4.79998H4.79961V2.39998H11.1996V4.79998ZM12.7996 7.19998H11.9996V6.39998H12.7996V7.19998Z" fill="white"/>
                                                                </svg>
                                                            </div>
                                                            <span style="margin-left: 6px">Print</span>
                                                        </div>
                                                    </div>
                                                </a>
                                                <?php endif; ?>
                                                <a href="<?php echo esc_url( $kiriof_order_edit_url ); ?>" target="_blank" class="button button-primary-secondary" style="border-radius: 4px;">
                                                    <div style="display: flex">
                                                        <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                            <span>Detail</span>
                                                        </div>
                                                    </div>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                        endforeach;
                                    else :
                                    ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center" class="manage-column column-thumb">
                                            <?php esc_html_e('Not Found','kiriminaja-official'); ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>

                                <div class="row-divider" style="margin-top: .75rem"></div>
                                <?php include dirname( __DIR__ ) . '/../partials/footer.php'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="woocommerce-layout__footer">
                        <div class="components-snackbar-list woocommerce-transient-notices components-notices__snackbar"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
