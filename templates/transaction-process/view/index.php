<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Cache frequently used values
$kiriof_helper = kiriof_helper();
$kiriof_homeUrl = home_url();
$kiriof_adminUrl = $kiriof_homeUrl . '/wp-admin';
?>
<div class="kj-wrapper kj-wrap">
    <div class="wrap ">
        <div id="root">
            <div class="woocommerce-layout">
                <div class="woocommerce-layout__header is-scrolled">
                    <div class="woocommerce-layout__header-wrapper">
                        <h1 data-wp-c16t="true" data-wp-component="Text" class="components-truncate components-text woocommerce-layout__header-heading css-wv5nn e19lxcc00">Transactions</h1>
                        <div style="padding-right: 40px">
                            <button onclick="kjRequestPickupSchedule()" class="button button-wp" type="button">
                                <div style="display: flex">
                                    <div style="margin: auto">
                                        <span>Request Pickup</span>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="woocommerce-layout__primary" id="woocommerce-layout__primary">
                    <div id="woocommerce-layout__notice-list" class="woocommerce-layout__notice-list"></div>
                    <div class="woocommerce-layout__main">

                        <div class="woocommerce-homescreen">
                            <div class="woocommerce-homescreen-column" style="position: static;width: 100%">

                                <!--CONTENT-->
                                <form id="table-form" action="" style="display: none">
                                    <?php
                                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
                                    $kiriof_page_filter = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
                                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                                    $kiriof_key_filter = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
                                    ?>
                                    <input type="text" name="page" value="<?php echo esc_attr( $kiriof_page_filter ); ?>">
                                    <input type="text" name="cpage" value="1">
                                    <input type="text" name="key" value="<?php echo esc_attr( $kiriof_key_filter ); ?>">
                                    <input type="text" name="month" value="<?php echo esc_attr( $kiriof_month_filter ); ?>">
                                    <input type="text" name="status" value="<?php echo esc_attr( $kiriof_status_filter ); ?>">
                                </form>


                                <div>

                                    <div style="padding-left: 5px; background-color: #2271b1;">
                                        <div style="padding: 12px; border: 1px solid #c3c4c7; background-color: white">
                                            <div style="display:flex;">
                                                <div>
                                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M1 10C1.41 10.29 1.96 10.43 2.5 10.43C3.05 10.43 3.59 10.29 4 10C4.62 9.54 5 8.83 5 8C5 8.83 5.37 9.54 6 10C6.41 10.29 6.96 10.43 7.5 10.43C8.05 10.43 8.59 10.29 9 10C9.62 9.54 10 8.83 10 8C10 8.83 10.37 9.54 11 10C11.41 10.29 11.96 10.43 12.51 10.43C13.05 10.43 13.59 10.29 14 10C14.62 9.54 15 8.83 15 8C15 8.83 15.37 9.54 16 10C16.41 10.29 16.96 10.43 17.5 10.43C18.05 10.43 18.59 10.29 19 10C19.63 9.54 20 8.83 20 8V7L17 0H4L0 7V8C0 8.83 0.37 9.54 1 10ZM3 18.99H8V13.99H12V18.99H17V11.99C16.63 11.94 16.28 11.77 16 11.56C15.37 11.11 15 10.83 15 10C15 10.83 14.62 11.11 14 11.56C13.59 11.86 13.05 11.99 12.51 12C11.96 12 11.41 11.86 11 11.56C10.37 11.11 10 10.83 10 10C10 10.83 9.62 11.11 9 11.56C8.59 11.86 8.05 11.99 7.5 12C6.96 12 6.41 11.86 6 11.56C5.37 11.11 5 10.83 5 9.99C5 10.83 4.62 11.11 4 11.56C3.71 11.77 3.37 11.94 3 12V18.99Z" fill="black" />
                                                    </svg>
                                                </div>
                                                <div style="margin-left: 8px">
                                                    <div style="font-weight: 600; font-size: 16px;">
                                                        Note
                                                    </div>
                                                    <div class="row-divider" style="margin-top: .5rem"></div>
                                                    <div style="font-weight: 500;">
                                                        - <?php echo esc_html($kiriof_helper->tlThis('Recent transaction / order with <u>processing</u> status may not shown here immidiately. If this happen please wait for 30 seconds and refresh the page.', $locale)); ?>
                                                        <br>
                                                        - <?php echo esc_html($kiriof_helper->tlThis('Only transaction / order with billing region is Indonesia can be shown here.', $locale)); ?>
                                                        <br>
                                                        - <?php echo esc_html($kiriof_helper->tlThis('Only transaction / order which has not been request pickuped can be shown here.', $locale)); ?>
                                                        <br>
                                                        - <?php echo esc_html($kiriof_helper->tlThis('Only transaction / order which created when KiriminAja plugin is installed and activated can appear here.', $locale)); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row-divider"></div>

                                    <div style="display: inline-block">
                                        <ul class="subsubsub">
                                            <li><a href="#" onclick="kiriofApplySearch('status','wc-processing')" <?php echo $kiriof_status_filter === 'wc-processing' ? 'class="current"' : ''; ?>>Processing <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['wc-processing'] ?? 0 ) ) ); ?>)</span></a> |</li>
                                            <li><a href="#" onclick="kiriofApplySearch('status','wc-on-hold')" <?php echo $kiriof_status_filter === 'wc-on-hold' ? 'class="current"' : ''; ?>>On Hold <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['wc-on-hold'] ?? 0 ) ) ); ?>)</span></a> |</li>
                                            <li><a href="#" onclick="kiriofApplySearch('status','wc-pending')" <?php echo $kiriof_status_filter === 'wc-pending' ? 'class="current"' : ''; ?>>Pending Payment <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['wc-pending'] ?? 0 ) ) ); ?>)</span></a></li>
                                        </ul>
                                    </div>
                                    <div class="row-divider"></div>

                                    <div class="container-fluid p-0">
                                        <div class="row">
                                            <div class="col">
                                                <!--Month Search-->
                                                <div style="display: flex;width: 100%; gap: 2px">
                                                    <select style="width: 100%; max-width: 12.5rem" name="month_search" id="month_search_1">
                                                        <?php
                                                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
                                                        $kiriof_month_filter = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : '';
                                                        ?>
                                                        <option selected="selected" value="" <?php echo empty( $kiriof_month_filter ) ? 'selected' : ''; ?>>All Dates</option>
                                                        <?php
                                                        if ( ! empty( $kiriof_monthOptions ) && count($kiriof_monthOptions) > 0 ) {
                                                            foreach ($kiriof_monthOptions as $kiriof_key => $kiriof_value) {
                                                                echo '<option value="' . esc_attr($kiriof_key) . '" ' . ( $kiriof_month_filter === $kiriof_key ? 'selected' : '' ) . '>' . esc_html($kiriof_value) . '</option>';
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                    <button class="button-wp-secondary" type="button" onclick="kiriofApplySearch('month',document.getElementById('month_search_1').value)">
                                                        <div style="display: flex">
                                                            <div style="margin: auto">
                                                                <span>Apply</span>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <!--Key Search-->
                                                <div style="display: flex;justify-content: end;width: 100%; gap: 2px">
                                                    <input style="width: 100%; max-width: 12.5rem" name="key_search" type="search" class="input-text regular-input" placeholder="Order Number" value="<?php
                                                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
                                                    echo esc_attr( isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '' );
                                                    ?>">
                                                    <button class="button-wp-secondary" type="button" onclick="kiriofApplySearch('key',document.getElementsByName('key_search')[0].value)">
                                                        <div style="display: flex">
                                                            <div style="margin: auto">
                                                                <span>Search</span>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row-divider"></div>
                                    <table class="wp-list-table widefat fixed striped table-view-list posts">
                                        <thead>
                                            <tr>
                                                <th style="width: 4rem;" scope="col" class="manage-column column-thumb">
                                                    <input style="margin: 0" type="checkbox" id="check_order_id_all_top">
                                                </th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Order', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Date', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Status', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Billing', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Ship To', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html(kiriof_helper()->tlThis('Total', $locale)); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="the-list">


                                            <?php
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
                                                    $kiriof_shippingFirstName = $kiriof_shippingData->_shipping_first_name ?? $kiriof_billingFirstName;
                                                    $kiriof_shippingLastName = $kiriof_shippingData->_shipping_last_name ?? $kiriof_billingLastName;
                                                    $kiriof_shippingAddress1 = $kiriof_shippingData->_shipping_address_1 ?? $kiriof_billingAddress1;
                                                    $kiriof_shippingAddress2 = $kiriof_shippingData->_shipping_address_2 ?? $kiriof_billingAddress2;
                                                    $kiriof_shippingPostcode = $kiriof_shippingData->_shipping_postcode ?? $kiriof_billingPostcode;
                                                    $kiriof_destinationSubDistrict = $kiriof_row->destination_sub_district ?? '';
                                                    $kiriof_paymentMethod = $kiriof_shippingData->_payment_method ?? '';
                                                    $kiriof_isCod = $kiriof_paymentMethod === 'cod';
                                                    $kiriof_paymentLabel = $kiriof_isCod ? 'COD' : 'NON COD';
                                                    
                                                    // Build URLs
                                                    $kiriof_orderEditUrl = $kiriof_adminUrl . '/post.php?post=' . esc_attr($kiriof_row->wc_order_id) . '&action=edit';
                                                    $kiriof_orderDate = wp_date('M d, Y H:i', strtotime($kiriof_row->wc_date_created));

                                                    /**
                                                     * Status column should reflect what the merchant actually
                                                     * sees in WooCommerce, not the internal pickup-state machine.
                                                     * Only "Processing" orders are eligible for pickup; everything
                                                     * else (On Hold, Pending Payment, …) shows the WC label and
                                                     * gets a disabled checkbox so it can't be batched into a
                                                     * pickup request by mistake.
                                                     */
                                                    $kiriof_postStatus = $kiriof_row->post_status ?? 'wc-processing';
                                                    $kiriof_isProcessable = ( 'wc-processing' === $kiriof_postStatus );
                                                    if ( $kiriof_isProcessable ) {
                                                        $kiriof_statusLabel = $kiriof_helper->transactionStatusLabel($kiriof_row->status);
                                                        $kiriof_statusBadgeClass = 'kj-badge processing';
                                                    } else {
                                                        // Strip the "wc-" prefix and prettify (e.g. "On Hold").
                                                        $kiriof_statusLabel = ucwords( str_replace( '-', ' ', preg_replace( '/^wc-/', '', $kiriof_postStatus ) ) );
                                                        $kiriof_statusBadgeClass = 'kj-badge warning';
                                                    }
                                                    $kiriof_serviceName = strtoupper($kiriof_row->service);
                                                    $kiriof_statusUpper = strtoupper($kiriof_row->status);

                                                    $kiriof_checkboxAttrs = $kiriof_isProcessable
                                                        ? ''
                                                        : ' disabled title="' . esc_attr( $kiriof_helper->tlThis( 'Order must be in Processing status before it can be picked up.', $locale ) ) . '"';

                                                    echo '
                                                      <tr class="' . ( $kiriof_isProcessable ? '' : 'kj-row-disabled' ) . '"' . ( $kiriof_isProcessable ? '' : ' style="opacity: .65"' ) . '>
                                                        <td class="manage-column column-thumb">
                                                            <input type="checkbox" name="transaction_id[]" value="' . esc_attr($kiriof_row->order_id) . '"' . $kiriof_checkboxAttrs . '>
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                        <a href="' . esc_url($kiriof_orderEditUrl) . '" target="_blank" style="font-weight: 700">#' . esc_html($kiriof_row->wc_order_id) . ' ' . esc_html($kiriof_billingFirstName) . ' ' . esc_html($kiriof_billingLastName) . ' </a>
                                                        </td>
                                                        <td class="manage-column column-thumb">' . esc_html($kiriof_orderDate) . '</td>
                                                        <td class="manage-column column-thumb">
                                                        <span class="' . esc_attr( $kiriof_statusBadgeClass ) . '">' . esc_html($kiriof_statusLabel) . '</span>
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                            <div>' . esc_html(trim($kiriof_billingFirstName . ' ' . $kiriof_billingLastName . ', ' . $kiriof_billingAddress1 . ', ' . $kiriof_billingAddress2 . ', ' . $kiriof_destinationSubDistrict . ', ' . $kiriof_billingPostcode)) . '</div>
                                                            <div style="position: relative; margin-top: .75rem"></div>
                                                            <div>via ' . esc_html($kiriof_paymentLabel) . '</div>
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                            <div style="color: #2271b1;cursor: pointer" onclick="showTransactionSummaryModal(`' . esc_js($kiriof_row->wc_order_id) . '`)">' . esc_html(trim($kiriof_shippingFirstName . ' ' . $kiriof_shippingLastName . ', ' . $kiriof_shippingAddress1 . ', ' . $kiriof_shippingAddress2 . ', ' . $kiriof_destinationSubDistrict . ', ' . $kiriof_shippingPostcode)) . '</div>
                                                            <div style="position: relative; margin-top: .75rem"></div>
                                                            <div>via ' . esc_html($kiriof_serviceName) . '</div>
                                                            <div style="position: relative; margin-top: .1rem"></div>
                                                            <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <g opacity="0.6">
                                                                <path d="M5.3998 5.40005V1.80005H1.7998V5.40005H5.3998ZM10.1998 5.40005V1.80005H6.5998V5.40005H10.1998ZM5.3998 10.2V6.60005H1.7998V10.2H5.3998ZM10.1998 10.2V6.60005H6.5998V10.2H10.1998Z" fill="black"/>
                                                                </g>
                                                                </svg>
                                                                <span style="margin-left: .5rem">' . esc_html($kiriof_statusUpper) . '</span>
                                                            </div>
                                                        </td>
                                                        <td class="manage-column column-thumb">
                                                            <p style="font-weight: 600">(' . esc_html($kiriof_paymentLabel) . ') Rp' . esc_html(kiriof_money_format($kiriof_shippingFee)) . '</p>
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
                                                <th style="width: 4rem;" scope="col" class="manage-column column-thumb">
                                                    <input style="margin: 0" type="checkbox" id="check_order_id_all_bottom">
                                                </th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html($kiriof_helper->tlThis('Order', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html($kiriof_helper->tlThis('Date', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html($kiriof_helper->tlThis('Status', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html($kiriof_helper->tlThis('Billing', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html($kiriof_helper->tlThis('Ship To', $locale)); ?></th>
                                                <th scope="col" class="manage-column column-thumb"><?php echo esc_html($kiriof_helper->tlThis('Total', $locale)); ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>

                                    <div class="row-divider"></div>
                                    <div class="container-fluid p-0">
                                        <div class="row">
                                            <div class="col">
                                                <!--Month Search-->
                                                <div style="display: flex;width: 100%; gap: 2px">
                                                    <select style="width: 100%; max-width: 12.5rem" name="month_search_2" id="month_search_2">
                                                        <option selected="selected" value="" <?php echo empty( $kiriof_month_filter ) ? 'selected' : ''; ?>>All Dates</option>
                                                        <?php
                                                        if ( ! empty( $kiriof_monthOptions ) && count($kiriof_monthOptions) > 0 ) {
                                                            foreach ($kiriof_monthOptions as $kiriof_key => $kiriof_value) {
                                                                echo '<option value="' . esc_attr($kiriof_key) . '" ' . ( $kiriof_month_filter === $kiriof_key ? 'selected' : '' ) . '>' . esc_html($kiriof_value) . '</option>';
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                    <button class="button-wp-secondary" type="button" onclick="kiriofApplySearch('month',document.getElementById('month_search_2').value)">
                                                        <div style="display: flex">
                                                            <div style="margin: auto">
                                                                <span>Apply</span>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row-divider"></div>
                                    <?php include __DIR__ . '/../../partials/footer.php'; ?>
                                </div>
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

    <?php include 'modal-request-pickup.php' ?>
    <?php include 'modal-detail.php' ?>

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
        
        // Make functions globally accessible
        window.kiriofApplySearch = function(key, value) {
            if ($(`#table-form [name="${key}"]`).length > 0) {
                $(`#table-form [name="${key}"]`).val(value);
                $(`#table-form`).trigger('submit');
            }
        };
        
        $(document).on('change', '#check_order_id_all_top, #check_order_id_all_bottom', function() {
            const is_checked = $(this).prop('checked');
            $checkAllTop.prop('checked', is_checked);
            $checkAllBottom.prop('checked', is_checked);
            // Skip disabled rows (e.g. On Hold / Pending Payment) so they
            // can never be batched into a pickup request via "select all".
            $transactionCheckboxes().not(':disabled').prop('checked', is_checked);
        });

        window.kjRequestPickupSchedule = function() {
        /** Reset orderIds*/
        orderIds = [];
        $('input[name="transaction_id[]"]:checked').each(function() {
            orderIds.push($(this).val());
        });

        if (orderIds.length === 0) {
            alert('There is no selected transaction');
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
                    alert(resp?.message ?? 'Terjadi kesalahan');
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

                window.location.href = `<?php echo esc_url($kiriof_adminUrl); ?>/admin.php?page=kiriminaja-request-pickup&pickup_number=${resp?.data?.pickup_number}`;
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
    
    })(jQuery);

<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>