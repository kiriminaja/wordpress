<?php
// Exit if accessed directly
if (! defined('ABSPATH')) {
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

    <?php $kiriof_title = __('Transactions', 'kiriminaja-official');
    $kiriof_header_extra = '<button id="kj-request-pickup-btn" onclick="kjRequestPickupSchedule()" class="page-title-action" type="button">' . esc_html__('Request Pickup', 'kiriminaja-official') . '</button>';
    include KIRIOF_DIR . 'templates/_header.php'; ?>
    <hr class="wp-header-end">

    <!--CONTENT-->
    <form id="table-form" action="" style="display: none">
        <?php
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
        $kiriof_page_filter = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $kiriof_key_filter = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $kiriof_cod_filter = isset($_GET['cod']) ? sanitize_text_field(wp_unslash($_GET['cod'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $kiriof_courier_filter = isset($_GET['courier']) ? sanitize_text_field(wp_unslash($_GET['courier'])) : '';
        ?>
        <input type="text" name="page" value="<?php echo esc_attr($kiriof_page_filter); ?>">
        <input type="text" name="cpage" value="1">
        <input type="text" name="key" value="<?php echo esc_attr($kiriof_key_filter); ?>">
        <input type="text" name="month" value="<?php echo esc_attr($kiriof_month_filter); ?>">
        <input type="text" name="status" value="<?php echo esc_attr($kiriof_status_filter); ?>">
        <input type="text" name="cod" value="<?php echo esc_attr($kiriof_cod_filter); ?>">
        <input type="text" name="courier" value="<?php echo esc_attr($kiriof_courier_filter); ?>">
        <input type="text" name="per_page" value="<?php echo esc_attr($kiriof_per_page); ?>">
        <input type="text" name="search_by" value="<?php echo esc_attr($kiriof_search_by); ?>">
    </form>


    <div class="wp-filter" style="display: flex;justify-content: space-between;">
        <ul class="filter-links">
            <li><a href="#" onclick="kiriofApplySearch('status','all');return false" <?php echo $kiriof_status_filter === 'all' ? 'class="current" aria-current="page"' : ''; ?>><?php esc_html_e('All', 'kiriminaja-official'); ?> <span class="count">(<?php echo esc_html(number_format_i18n((int) ($kiriof_statusCounts['all'] ?? 0))); ?>)</span></a></li>
            <li><a href="#" onclick="kiriofApplySearch('status','wc-processing');return false" <?php echo $kiriof_status_filter === 'wc-processing' ? 'class="current" aria-current="page"' : ''; ?>><?php esc_html_e('New / Waiting for Shipment', 'kiriminaja-official'); ?> <span class="count">(<?php echo esc_html(number_format_i18n((int) ($kiriof_statusCounts['wc-processing'] ?? 0))); ?>)</span></a></li>
            <li><a href="#" onclick="kiriofApplySearch('status','wc-on-hold');return false" <?php echo $kiriof_status_filter === 'wc-on-hold' ? 'class="current" aria-current="page"' : ''; ?>><?php esc_html_e('On Hold', 'kiriminaja-official'); ?> <span class="count">(<?php echo esc_html(number_format_i18n((int) ($kiriof_statusCounts['wc-on-hold'] ?? 0))); ?>)</span></a></li>
            <li><a href="#" onclick="kiriofApplySearch('status','wc-pending');return false" <?php echo $kiriof_status_filter === 'wc-pending' ? 'class="current" aria-current="page"' : ''; ?>><?php esc_html_e('Pending Payment', 'kiriminaja-official'); ?> <span class="count">(<?php echo esc_html(number_format_i18n((int) ($kiriof_statusCounts['wc-pending'] ?? 0))); ?>)</span></a></li>
            <li><a href="#" onclick="kiriofApplySearch('status','processed');return false" <?php echo $kiriof_status_filter === 'processed' ? 'class="current" aria-current="page"' : ''; ?>><?php esc_html_e('Processed', 'kiriminaja-official'); ?> <span class="count">(<?php echo esc_html(number_format_i18n((int) ($kiriof_statusCounts['processed'] ?? 0))); ?>)</span></a></li>
            <li><a href="#" onclick="kiriofApplySearch('status','wc-cancelled');return false" <?php echo $kiriof_status_filter === 'wc-cancelled' ? 'class="current" aria-current="page"' : ''; ?>><?php esc_html_e('Cancelled', 'kiriminaja-official'); ?> <span class="count">(<?php echo esc_html(number_format_i18n((int) ($kiriof_statusCounts['wc-cancelled'] ?? 0))); ?>)</span></a></li>
            <li><a href="#" onclick="kiriofApplySearch('status','order-issue');return false" <?php echo $kiriof_status_filter === 'order-issue' ? 'class="current" aria-current="page"' : ''; ?> style="color:#d63638;"><?php esc_html_e('Order Issue', 'kiriminaja-official'); ?> <span class="count">(<?php echo esc_html(number_format_i18n((int) ($kiriof_statusCounts['order-issue'] ?? 0))); ?>)</span></a></li>
        </ul>
        <form class="search-form search-plugins" onsubmit="return false">
            <label class="screen-reader-text" for="kiriof-search-input"><?php esc_html_e('Search Orders', 'kiriminaja-official'); ?></label>
            <input type="search" id="kiriof-search-input" class="wp-filter-search" placeholder="<?php esc_attr_e('Search order…', 'kiriminaja-official'); ?>" value="<?php
                                                                                                                                                                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
                                                                                                                                                                        echo esc_attr(isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '');
                                                                                                                                                                        ?>">
            <label class="screen-reader-text" for="kiriof-search-by"><?php esc_html_e('Search by:', 'kiriminaja-official'); ?></label>
            <select id="kiriof-search-by" onchange="if(document.getElementById('kiriof-search-input').value.trim()){kiriofApplySearch('search_by',this.value)}">
                <option value="wc_order_id" <?php selected($kiriof_search_by, 'wc_order_id'); ?>><?php esc_html_e('Order Number', 'kiriminaja-official'); ?></option>
                <option value="ka_order_id" <?php selected($kiriof_search_by, 'ka_order_id'); ?>><?php esc_html_e('KA Order ID', 'kiriminaja-official'); ?></option>
                <option value="awb" <?php selected($kiriof_search_by, 'awb'); ?>><?php esc_html_e('AWB', 'kiriminaja-official'); ?></option>
            </select>
        </form>
    </div>

    <div class="tablenav top">
        <div class="alignleft actions" style="display:flex;align-items:center;">
            <?php $kiriof_filter_suffix = '_1';
            $kiriof_show_apply = true;
            include '_filters.php'; ?>
        </div>
        <div class="tablenav-pages">
            <span class="displaying-num"><?php
                                            /* translators: %s: total number of items */
                                            echo esc_html(sprintf(_n('%s item', '%s items', $kiriof_total, 'kiriminaja-official'), number_format_i18n($kiriof_total))); ?></span>
            <span class="pagination-links">
                <?php if ($kiriof_current_page <= 1) : ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                <?php else : ?>
                    <a class="first-page button" href="#" onclick="kiriofGoToPage(1);return false"><span>&laquo;</span></a>
                    <a class="prev-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) ($kiriof_current_page - 1); ?>);return false"><span>&lsaquo;</span></a>
                <?php endif; ?>
                <span class="paging-input">
                    <label for="current-page-selector" class="screen-reader-text"><?php esc_html_e('Current Page', 'kiriminaja-official'); ?></label>
                    <input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr($kiriof_current_page); ?>" size="3" aria-describedby="table-paging">
                    <span class="tablenav-paging-text"><?php esc_html_e('of', 'kiriminaja-official'); ?> <span class="total-pages"><?php echo esc_html(number_format_i18n($kiriof_total_pages)); ?></span></span>
                </span>
                <?php if ($kiriof_current_page >= $kiriof_total_pages) : ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
                <?php else : ?>
                    <a class="next-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) ($kiriof_current_page + 1); ?>);return false"><span>&rsaquo;</span></a>
                    <a class="last-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) $kiriof_total_pages; ?>);return false"><span>&raquo;</span></a>
                <?php endif; ?>
            </span>
        </div>
        <br class="clear">
    </div>

    <table class="wp-list-table widefat fixed striped table-view-list posts kiriof-transaction-table">
        <thead>
            <tr>
                <th style="width: 24px;" scope="col" class="manage-column column-thumb kiriof-col-select">
                    <input style="margin: 0" type="checkbox" id="check_order_id_all_top">
                </th>
                <th scope="col" class="manage-column column-thumb kiriof-col-order"><?php echo esc_html(__('Order / Transaction', 'kiriminaja-official')); ?></th>
                <th scope="col" class="manage-column column-thumb kiriof-col-expedition"><?php echo esc_html(__('Expedition & Service', 'kiriminaja-official')); ?></th>
                <th scope="col" class="manage-column column-thumb kiriof-col-airwaybill"><?php echo esc_html(__('Airwaybill / Order ID', 'kiriminaja-official')); ?></th>
                <th scope="col" class="manage-column column-thumb kiriof-col-shipto"><?php echo esc_html(__('Ship To', 'kiriminaja-official')); ?></th>
                <th scope="col" class="manage-column column-thumb kiriof-col-packages"><?php echo esc_html(__('Packages & Fee', 'kiriminaja-official')); ?></th>
                <th scope="col" class="manage-column column-thumb kiriof-col-action" style="width:7rem"><?php echo esc_html(__('Action', 'kiriminaja-official')); ?></th>
            </tr>
        </thead>
        <tbody id="the-list">


            <?php
            $kiriof_print_nonce = wp_create_nonce('kiriof_resi_print');
            $kiriof_print_base_url = admin_url('admin-post.php?action=kiriof_resi_print');
            $kiriof_adj_nonce = wp_create_nonce(KIRIOF_NONCE);
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
                    $kiriof_shippingCity = $kiriof_shippingData->_shipping_city ?? '';
                    $kiriof_shippingState = $kiriof_shippingData->_shipping_state ?? '';
                    $kiriof_shippingCountry = $kiriof_shippingData->_shipping_country ?? '';
                    $kiriof_shippingPostcode = $kiriof_shippingData->_shipping_postcode ?? $kiriof_billingPostcode;
                    $kiriof_destinationSubDistrict = $kiriof_row->destination_sub_district ?? '';
                    $kiriof_wcOrder = function_exists('wc_get_order') ? wc_get_order($kiriof_row->wc_order_id) : false;
                    $kiriof_paymentMethod = $kiriof_wcOrder ? $kiriof_wcOrder->get_payment_method() : ($kiriof_shippingData->_payment_method ?? '');
                    $kiriof_isCod = $kiriof_paymentMethod === 'cod';

                    // Split WC discount for column badges (loaded for every row).
                    $kiriof_colItemDiscount = 0.0;
                    $kiriof_colShipDiscount = 0.0;
                    $kiriof_colItemCoupon   = '';
                    $kiriof_colShipCoupon   = '';
                    if ($kiriof_wcOrder) {
                        $kiriof_colItemDiscount = (float) $kiriof_wcOrder->get_discount_total();
                        $kiriof_colShipDiscount = max(0.0, $kiriof_shippingCost - (float) $kiriof_wcOrder->get_shipping_total());
                        $kiriof_colCoupons      = $kiriof_wcOrder->get_coupon_codes();
                        $kiriof_couponService   = new \KiriminAjaOfficial\Services\ShippingDiscountCouponService();
                        $kiriof_couponScopes    = $kiriof_couponService->splitCouponCodesByScope((array) $kiriof_colCoupons);
                        $kiriof_colItemCoupon   = $kiriof_couponScopes['item'][0] ?? '';
                        $kiriof_colShipCoupon   = $kiriof_couponScopes['shipping'][0] ?? '';
                    }
                    $kiriof_paymentLabel = $kiriof_isCod ? __('COD', 'kiriminaja-official') : __('NON COD', 'kiriminaja-official');

                    $kiriof_weight       = (float) ($kiriof_row->weight ?? 0);
                    $kiriof_dimensions   = sprintf(
                        '%s × %s × %s cm',
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
                    $kiriof_isProcessable   = ('wc-processing' === $kiriof_postStatus && 'new' === $kiriof_row->status);
                    $kiriof_isKAOrder       = ('wc-processing' === $kiriof_postStatus);
                    $kiriof_isDeficitRow    = ! empty($kiriof_row->is_deficit);
                    $kiriof_statusLabel     = $kiriof_isDeficitRow
                        ? __('COD Deficit', 'kiriminaja-official')
                        : ($kiriof_isKAOrder
                            ? $kiriof_helper->transactionStatusLabel($kiriof_row->status)
                            : $kiriof_helper->wcStatusLabel($kiriof_postStatus));
                    $kiriof_statusBadgeClass = $kiriof_isDeficitRow
                        ? 'kj-badge kiriof-badge--deficit-label'
                        : ($kiriof_isKAOrder
                            ? $kiriof_helper->transactionStatusClass($kiriof_row->status)
                            : $kiriof_helper->wcStatusClass($kiriof_postStatus));
                    $kiriof_serviceName = $kiriof_helper->formatServiceName($kiriof_row->service, $kiriof_row->service_name ?? '');
                    $kiriof_statusUpper = strtoupper($kiriof_row->status);
                    $kiriof_shippingAddressLineTwo = $kiriof_shippingAddress2;
                    $kiriof_shippingAddressLineThree = implode(
                        ', ',
                        array_filter(
                            array(
                                $kiriof_destinationSubDistrict,
                                $kiriof_shippingCity,
                                $kiriof_shippingState,
                            )
                        )
                    );
                    $kiriof_shippingAddressLineFour = implode(
                        ', ',
                        array_filter(
                            array(
                                $kiriof_shippingPostcode,
                                $kiriof_shippingCountry,
                            )
                        )
                    );

                    // Minimum COD tooltip for deficit rows (matches Shopify TableDetail.tsx logic).
                    $kiriof_deficitMinCod = max(
                        (float) ($kiriof_row->cod_minimum ?? 0),
                        $kiriof_shippingCost + $kiriof_insuranceCost + $kiriof_codFee
                    );
                    $kiriof_deficitTooltip = $kiriof_isDeficitRow
                        ? sprintf(
                            /* translators: %s: formatted minimum COD amount */
                            __('Minimum COD: Rp%s (to avoid deficit)', 'kiriminaja-official'),
                            kiriof_money_format($kiriof_deficitMinCod)
                        )
                        : '';

                    // For deficit rows, load WC order for accurate subtotal/discount/total.
                    $kiriof_wcSubtotal           = 0.0;
                    $kiriof_wcDiscountTotal      = 0.0;
                    $kiriof_wcShippingDiscount   = 0.0;
                    $kiriof_wcCouponCodes        = [];
                    $kiriof_wcTotal              = $kiriof_shippingFee; // fallback
                    if ($kiriof_isDeficitRow && ! empty($kiriof_row->wc_order_id)) {
                        $kiriof_wcOrderForDeficit = wc_get_order((int) $kiriof_row->wc_order_id);
                        if ($kiriof_wcOrderForDeficit) {
                            $kiriof_wcSubtotal         = (float) $kiriof_wcOrderForDeficit->get_subtotal();
                            $kiriof_wcDiscountTotal    = (float) $kiriof_wcOrderForDeficit->get_discount_total();
                            $kiriof_wcTotal            = (float) $kiriof_wcOrderForDeficit->get_total();
                            $kiriof_wcShippingDiscount = max(0.0, $kiriof_shippingCost - (float) $kiriof_wcOrderForDeficit->get_shipping_total());
                            $kiriof_wcCouponCodes      = $kiriof_wcOrderForDeficit->get_coupon_codes();
                        }
                    }
                    $kiriof_adjCouponScopes  = (new \KiriminAjaOfficial\Services\ShippingDiscountCouponService())->splitCouponCodesByScope((array) $kiriof_wcCouponCodes);
                    $kiriof_adjItemCoupon    = $kiriof_adjCouponScopes['item'][0] ?? '';
                    $kiriof_adjShipCoupon    = $kiriof_adjCouponScopes['shipping'][0] ?? '';

                    $kiriof_isProcessedFilter = ('processed' === $kiriof_status_filter);
                    $kiriof_checkboxDisabled = $kiriof_isDeficitRow || ! $kiriof_isProcessable || $kiriof_isProcessedFilter;
                    $kiriof_checkboxTitle   = $kiriof_isDeficitRow
                        ? __('Resolve the COD deficit before proceeding.', 'kiriminaja-official')
                        : ($kiriof_isProcessedFilter
                            ? __('This order has already been processed.', 'kiriminaja-official')
                            : ($kiriof_isProcessable ? '' : __('Order must be in Processing status before it can be picked up.', 'kiriminaja-official')));

                    echo '
                                                      <tr>
                                                        <td class="manage-column column-thumb kiriof-col-select">
                                                            <input type="checkbox" name="transaction_id[]" value="' . esc_attr($kiriof_orderIdKA) . '"' . ($kiriof_checkboxDisabled ? ' disabled' : '') . ($kiriof_checkboxTitle ? ' title="' . esc_attr($kiriof_checkboxTitle) . '"' : '') . '>
                                                        </td>
                                                        <td class="manage-column column-thumb kiriof-col-order">
                                                            <a href="' . esc_url($kiriof_orderEditUrl) . '" target="_blank" style="font-weight: 700">#' . esc_html($kiriof_row->wc_order_id) . '</a>
                                                            <div style="font-weight: 600; margin-top: 2px">' . esc_html(trim($kiriof_billingFirstName . ' ' . $kiriof_billingLastName)) . '</div>
                                                            <a href="tel:' . esc_attr($kiriof_shippingPhone) . '" style="font-size: 12px; color: #50575e">' . esc_html($kiriof_shippingPhone) . '</a>
                                                            <div style="font-size: 12px; color: #8c8f94">' . esc_html($kiriof_orderDate) . '</div>
                                                        </td>
                                                        <td class="manage-column column-thumb kiriof-col-expedition">
                                                            <div style="font-weight: 600">' . esc_html($kiriof_serviceName) . '</div>
                                                            <div style="display: flex; align-items: center; gap: 6px; margin-top: 4px">
                                                                <span class="' . esc_attr($kiriof_statusBadgeClass) . '" style="font-size: 11px' . ($kiriof_isDeficitRow ? ';cursor:help' : '') . '"' . ($kiriof_isDeficitRow ? ' title="' . esc_attr($kiriof_deficitTooltip) . '"' : '') . '>' . esc_html($kiriof_statusLabel) . ($kiriof_isDeficitRow ? ' <span class="dashicons dashicons-warning" style="font-size:11px;width:11px;height:11px;vertical-align:middle;margin-left:2px;"></span>' : '') . '</span>
                                                                ' . (! empty($kiriof_row->is_deficit) ? '' : '') . '
                                                                <span style="font-size: 11px; color: #8c8f94">' . esc_html__('via', 'kiriminaja-official') . ' ' . esc_html($kiriof_paymentLabel) . '</span>
                                                            </div>
                                                            <div style="display: flex; align-items: center; gap: 4px; margin-top: 4px">
                                                                <svg width="10" height="10" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><g opacity="0.6"><path d="M5.3998 5.40005V1.80005H1.7998V5.40005H5.3998ZM10.1998 5.40005V1.80005H6.5998V5.40005H10.1998ZM5.3998 10.2V6.60005H1.7998V10.2H5.3998ZM10.1998 10.2V6.60005H6.5998V10.2H10.1998Z" fill="black"/></g></svg>
                                                                <span style="font-size: 12px">' . esc_html($kiriof_statusUpper) . '</span>
                                                            </div>
                                                        </td>
                                                        <td class="manage-column column-thumb kiriof-col-airwaybill">'
                        . ($kiriof_awb
                            ? '<div><span style="color: #8c8f94">' . esc_html__('AWB', 'kiriminaja-official') . ': </span><span style="font-weight: 700">' . esc_html($kiriof_awb) . '</span></div>'
                            : '<div style="color: #8c8f94">' . esc_html__('AWB', 'kiriminaja-official') . ': —</div>')
                        . '<div><span style="color: #8c8f94">' . esc_html__('Order ID', 'kiriminaja-official') . ': </span><span style="font-weight: 700">' . esc_html($kiriof_orderIdKA) . '</span></div>
                                                        </td>
                                                        <td class="manage-column column-thumb kiriof-col-shipto">
                                                            <div class="kiriof-shipto-name">' . esc_html(trim($kiriof_shippingFirstName . ' ' . $kiriof_shippingLastName)) . '</div>
                                                            <div class="kiriof-shipto-line" title="' . esc_attr($kiriof_shippingAddress1) . '">' . esc_html($kiriof_shippingAddress1) . '</div>'
                        . ($kiriof_shippingAddressLineTwo ? '<div class="kiriof-shipto-line" title="' . esc_attr($kiriof_shippingAddressLineTwo) . '">' . esc_html($kiriof_shippingAddressLineTwo) . '</div>' : '')
                        . ($kiriof_shippingAddressLineThree ? '<div class="kiriof-shipto-line" title="' . esc_attr($kiriof_shippingAddressLineThree) . '">' . esc_html($kiriof_shippingAddressLineThree) . '</div>' : '')
                        . ($kiriof_shippingAddressLineFour ? '<div class="kiriof-shipto-line" title="' . esc_attr($kiriof_shippingAddressLineFour) . '">' . esc_html($kiriof_shippingAddressLineFour) . '</div>' : '') . '
                                                        </td>
                                                        <td class="manage-column column-thumb kiriof-col-packages">' .
                        // Weight + package count.
                        '<div style="font-size:11px;color:#8c8f94">'
                        . esc_html(number_format_i18n($kiriof_weight, 0)) . ' g'
                        . ($kiriof_packageCount > 1 ? ' &times; ' . (int) $kiriof_packageCount : '')
                        . '</div>'
                        // Shipping cost — always the primary number.
                        . '<div style="font-weight:600;margin-top:4px">Rp' . esc_html(kiriof_money_format($kiriof_shippingCost)) . '</div>'
                        // Extra-fee pills: only shown when applicable.
                        . (($kiriof_insuranceCost > 0 || $kiriof_codFee > 0 || $kiriof_colItemDiscount > 0 || $kiriof_colShipDiscount > 0)
                            ? '<div style="margin-top:4px;display:flex;flex-wrap:wrap;gap:3px">'
                            . ($kiriof_insuranceCost > 0
                                ? '<span style="font-size:10px;background:#f0f0f0;border-radius:3px;padding:1px 5px;white-space:nowrap" title="' . esc_attr(__('Insurance', 'kiriminaja-official')) . '">'
                                . esc_html__('Ins', 'kiriminaja-official') . ' Rp' . esc_html(kiriof_money_format($kiriof_insuranceCost))
                                . '</span>'
                                : '')
                            . ($kiriof_codFee > 0
                                ? '<span style="font-size:10px;background:#f0f0f0;border-radius:3px;padding:1px 5px;white-space:nowrap" title="' . esc_attr(__('COD Fee', 'kiriminaja-official')) . '">'
                                . esc_html__('COD Fee', 'kiriminaja-official') . ' Rp' . esc_html(kiriof_money_format($kiriof_codFee))
                                . '</span>'
                                : '')
                            . ($kiriof_colItemDiscount > 0
                                ? '<span style="font-size:10px;background:#fce8e8;color:#d63638;border-radius:3px;padding:1px 5px;white-space:nowrap" title="' . esc_attr(($kiriof_colItemCoupon ? $kiriof_colItemCoupon . ' — ' : '') . __('Item Discount', 'kiriminaja-official')) . '">'
                                . ($kiriof_colItemCoupon ? esc_html($kiriof_colItemCoupon) . ' ' : '') . '-Rp' . esc_html(kiriof_money_format($kiriof_colItemDiscount))
                                . '</span>'
                                : '')
                            . ($kiriof_colShipDiscount > 0
                                ? '<span style="font-size:10px;background:#fce8e8;color:#d63638;border-radius:3px;padding:1px 5px;white-space:nowrap" title="' . esc_attr(($kiriof_colShipCoupon ? $kiriof_colShipCoupon . ' — ' : '') . __('Shipping Discount', 'kiriminaja-official')) . '">'
                                . ($kiriof_colShipCoupon ? esc_html($kiriof_colShipCoupon) . ' ' : '') . '-Rp' . esc_html(kiriof_money_format($kiriof_colShipDiscount))
                                . '</span>'
                                : '')
                            . '</div>'
                            : '')
                        . '
                                                        </td>
                                                        <td class="manage-column column-thumb kiriof-col-action" style="white-space:nowrap">' .
                        '<a href="#" class="button order-preview" data-order-id="' . esc_attr($kiriof_row->wc_order_id) . '" style="padding:4px;width:32px;height:32px;border:none;box-shadow:none" title="' . esc_attr(__('Detail', 'kiriminaja-official')) . '" aria-label="' . esc_attr(__('Detail', 'kiriminaja-official')) . '"><span class="dashicons dashicons-visibility" style="font-size:20px;width:20px;height:20px;line-height:20px;"></span></a>' .
                        (! empty($kiriof_isDeficitRow)
                            ? ' <button type="button"'
                            . ' class="button"'
                            . ' style="padding:4px;width:32px;height:32px;border:none;box-shadow:none;color:#d97706"'
                            . ' onclick="kjShowCodAdjustModal(this)"'
                            . ' data-ka-order-id="' . esc_attr($kiriof_orderIdKA) . '"'
                            . ' data-current-cod="' . esc_attr($kiriof_wcTotal) . '"'
                            . ' data-cod-minimum="' . esc_attr($kiriof_deficitMinCod) . '"'
                            . ' data-cod-maximum="' . esc_attr((float) KIRIOF_MAX_COD_AMOUNT) . '"'
                            . ' data-shipping-cost="' . esc_attr($kiriof_shippingCost) . '"'
                            . ' data-insurance-fee="' . esc_attr($kiriof_insuranceCost) . '"'
                            . ' data-cod-fee="' . esc_attr($kiriof_codFee) . '"'
                            . ' data-item-price="' . esc_attr($kiriof_wcSubtotal) . '"'
                            . ' data-item-discount="' . esc_attr($kiriof_wcDiscountTotal) . '"'
                            . ' data-shipping-discount="' . esc_attr($kiriof_wcShippingDiscount) . '"'
                            . ' data-item-coupon="' . esc_attr($kiriof_adjItemCoupon) . '"'
                            . ' data-shipping-coupon="' . esc_attr($kiriof_adjShipCoupon) . '"'
                            . ' data-nonce="' . esc_attr($kiriof_adj_nonce) . '"'
                            . ' title="' . esc_attr(__('Adjust Deficit', 'kiriminaja-official')) . '"'
                            . ' aria-label="' . esc_attr(__('Adjust Deficit', 'kiriminaja-official')) . '">'
                            . '<span class="dashicons dashicons-update-alt" style="font-size:20px;width:20px;height:20px;line-height:20px;"></span></button>'
                            . ' <button type="button"'
                            . ' class="button"'
                            . ' style="padding:4px;width:32px;height:32px;border:none;box-shadow:none;color:#d63638"'
                            . ' onclick="kjShowCancelDeficitModal(this)"'
                            . ' data-ka-order-id="' . esc_attr($kiriof_orderIdKA) . '"'
                            . ' data-nonce="' . esc_attr($kiriof_adj_nonce) . '"'
                            . ' title="' . esc_attr(__('Cancel Deficit Order', 'kiriminaja-official')) . '"'
                            . ' aria-label="' . esc_attr(__('Cancel Deficit Order', 'kiriminaja-official')) . '">'
                            . '<span class="dashicons dashicons-no-alt" style="font-size:20px;width:20px;height:20px;line-height:20px;"></span></button>'
                            : (
                                (! empty( $kiriof_awb ) && 'request_pickup' === $kiriof_row->status
                                    ? ' <a href="' . esc_url($kiriof_print_base_url . '&oids=' . urlencode($kiriof_orderIdKA) . '&_wpnonce=' . $kiriof_print_nonce) . '" target="_blank" class="button" title="' . esc_attr(__('Print', 'kiriminaja-official')) . '" aria-label="' . esc_attr(__('Print', 'kiriminaja-official')) . '" style="padding:4px;width:32px;height:32px;border:none;box-shadow:none;border-radius:4px"><span class="dashicons dashicons-printer" style="font-size:20px;width:20px;height:20px;line-height:20px;"></span></a>'
                                    : '') .
                                (! empty( $kiriof_awb ) && ! in_array($kiriof_row->status, ['shipped', 'finished', 'returned', 'return', 'canceled'], true)
                                    ? ' <button class="button" style="color:#d63638;padding:4px;width:32px;height:32px;border:none;box-shadow:none" onclick="kjShowCancelModal(\'' . esc_js($kiriof_orderIdKA) . '\')" title="' . esc_attr(__('Cancel', 'kiriminaja-official')) . '" aria-label="' . esc_attr(__('Cancel', 'kiriminaja-official')) . '"><span class="dashicons dashicons-no-alt" style="font-size:20px;width:20px;height:20px;line-height:20px;"></span></button>'
                                    : '')
                            )) . '
                                                        </td>
                                                    </tr>
                                                    ';
                }
            } else {
                echo '<tr><td colspan="7" style="text-align: center" class="manage-column column-thumb">' . esc_html(__('Not Found', 'kiriminaja-official')) . '</td></tr>';
            }
            ?>

        </tbody>
        <tfoot>
            <tr>
                <th style="width: 24px;" scope="col" class="manage-column column-thumb kiriof-col-select">
                    <input style="margin: 0" type="checkbox" id="check_order_id_all_bottom">
                </th>
                <th scope="col" class="manage-column column-thumb kiriof-col-order"><?php echo esc_html(__('Order / Transaction', 'kiriminaja-official')); ?></th>
                <th scope="col" class="manage-column column-thumb kiriof-col-expedition"><?php echo esc_html(__('Expedition & Service', 'kiriminaja-official')); ?></th>
                <th scope="col" class="manage-column column-thumb kiriof-col-airwaybill"><?php echo esc_html(__('Airwaybill / Order ID', 'kiriminaja-official')); ?></th>
                <th scope="col" class="manage-column column-thumb kiriof-col-shipto"><?php echo esc_html(__('Ship To', 'kiriminaja-official')); ?></th>
                <th scope="col" class="manage-column column-thumb kiriof-col-packages"><?php echo esc_html(__('Packages & Fee', 'kiriminaja-official')); ?></th>
                <th scope="col" class="manage-column column-thumb kiriof-col-action" style="width:7rem"><?php echo esc_html(__('Action', 'kiriminaja-official')); ?></th>
            </tr>
        </tfoot>
    </table>
    <br class="clear">
    <div class="tablenav bottom">
        <div class="alignleft actions" style="display:flex;align-items:center;">
            <?php $kiriof_filter_suffix = '_2';
            $kiriof_show_apply = true;
            include '_filters.php'; ?>
        </div>

        <div class="tablenav-pages">
            <span class="displaying-num"><?php
                                            /* translators: %s: total number of items */
                                            echo esc_html(sprintf(_n('%s item', '%s items', $kiriof_total, 'kiriminaja-official'), number_format_i18n($kiriof_total))); ?></span>
            <span class="pagination-links">
                <?php if ($kiriof_current_page <= 1) : ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                <?php else : ?>
                    <a class="first-page button" href="#" onclick="kiriofGoToPage(1);return false"><span>&laquo;</span></a>
                    <a class="prev-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) ($kiriof_current_page - 1); ?>);return false"><span>&lsaquo;</span></a>
                <?php endif; ?>
                <span class="paging-input">
                    <label for="current-page-selector-bottom" class="screen-reader-text"><?php esc_html_e('Current Page', 'kiriminaja-official'); ?></label>
                    <input class="current-page" id="current-page-selector-bottom" type="text" name="paged" value="<?php echo esc_attr($kiriof_current_page); ?>" size="3">
                    <span class="tablenav-paging-text"><?php esc_html_e('of', 'kiriminaja-official'); ?> <span class="total-pages"><?php echo esc_html(number_format_i18n($kiriof_total_pages)); ?></span></span>
                </span>
                <?php if ($kiriof_current_page >= $kiriof_total_pages) : ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
                <?php else : ?>
                    <a class="next-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) ($kiriof_current_page + 1); ?>);return false"><span>&rsaquo;</span></a>
                    <a class="last-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) $kiriof_total_pages; ?>);return false"><span>&raquo;</span></a>
                <?php endif; ?>
            </span>
        </div>
        <br class="clear">
    </div>

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
const $checkAllTop = $('#check_order_id_all_top');
const $checkAllBottom = $('#check_order_id_all_bottom');
const $transactionCheckboxes = () => $('[name="transaction_id[]"]');
const $requestPickupBtn = $('#kj-request-pickup-btn');
const kjRequestPickupLabel = '<?php echo esc_js(__('Request Pickup', 'kiriminaja-official')); ?>';
const kjUpdateRequestPickupCount = () => {
const count = $transactionCheckboxes().filter(':checked:not(:disabled)').length;
$requestPickupBtn.text(count > 0 ? `${kjRequestPickupLabel} (${count})` : kjRequestPickupLabel);
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
    $(document).on('keyup', '#kiriof-search-input' , function() {
    clearTimeout(kiriofSearchTimer);
    var $input=$(this);
    kiriofSearchTimer=setTimeout(function() {
    kiriofApplySearch('key', $input.val());
    }, 400);
    });

    function kiriofGetRequestPickupModal() {
    return $('.kiriof-request-pickup-modal');
    }

    function kiriofGetCancelModal() {
    return $('.kiriof-cancel-transaction-modal');
    }

    function kiriofSetModalState($modal, state) {
    $modal.find('.kiriof-modal-state').hide();
    $modal.find('.err_msg').hide().text('');

    if (state==='loading' ) {
    $modal.find('.kiriof-modal-state-loading').show();
    } else if (state==='error' ) {
    $modal.find('.kiriof-modal-state-error').show();
    } else {
    $modal.find('.kiriof-modal-state-content').show();
    }
    }

    function kiriofUpdateCancelReasonCounter() {
    const $modal=kiriofGetCancelModal();
    $modal.find('.kiriof-cancel-reason-count').text(($modal.find('.kiriof-cancel-reason').val() || '' ).length);
    }

    window.kjRequestPickupSchedule = function() {
        orderIds = [];
        $('input[name="transaction_id[]"]:checked').each(function() {
            orderIds.push($(this).val());
        });

        if (orderIds.length === 0) {
            alert('<?php echo esc_js(__('There is no selected transaction.', 'kiriminaja-official')); ?>');
            return;
        }

        $(document.body).WCBackboneModal({
            template: 'kiriof-modal-request-pickup',
            variable: {}
        });

        const $modal = kiriofGetRequestPickupModal();
        kiriofSetModalState($modal, 'loading');
        $modal.find('#btn-next').prop('disabled', true);

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
                    kiriofSetModalState($modal, 'error');
                    $modal.find('.kiriof-backbone-modal-error-text').text(resp?.message ?? '<?php echo esc_js(__('An error occurred.', 'kiriminaja-official')); ?>');
                    return;
                }

                if (resp?.data?.pickup_number) {
                    const pickupNumber = encodeURIComponent(resp?.data?.pickup_number || '');
                    const redirectBase = `<?php echo esc_url(admin_url('admin.php?page=kiriminaja-request-pickup')); ?>&pickup_number=${pickupNumber}`;
                    const shouldOpenPayment = resp?.data?.open_payment === true || resp?.data?.open_payment === 1 || resp?.data?.open_payment === '1';
                    window.location.href = shouldOpenPayment ? `${redirectBase}&open_payment=1` : redirectBase;
                    return;
                }

                const schedules = resp?.data?.schedules ?? [];
                const transaction_summary = resp?.data?.transaction_summary ?? {};
                const sum_cod_fee = transaction_summary?.sum_fee_cod ?? 0;
                const sum_non_cod_fee = transaction_summary?.sum_fee_non_cod ?? 0;
                const total_fee = parseInt(sum_cod_fee || 0, 10) + parseInt(sum_non_cod_fee || 0, 10);

                $modal.find('.kiriof-summary-cod').text(`Rp${kiriofMoneyFormat(transaction_summary?.sum_fee_cod ?? 0)}`);
                $modal.find('.kiriof-summary-non-cod').text(`Rp${kiriofMoneyFormat(transaction_summary?.sum_fee_non_cod ?? 0)}`);
                $modal.find('.kiriof-summary-total').text(`Rp${kiriofMoneyFormat(total_fee)}`);

                const $scheduleList = $modal.find('.kiriof-schedule-opt-list');
                $scheduleList.empty();
                $.each(schedules, function(idx, schedule) {
                    $scheduleList.append(`
                        <div class="kiriof-schedule-option">
                            <div class="kiriof-schedule-option-row">
                                <input id="opt_${schedule?.clock}" value="${schedule?.clock}" type="radio" name="schedule_opt">
                                <span class="kiriof-schedule-option-label">
                                    <label for="opt_${schedule?.clock}">${schedule?.label}</label>
                                </span>
                            </div>
                        </div>
                    `);
                });

                kiriofSetModalState($modal, 'content');
                $modal.find('#btn-next').prop('disabled', schedules.length === 0);
                if (schedules.length === 0) {
                    $modal.find('.err_msg').text('*<?php echo esc_js(__('No pickup schedule is available.', 'kiriminaja-official')); ?>').show();
                }

                kjLoadPaymentMethodConfig($modal, parseInt(total_fee || 0, 10));
            }
        });
    };

    function kjLoadPaymentMethodConfig($modal, totalFee) {
        $.ajax({
            type: "post",
            url: kiriofAjaxRoute(),
            data: {
                action: "kiriof_get_payment_method_config",
                nonce: kiriofAjax.nonce
            },
            complete: function(response) {
                const resp = JSON.parse(response.responseText).data;
                if (resp?.status !== 200) {
                    return;
                }

                const isTop = resp?.data?.is_top === true;
                const hasPin = resp?.data?.has_pin === true;

                if (isTop) {
                    $modal.find('.kiriof-payment-method-section').hide();
                    return;
                }

                $modal.find('.kiriof-payment-method-section').show();

                const $creditOpt = $modal.find('.kiriof-payment-method-option[data-method="credit"]');
                const $creditWarning = $modal.find('.kiriof-pm-credit-warning');
                const $balanceLabel = $modal.find('.kiriof-pm-balance');

                if (!hasPin) {
                    $creditOpt.addClass('kiriof-pm-disabled');
                    $modal.find('#kiriof-pm-credit').prop('disabled', true);
                    $creditWarning.html('<?php echo esc_js(__('PIN is not configured.', 'kiriminaja-official')); ?> <a href="https://app.kiriminaja.com/settings/profile?tab=keamanan&action=pin" target="_blank"><?php echo esc_js(__('Configure PIN', 'kiriminaja-official')); ?></a>').show();
                }

                $.ajax({
                    type: "post",
                    url: kiriofAjaxRoute(),
                    data: {
                        action: "kiriof_get_credit_balance",
                        nonce: kiriofAjax.nonce
                    },
                    complete: function(balanceResp) {
                        const bResp = JSON.parse(balanceResp.responseText).data;
                        const balance = bResp?.data?.balance ?? 0;
                        $balanceLabel.text('Rp' + kiriofMoneyFormat(balance));

                        if (balance < totalFee) {
                            $creditOpt.addClass('kiriof-pm-disabled');
                            $modal.find('#kiriof-pm-credit').prop('disabled', true);
                            if (hasPin) {
                                $creditWarning.html('<?php echo esc_js(__('Insufficient credit.', 'kiriminaja-official')); ?> <a href="https://app.kiriminaja.com/credit/top-up" target="_blank"><?php echo esc_js(__('Top Up Now', 'kiriminaja-official')); ?></a>').show();
                            }
                        } else if (hasPin) {
                            $creditWarning.hide();
                        }

                        if (totalFee > 10000000) {
                            $modal.find('.kiriof-payment-method-option[data-method="qris"]').addClass('kiriof-pm-disabled');
                            $modal.find('#kiriof-pm-qris').prop('disabled', true);
                        }
                    }
                });
            }
        });

        $modal.on('change', 'input[name="payment_method"]', function() {
            const method = $(this).val();
            const $pinSection = $modal.find('.kiriof-pin-section');
            if (method === 'credit') {
                $pinSection.show();
                $modal.find('#kiriof-pin-input').focus();
            } else {
                $pinSection.hide();
                $modal.find('.kiriof-pin-error').hide().text('');
            }
            kjUpdatePickupButton($modal);
        });

        $modal.on('input', '#kiriof-pin-input', function() {
            $(this).val($(this).val().replace(/\D/g, '').substring(0, 6));
            kjUpdatePickupButton($modal);
        });
    }

    function kjUpdatePickupButton($modal) {
        const method = $modal.find('input[name="payment_method"]:checked').val();
        const scheduleSelected = $modal.find('input[name="schedule_opt"]:checked').length > 0;
        const pmSectionVisible = $modal.find('.kiriof-payment-method-section').is(':visible');
        const pin = $modal.find('#kiriof-pin-input').val();

        let enabled = scheduleSelected;
        if (pmSectionVisible) {
            if (!method) {
                enabled = false;
            } else if (method === 'credit' && (!pin || pin.length !== 6)) {
                enabled = false;
            }
        }
        $modal.find('#btn-next').prop('disabled', !enabled);
    }

    function kjSubmitPickup($modal, ids, schedule, paymentMethod, pin, closeModal) {
        const $errMsg = $modal.find('.err_msg');
        $.ajax({
            type: "post",
            url: kiriofAjaxRoute(),
            data: {
                action: "kiriof_request_pickup_transaction",
                data: {
                    schedule: schedule,
                    order_ids: ids,
                    payment_method: paymentMethod,
                    pin: pin,
                    nonce: kiriofAjax.nonce
                }
            },
            complete: function(response) {
                const resp = JSON.parse(response.responseText).data;
                if (resp?.status !== 200) {
                    kiriofSetModalState($modal, 'content');
                    $modal.find('#btn-next').prop('disabled', false);

                    const errCode = resp?.data?.error_code;
                    if (errCode === 'BALANCE_NOT_ENOUGH') {
                        $errMsg.text('*<?php echo esc_js(__('Insufficient credit balance. Please top up or use QRIS.', 'kiriminaja-official')); ?>').show();
                    } else if (errCode === 'PIN_INVALID' || errCode === 'PIN_MAX_ATTEMPT_REACHED') {
                        $modal.find('.kiriof-pin-error').text('*' + (resp?.message || errCode)).show();
                        $errMsg.text('*' + (resp?.message || errCode)).show();
                    } else {
                        $errMsg.text('*' + (resp?.message || '<?php echo esc_js(__('Something went wrong.', 'kiriminaja-official')); ?>')).show();
                    }
                    return;
                }

                closeModal();
                const pickupNumber = resp?.data?.pickup_number;
                const shouldOpenPayment = resp?.data?.open_payment === true || resp?.data?.open_payment === 1;
                const redirectBase = `<?php echo esc_url(admin_url('admin.php?page=kiriminaja-request-pickup')); ?>&pickup_number=${pickupNumber}`;
                window.location.href = shouldOpenPayment ? `${redirectBase}&open_payment=1` : redirectBase;
            }
        });
    }

    window.kjShowCancelModal = function(orderId) {
        $(document.body).WCBackboneModal({
            template: 'kiriof-modal-cancel-transaction',
            variable: {
                order_id: orderId
            }
        });
        kiriofUpdateCancelReasonCounter();
    };

    $(document).on('input', '.kiriof-cancel-reason', function() {
        kiriofUpdateCancelReasonCounter();
    });

    $(document.body).on('wc_backbone_modal_next_response', function(event, target, data, closeModal) {
        if (target === 'kiriof-modal-request-pickup') {
            const $modal = kiriofGetRequestPickupModal();
            const $errMsg = $modal.find('.err_msg');
            const schedule = data?.schedule_opt;
            const pmSectionVisible = $modal.find('.kiriof-payment-method-section').is(':visible');
            const paymentMethod = pmSectionVisible ? ($modal.find('input[name="payment_method"]:checked').val() || '') : '';
            const pin = $modal.find('#kiriof-pin-input').val() || '';

            if (!schedule) {
                $errMsg.text('*<?php echo esc_js(__('Please select a pickup schedule.', 'kiriminaja-official')); ?>').show();
                return;
            }

            if (pmSectionVisible && !paymentMethod) {
                $errMsg.text('*<?php echo esc_js(__('Please select a payment method.', 'kiriminaja-official')); ?>').show();
                return;
            }

            if (paymentMethod === 'credit' && (!pin || pin.length !== 6)) {
                $modal.find('.kiriof-pin-error').text('*<?php echo esc_js(__('Please enter a 6-digit PIN.', 'kiriminaja-official')); ?>').show();
                return;
            }

            $errMsg.hide().text('');
            $modal.find('.kiriof-pin-error').hide().text('');
            kiriofSetModalState($modal, 'loading');
            $modal.find('#btn-next').prop('disabled', true);

            if (paymentMethod === 'credit') {
                $.ajax({
                    type: "post",
                    url: kiriofAjaxRoute(),
                    data: {
                        action: "kiriof_validate_pin",
                        nonce: kiriofAjax.nonce,
                        pin: pin
                    },
                    complete: function(pinResp) {
                        const pinData = JSON.parse(pinResp.responseText).data;
                        if (pinData?.status !== 200) {
                            kiriofSetModalState($modal, 'content');
                            $modal.find('#btn-next').prop('disabled', false);
                            const pinErr = pinData?.data;
                            if (pinErr?.error === 'PIN_MAX_ATTEMPT_REACHED') {
                                $modal.find('.kiriof-pin-error').text('*' + (pinData?.message || '<?php echo esc_js(__('PIN max attempts reached. Please try again later.', 'kiriminaja-official')); ?>')).show();
                                $modal.find('#kiriof-pm-credit').prop('disabled', true);
                            } else {
                                const remaining = (pinErr?.max_attempt ?? 3) - (pinErr?.attempt ?? 0);
                                $modal.find('.kiriof-pin-error').text('*' + (pinData?.message || '<?php echo esc_js(__('Incorrect PIN.', 'kiriminaja-official')); ?>') + ' ' + remaining + ' <?php echo esc_js(__('chances remaining.', 'kiriminaja-official')); ?>').show();
                            }
                            return;
                        }
                        kjSubmitPickup($modal, orderIds, schedule, paymentMethod, pin, closeModal);
                    }
                });
                return;
            }

            kjSubmitPickup($modal, orderIds, schedule, paymentMethod, pin, closeModal);
            return;
        }

        if (target === 'kiriof-modal-cancel-transaction') {
            const $modal = kiriofGetCancelModal();
            const $errMsg = $modal.find('.err_msg');
            const $loader = $modal.find('.kiriof-modal-state-loading');
            const reason = (data?.reason || '').trim();
            const orderId = data?.order_id || '';

            if (reason.length < 5) {
                $errMsg.text('<?php echo esc_js(__('*Alasan minimal 5 karakter', 'kiriminaja-official')); ?>').show();
                return;
            }
            if (reason.length > 200) {
                $errMsg.text('<?php echo esc_js(__('*Alasan maksimal 200 karakter', 'kiriminaja-official')); ?>').show();
                return;
            }

            if (!confirm('<?php echo esc_js(__('Are you sure you want to cancel this transaction?', 'kiriminaja-official')); ?>')) {
                return;
            }

            $errMsg.hide().text('');
            $loader.show();
            $modal.find('form').css('opacity', 0.45);
            $modal.find('#btn-next').prop('disabled', true);

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
                        $loader.hide();
                        $modal.find('form').css('opacity', 1);
                        $modal.find('#btn-next').prop('disabled', false);
                        $errMsg.text('*' + (resp?.message ?? '<?php echo esc_js(__('Terjadi kesalahan', 'kiriminaja-official')); ?>')).show();
                        return;
                    }

                    closeModal();
                    alert(resp?.message ?? '<?php echo esc_js(__('Transaction cancelled successfully.', 'kiriminaja-official')); ?>');
                    window.location.reload();
                }
            });
        }
    });

    })(jQuery);

        <?php
        $kiriof_inline_script = ob_get_clean();
        wp_add_inline_script('kiriof-script', $kiriof_inline_script);
        ?>
