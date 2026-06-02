<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Cache frequently used values
$kiriof_helper = kiriof_helper();
$kiriof_homeUrl = home_url();
$kiriof_adminUrl = $kiriof_homeUrl . '/wp-admin';

/**
 * @var string $locale
 * @var array $kiriof_results
 * @var array $kiriof_statusCounts
 * @var array $kiriof_monthOptions
 * @var string $kiriof_status_filter
 * @var string $kiriof_month_filter
 * @var int $kiriof_current_page
 * @var int $kiriof_total_pages
 * @var int $kiriof_total
 * @var int $kiriof_per_page
 * @var string $kiriof_search_by
 */
?>
<div class="wrap kj-wrap">

    <?php $kiriof_title = kiriof_helper()->tlThis('Transactions', $locale); $kiriof_header_extra = '<button id="kj-request-pickup-btn" onclick="kjRequestPickupSchedule()" class="page-title-action" type="button">' . esc_html__('Request Pickup','kiriminaja-official') . '</button>'; include KIRIOF_DIR . 'templates/_header.php'; ?>
    <hr class="wp-header-end">

                                <!--CONTENT-->
                                <form id="table-form" action="" style="display: none">
                                    <?php
                                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
                                    $kiriof_page_filter = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
                                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                                    $kiriof_key_filter = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
                                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                                    $kiriof_cod_filter = isset( $_GET['cod'] ) ? sanitize_text_field( wp_unslash( $_GET['cod'] ) ) : '';
                                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                                    $kiriof_courier_filter = isset( $_GET['courier'] ) ? sanitize_text_field( wp_unslash( $_GET['courier'] ) ) : '';
                                    ?>
                                    <input type="text" name="page" value="<?php echo esc_attr( $kiriof_page_filter ); ?>">
                                    <input type="text" name="cpage" value="1">
                                    <input type="text" name="key" value="<?php echo esc_attr( $kiriof_key_filter ); ?>">
                                    <input type="text" name="month" value="<?php echo esc_attr( $kiriof_month_filter ); ?>">
                                    <input type="text" name="status" value="<?php echo esc_attr( $kiriof_status_filter ); ?>">
                                    <input type="text" name="cod" value="<?php echo esc_attr( $kiriof_cod_filter ); ?>">
                                    <input type="text" name="courier" value="<?php echo esc_attr( $kiriof_courier_filter ); ?>">
                                    <input type="text" name="per_page" value="<?php echo esc_attr( $kiriof_per_page ); ?>">
                                    <input type="text" name="search_by" value="<?php echo esc_attr( $kiriof_search_by ); ?>">
                                </form>


                                <div class="wp-filter" style="display: flex;justify-content: space-between;">
                                    <ul class="filter-links">
                                        <li><a href="#" onclick="kiriofApplySearch('status','all');return false" <?php echo $kiriof_status_filter === 'all' ? 'class="current" aria-current="page"' : ''; ?>>All <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['all'] ?? 0 ) ) ); ?>)</span></a></li>
                                        <li><a href="#" onclick="kiriofApplySearch('status','wc-processing');return false" <?php echo $kiriof_status_filter === 'wc-processing' ? 'class="current" aria-current="page"' : ''; ?>>New / Waiting for Shipment <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['wc-processing'] ?? 0 ) ) ); ?>)</span></a></li>
                                        <li><a href="#" onclick="kiriofApplySearch('status','wc-on-hold');return false" <?php echo $kiriof_status_filter === 'wc-on-hold' ? 'class="current" aria-current="page"' : ''; ?>>On Hold <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['wc-on-hold'] ?? 0 ) ) ); ?>)</span></a></li>
                                        <li><a href="#" onclick="kiriofApplySearch('status','wc-pending');return false" <?php echo $kiriof_status_filter === 'wc-pending' ? 'class="current" aria-current="page"' : ''; ?>>Pending Payment <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['wc-pending'] ?? 0 ) ) ); ?>)</span></a></li>
                                        <li><a href="#" onclick="kiriofApplySearch('status','processed');return false" <?php echo $kiriof_status_filter === 'processed' ? 'class="current" aria-current="page"' : ''; ?>>Processed <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['processed'] ?? 0 ) ) ); ?>)</span></a></li>
                                        <li><a href="#" onclick="kiriofApplySearch('status','wc-cancelled');return false" <?php echo $kiriof_status_filter === 'wc-cancelled' ? 'class="current" aria-current="page"' : ''; ?>>Cancelled <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['wc-cancelled'] ?? 0 ) ) ); ?>)</span></a></li>
                                    </ul>
                                    <form class="search-form search-plugins" onsubmit="return false">
                                        <label class="screen-reader-text" for="kiriof-search-input"><?php esc_html_e( 'Search Orders', 'kiriminaja-official' ); ?></label>
                                        <input type="search" id="kiriof-search-input" class="wp-filter-search" placeholder="<?php esc_attr_e( 'Search order…', 'kiriminaja-official' ); ?>" value="<?php
                                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
                                        echo esc_attr( isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '' );
                                        ?>">
                                        <label class="screen-reader-text" for="kiriof-search-by"><?php esc_html_e( 'Search by:', 'kiriminaja-official' ); ?></label>
                                        <select id="kiriof-search-by" onchange="if(document.getElementById('kiriof-search-input').value.trim()){kiriofApplySearch('search_by',this.value)}">
                                            <option value="wc_order_id" <?php selected( $kiriof_search_by, 'wc_order_id' ); ?>>Order Number</option>
                                            <option value="ka_order_id" <?php selected( $kiriof_search_by, 'ka_order_id' ); ?>>KA Order ID</option>
                                            <option value="awb" <?php selected( $kiriof_search_by, 'awb' ); ?>>AWB</option>
                                        </select>
                                    </form>
                                </div>

                                <div class="tablenav top">
                                        <div class="alignleft actions" style="display:flex;align-items:center;">
                                            <?php $kiriof_filter_suffix = '_1'; $kiriof_show_apply = true; include '_filters.php'; ?>
                                        </div>
                                        <div class="tablenav-pages">
                                            <span class="displaying-num"><?php
                                            /* translators: %s: total number of items */
                                            echo esc_html( sprintf( _n( '%s item', '%s items', $kiriof_total, 'kiriminaja-official' ), number_format_i18n( $kiriof_total ) ) ); ?></span>
                                            <span class="pagination-links">
                                                <?php if ( $kiriof_current_page <= 1 ) : ?>
                                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
                                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                                                <?php else : ?>
                                                <a class="first-page button" href="#" onclick="kiriofGoToPage(1);return false"><span>&laquo;</span></a>
                                                <a class="prev-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) ( $kiriof_current_page - 1 ); ?>);return false"><span>&lsaquo;</span></a>
                                                <?php endif; ?>
                                                <span class="paging-input">
                                                    <label for="current-page-selector" class="screen-reader-text"><?php esc_html_e( 'Current Page', 'kiriminaja-official' ); ?></label>
                                                    <input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr( $kiriof_current_page ); ?>" size="3" aria-describedby="table-paging">
                                                    <span class="tablenav-paging-text"><?php esc_html_e( 'of', 'kiriminaja-official' ); ?> <span class="total-pages"><?php echo esc_html( number_format_i18n( $kiriof_total_pages ) ); ?></span></span>
                                                </span>
                                                <?php if ( $kiriof_current_page >= $kiriof_total_pages ) : ?>
                                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
                                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
                                                <?php else : ?>
                                                <a class="next-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) ( $kiriof_current_page + 1 ); ?>);return false"><span>&rsaquo;</span></a>
                                                <a class="last-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) $kiriof_total_pages; ?>);return false"><span>&raquo;</span></a>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <br class="clear">
                                    </div>

                                    <table class="wp-list-table widefat fixed striped table-view-list posts">
                                        <thead>
                                            <tr>
                                                <th style="width: 24px;" scope="col" class="manage-column column-thumb">
                                                    <input style="margin: 0" type="checkbox" id="check_order_id_all_top">
                                                </th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Order / Transaction', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Expedition & Service', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Airwaybill / Order ID', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Ship To', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Packages & Fee', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb" style="width:7rem"><?php echo esc_html(kiriof_helper()->tlThis('Action', $locale)); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="the-list">


                                            <?php
                                            $kiriof_print_nonce = wp_create_nonce( 'kiriof_resi_print' );
                                            $kiriof_print_base_url = admin_url( 'admin-post.php?action=kiriof_resi_print' );
                                            if (!empty($kiriof_results)) {
                                                foreach ($kiriof_results as $id => $kiriof_row) {
                                                    $kiriof_shippingData = json_decode($kiriof_row->shipping_info ?? '{}');
                                                    
                                                    // Calculate shipping fee
                                                    $kiriof_shippingCost = (float) ($kiriof_row->shipping_cost ?? 0);
                                                    $kiriof_insuranceCost = (float) ($kiriof_row->insurance_cost ?? 0);
                                                    $kiriof_discountAmount = (float) ($kiriof_row->discount_amount ?? 0);
                                                    $kiriof_codFee = (float) ($kiriof_row->cod_fee ?? 0);
                                                    $kiriof_transactionValue = (float) ($kiriof_row->transaction_value ?? 0);
                                                    
                                                    $kiriof_shippingFee = ($kiriof_shippingCost + $kiriof_insuranceCost) - $kiriof_discountAmount;
                                                    if ($kiriof_codFee > 0) {
                                                        $kiriof_shippingFee += $kiriof_transactionValue + $kiriof_codFee;
                                                    }
                                                    
                                                    // Cache shipping data properties
                                                    $kiriof_billingFirstName = $kiriof_shippingData->_billing_first_name ?? '';
                                                    $kiriof_billingLastName = $kiriof_shippingData->_billing_last_name ?? '';
                                                    $kiriof_billingAddress1 = $kiriof_shippingData->_billing_address_1 ?? '';
                                                    $kiriof_billingAddress2 = $kiriof_shippingData->_billing_address_2 ?? '';
                                                    $kiriof_billingPostcode = $kiriof_shippingData->_billing_postcode ?? '';
                                                    $kiriof_billingPhone   = $kiriof_shippingData->_billing_phone ?? '';
                                                    $kiriof_shippingPhone  = $kiriof_shippingData->_shipping_phone ?? $kiriof_billingPhone;
                                                    $kiriof_shippingFirstName = $kiriof_shippingData->_shipping_first_name ?? $kiriof_billingFirstName;
                                                    $kiriof_shippingLastName = $kiriof_shippingData->_shipping_last_name ?? $kiriof_billingLastName;
                                                    $kiriof_shippingAddress1 = $kiriof_shippingData->_shipping_address_1 ?? $kiriof_billingAddress1;
                                                    $kiriof_shippingAddress2 = $kiriof_shippingData->_shipping_address_2 ?? $kiriof_billingAddress2;
                                                    $kiriof_shippingPostcode = $kiriof_shippingData->_shipping_postcode ?? $kiriof_billingPostcode;
                                                    $kiriof_destinationSubDistrict = $kiriof_row->destination_sub_district ?? '';
                                                    $kiriof_wcOrder = function_exists('wc_get_order') ? wc_get_order($kiriof_row->wc_order_id) : false;
                                                    $kiriof_paymentMethod = $kiriof_wcOrder ? $kiriof_wcOrder->get_payment_method() : ($kiriof_shippingData->_payment_method ?? '');
                                                    $kiriof_isCod = $kiriof_paymentMethod === 'cod';
                                                    $kiriof_paymentLabel = $kiriof_isCod ? 'COD' : 'NON COD';
                                                    
                                                    $kiriof_weight       = (float) ($kiriof_row->weight ?? 0);
                                                    $kiriof_dimensions   = sprintf('%s × %s × %s cm',
                                                        number_format_i18n((float) ($kiriof_row->length ?? 0), 1),
                                                        number_format_i18n((float) ($kiriof_row->width ?? 0), 1),
                                                        number_format_i18n((float) ($kiriof_row->height ?? 0), 1)
                                                    );
                                                    $kiriof_awb          = $kiriof_row->awb ?? '';
                                                    $kiriof_orderIdKA    = $kiriof_row->order_id ?? '';
                                                    $kiriof_packageCount = isset($kiriof_row->quantity) ? (int) $kiriof_row->quantity : 1;
                                                    
                                                    // Build URLs
                                                    $kiriof_orderEditUrl = $kiriof_adminUrl . '/post.php?post=' . esc_attr($kiriof_row->wc_order_id) . '&action=edit';
                                                    // post_date is already stored in the site's local timezone,
                                                    // so pass a UTC DateTimeZone to avoid a double conversion.
                                                    $kiriof_orderDate = wp_date('M d, Y H:i', strtotime($kiriof_row->wc_date_created), new DateTimeZone('UTC'));

                                                    $kiriof_postStatus = $kiriof_row->post_status ?? 'wc-processing';
                                                    $kiriof_isProcessable   = ( 'wc-processing' === $kiriof_postStatus && 'new' === $kiriof_row->status );
                                                    $kiriof_isKAOrder       = ( 'wc-processing' === $kiriof_postStatus );
                                                    $kiriof_statusLabel     = $kiriof_isKAOrder
                                                        ? $kiriof_helper->transactionStatusLabel($kiriof_row->status)
                                                        : $kiriof_helper->wcStatusLabel($kiriof_postStatus);
                                                    $kiriof_statusBadgeClass = $kiriof_isKAOrder
                                                        ? $kiriof_helper->transactionStatusClass($kiriof_row->status)
                                                        : $kiriof_helper->wcStatusClass($kiriof_postStatus);
                                                    $kiriof_serviceName = strtoupper(trim($kiriof_row->service . ' ' . ($kiriof_row->service_name ?? '')));
                                                    $kiriof_statusUpper = strtoupper($kiriof_row->status);

                                                    $kiriof_isProcessedFilter = ( 'processed' === $kiriof_status_filter );
                                                    $kiriof_checkboxDisabled = ! $kiriof_isProcessable || $kiriof_isProcessedFilter;
                                                    $kiriof_checkboxTitle   = $kiriof_isProcessedFilter
                                                        ? $kiriof_helper->tlThis( 'This order has already been processed.', $locale )
                                                        : ( $kiriof_isProcessable ? '' : $kiriof_helper->tlThis( 'Order must be in Processing status before it can be picked up.', $locale ) );

                                                    echo '
                                                      <tr>
                                                        <td class="manage-column column-thumb">
                                                            <input type="checkbox" name="transaction_id[]" value="' . esc_attr($kiriof_orderIdKA) . '"' . ( $kiriof_checkboxDisabled ? ' disabled' : '' ) . ( $kiriof_checkboxTitle ? ' title="' . esc_attr( $kiriof_checkboxTitle ) . '"' : '' ) . '>
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                            <a href="' . esc_url($kiriof_orderEditUrl) . '" target="_blank" style="font-weight: 700">#' . esc_html($kiriof_row->wc_order_id) . '</a>
                                                            <div style="font-weight: 600; margin-top: 2px">' . esc_html(trim($kiriof_billingFirstName . ' ' . $kiriof_billingLastName)) . '</div>
                                                            <a href="tel:' . esc_attr($kiriof_shippingPhone) . '" style="font-size: 12px; color: #50575e">' . esc_html($kiriof_shippingPhone) . '</a>
                                                            <div style="font-size: 12px; color: #8c8f94">' . esc_html($kiriof_orderDate) . '</div>
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                            <div style="font-weight: 600">' . esc_html($kiriof_serviceName) . '</div>
                                                            <div style="display: flex; align-items: center; gap: 6px; margin-top: 4px">
                                                                <span class="' . esc_attr($kiriof_statusBadgeClass) . '" style="font-size: 11px">' . esc_html($kiriof_statusLabel) . '</span>
                                                                <span style="font-size: 11px; color: #8c8f94">via ' . esc_html($kiriof_paymentLabel) . '</span>
                                                            </div>
                                                            <div style="display: flex; align-items: center; gap: 4px; margin-top: 4px">
                                                                <svg width="10" height="10" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><g opacity="0.6"><path d="M5.3998 5.40005V1.80005H1.7998V5.40005H5.3998ZM10.1998 5.40005V1.80005H6.5998V5.40005H10.1998ZM5.3998 10.2V6.60005H1.7998V10.2H5.3998ZM10.1998 10.2V6.60005H6.5998V10.2H10.1998Z" fill="black"/></g></svg>
                                                                <span style="font-size: 12px">' . esc_html($kiriof_statusUpper) . '</span>
                                                            </div>
                                                        </td>
                                                        <td class="manage-column column-thumb">'
                                                            . ( $kiriof_awb
                                                                ? '<div><span style="color: #8c8f94">AWB: </span><span style="font-weight: 700">' . esc_html($kiriof_awb) . '</span></div>'
                                                                : '<div style="color: #8c8f94">AWB: —</div>' )
                                                            . '<div><span style="color: #8c8f94">Order ID: </span><span style="font-weight: 700">' . esc_html($kiriof_orderIdKA) . '</span></div>
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                            <div>' . esc_html(trim($kiriof_shippingFirstName . ' ' . $kiriof_shippingLastName)) . '</div>
                                                            <div style="font-size: 12px; color: #50575e">' . esc_html(trim($kiriof_shippingAddress1 . ', ' . $kiriof_destinationSubDistrict . ', ' . $kiriof_shippingPostcode)) . '</div>
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                            <div style="font-size: 11px; color: #8c8f94">' . esc_html(number_format_i18n($kiriof_weight, 0)) . ' g' . ( $kiriof_packageCount > 1 ? ' × ' . (int) $kiriof_packageCount : '' ) . '</div>
                                                            <div style="font-weight: 600; margin-top: 4px">Rp' . esc_html(kiriof_money_format($kiriof_shippingCost)) . '</div>'
                                                            . ( $kiriof_insuranceCost > 0 ? '<div style="font-size: 12px">Insurance: Rp' . esc_html(kiriof_money_format($kiriof_insuranceCost)) . '</div>' : '' )
                                                            . ( $kiriof_codFee > 0 ? '<div style="font-size: 12px">COD Fee: Rp' . esc_html(kiriof_money_format($kiriof_codFee)) . '</div>' : '' )
                                                            . ( $kiriof_discountAmount > 0 ? '<div style="font-size: 12px; color: #007017">Discount: -Rp' . esc_html(kiriof_money_format($kiriof_discountAmount)) . '</div>' : '' )
                                                            . '<div style="font-weight: 600; margin-top: 2px; border-top: 1px solid #e3e3e3; padding-top: 2px">Total: Rp' . esc_html(kiriof_money_format($kiriof_shippingFee)) . '</div>
                                                        </td>
                                                        <td class="manage-column column-thumb" style="white-space:nowrap">' .
                                                            '<button class="button" style="padding:4px;width:32px;height:32px;border:none;box-shadow:none" onclick="showTransactionSummaryModal(\'' . esc_js($kiriof_row->wc_order_id) . '\')" title="' . esc_attr($kiriof_helper->tlThis('Detail', $locale)) . '" aria-label="' . esc_attr($kiriof_helper->tlThis('Detail', $locale)) . '"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path fill="currentColor" d="M15.188 14.688Q16.5 13.375 16.5 11.5t-1.312-3.187T12 7T8.813 8.313T7.5 11.5t1.313 3.188T12 16t3.188-1.312m-5.1-1.276Q9.3 12.625 9.3 11.5t.788-1.912T12 8.8t1.913.788t.787 1.912t-.787 1.913T12 14.2t-1.912-.787m-4.738 3.55Q2.35 14.925 1 11.5q1.35-3.425 4.35-5.462T12 4t6.65 2.038T23 11.5q-1.35 3.425-4.35 5.463T12 19t-6.65-2.037"/></svg></button>' .
                                                            ( ! empty( $kiriof_awb ) && 'request_pickup' === $kiriof_row->status
                                                                ? ' <a href="' . esc_url( $kiriof_print_base_url . '&oids=' . urlencode( $kiriof_orderIdKA ) . '&_wpnonce=' . $kiriof_print_nonce ) . '" target="_blank" class="button" title="' . esc_attr($kiriof_helper->tlThis('Print', $locale)) . '" aria-label="' . esc_attr($kiriof_helper->tlThis('Print', $locale)) . '" style="padding:4px;width:32px;height:32px;border:none;box-shadow:none;border-radius:4px"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path fill="currentColor" d="M18 7H6V3h12zm0 5.5q.425 0 .713-.288T19 11.5t-.288-.712T18 10.5t-.712.288T17 11.5t.288.713t.712.287M16 19v-4H8v4zm2 2H6v-4H2v-6q0-1.275.875-2.137T5 8h14q1.275 0 2.138.863T22 11v6h-4z"/></svg></a>'
                                                                : '' ) .
                                                            ( ! empty( $kiriof_awb ) && ! in_array( $kiriof_row->status, [ 'shipped', 'finished', 'returned', 'return', 'canceled' ], true )
                                                                ? ' <button class="button" style="color:#d63638;padding:4px;width:32px;height:32px;border:none;box-shadow:none" onclick="kjShowCancelModal(\'' . esc_js($kiriof_orderIdKA) . '\')" title="' . esc_attr($kiriof_helper->tlThis('Cancel', $locale)) . '" aria-label="' . esc_attr($kiriof_helper->tlThis('Cancel', $locale)) . '"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path fill="currentColor" d="m12 13.4l-4.9 4.9q-.275.275-.7.275t-.7-.275t-.275-.7t.275-.7l4.9-4.9l-4.9-4.9q-.275-.275-.275-.7t.275-.7t.7-.275t.7.275l4.9 4.9l4.9-4.9q.275-.275.7-.275t.7.275t.275.7t-.275.7L13.4 12l4.9 4.9q.275.275.275.7t-.275.7t-.7.275t-.7-.275z"/></svg></button>'
                                                                : '' ) . '
                                                        </td>
                                                    </tr>
                                                    ';
                                                }
                                            } else {
                                                echo '<tr><td colspan="7" style="text-align: center" class="manage-column column-thumb">' . esc_html($kiriof_helper->tlThis('Not Found', $locale)) . '</td></tr>';
                                            }
                                            ?>

                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th style="width: 24px;" scope="col" class="manage-column column-thumb">
                                                    <input style="margin: 0" type="checkbox" id="check_order_id_all_bottom">
                                                </th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Order / Transaction', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Expedition & Service', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Airwaybill / Order ID', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Ship To', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Packages & Fee', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb" style="width:7rem"><?php echo esc_html(kiriof_helper()->tlThis('Action', $locale)); ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>

                                    <br class="clear">
                                    <div class="tablenav bottom">
                                        <div class="alignleft actions" style="display:flex;align-items:center;">
                                            <?php $kiriof_filter_suffix = '_2'; $kiriof_show_apply = true; include '_filters.php'; ?>
                                        </div>

                                        <div class="tablenav-pages">
                                            <span class="displaying-num"><?php
                                            /* translators: %s: total number of items */
                                            echo esc_html( sprintf( _n( '%s item', '%s items', $kiriof_total, 'kiriminaja-official' ), number_format_i18n( $kiriof_total ) ) ); ?></span>
                                            <span class="pagination-links">
                                                <?php if ( $kiriof_current_page <= 1 ) : ?>
                                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
                                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                                                <?php else : ?>
                                                <a class="first-page button" href="#" onclick="kiriofGoToPage(1);return false"><span>&laquo;</span></a>
                                                <a class="prev-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) ( $kiriof_current_page - 1 ); ?>);return false"><span>&lsaquo;</span></a>
                                                <?php endif; ?>
                                                <span class="paging-input">
                                                    <label for="current-page-selector-bottom" class="screen-reader-text"><?php esc_html_e( 'Current Page', 'kiriminaja-official' ); ?></label>
                                                    <input class="current-page" id="current-page-selector-bottom" type="text" name="paged" value="<?php echo esc_attr( $kiriof_current_page ); ?>" size="3">
                                                    <span class="tablenav-paging-text"><?php esc_html_e( 'of', 'kiriminaja-official' ); ?> <span class="total-pages"><?php echo esc_html( number_format_i18n( $kiriof_total_pages ) ); ?></span></span>
                                                </span>
                                                <?php if ( $kiriof_current_page >= $kiriof_total_pages ) : ?>
                                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
                                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
                                                <?php else : ?>
                                                <a class="next-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) ( $kiriof_current_page + 1 ); ?>);return false"><span>&rsaquo;</span></a>
                                                <a class="last-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) $kiriof_total_pages; ?>);return false"><span>&raquo;</span></a>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <br class="clear">
                                    </div>

    <?php include 'modal-request-pickup.php' ?>
    <?php include 'modal-detail.php' ?>
    <?php include 'modal-cancel.php' ?>

</div>

<!--Table Search-->
<?php ob_start(); ?>
    (function($) {
        'use strict';
        
        // Heartbeat nonce auto-refresh
        $(document).on('heartbeat-send', function(e, data){
            data.kiriof_nonce_check = true;
        });
        $(document).on('heartbeat-tick', function(e, data){
            if (data.kiriof_new_nonce){
                kiriofAjax.nonce = data.kiriof_new_nonce;
            }
        });

        // Cache jQuery selectors
        let orderIds = [];
        let lastwcOrderIdForshowTransactionSummaryModal = 0;
        const $checkAllTop = $('#check_order_id_all_top');
        const $checkAllBottom = $('#check_order_id_all_bottom');
        const $transactionCheckboxes = () => $('[name="transaction_id[]"]');
        const $requestPickupBtn = $('#kj-request-pickup-btn');
        const kjUpdateRequestPickupCount = () => {
            const count = $transactionCheckboxes().filter(':checked:not(:disabled)').length;
            $requestPickupBtn.text(count > 0 ? `Request Pickup (${count})` : 'Request Pickup');
        };
        
        // Make functions globally accessible
        window.kiriofApplySearch = function(key, value) {
            if ($(`#table-form [name="${key}"]`).length > 0) {
                $(`#table-form [name="${key}"]`).val(value);
            }
            // Clear search when switching status tabs
            if (key === 'status' && value) {
                $(`#table-form [name="key"]`).val('');
                $('#kiriof-search-input').val('');
            }
            $(`#table-form [name="cpage"]`).val('1');
            $(`#table-form`).trigger('submit');
        };

        window.kiriofSubmitFilters = function() {
            $(`#table-form [name="month"]`).val(document.getElementById('month_search_1').value);
            $(`#table-form [name="cod"]`).val(document.getElementById('cod_search_1').value);
            $(`#table-form [name="courier"]`).val(document.getElementById('courier_search_1').value);
            $(`#table-form [name="cpage"]`).val('1');
            $(`#table-form`).trigger('submit');
        };

        window.kiriofSubmitFiltersBottom = function() {
            document.getElementById('month_search_1').value = document.getElementById('month_search_2').value;
            document.getElementById('cod_search_1').value = document.getElementById('cod_search_2').value;
            document.getElementById('courier_search_1').value = document.getElementById('courier_search_2').value;
            $(`#table-form [name="month"]`).val(document.getElementById('month_search_2').value);
            $(`#table-form [name="cod"]`).val(document.getElementById('cod_search_2').value);
            $(`#table-form [name="courier"]`).val(document.getElementById('courier_search_2').value);
            $(`#table-form [name="cpage"]`).val('1');
            $(`#table-form`).trigger('submit');
        };

        window.kiriofGoToPage = function(page) {
            $(`#table-form [name="cpage"]`).val(page);
            $(`#table-form`).trigger('submit');
        };
        
        $(document).on('change', '#check_order_id_all_top, #check_order_id_all_bottom', function() {
            const is_checked = $(this).prop('checked');
            $checkAllTop.prop('checked', is_checked);
            $checkAllBottom.prop('checked', is_checked);
            // Skip disabled rows (e.g. On Hold / Pending Payment) so they
            // can never be batched into a pickup request via "select all".
            $transactionCheckboxes().not(':disabled').prop('checked', is_checked);
            kjUpdateRequestPickupCount();
        });

        $(document).on('change', '[name="transaction_id[]"]', function() {
            kjUpdateRequestPickupCount();
        });

        $(document).on('keypress', '.current-page', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                var $this = $(this);
                var page = parseInt($this.val(), 10) || 1;
                var max = parseInt($this.closest('.tablenav-pages').find('.total-pages').text().replace(/,/g, ''), 10);
                if (page >= 1 && page <= max) {
                    kiriofGoToPage(page);
                }
            }
        });

        var kiriofSearchTimer;
        $(document).on('keyup', '#kiriof-search-input', function() {
            clearTimeout(kiriofSearchTimer);
            var $input = $(this);
            kiriofSearchTimer = setTimeout(function() {
                kiriofApplySearch('key', $input.val());
            }, 400);
        });

        window.kjRequestPickupSchedule = function() {
        /** Reset orderIds*/
        orderIds = [];
        $('input[name="transaction_id[]"]:checked').each(function() {
            orderIds.push($(this).val());
        });

        if (orderIds.length === 0) {
            alert('<?php echo esc_js(__('There is no selected transaction.', 'kiriminaja-official')); ?>');
            return;
        }

        const $modal = $('#request-pickup-modal');
        const modalElem = $modal[0];
        const modalElemContent = $modal.find('.kj-modal-content')[0];
        const modalElemLoader = $modal.find('.kj-modal-loader')[0];
        const modalElemErr = $modal.find('.kj-err-container')[0];
        
        const $modalElem = $modal;
        const $modalElemContent = $(modalElemContent);
        const $modalElemLoader = $(modalElemLoader);
        const $modalElemErr = $(modalElemErr)

        $modalElem.removeClass('kj-hidden');
        $modalElemLoader.removeClass('kj-hidden');
        $modalElemContent.addClass('kj-hidden');
        $modalElemErr.addClass('kj-hidden');

        $.ajax({
            type: "post",
            url: kiriofAjaxRoute(),
            data: {
                action: "kiriof_request_pickup_schedule",
                data: {
                    order_ids: orderIds,
                    nonce: kiriofAjax.nonce
                }
            },
            complete: function(response) {
                const resp = JSON.parse(response.responseText).data;

                if (resp?.status !== 200) {
                    $modalElemLoader.addClass('kj-hidden');
                    $modalElemContent.addClass('kj-hidden');
                    $modalElemErr.removeClass('kj-hidden');
                    alert(resp?.message ?? '<?php echo esc_js(__('An error occurred.', 'kiriminaja-official')); ?>');
                    return;
                }

                const schedules = resp?.data?.schedules ?? [];
                const transaction_summary = resp?.data?.transaction_summary ?? {};
                const sum_cod_fee = transaction_summary?.sum_fee_cod ?? 0;
                const sum_non_cod_fee = transaction_summary?.sum_fee_non_cod ?? 0;

                /** transaction_summary*/
                const $scheduleSummary = $('#schedule-transaction-summary');
                $scheduleSummary.empty().append(`
                <div>
                    <div class="row">
                        <div class="col">Tagihan Paket COD</div>
                        <div class="col" style="text-align: right; font-weight: 700">Rp${kiriofMoneyFormat((transaction_summary?.sum_fee_cod ?? 0))}</div>
                    </div>
                    <div class="row-divider" style="margin-top: .5rem"></div>
                    <div class="row">
                        <div class="col">Tagihan Paket Non-COD</div>
                        <div class="col" style="text-align: right; font-weight: 700">Rp${kiriofMoneyFormat((transaction_summary?.sum_fee_non_cod ?? 0))}</div>
                    </div>
                    <div class="row-divider" style="margin-top: .5rem"></div>
                    <div class="row">
                        <div class="col">Total Tagihan</div>
                        <div class="col" style="text-align: right; font-weight: 700">Rp${kiriofMoneyFormat((sum_cod_fee))}</div>
                    </div>
                </div>
                `);

                /** schedules*/
                const $scheduleList = $('#schedule-opt-list');
                $scheduleList.empty();
                $.each(schedules, function(idx, schedule) {
                    $scheduleList.append(`
                        <div style="margin-bottom: .75rem">
                            <div style="display: flex;align-items: center;justify-items: center;">
                                <input id="opt_${schedule?.clock}" style="margin: 0" value="${schedule?.clock}" type="radio" name="schedule-opt">
                                <span style="margin-left: .5rem;margin-top: auto;margin-bottom: auto">
                                    <label for="opt_${schedule?.clock}">${schedule?.label}</label>                                
                                </span>
                            </div>
                        </div>
                `)
                });

                $modalElemLoader.addClass('kj-hidden');
                $modalElemContent.removeClass('kj-hidden');
                $modalElemErr.addClass('kj-hidden');
            }
        });
    };

    window.kjRequestPickupProcess = function() {
        const $modal = $('#request-pickup-modal');
        const $errMsg = $modal.find('.err_msg');
        const $modalContent = $modal.find('.kj-modal-content');
        const $modalLoader = $modal.find('.kj-modal-loader');
        const $modalErr = $modal.find('.kj-err-container');
        
        $errMsg.addClass('kj-hidden');
        $modalLoader.removeClass('kj-hidden');
        $modalContent.addClass('kj-hidden');
        $modalErr.addClass('kj-hidden');

        $.ajax({
            type: "post",
            url: kiriofAjaxRoute(),
            data: {
                action: "kiriof_request_pickup_transaction",
                data: {
                    schedule: $('[name="schedule-opt"]:checked').val(),
                    order_ids: orderIds,
                    nonce: kiriofAjax.nonce
                }
            },
            complete: function(response) {
                /** Reset Err*/
                $errMsg.empty().addClass('kj-hidden');

                const resp = JSON.parse(response.responseText).data;
                if (resp?.status !== 200) {
                    $modalLoader.addClass('kj-hidden');
                    $modalErr.addClass('kj-hidden');
                    $modalContent.removeClass('kj-hidden');
                    $errMsg.text('*' + resp?.message).removeClass('kj-hidden');
                    return;
                }

                window.location.href = `<?php echo esc_url( admin_url( 'admin.php?page=kiriminaja-request-pickup' ) ); ?>&pickup_number=${resp?.data?.pickup_number}&open_payment=1`;
            }
        });
    };

    window.showTransactionSummaryModalRefresh = function() {
        window.showTransactionSummaryModal(lastwcOrderIdForshowTransactionSummaryModal);
    };

    window.showTransactionSummaryModal = function(wcOrderId) {
        lastwcOrderIdForshowTransactionSummaryModal = wcOrderId;
        
        const $modal = $('#transaction-detail-modal');
        const $modalContent = $modal.find('.kj-modal-content');
        const $modalLoader = $modal.find('.kj-modal-loader');
        const $modalErr = $modal.find('.kj-err-container');
        const $statusContainer = $modal.find('.status-container');
        const $wcOrderId = $modal.find('.wc-order-id');

        /** Show Modal & show loader*/
        $modal.removeClass('kj-hidden');
        $modalLoader.removeClass('kj-hidden');
        $modalContent.addClass('kj-hidden');
        $modalErr.addClass('kj-hidden');
        $statusContainer.empty();

        $.ajax({
            type: "post",
            url: kiriofAjaxRoute(),
            data: {
                action: "kiriof_transaction-detail-summary",
                data: {
                    wc_order_id: wcOrderId,
                    nonce: kiriofAjax.nonce
                }
            },
            complete: function(response) {
                const resp = JSON.parse(response.responseText).data;

                if (resp?.status !== 200) {
                    /** Hide loader & Show Err*/
                    $modalLoader.addClass('kj-hidden');
                    $modalContent.addClass('kj-hidden');
                    $modalErr.removeClass('kj-hidden');
                    return;
                }

                const checkout_data = resp?.data?.checkout_data;
                const cart_data = resp?.data?.cart_data;
                const transaction_data = resp?.data?.transaction_data;

                /** Add transaction number to modal*/
                $wcOrderId.text(wcOrderId);

                /** Empty and add content*/
                $modalContent.empty().append(`
                <div>
                    <div style="padding: 0 15px 0 15px;">
                        <!--1 row-->
                        <div class="row-divider" style="margin-top: .75rem"></div>
                        <div class="row gx-2">
                            <div class="col">
                                <div style="font-weight: 700">Billing Details</div>
                                <div class="row-divider" style="margin-top: .25rem"></div>
                                <div>${checkout_data?._billing_first_name} ${checkout_data?._billing_last_name}, ${checkout_data?._billing_address_1} ${checkout_data?._billing_address_2}, ${transaction_data?.destination_sub_district}, ${checkout_data?._billing_postcode}</div>
                            </div>
                            <div class="col">
                                <div style="font-weight: 700">Shipping Details</div>
                                <div class="row-divider" style="margin-top: .25rem"></div>
                                <div>${checkout_data?._shipping_first_name ?? checkout_data?._billing_first_name} ${checkout_data?._shipping_last_name ?? checkout_data?._billing_last_name}, ${checkout_data?._shipping_address_1 ?? checkout_data?._billing_address_1} ${checkout_data?._shipping_address_2 ?? checkout_data?._billing_address_2}, ${transaction_data?.destination_sub_district}, ${checkout_data?._shipping_postcode ?? checkout_data?._billing_postcode}</div>
                            </div>
                        </div>
                        <!--2 row-->
                        <div class="row-divider" style="margin-top: .75rem"></div>
                        <div class="row gx-2">
                            <div class="col">
                                <div>
                                    <div style="font-weight: 700">Email</div>
                                    <div class="row-divider" style="margin-top: .25rem"></div>
                                    <div>${checkout_data?._billing_email}</div>
                                </div>
        
                                <div class="row-divider" style="margin-top: .75rem"></div>
                                <div>
                                    <div style="font-weight: 700">Phone</div>
                                    <div class="row-divider" style="margin-top: .25rem"></div>
                                    <div>${checkout_data?._billing_phone}</div>
                                </div>
        
                                <div class="row-divider" style="margin-top: .75rem"></div>
                                <div>
                                    <div style="font-weight: 700">Payment via</div>
                                    <div class="row-divider" style="margin-top: .25rem"></div>
                                    <div>${resp?.data?.payment}</div>
                                </div>
                            </div>
                            <div class="col">
                                <div style="font-weight: 700">Shipping Method</div>
                                <div class="row-divider" style="margin-top: .25rem"></div>
                                <div>${resp?.data?.expedition_service}</div>
                            </div>
                        </div>
                    </div>
                    <!--3 row-->
                    <div class="row-divider"></div>
                    <div>
                        <table id="cart-table">
                            <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td>Product</td>
                                <td>Quantity</td>
                                <td>Total</td>
                            </tr>
                            </tbody>
                            <tfoot>
                            <tr>
                                <th colspan="2">Sub Total</th>
                                <th>Rp.${kiriofMoneyFormat(transaction_data?.transaction_value ?? 0)}</th>
                            </tr>
                            <tr>
                                <th colspan="2">Shipping Fee</th>
                                <th>Rp.${kiriofMoneyFormat(transaction_data?.shipping_cost ?? 0)}</th>
                            </tr>
                            ` +
                    (
                        transaction_data?.cod_fee > 0 ?
                        `
                            <tr>
                            <th colspan="2">COD Fee</th>
                            <th>Rp.${kiriofMoneyFormat(transaction_data?.cod_fee ?? 0)}</th>
                            </tr>` :
                        '') +
                    (
                        transaction_data?.insurance_cost > 0 ?
                        `
                            <tr>
                            <th colspan="2">Insurance Fee</th>
                            <th>Rp.${kiriofMoneyFormat(transaction_data?.insurance_cost ?? 0)}</th>
                            </tr>` :
                        '') +
                    `
                            <tr>
                                <th colspan="2">Total</th>
                                <th>Rp.${kiriofMoneyFormat(
                                (
                                    parseInt(transaction_data?.transaction_value ?? 0)
                                    +
                                    parseInt(transaction_data?.shipping_cost ?? 0)
                                    +
                                    parseInt(transaction_data?.cod_fee ?? 0)
                                    +
                                    parseInt(transaction_data?.insurance_cost ?? 0)
                                )
                            )}</th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                `);

                /** Status*/
                $statusContainer.empty().append(`<span class="${resp?.data?.status_classes}">${resp?.data?.status_label}</span>`);

                /**emptying and add the cart table list*/
                const $cartTableBody = $modalContent.find('#cart-table tbody').empty();
                
                $.each(cart_data, function(index, obj) {
                    $cartTableBody.append(`
                    <tr>
                        <td>${obj?.product_name}</td>
                        <td>${kiriofMoneyFormat(obj?.product_qty ?? 0)}</td>
                        <td>Rp.${kiriofMoneyFormat(obj?.product_gross_revenue ?? 0)}</td>
                    </tr>
                    `);
                });

                /** Show Modal*/
                $modalLoader.addClass('kj-hidden');
                $modalContent.removeClass('kj-hidden');
            }
        });
    };

    window.kjShowCancelModal = function(orderId) {
        const $modal = $('#cancel-transaction-modal');
        $('#cancel-order-id').val(orderId);
        $('#cancel-reason').val('');
        $('#cancel-reason-count').text('0');
        $modal.find('.err_msg').html('').addClass('kj-hidden');
        $modal.find('.kj-modal-loader').addClass('kj-hidden');
        $modal.find('.kj-modal-content').removeClass('kj-hidden');
        $modal.removeClass('kj-hidden');
    };

    window.kjCancelTransactionProcess = function() {
        const $modal = $('#cancel-transaction-modal');
        const $errMsg = $modal.find('.err_msg');
        const $modalContent = $modal.find('.kj-modal-content');
        const $modalLoader = $modal.find('.kj-modal-loader');

        const orderId = $('#cancel-order-id').val();
        const reason = $('#cancel-reason').val().trim();

        if (reason.length < 5) {
            $errMsg.text('*Alasan minimal 5 karakter').removeClass('kj-hidden');
            return;
        }
        if (reason.length > 200) {
            $errMsg.text('*Alasan maksimal 200 karakter').removeClass('kj-hidden');
            return;
        }

        if (!confirm('<?php echo esc_js(__('Are you sure you want to cancel this transaction?', 'kiriminaja-official')); ?>')) {
            return;
        }

        $errMsg.addClass('kj-hidden');
        $modalLoader.removeClass('kj-hidden');
        $modalContent.addClass('kj-hidden');

        $.ajax({
            type: "post",
            url: kiriofAjaxRoute(),
            data: {
                action: "kiriof_cancel_transaction",
                data: {
                    order_id: orderId,
                    reason: reason,
                    nonce: kiriofAjax.nonce
                }
            },
            complete: function(response) {
                const resp = JSON.parse(response.responseText).data;

                if (resp?.status !== 200) {
                    $modalLoader.addClass('kj-hidden');
                    $modalContent.removeClass('kj-hidden');
                    $errMsg.text('*' + (resp?.message ?? 'Terjadi kesalahan')).removeClass('kj-hidden');
                    return;
                }

                alert(resp?.message ?? '<?php echo esc_js(__('Transaction cancelled successfully.', 'kiriminaja-official')); ?>');
                window.location.reload();
            }
        });
    };
    
    })(jQuery);

<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
