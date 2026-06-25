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
<div class="wrap kj-wrap">

    <?php
    // Build Print All button
    $kiriof_print_all_ids = array();
    foreach ( $kiriof_transactions_data as $kiriof_txn ) {
        if ( ! empty( $kiriof_txn->awb ) ) {
            $kiriof_print_all_ids[] = $kiriof_txn->order_id;
        }
    }
    if ( ! empty( $kiriof_print_all_ids ) ) {
        $kiriof_print_all_url = admin_url( 'admin-post.php?action=kiriof_resi_print&oids=' . implode( ',', array_map( 'urlencode', $kiriof_print_all_ids ) ) . '&_wpnonce=' . wp_create_nonce( 'kiriof_resi_print' ) );
        $kiriof_header_extra = '<a href="' . esc_url( $kiriof_print_all_url ) . '" target="_blank" class="page-title-action" style="border-radius:4px;margin-left:auto"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" style="position:relative;top:4px"><path d="M0 0h24v24H0z" fill="none"/><path fill="currentColor" d="M18 7H6V3h12zm0 5.5q.425 0 .713-.288T19 11.5t-.288-.712T18 10.5t-.712.288T17 11.5t.288.713t.712.287M16 19v-4H8v4zm2 2H6v-4H2v-6q0-1.275.875-2.137T5 8h14q1.275 0 2.138.863T22 11v6h-4z"/></svg> <span style="margin-left:4px">' . esc_html__('Print All','kiriminaja-official') . '</span></a>';
    }
    $kiriof_title = $kiriof_payment_data['pickup_number'];
    $kiriof_parent_url = $kiriof_back_url;
    $kiriof_parent_title = __('Payments','kiriminaja-official');
    $kiriof_subtitle = '';
    include KIRIOF_DIR . 'templates/_header.php';
    ?>
    <hr class="wp-header-end">
    <?php
    $kiriof_print_error = isset( $_GET['kiriof_print_error'] ) ? sanitize_text_field( wp_unslash( $_GET['kiriof_print_error'] ) ) : '';
    if ( '' !== $kiriof_print_error ) :
        ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $kiriof_print_error ); ?></p></div>
    <?php endif; ?>

                                <div style="margin-bottom: .75rem;">

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
                                        <th style="width:3rem" scope="col" class="manage-column column-thumb">#</th>
                                        <th scope="col" class="manage-column column-thumb"><?php esc_html_e('Order / Transaction','kiriminaja-official'); ?></th>
                                        <th scope="col" class="manage-column column-thumb"><?php esc_html_e('Expedition & Service','kiriminaja-official'); ?></th>
                                        <th scope="col" class="manage-column column-thumb"><?php esc_html_e('Airwaybill / Order ID','kiriminaja-official'); ?></th>
                                        <th scope="col" class="manage-column column-thumb"><?php esc_html_e('Ship To','kiriminaja-official'); ?></th>
                                        <th scope="col" class="manage-column column-thumb"><?php esc_html_e('Packages & Fee','kiriminaja-official'); ?></th>
                                        <th scope="col" class="manage-column column-thumb"><?php esc_html_e('COD Value','kiriminaja-official'); ?></th>
                                        <th scope="col" class="manage-column column-thumb"><?php esc_html_e('Status','kiriminaja-official'); ?></th>
                                        <th scope="col" class="manage-column column-thumb" style="width:7rem"><?php esc_html_e('Action','kiriminaja-official'); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody id="the-list">
                                    <?php
                                    if ( ! empty( $kiriof_transactions_data ) ) :
                                        $kiriof_print_nonce = wp_create_nonce( 'kiriof_resi_print' );
                                        $kiriof_print_base_url = admin_url( 'admin-post.php?action=kiriof_resi_print' );
                                        foreach ( $kiriof_transactions_data as $kiriof_idx => $kiriof_txn ) :
                                            $kiriof_shipping_info = json_decode( $kiriof_txn->shipping_info ?? '{}' );
                                            $kiriof_wc_order       = function_exists( 'wc_get_order' ) ? wc_get_order( (int) ( $kiriof_txn->wp_wc_order_stat_order_id ?? 0 ) ) : false;
                                            $kiriof_read_shipping_info = static function ( $shipping_info, array $keys ) {
                                                foreach ( $keys as $key ) {
                                                    if ( isset( $shipping_info->$key ) ) {
                                                        $value = trim( (string) $shipping_info->$key );
                                                        if ( '' !== $value ) {
                                                            return $value;
                                                        }
                                                    }
                                                }

                                                return '';
                                            };
                                            $kiriof_billing_data = $kiriof_wc_order && method_exists( $kiriof_wc_order, 'get_address' )
                                                ? (array) $kiriof_wc_order->get_address( 'billing' )
                                                : array();
                                            $kiriof_shipping_data = $kiriof_wc_order && method_exists( $kiriof_wc_order, 'get_address' )
                                                ? (array) $kiriof_wc_order->get_address( 'shipping' )
                                                : array();

                                            $kiriof_billing_first_name = $kiriof_read_shipping_info( $kiriof_shipping_info, array( '_billing_first_name', 'billing_first_name', 'first_name' ) );
                                            $kiriof_billing_last_name  = $kiriof_read_shipping_info( $kiriof_shipping_info, array( '_billing_last_name', 'billing_last_name', 'last_name' ) );
                                            $kiriof_billing_postcode   = $kiriof_read_shipping_info( $kiriof_shipping_info, array( '_billing_postcode', 'billing_postcode', 'postcode' ) );
                                            $kiriof_billing_phone      = $kiriof_read_shipping_info( $kiriof_shipping_info, array( '_billing_phone', 'billing_phone', 'phone' ) );
                                            $kiriof_shipping_first_name = $kiriof_read_shipping_info( $kiriof_shipping_info, array( '_shipping_first_name', 'shipping_first_name' ) );
                                            $kiriof_shipping_last_name  = $kiriof_read_shipping_info( $kiriof_shipping_info, array( '_shipping_last_name', 'shipping_last_name' ) );
                                            $kiriof_shipping_address_1  = $kiriof_read_shipping_info( $kiriof_shipping_info, array( '_shipping_address_1', 'shipping_address_1', '_billing_address_1', 'billing_address_1', 'address_1' ) );
                                            $kiriof_shipping_address_2  = $kiriof_read_shipping_info( $kiriof_shipping_info, array( '_shipping_address_2', 'shipping_address_2', '_billing_address_2', 'billing_address_2', 'address_2' ) );
                                            $kiriof_shipping_city       = $kiriof_read_shipping_info( $kiriof_shipping_info, array( '_shipping_city', 'shipping_city', '_billing_city', 'billing_city', 'city' ) );
                                            $kiriof_shipping_state      = $kiriof_read_shipping_info( $kiriof_shipping_info, array( '_shipping_state', 'shipping_state', '_billing_state', 'billing_state', 'state' ) );
                                            $kiriof_shipping_country    = $kiriof_read_shipping_info( $kiriof_shipping_info, array( '_shipping_country', 'shipping_country', '_billing_country', 'billing_country', 'country' ) );
                                            $kiriof_shipping_postcode   = $kiriof_read_shipping_info( $kiriof_shipping_info, array( '_shipping_postcode', 'shipping_postcode', '_billing_postcode', 'billing_postcode', 'postcode' ) );
                                            $kiriof_phone               = $kiriof_read_shipping_info( $kiriof_shipping_info, array( '_shipping_phone', 'shipping_phone', '_billing_phone', 'billing_phone', 'phone' ) );

                                            if ( $kiriof_wc_order ) {
                                                if ( '' === $kiriof_billing_first_name ) {
                                                    $kiriof_billing_first_name = trim( (string) ( $kiriof_billing_data['first_name'] ?? '' ) );
                                                }
                                                if ( '' === $kiriof_billing_first_name ) {
                                                    $kiriof_billing_first_name = (string) $kiriof_wc_order->get_billing_first_name();
                                                }
                                                if ( '' === $kiriof_billing_last_name ) {
                                                    $kiriof_billing_last_name = trim( (string) ( $kiriof_billing_data['last_name'] ?? '' ) );
                                                }
                                                if ( '' === $kiriof_billing_last_name ) {
                                                    $kiriof_billing_last_name = (string) $kiriof_wc_order->get_billing_last_name();
                                                }
                                                if ( '' === $kiriof_billing_postcode ) {
                                                    $kiriof_billing_postcode = trim( (string) ( $kiriof_billing_data['postcode'] ?? '' ) );
                                                }
                                                if ( '' === $kiriof_billing_postcode ) {
                                                    $kiriof_billing_postcode = (string) $kiriof_wc_order->get_billing_postcode();
                                                }
                                                if ( '' === $kiriof_billing_phone ) {
                                                    $kiriof_billing_phone = trim( (string) ( $kiriof_billing_data['phone'] ?? '' ) );
                                                }
                                                if ( '' === $kiriof_billing_phone ) {
                                                    $kiriof_billing_phone = (string) $kiriof_wc_order->get_billing_phone();
                                                }
                                                if ( '' === $kiriof_shipping_first_name ) {
                                                    $kiriof_shipping_first_name = trim( (string) ( $kiriof_shipping_data['first_name'] ?? '' ) );
                                                }
                                                if ( '' === $kiriof_shipping_first_name ) {
                                                    $kiriof_shipping_first_name = (string) $kiriof_wc_order->get_shipping_first_name();
                                                }
                                                if ( '' === $kiriof_shipping_last_name ) {
                                                    $kiriof_shipping_last_name = trim( (string) ( $kiriof_shipping_data['last_name'] ?? '' ) );
                                                }
                                                if ( '' === $kiriof_shipping_last_name ) {
                                                    $kiriof_shipping_last_name = (string) $kiriof_wc_order->get_shipping_last_name();
                                                }
                                                if ( '' === $kiriof_shipping_address_1 ) {
                                                    $kiriof_shipping_address_1 = trim( (string) ( $kiriof_shipping_data['address_1'] ?? '' ) );
                                                }
                                                if ( '' === $kiriof_shipping_address_1 ) {
                                                    $kiriof_shipping_address_1 = (string) $kiriof_wc_order->get_shipping_address_1();
                                                }
                                                if ( '' === $kiriof_shipping_address_2 ) {
                                                    $kiriof_shipping_address_2 = trim( (string) ( $kiriof_shipping_data['address_2'] ?? '' ) );
                                                }
                                                if ( '' === $kiriof_shipping_address_2 ) {
                                                    $kiriof_shipping_address_2 = (string) $kiriof_wc_order->get_shipping_address_2();
                                                }
                                                if ( '' === $kiriof_shipping_city ) {
                                                    $kiriof_shipping_city = trim( (string) ( $kiriof_shipping_data['city'] ?? '' ) );
                                                }
                                                if ( '' === $kiriof_shipping_city ) {
                                                    $kiriof_shipping_city = (string) $kiriof_wc_order->get_shipping_city();
                                                }
                                                if ( '' === $kiriof_shipping_state ) {
                                                    $kiriof_shipping_state = trim( (string) ( $kiriof_shipping_data['state'] ?? '' ) );
                                                }
                                                if ( '' === $kiriof_shipping_state ) {
                                                    $kiriof_shipping_state = (string) $kiriof_wc_order->get_shipping_state();
                                                }
                                                if ( '' === $kiriof_shipping_country ) {
                                                    $kiriof_shipping_country = trim( (string) ( $kiriof_shipping_data['country'] ?? '' ) );
                                                }
                                                if ( '' === $kiriof_shipping_country ) {
                                                    $kiriof_shipping_country = (string) $kiriof_wc_order->get_shipping_country();
                                                }
                                                if ( '' === $kiriof_shipping_postcode ) {
                                                    $kiriof_shipping_postcode = trim( (string) ( $kiriof_shipping_data['postcode'] ?? '' ) );
                                                }
                                                if ( '' === $kiriof_shipping_postcode ) {
                                                    $kiriof_shipping_postcode = (string) $kiriof_wc_order->get_shipping_postcode();
                                                }
                                                if ( '' === $kiriof_phone ) {
                                                    $kiriof_phone = trim( (string) ( $kiriof_shipping_data['phone'] ?? '' ) );
                                                }
                                                if ( '' === $kiriof_phone ) {
                                                    $kiriof_phone = method_exists( $kiriof_wc_order, 'get_shipping_phone' )
                                                        ? (string) $kiriof_wc_order->get_shipping_phone()
                                                        : '';
                                                }
                                            }

                                            if ( '' === $kiriof_shipping_first_name ) {
                                                $kiriof_shipping_first_name = $kiriof_billing_first_name;
                                            }
                                            if ( '' === $kiriof_shipping_last_name ) {
                                                $kiriof_shipping_last_name = $kiriof_billing_last_name;
                                            }
                                            if ( '' === $kiriof_shipping_postcode ) {
                                                $kiriof_shipping_postcode = $kiriof_billing_postcode;
                                            }
                                            if ( '' === $kiriof_phone ) {
                                                $kiriof_phone = $kiriof_billing_phone;
                                            }

                                            $kiriof_billing_name = trim( $kiriof_billing_first_name . ' ' . $kiriof_billing_last_name );
                                            if ( '' === $kiriof_billing_name && $kiriof_wc_order && method_exists( $kiriof_wc_order, 'get_formatted_billing_full_name' ) ) {
                                                $kiriof_billing_name = trim( (string) $kiriof_wc_order->get_formatted_billing_full_name() );
                                            }
                                            if ( '' === $kiriof_billing_name ) {
                                                $kiriof_billing_name = trim( $kiriof_shipping_first_name . ' ' . $kiriof_shipping_last_name );
                                            }
                                            if ( '' === $kiriof_billing_name && $kiriof_wc_order && method_exists( $kiriof_wc_order, 'get_formatted_shipping_full_name' ) ) {
                                                $kiriof_billing_name = trim( (string) $kiriof_wc_order->get_formatted_shipping_full_name() );
                                            }

                                            $kiriof_shipping_name = trim( $kiriof_shipping_first_name . ' ' . $kiriof_shipping_last_name );
                                            if ( '' === $kiriof_shipping_name && $kiriof_wc_order && method_exists( $kiriof_wc_order, 'get_formatted_shipping_full_name' ) ) {
                                                $kiriof_shipping_name = trim( (string) $kiriof_wc_order->get_formatted_shipping_full_name() );
                                            }
                                            if ( '' === $kiriof_shipping_name ) {
                                                $kiriof_shipping_name = $kiriof_billing_name;
                                            }
                                            if ( '' === $kiriof_phone && $kiriof_wc_order ) {
                                                $kiriof_phone = (string) $kiriof_wc_order->get_billing_phone();
                                            }
                                            if ( '' === $kiriof_phone && $kiriof_wc_order ) {
                                                $kiriof_phone = (string) $kiriof_wc_order->get_meta( '_billing_phone', true );
                                            }
                                            if ( '' === $kiriof_phone && $kiriof_wc_order ) {
                                                $kiriof_phone = (string) $kiriof_wc_order->get_meta( '_shipping_phone', true );
                                            }
                                            $kiriof_shipping_addr_line_three = implode(
                                                ', ',
                                                array_filter(
                                                    array(
                                                        $kiriof_txn->destination_sub_district ?? '',
                                                        $kiriof_shipping_city,
                                                        $kiriof_shipping_state,
                                                    )
                                                )
                                            );
                                            $kiriof_shipping_addr_line_four = implode(
                                                ', ',
                                                array_filter(
                                                    array(
                                                        $kiriof_shipping_postcode,
                                                        $kiriof_shipping_country,
                                                    )
                                                )
                                            );
                                            $kiriof_is_cod          = (float) ( $kiriof_txn->cod_fee ?? 0 ) > 0;
                                            $kiriof_weight          = (float) ( $kiriof_txn->weight ?? 0 );
                                            $kiriof_shipping_cost   = (float) ( $kiriof_txn->shipping_cost ?? 0 );
                                            $kiriof_insurance_cost  = (float) ( $kiriof_txn->insurance_cost ?? 0 );
                                            $kiriof_cod_fee         = (float) ( $kiriof_txn->cod_fee ?? 0 );
                                            $kiriof_discount        = (float) ( $kiriof_txn->discount_amount ?? 0 );
                                            $kiriof_trans_val       = (float) ( $kiriof_txn->transaction_value ?? 0 );
                                            $kiriof_ship_total      = $kiriof_shipping_cost + $kiriof_insurance_cost + $kiriof_cod_fee - $kiriof_discount;
                                            $kiriof_cod_value       = $kiriof_shipping_cost + $kiriof_insurance_cost;
                                            if ( $kiriof_cod_fee > 0 ) {
                                                $kiriof_cod_value += $kiriof_cod_fee + $kiriof_trans_val;
                                            }
                                            $kiriof_order_edit_url  = admin_url( 'post.php?post=' . absint( $kiriof_txn->wp_wc_order_stat_order_id ) . '&action=edit' );
                                            $kiriof_print_resi_url  = $kiriof_print_base_url . '&oids=' . urlencode( $kiriof_txn->order_id ) . '&_wpnonce=' . $kiriof_print_nonce;
                                    ?>
                                    <tr>
                                        <td class="manage-column column-thumb" style="text-align:center;color:#8c8f94"><?php echo esc_html( $kiriof_idx + 1 ); ?></td>
                                        <td class="manage-column column-thumb">
                                            <a href="<?php echo esc_url( $kiriof_order_edit_url ); ?>" target="_blank" style="font-weight: 700"><?php echo esc_html( $kiriof_txn->order_id ); ?></a>
                                            <div style="font-weight: 600; margin-top: 2px"><?php echo esc_html( $kiriof_billing_name ); ?></div>
                                            <?php if ( $kiriof_phone ) : ?>
                                            <a href="tel:<?php echo esc_attr( $kiriof_phone ); ?>" style="font-size: 12px; color: #50575e"><?php echo esc_html( $kiriof_phone ); ?></a>
                                            <?php endif; ?>
                                            <div style="margin-top: 4px"><span style="font-size: 11px; color: #8c8f94; border: 1px solid #dcdcde; border-radius: 4px; padding: 1px 6px"><?php echo $kiriof_is_cod ? 'COD' : 'Non-COD'; ?></span></div>
                                        </td>
                                        <td class="manage-column column-thumb">
                                            <div style="font-weight: 600"><?php echo esc_html( kiriof_helper()->formatServiceName( $kiriof_txn->service, $kiriof_txn->service_name ?? '' ) ); ?></div>
                                            <?php /* translators: %s: pickup schedule date/time. */ ?>
                                            <div style="font-size: 12px; color: #50575e; margin-top: 4px"><?php echo esc_html( sprintf( __( 'Pickup: %s', 'kiriminaja-official' ), $kiriof_payment_data['schedule'] ) ); ?></div>
                                        </td>
                                        <td class="manage-column column-thumb">
                                            <?php if ( ! empty( $kiriof_txn->awb ) ) : ?>
                                            <div><span style="color: #8c8f94">AWB: </span><span style="font-weight: 700"><?php echo esc_html( $kiriof_txn->awb ); ?></span></div>
                                            <?php else : ?>
                                            <div style="color: #8c8f94">AWB: —</div>
                                            <?php endif; ?>
                                            <div style="font-size: 12px; color: #50575e">Order ID: <?php echo esc_html( $kiriof_txn->order_id ); ?></div>
                                        </td>
                                        <td class="manage-column column-thumb">
                                            <div><?php echo esc_html( $kiriof_shipping_name ); ?></div>
                                            <?php if ( '' !== $kiriof_shipping_address_1 ) : ?>
                                            <div style="font-size: 12px; color: #50575e"><?php echo esc_html( $kiriof_shipping_address_1 ); ?></div>
                                            <?php endif; ?>
                                            <?php if ( '' !== $kiriof_shipping_address_2 ) : ?>
                                            <div style="font-size: 12px; color: #50575e"><?php echo esc_html( $kiriof_shipping_address_2 ); ?></div>
                                            <?php endif; ?>
                                            <?php if ( '' !== $kiriof_shipping_addr_line_three ) : ?>
                                            <div style="font-size: 12px; color: #50575e"><?php echo esc_html( $kiriof_shipping_addr_line_three ); ?></div>
                                            <?php endif; ?>
                                            <?php if ( '' !== $kiriof_shipping_addr_line_four ) : ?>
                                            <div style="font-size: 12px; color: #50575e"><?php echo esc_html( $kiriof_shipping_addr_line_four ); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="manage-column column-thumb">
                                            <?php if ( $kiriof_weight > 0 ) : ?>
                                            <div style="font-size: 11px; color: #8c8f94"><?php echo esc_html( number_format_i18n( $kiriof_weight, 0 ) . ' g' ); ?></div>
                                            <?php endif; ?>
                                            <div style="font-weight: 600; margin-top: 2px">Rp<?php echo esc_html( kiriof_money_format( $kiriof_shipping_cost ) ); ?></div>
                                            <?php if ( $kiriof_insurance_cost > 0 ) : ?>
                                            <div style="font-size: 12px">Insurance: Rp<?php echo esc_html( kiriof_money_format( $kiriof_insurance_cost ) ); ?></div>
                                            <?php endif; ?>
                                            <?php if ( $kiriof_cod_fee > 0 ) : ?>
                                            <div style="font-size: 12px">COD Fee: Rp<?php echo esc_html( kiriof_money_format( $kiriof_cod_fee ) ); ?></div>
                                            <?php endif; ?>
                                            <?php if ( $kiriof_discount > 0 ) : ?>
                                            <div style="font-size: 12px; color: #007017">Discount: -Rp<?php echo esc_html( kiriof_money_format( $kiriof_discount ) ); ?></div>
                                            <?php endif; ?>
                                            <div style="font-weight: 600; margin-top: 2px; border-top: 1px solid #e3e3e3; padding-top: 2px">Total: Rp<?php echo esc_html( kiriof_money_format( $kiriof_ship_total ) ); ?></div>
                                        </td>
                                        <td class="manage-column column-thumb">
                                            <div style="font-weight: 700">Rp<?php echo esc_html( kiriof_money_format( $kiriof_cod_value ) ); ?></div>
                                        </td>
                                        <td class="manage-column column-thumb">
                                            <span class="<?php echo esc_attr( $kiriof_txn->status_classes ); ?>"><?php echo esc_html( $kiriof_txn->status ); ?></span>
                                        </td>
                                        <td class="manage-column column-thumb" style="white-space:nowrap">
                                            <?php if ( ! empty( $kiriof_txn->awb ) ) : ?>
                                            <a href="<?php echo esc_url( $kiriof_print_resi_url ); ?>" target="_blank" class="button" title="<?php esc_attr_e('Print','kiriminaja-official'); ?>" aria-label="<?php esc_attr_e('Print','kiriminaja-official'); ?>" style="padding:4px;width:32px;height:32px;border:none;box-shadow:none;border-radius:4px"><span class="dashicons dashicons-printer" style="font-size:20px;width:20px;height:20px;line-height:20px;"></span></a>
                                            <?php endif; ?>
                                            <a href="<?php echo esc_url( $kiriof_order_edit_url ); ?>" target="_blank" class="button" title="<?php esc_attr_e('Detail','kiriminaja-official'); ?>" aria-label="<?php esc_attr_e('Detail','kiriminaja-official'); ?>" style="padding:4px;width:32px;height:32px;border:none;box-shadow:none;border-radius:4px"><span class="dashicons dashicons-visibility" style="font-size:20px;width:20px;height:20px;line-height:20px;"></span></a>
                                        </td>
                                    </tr>
                                    <?php
                                        endforeach;
                                    else :
                                    ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center" class="manage-column column-thumb">
                                            <?php esc_html_e('Not Found','kiriminaja-official'); ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>

</div>
