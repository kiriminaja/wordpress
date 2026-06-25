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
 * @var string $kiriof_print_status_filter
 * @var int $kiriof_current_page
 * @var int $kiriof_total_pages
 * @var int $kiriof_total
 * @var int $kiriof_per_page
 * @var string $kiriof_search_by
 */
?>
<div class="wrap kj-wrap">

    <?php
    if (! in_array($kiriof_status_filter, ['all', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-cancelled', 'processed', 'order-issue'], true)) {
        $kiriof_status_filter = 'all';
    }
    $kiriof_title = __('Transactions', 'kiriminaja-official');
    $kiriof_is_processed_tab = ('processed' === $kiriof_status_filter);
    $kiriof_is_all_tab = ('all' === $kiriof_status_filter);
    $kiriof_header_extra = '<button id="kj-request-pickup-btn" onclick="kjRequestPickupSchedule()" class="page-title-action" type="button">' . esc_html__('Request Pickup', 'kiriminaja-official') . '</button>';
    if ($kiriof_is_processed_tab || $kiriof_is_all_tab) {
        $kiriof_header_extra .= ' <button id="kj-print-btn" onclick="kjPrintBulk()" class="page-title-action" type="button">' . esc_html__('Print', 'kiriminaja-official') . '</button>';
    }
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
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $kiriof_print_status_filter = isset($_GET['print_status']) ? sanitize_text_field(wp_unslash($_GET['print_status'])) : '';
        ?>
        <input type="text" name="page" value="<?php echo esc_attr($kiriof_page_filter); ?>">
        <input type="text" name="cpage" value="1">
        <input type="text" name="key" value="<?php echo esc_attr($kiriof_key_filter); ?>">
        <input type="text" name="month" value="<?php echo esc_attr($kiriof_month_filter); ?>">
        <input type="text" name="status" value="<?php echo esc_attr($kiriof_status_filter); ?>">
        <input type="text" name="cod" value="<?php echo esc_attr($kiriof_cod_filter); ?>">
        <input type="text" name="courier" value="<?php echo esc_attr($kiriof_courier_filter); ?>">
        <input type="text" name="print_status" value="<?php echo esc_attr($kiriof_print_status_filter); ?>">
        <input type="text" name="per_page" value="<?php echo esc_attr($kiriof_per_page); ?>">
        <input type="text" name="search_by" value="<?php echo esc_attr($kiriof_search_by); ?>">
    </form>
    <form id="kiriof-print-bulk-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank" style="display:none">
        <input type="hidden" name="action" value="kiriof_resi_print_bulk">
        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('kiriof_resi_print_bulk')); ?>">
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
                    $kiriofReadShippingData = static function ($shippingData, array $keys) {
                        foreach ($keys as $key) {
                            if (isset($shippingData->$key)) {
                                $value = trim((string) $shippingData->$key);
                                if ('' !== $value) {
                                    return $value;
                                }
                            }
                        }

                        return '';
                    };

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
                    $kiriof_billingFirstName = $kiriofReadShippingData($kiriof_shippingData, ['_billing_first_name', 'billing_first_name', 'first_name']);
                    $kiriof_billingLastName = $kiriofReadShippingData($kiriof_shippingData, ['_billing_last_name', 'billing_last_name', 'last_name']);
                    $kiriof_billingAddress1 = $kiriofReadShippingData($kiriof_shippingData, ['_billing_address_1', 'billing_address_1', 'address_1']);
                    $kiriof_billingAddress2 = $kiriofReadShippingData($kiriof_shippingData, ['_billing_address_2', 'billing_address_2', 'address_2']);
                    $kiriof_billingPostcode = $kiriofReadShippingData($kiriof_shippingData, ['_billing_postcode', 'billing_postcode', 'postcode']);
                    $kiriof_billingPhone   = $kiriofReadShippingData($kiriof_shippingData, ['_billing_phone', 'billing_phone', 'phone']);
                    $kiriof_shippingPhone  = $kiriofReadShippingData($kiriof_shippingData, ['_shipping_phone', 'shipping_phone', '_billing_phone', 'billing_phone', 'phone']);
                    $kiriof_shippingFirstName = $kiriofReadShippingData($kiriof_shippingData, ['_shipping_first_name', 'shipping_first_name']);
                    $kiriof_shippingLastName = $kiriofReadShippingData($kiriof_shippingData, ['_shipping_last_name', 'shipping_last_name']);
                    $kiriof_shippingAddress1 = $kiriofReadShippingData($kiriof_shippingData, ['_shipping_address_1', 'shipping_address_1', '_billing_address_1', 'billing_address_1', 'address_1']);
                    $kiriof_shippingAddress2 = $kiriofReadShippingData($kiriof_shippingData, ['_shipping_address_2', 'shipping_address_2', '_billing_address_2', 'billing_address_2', 'address_2']);
                    $kiriof_shippingCity = $kiriofReadShippingData($kiriof_shippingData, ['_shipping_city', 'shipping_city', '_billing_city', 'billing_city', 'city']);
                    $kiriof_shippingState = $kiriofReadShippingData($kiriof_shippingData, ['_shipping_state', 'shipping_state', '_billing_state', 'billing_state', 'state']);
                    $kiriof_shippingCountry = $kiriofReadShippingData($kiriof_shippingData, ['_shipping_country', 'shipping_country', '_billing_country', 'billing_country', 'country']);
                    $kiriof_shippingPostcode = $kiriofReadShippingData($kiriof_shippingData, ['_shipping_postcode', 'shipping_postcode', '_billing_postcode', 'billing_postcode', 'postcode']);
                    $kiriof_destinationSubDistrict = $kiriof_row->destination_sub_district ?? '';
                    $kiriof_wcOrder = function_exists('wc_get_order') ? wc_get_order($kiriof_row->wc_order_id) : false;
                    $kiriofBillingAddress = $kiriof_wcOrder && method_exists($kiriof_wcOrder, 'get_address') ? (array) $kiriof_wcOrder->get_address('billing') : [];
                    $kiriofShippingAddress = $kiriof_wcOrder && method_exists($kiriof_wcOrder, 'get_address') ? (array) $kiriof_wcOrder->get_address('shipping') : [];
                    if ('' === $kiriof_billingFirstName) {
                        $kiriof_billingFirstName = trim((string) ($kiriofBillingAddress['first_name'] ?? ''));
                    }
                    if ('' === $kiriof_billingLastName) {
                        $kiriof_billingLastName = trim((string) ($kiriofBillingAddress['last_name'] ?? ''));
                    }
                    if ('' === $kiriof_billingAddress1) {
                        $kiriof_billingAddress1 = trim((string) ($kiriofBillingAddress['address_1'] ?? ''));
                    }
                    if ('' === $kiriof_billingAddress2) {
                        $kiriof_billingAddress2 = trim((string) ($kiriofBillingAddress['address_2'] ?? ''));
                    }
                    if ('' === $kiriof_billingPostcode) {
                        $kiriof_billingPostcode = trim((string) ($kiriofBillingAddress['postcode'] ?? ''));
                    }
                    if ('' === $kiriof_billingPhone) {
                        $kiriof_billingPhone = trim((string) ($kiriofBillingAddress['phone'] ?? ''));
                    }
                    if ('' === $kiriof_shippingFirstName) {
                        $kiriof_shippingFirstName = trim((string) ($kiriofShippingAddress['first_name'] ?? ''));
                    }
                    if ('' === $kiriof_shippingLastName) {
                        $kiriof_shippingLastName = trim((string) ($kiriofShippingAddress['last_name'] ?? ''));
                    }
                    if ('' === $kiriof_shippingAddress1) {
                        $kiriof_shippingAddress1 = trim((string) ($kiriofShippingAddress['address_1'] ?? ''));
                    }
                    if ('' === $kiriof_shippingAddress2) {
                        $kiriof_shippingAddress2 = trim((string) ($kiriofShippingAddress['address_2'] ?? ''));
                    }
                    if ('' === $kiriof_shippingCity) {
                        $kiriof_shippingCity = trim((string) ($kiriofShippingAddress['city'] ?? ''));
                    }
                    if ('' === $kiriof_shippingState) {
                        $kiriof_shippingState = trim((string) ($kiriofShippingAddress['state'] ?? ''));
                    }
                    if ('' === $kiriof_shippingCountry) {
                        $kiriof_shippingCountry = trim((string) ($kiriofShippingAddress['country'] ?? ''));
                    }
                    if ('' === $kiriof_shippingPostcode) {
                        $kiriof_shippingPostcode = trim((string) ($kiriofShippingAddress['postcode'] ?? ''));
                    }
                    if ('' === $kiriof_shippingPhone) {
                        $kiriof_shippingPhone = trim((string) ($kiriofShippingAddress['phone'] ?? ''));
                    }
                    if ('' === $kiriof_billingFirstName && $kiriof_wcOrder) {
                        $kiriof_billingFirstName = (string) $kiriof_wcOrder->get_billing_first_name();
                    }
                    if ('' === $kiriof_billingLastName && $kiriof_wcOrder) {
                        $kiriof_billingLastName = (string) $kiriof_wcOrder->get_billing_last_name();
                    }
                    if ('' === $kiriof_billingPhone && $kiriof_wcOrder) {
                        $kiriof_billingPhone = (string) $kiriof_wcOrder->get_billing_phone();
                    }
                    if ('' === $kiriof_shippingFirstName && $kiriof_wcOrder) {
                        $kiriof_shippingFirstName = (string) $kiriof_wcOrder->get_shipping_first_name();
                    }
                    if ('' === $kiriof_shippingLastName && $kiriof_wcOrder) {
                        $kiriof_shippingLastName = (string) $kiriof_wcOrder->get_shipping_last_name();
                    }
                    if ('' === $kiriof_shippingPhone && $kiriof_wcOrder && method_exists($kiriof_wcOrder, 'get_shipping_phone')) {
                        $kiriof_shippingPhone = (string) $kiriof_wcOrder->get_shipping_phone();
                    }
                    if ('' === $kiriof_shippingFirstName) {
                        $kiriof_shippingFirstName = $kiriof_billingFirstName;
                    }
                    if ('' === $kiriof_shippingLastName) {
                        $kiriof_shippingLastName = $kiriof_billingLastName;
                    }
                    if ('' === $kiriof_shippingPhone) {
                        $kiriof_shippingPhone = $kiriof_billingPhone;
                    }
                    $kiriofBillingName = trim($kiriof_billingFirstName . ' ' . $kiriof_billingLastName);
                    if ('' === $kiriofBillingName && $kiriof_wcOrder && method_exists($kiriof_wcOrder, 'get_formatted_billing_full_name')) {
                        $kiriofBillingName = trim((string) $kiriof_wcOrder->get_formatted_billing_full_name());
                    }
                    if ('' === $kiriofBillingName) {
                        $kiriofBillingName = trim($kiriof_shippingFirstName . ' ' . $kiriof_shippingLastName);
                    }
                    $kiriofShippingName = trim($kiriof_shippingFirstName . ' ' . $kiriof_shippingLastName);
                    if ('' === $kiriofShippingName && $kiriof_wcOrder && method_exists($kiriof_wcOrder, 'get_formatted_shipping_full_name')) {
                        $kiriofShippingName = trim((string) $kiriof_wcOrder->get_formatted_shipping_full_name());
                    }
                    if ('' === $kiriofShippingName) {
                        $kiriofShippingName = $kiriofBillingName;
                    }
                    if ('' === $kiriof_shippingPhone && $kiriof_wcOrder) {
                        $kiriof_shippingPhone = (string) $kiriof_wcOrder->get_meta('_billing_phone', true);
                    }
                    if ('' === $kiriof_shippingPhone && $kiriof_wcOrder) {
                        $kiriof_shippingPhone = (string) $kiriof_wcOrder->get_meta('_shipping_phone', true);
                    }
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
                    $kiriof_isAllFilter = ('all' === $kiriof_status_filter);
                    $kiriof_canPrintRow = (($kiriof_isProcessedFilter || $kiriof_isAllFilter) && ! empty($kiriof_awb) && 'request_pickup' === $kiriof_row->status);
                    $kiriof_checkboxDisabled = ($kiriof_isProcessedFilter || $kiriof_isAllFilter)
                        ? (! $kiriof_canPrintRow && ($kiriof_isDeficitRow || ! $kiriof_isProcessable))
                        : ($kiriof_isDeficitRow || ! $kiriof_isProcessable);
                    $kiriof_checkboxTitle   = $kiriof_isDeficitRow
                        ? __('Resolve the COD deficit before proceeding.', 'kiriminaja-official')
                        : (($kiriof_isProcessedFilter || ($kiriof_isAllFilter && ! $kiriof_isProcessable))
                            ? ($kiriof_canPrintRow ? '' : __('Order must have an AWB and request pickup status before it can be printed.', 'kiriminaja-official'))
                            : ($kiriof_isProcessable ? '' : __('Order must be in Processing status before it can be picked up.', 'kiriminaja-official')));
                    $kiriof_printStatusBadge = ! empty($kiriof_row->is_printed)
                        ? '<span class="kiriof-print-status printed" style="display:inline-block;font-size:11px;background:#e7f5e9;color:#008a20;border-radius:3px;padding:1px 5px">' . esc_html__('Printed', 'kiriminaja-official') . '</span>'
                        : '<span class="kiriof-print-status unprinted" style="display:inline-block;font-size:11px;background:#f6f7f7;color:#646970;border-radius:3px;padding:1px 5px">' . esc_html__('Unprinted', 'kiriminaja-official') . '</span>';

                    echo '
                                                      <tr>
                                                        <td class="manage-column column-thumb kiriof-col-select">
                                                             <input type="checkbox" name="transaction_id[]" value="' . esc_attr($kiriof_orderIdKA) . '" data-can-pickup="' . ($kiriof_isProcessable && ! $kiriof_isDeficitRow ? '1' : '0') . '" data-can-print="' . ($kiriof_canPrintRow ? '1' : '0') . '"' . ($kiriof_checkboxDisabled ? ' disabled' : '') . ($kiriof_checkboxTitle ? ' title="' . esc_attr($kiriof_checkboxTitle) . '"' : '') . '>
                                                        </td>
                                                        <td class="manage-column column-thumb kiriof-col-order">
                                                            <a href="' . esc_url($kiriof_orderEditUrl) . '" target="_blank" style="font-weight: 700">#' . esc_html($kiriof_row->wc_order_id) . '</a>
                                                            <div style="font-weight: 600; margin-top: 2px">' . esc_html($kiriofBillingName) . '</div>'
                        . ($kiriof_shippingPhone
                            ? '<a href="tel:' . esc_attr($kiriof_shippingPhone) . '" style="font-size: 12px; color: #50575e">' . esc_html($kiriof_shippingPhone) . '</a>'
                            : '') . '
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
                                                                ' . wp_kses(
                            $kiriof_printStatusBadge,
                            array(
                                'span' => array(
                                    'class' => true,
                                    'style' => true,
                                ),
                            )
                        ) . '
                                                            </div>
                                                        </td>
                                                        <td class="manage-column column-thumb kiriof-col-airwaybill">'
                        . ($kiriof_awb
                            ? '<div><span style="color: #8c8f94">' . esc_html__('AWB', 'kiriminaja-official') . ': </span><span style="font-weight: 700">' . esc_html($kiriof_awb) . '</span></div>'
                            : '<div style="color: #8c8f94">' . esc_html__('AWB', 'kiriminaja-official') . ': —</div>')
                        . '<div><span style="color: #8c8f94">' . esc_html__('Order ID', 'kiriminaja-official') . ': </span><span style="font-weight: 700">' . esc_html($kiriof_orderIdKA) . '</span></div>
                                                         </td>
                                                        <td class="manage-column column-thumb kiriof-col-shipto">
                                                            <div class="kiriof-shipto-name">' . esc_html($kiriofShippingName) . '</div>
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
const $printBtn = $('#kj-print-btn');
const kjRequestPickupLabel = '<?php echo esc_js(__('Request Pickup', 'kiriminaja-official')); ?>';
const kjPrintLabel = '<?php echo esc_js(__('Print', 'kiriminaja-official')); ?>';
const kjPickScheduleLabel = '<?php echo esc_js(__('Pick Schedule', 'kiriminaja-official')); ?>';
const kjConfirmPinLabel = '<?php echo esc_js(__('Confirm PIN', 'kiriminaja-official')); ?>';
const kjValidateLabel = '<?php echo esc_js(__('Validate', 'kiriminaja-official')); ?>';
const kjUpdateRequestPickupCount = () => {
const pickupCount = $transactionCheckboxes().filter(':checked:not(:disabled)[data-can-pickup="1"]').length;
const printCount = $transactionCheckboxes().filter(':checked:not(:disabled)[data-can-print="1"]').length;
$requestPickupBtn.text(pickupCount > 0 ? `${kjRequestPickupLabel} (${pickupCount})` : kjRequestPickupLabel);
$printBtn.text(printCount > 0 ? `${kjPrintLabel} (${printCount})` : kjPrintLabel);
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
$(`#table-form [name="print_status"]`).val(document.getElementById('print_status_search_1').value);
$(`#table-form [name="cpage"]`).val('1');
$(`#table-form`).trigger('submit');
};

window.kiriofSubmitFiltersBottom = function() {
document.getElementById('month_search_1').value = document.getElementById('month_search_2').value;
document.getElementById('cod_search_1').value = document.getElementById('cod_search_2').value;
document.getElementById('courier_search_1').value = document.getElementById('courier_search_2').value;
document.getElementById('print_status_search_1').value = document.getElementById('print_status_search_2').value;
$(`#table-form [name="month"]`).val(document.getElementById('month_search_2').value);
$(`#table-form [name="cod"]`).val(document.getElementById('cod_search_2').value);
$(`#table-form [name="courier"]`).val(document.getElementById('courier_search_2').value);
$(`#table-form [name="print_status"]`).val(document.getElementById('print_status_search_2').value);
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
    $modal.data('kiriofStep', state);

    if (state==='loading' ) {
    $modal.find('.kiriof-modal-state-loading').show();
    } else if (state==='error' ) {
    $modal.find('.kiriof-modal-state-error').show();
    } else if (state==='pin' ) {
    $modal.find('.kiriof-modal-state-pin').show();
    $modal.find('#btn-next').text(kjValidateLabel);
    kjFocusPinInput($modal);
    } else {
    $modal.find('.kiriof-modal-state-content').show();
    }
    }

    function kiriofUpdateCancelReasonCounter() {
    const $modal=kiriofGetCancelModal();
    $modal.find('.kiriof-cancel-reason-count').text(($modal.find('.kiriof-cancel-reason').val() || '' ).length);
    }

    function kjEnsurePinInputReady($modal) {
    const widget = $modal.find('#kiriof-pin-widget').get(0);
    const $fallback = $modal.find('#kiriof-pin-fallback');
    const hasRenderedWidget = !!(widget && widget.shadowRoot && widget.shadowRoot.childNodes.length > 0);

    if (hasRenderedWidget) {
    $modal.find('#kiriof-pin-widget').show();
    $fallback.hide();
    return 'widget';
    }

    $modal.find('#kiriof-pin-widget').hide();
    $fallback.show();
    return 'fallback';
    }

    function kjFocusPinInput($modal) {
    const mode = kjEnsurePinInputReady($modal);
    if ('widget' === mode) {
    $modal.find('#kiriof-pin-widget').trigger('focus');
    return;
    }

    $modal.find('#kiriof-pin-fallback').trigger('focus');
    }

    function kjSetPinValue($modal, value) {
    const normalizedValue = (value || '').toString().replace(/\D/g, '').substring(0, 6);
    $modal.find('#kiriof-pin-input').val(normalizedValue);
    $modal.find('#kiriof-pin-fallback').val(normalizedValue);
    $modal.find('#kiriof-pin-widget').attr('value', normalizedValue);
    }

    function kjGetPinValue($modal) {
    return ($modal.find('#kiriof-pin-input').val() || '').toString();
    }

    function kjResetPinRetryState($modal) {
    const timer = $modal.data('kiriofPinCooldownTimer');
    if (timer) {
    clearInterval(timer);
    }
    $modal.data('kiriofPinAttempts', 0);
    $modal.data('kiriofPinMaxAttempts', 3);
    $modal.data('kiriofPinLockUntil', '');
    $modal.data('kiriofPinCooldownTimer', null);
    }

    function kjNormalizePinErrorData($modal, data) {
    const normalized = Object.assign({}, data || {});
    const error = normalized.error || '';
    const maxAttempts = Math.max(parseInt(normalized.max_attempt || $modal.data('kiriofPinMaxAttempts') || 3, 10) || 3, 1);
    const localAttempts = parseInt($modal.data('kiriofPinAttempts') || 0, 10) || 0;
    let attempts = parseInt(normalized.attempt || 0, 10) || 0;

    if ('PIN_INVALID' === error || normalized.valid === false) {
    attempts = Math.max(attempts, localAttempts + 1);
    }

    if ('PIN_MAX_ATTEMPT_REACHED' === error) {
    attempts = Math.max(attempts, maxAttempts);
    }

    normalized.attempt = Math.min(attempts, maxAttempts);
    normalized.max_attempt = maxAttempts;

    let lockUntil = normalized.lock_until || '';
    if (!lockUntil && normalized.attempt >= maxAttempts) {
    lockUntil = new Date(Date.now() + 60 * 60 * 1000).toISOString();
    normalized.error = 'PIN_MAX_ATTEMPT_REACHED';
    }

    normalized.lock_until = lockUntil;
    $modal.data('kiriofPinAttempts', normalized.attempt);
    $modal.data('kiriofPinMaxAttempts', normalized.max_attempt);
    $modal.data('kiriofPinLockUntil', lockUntil);

    return normalized;
    }

    window.kjRequestPickupSchedule = function() {
        orderIds = [];
        $('input[name="transaction_id[]"][data-can-pickup="1"]:checked:not(:disabled)').each(function() {
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
        kjResetPinRetryState($modal);
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
                const count_non_cod = parseInt(transaction_summary?.count_non_cod ?? 0, 10);
                const total_fee = parseInt(sum_cod_fee || 0, 10) + parseInt(sum_non_cod_fee || 0, 10);

                $modal.find('.kiriof-summary-cod').text(`Rp${kiriofMoneyFormat(transaction_summary?.sum_fee_cod ?? 0)}`);
                $modal.find('.kiriof-summary-non-cod').text(`Rp${kiriofMoneyFormat(transaction_summary?.sum_fee_non_cod ?? 0)}`);
                $modal.find('.kiriof-summary-total').text(`Rp${kiriofMoneyFormat(total_fee)}`);

                const $scheduleSelect = $modal.find('.kiriof-schedule-select');
                $scheduleSelect.find('option:not(:first)').remove();
                $.each(schedules, function(idx, schedule) {
                    $scheduleSelect.append(
                        $('<option>', { value: schedule?.clock, text: schedule?.label })
                    );
                });

                kiriofSetModalState($modal, 'content');
                $modal.data('kiriofPaymentConfigLoaded', false);
                $modal.data('kiriofPaymentRequired', true);
                $modal.data('kiriofCountNonCod', count_non_cod);
                $modal.find('#btn-next').prop('disabled', true);
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
                const countNonCod = parseInt($modal.data('kiriofCountNonCod') || 0, 10);
                const $stateBanner = $modal.find('.kiriof-pm-state-banner');

                $stateBanner.hide().text('');

                if (countNonCod <= 0) {
                    $modal.find('.kiriof-payment-method-section').hide();
                    $modal.data('kiriofPaymentConfigLoaded', true);
                    $modal.data('kiriofPaymentRequired', false);
                    $stateBanner.text('<?php echo esc_js(__('COD-only pickups do not require a payment method.', 'kiriminaja-official')); ?>').show();
                    kjUpdatePickupButton($modal);
                    return;
                }

                if (isTop) {
                    $modal.find('.kiriof-payment-method-section').hide();
                    $modal.data('kiriofPaymentConfigLoaded', true);
                    $modal.data('kiriofPaymentRequired', false);
                    $stateBanner.text('<?php echo esc_js(__('TOP merchant uses published rates. Payment method is not required for this pickup.', 'kiriminaja-official')); ?>').show();
                    kjUpdatePickupButton($modal);
                    return;
                }

                $modal.find('.kiriof-payment-method-section').show();
                $modal.data('kiriofPaymentConfigLoaded', true);
                $modal.data('kiriofPaymentRequired', true);

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
                        $balanceLabel.text(kiriofMoneyFormat(balance));

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

                        kjUpdatePickupButton($modal);
                    }
                });

                kjUpdatePickupButton($modal);
            }
        });

        $modal.on('change', 'input[name="payment_method"]', function() {
            const method = $(this).val();
            $modal.data('kiriofSelectedPaymentMethod', method || '');
            if (method !== 'credit') {
                kjSetPinValue($modal, '');
                kjResetPinRetryState($modal);
                $modal.find('#kiriof-pin-widget').removeAttr('invalid');
                $modal.find('.kiriof-pin-error').hide().text('');
            }
            kjUpdatePickupButton($modal);
        });

        $modal.on('click', '.kiriof-payment-method-option', function(event) {
            if ($(this).hasClass('kiriof-pm-disabled') || $(event.target).is('a')) {
                return;
            }

            const $input = $(this).find('input[name="payment_method"]:not(:disabled)');
            if ($input.length) {
                $input.prop('checked', true).trigger('change');
            }
        });

        $modal.on('pin-change pin-complete', '#kiriof-pin-widget', function(event) {
            const value = (event.originalEvent?.detail?.value || '').replace(/\D/g, '').substring(0, 6);
            kjSetPinValue($modal, value);
            $(this).removeAttr('invalid');
            $modal.find('.kiriof-pin-error').hide().text('');
            kjUpdatePickupButton($modal);
        });

        $modal.on('input', '#kiriof-pin-fallback', function() {
            kjSetPinValue($modal, $(this).val());
            $modal.find('#kiriof-pin-widget').removeAttr('invalid');
            $modal.find('.kiriof-pin-error').hide().text('');
            kjUpdatePickupButton($modal);
        });

        $modal.on('change', 'select[name="schedule_opt"]', function() {
            kjUpdatePickupButton($modal);
        });
    }

    window.kjPrintBulk = function() {
        const selectedOrderIds = [];
        $('input[name="transaction_id[]"][data-can-print="1"]:checked:not(:disabled)').each(function() {
            selectedOrderIds.push($(this).val());
        });

        if (selectedOrderIds.length === 0) {
            alert('<?php echo esc_js(__('Please select at least one order to print.', 'kiriminaja-official')); ?>');
            return;
        }

        const $form = $('#kiriof-print-bulk-form');
        $form.find('input[name="oids[]"]').remove();
        selectedOrderIds.forEach(function(orderId) {
            $('<input>', { type: 'hidden', name: 'oids[]', value: orderId }).appendTo($form);
        });
        $form.trigger('submit');
    };

    function kjUpdatePickupButton($modal) {
        const step = $modal.data('kiriofStep') || 'content';
        const paymentConfigLoaded = $modal.data('kiriofPaymentConfigLoaded') === true;
        const paymentRequired = $modal.data('kiriofPaymentRequired') === true;
        const method = $modal.find('input[name="payment_method"]:checked:not(:disabled)').val();
        const scheduleSelected = !!$modal.find('select[name="schedule_opt"]').val();
        const pin = kjGetPinValue($modal);

        if (step === 'pin') {
            $modal.find('#btn-next').text(kjValidateLabel).prop('disabled', !pin || pin.length !== 6);
            return;
        }

        let enabled = scheduleSelected && paymentConfigLoaded;
        if (paymentRequired) {
            if (!method) {
                enabled = false;
            }
        }
        $modal.find('#btn-next').text(method === 'credit' ? kjConfirmPinLabel : kjPickScheduleLabel).prop('disabled', !enabled);
    }

    function kjFormatPinLockCountdown(lockUntil) {
        if (!lockUntil) {
            return '';
        }

        const remainingSeconds = Math.max(0, Math.floor((new Date(lockUntil).getTime() - Date.now()) / 1000));
        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = remainingSeconds % 60;
        return `${minutes}m ${seconds}s`;
    }

    function kjRenderPinLockError($modal, lockUntil, message) {
        const cooldown = kjFormatPinLockCountdown(lockUntil);
        const description = cooldown
            ? `<?php echo esc_js(__('You have entered the wrong code three times. Please wait', 'kiriminaja-official')); ?> <span class="kiriof-pin-cooldown">${cooldown}</span> <?php echo esc_js(__('to try again.', 'kiriminaja-official')); ?>`
            : (message || '<?php echo esc_js(__('You have entered the wrong code too many times. Please try again later.', 'kiriminaja-official')); ?>');

        $modal.find('.kiriof-pin-error').attr('data-tone', 'critical').html('<strong><?php echo esc_js(__('Too Many Attempts', 'kiriminaja-official')); ?></strong><br>' + description).show();
    }

    function kjStartPinCooldown($modal, lockUntil, message) {
        const existingTimer = $modal.data('kiriofPinCooldownTimer');
        if (existingTimer) {
            clearInterval(existingTimer);
        }

        $modal.find('#kiriof-pin-widget, #kiriof-pin-fallback').hide();
        $modal.find('#btn-next').text('<?php echo esc_js(__('Back', 'kiriminaja-official')); ?>').prop('disabled', false);
        kjRenderPinLockError($modal, lockUntil, message);

        const timer = setInterval(function() {
            const remainingSeconds = Math.max(0, Math.floor((new Date(lockUntil).getTime() - Date.now()) / 1000));
            if (remainingSeconds <= 0) {
                clearInterval(timer);
                kjResetPinRetryState($modal);
                $modal.find('.kiriof-pin-error').hide().text('').removeAttr('data-tone');
                kjFocusPinInput($modal);
                kjUpdatePickupButton($modal);
                return;
            }
            kjRenderPinLockError($modal, lockUntil, message);
        }, 1000);

        $modal.data('kiriofPinCooldownTimer', timer);
    }

    function kjShowPinError($modal, data, message) {
        const normalizedData = kjNormalizePinErrorData($modal, data);
        const error = normalizedData?.error || '';
        if (error === 'PIN_MAX_ATTEMPT_REACHED') {
            kjStartPinCooldown($modal, normalizedData?.lock_until, message);
            $modal.find('#kiriof-pin-widget').attr('invalid', '');
            return;
        }

        const attempt = parseInt(normalizedData?.attempt || 0, 10);
        const maxAttempt = parseInt(normalizedData?.max_attempt || 0, 10);
        const remaining = Math.max(0, maxAttempt - attempt);
        const retryText = attempt > 0 && maxAttempt > 0
            ? '<div class="kiriof-pin-retry-text"><?php echo esc_js(__('You still have', 'kiriminaja-official')); ?> <strong>' + remaining + '</strong> <?php echo esc_js(__('chances to enter the PIN.', 'kiriminaja-official')); ?></div>'
            : '';

        $modal.find('.kiriof-pin-error').attr('data-tone', 'critical').html('<strong><?php echo esc_js(__('Incorrect PIN', 'kiriminaja-official')); ?></strong><br>' + (message || '<?php echo esc_js(__('Please check the PIN code you entered again.', 'kiriminaja-official')); ?>') + retryText).show();
        $modal.find('#kiriof-pin-widget').attr('invalid', '');
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
                    const errCode = resp?.data?.error_code;
                    if (errCode === 'BALANCE_NOT_ENOUGH') {
                        kiriofSetModalState($modal, 'content');
                        $errMsg.text('*<?php echo esc_js(__('Insufficient credit balance. Please top up or use QRIS.', 'kiriminaja-official')); ?>').show();
                    } else if (errCode === 'PIN_INVALID' || errCode === 'PIN_MAX_ATTEMPT_REACHED') {
                        kiriofSetModalState($modal, 'pin');
                        kjShowPinError($modal, resp?.data || {}, resp?.message || errCode);
                    } else {
                        kiriofSetModalState($modal, 'content');
                        $errMsg.text('*' + (resp?.message || '<?php echo esc_js(__('Something went wrong.', 'kiriminaja-official')); ?>')).show();
                    }
                    kjUpdatePickupButton($modal);
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
            const step = $modal.data('kiriofStep') || 'content';
            const schedule = data?.schedule_opt;
            const paymentMethod = $modal.find('input[name="payment_method"]:checked').val() || $modal.data('kiriofSelectedPaymentMethod') || '';
            const pin = kjGetPinValue($modal);

            if (!schedule) {
                $errMsg.text('*<?php echo esc_js(__('Please select a pickup schedule.', 'kiriminaja-official')); ?>').show();
                return;
            }

            if ($modal.data('kiriofPaymentRequired') === true && !paymentMethod) {
                $errMsg.text('*<?php echo esc_js(__('Please select a payment method.', 'kiriminaja-official')); ?>').show();
                return;
            }

            if (paymentMethod === 'credit' && step !== 'pin') {
                kiriofSetModalState($modal, 'pin');
                kjUpdatePickupButton($modal);
                return;
            }

            if (paymentMethod === 'credit' && step === 'pin' && $modal.data('kiriofPinLockUntil')) {
                kiriofSetModalState($modal, 'content');
                kjUpdatePickupButton($modal);
                return;
            }

            if (paymentMethod === 'credit' && (!pin || pin.length !== 6)) {
                $modal.find('.kiriof-pin-error').text('*<?php echo esc_js(__('Please enter a 6-digit PIN.', 'kiriminaja-official')); ?>').show();
                kjUpdatePickupButton($modal);
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
                            kiriofSetModalState($modal, 'pin');
                            const pinErr = pinData?.data;
                            if (pinErr?.error === 'PIN_MAX_ATTEMPT_REACHED') {
                                kjShowPinError($modal, pinErr || {}, pinData?.message || '<?php echo esc_js(__('PIN max attempts reached. Please try again later.', 'kiriminaja-official')); ?>');
                                $modal.find('#kiriof-pm-credit').prop('disabled', true);
                            } else {
                                kjShowPinError($modal, pinErr || {}, pinData?.message || '<?php echo esc_js(__('Incorrect PIN.', 'kiriminaja-official')); ?>');
                            }
                            kjUpdatePickupButton($modal);
                            return;
                        }
                        kjResetPinRetryState($modal);
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
