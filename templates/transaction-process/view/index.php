<?php
// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

// Cache frequently used values
$kiriof_helper = kiriof_helper();
$kiriof_homeUrl = home_url();
$kiriof_adminUrl = $kiriof_homeUrl . '/wp-admin';
$kiriof_current_user = wp_get_current_user();
$kiriof_pin_cache_ttl = (int) apply_filters('kiriof_pin_cache_ttl', 15 * MINUTE_IN_SECONDS, $kiriof_current_user);
if ($kiriof_pin_cache_ttl < MINUTE_IN_SECONDS) {
    $kiriof_pin_cache_ttl = MINUTE_IN_SECONDS;
}

        wp_localize_script(
    'kiriof-transaction-process',
    'kiriofTransactionProcess',
    array(
        'urls' => array(
            'pickup' => esc_url_raw(admin_url('admin.php?page=kiriminaja-request-pickup')),
        ),
        'pinCache' => array(
            'key' => 'kiriof_pin_cache_' . get_current_blog_id() . '_' . (int) get_current_user_id(),
            'ttl' => $kiriof_pin_cache_ttl,
            'userHash' => hash_hmac('sha256', (string) get_current_user_id(), wp_salt('auth')),
            'siteHash' => hash_hmac('sha256', home_url('/'), wp_salt('auth')),
        ),
        'i18n' => array(
            'requestPickup' => __('Request Pickup', 'kiriminaja-official'),
            'print' => __('Print', 'kiriminaja-official'),
            'pickSchedule' => __('Pick Schedule', 'kiriminaja-official'),
            'confirmPin' => __('Confirm PIN', 'kiriminaja-official'),
            'validate' => __('Validate', 'kiriminaja-official'),
            'noSelectedTransaction' => __('There is no selected transaction.', 'kiriminaja-official'),
            'genericError' => __('An error occurred.', 'kiriminaja-official'),
            'noSchedule' => __('No pickup schedule is available.', 'kiriminaja-official'),
            'codOnlyNoPayment' => __('COD-only pickups do not require a payment method.', 'kiriminaja-official'),
            'topNoPayment' => __('TOP merchant uses published rates. Payment method is not required for this pickup.', 'kiriminaja-official'),
            'pinNotConfigured' => __('PIN is not configured.', 'kiriminaja-official'),
            'configurePin' => __('Configure PIN', 'kiriminaja-official'),
            'insufficientCredit' => __('Insufficient credit.', 'kiriminaja-official'),
            'topUpNow' => __('Top Up Now', 'kiriminaja-official'),
            'noPrintSelection' => __('Please select at least one order to print.', 'kiriminaja-official'),
            'back' => __('Back', 'kiriminaja-official'),
            'pinWait' => __('You have entered the wrong code three times. Please wait', 'kiriminaja-official'),
            'toTryAgain' => __('to try again.', 'kiriminaja-official'),
            'pinLocked' => __('You have entered the wrong code too many times. Please try again later.', 'kiriminaja-official'),
            'tooManyAttempts' => __('Too Many Attempts', 'kiriminaja-official'),
            'pinRemainingPrefix' => __('You still have', 'kiriminaja-official'),
            'pinRemainingSuffix' => __('chances to enter the PIN.', 'kiriminaja-official'),
            'incorrectPin' => __('Incorrect PIN', 'kiriminaja-official'),
            'checkPin' => __('Please check the PIN code you entered again.', 'kiriminaja-official'),
            'insufficientBalance' => __('Insufficient credit balance. Please top up or use QRIS.', 'kiriminaja-official'),
            'somethingWrong' => __('Something went wrong.', 'kiriminaja-official'),
            'pinMaxAttempts' => __('PIN max attempts reached. Please try again later.', 'kiriminaja-official'),
            'incorrectPinPeriod' => __('Incorrect PIN.', 'kiriminaja-official'),
            'sixDigitPin' => __('Please enter a 6-digit PIN.', 'kiriminaja-official'),
            'selectSchedule' => __('Please select a pickup schedule.', 'kiriminaja-official'),
            'selectPayment' => __('Please select a payment method.', 'kiriminaja-official'),
            'reasonMin' => __('*Alasan minimal 5 karakter', 'kiriminaja-official'),
            'reasonMax' => __('*Alasan maksimal 200 karakter', 'kiriminaja-official'),
            'confirmCancel' => __('Are you sure you want to cancel this transaction?', 'kiriminaja-official'),
            'errorOccurred' => __('Terjadi kesalahan', 'kiriminaja-official'),
            'cancelSuccess' => __('Transaction cancelled successfully.', 'kiriminaja-official'),
            'pinRemembered' => __('Saved PIN will be reused until it expires on this browser.', 'kiriminaja-official'),
            'pinExpired' => __('Saved PIN expired. Please enter your PIN again.', 'kiriminaja-official'),
            'pinInvalidated' => __('Saved PIN was cleared. Please enter your latest PIN again.', 'kiriminaja-official'),
            'pinUnsupported' => __('Browser secure storage is unavailable. PIN will not be remembered.', 'kiriminaja-official'),
        ),
    )
);
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
<div class="wrap kj-wrap" data-kiriof-transactions-page>

    <?php
    if (! in_array($kiriof_status_filter, ['all', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-cancelled', 'processed', 'order-issue'], true)) {
        $kiriof_status_filter = 'all';
    }
    $kiriof_title = __('Transactions', 'kiriminaja-official');
    $kiriof_is_processed_tab = ('processed' === $kiriof_status_filter);
    $kiriof_is_all_tab = ('all' === $kiriof_status_filter);
    $kiriof_header_extra = '<button id="kj-request-pickup-btn" data-kj-action="request-pickup" class="page-title-action" type="button">' . esc_html__('Request Pickup', 'kiriminaja-official') . '</button>';
    if ($kiriof_is_processed_tab || $kiriof_is_all_tab) {
        $kiriof_header_extra .= ' <button id="kj-print-btn" data-kj-action="print-bulk" class="page-title-action" type="button">' . esc_html__('Print', 'kiriminaja-official') . '</button>';
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

    <div data-kiriof-transactions-filters-root></div>
    <script type="application/json" data-kiriof-transactions-filters-payload><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is hex-escaped for a non-executable data block. ?><?php echo wp_json_encode( $kiriof_transactions_filters_bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>

    <div data-kiriof-transactions-controls-fallback>
    <div class="wp-filter" style="display: flex;justify-content: space-between;">
        <ul class="filter-links">
            <li><a href="#" data-search-key="status" data-search-value="all" <?php echo $kiriof_status_filter === 'all' ? 'class="current" aria-current="page"' : ''; ?>><?php esc_html_e('All', 'kiriminaja-official'); ?> <span class="count">(<?php echo esc_html(number_format_i18n((int) ($kiriof_statusCounts['all'] ?? 0))); ?>)</span></a></li>
            <li><a href="#" data-search-key="status" data-search-value="wc-processing" <?php echo $kiriof_status_filter === 'wc-processing' ? 'class="current" aria-current="page"' : ''; ?>><?php esc_html_e('New / Waiting for Shipment', 'kiriminaja-official'); ?> <span class="count">(<?php echo esc_html(number_format_i18n((int) ($kiriof_statusCounts['wc-processing'] ?? 0))); ?>)</span></a></li>
            <li><a href="#" data-search-key="status" data-search-value="wc-on-hold" <?php echo $kiriof_status_filter === 'wc-on-hold' ? 'class="current" aria-current="page"' : ''; ?>><?php esc_html_e('On Hold', 'kiriminaja-official'); ?> <span class="count">(<?php echo esc_html(number_format_i18n((int) ($kiriof_statusCounts['wc-on-hold'] ?? 0))); ?>)</span></a></li>
            <li><a href="#" data-search-key="status" data-search-value="wc-pending" <?php echo $kiriof_status_filter === 'wc-pending' ? 'class="current" aria-current="page"' : ''; ?>><?php esc_html_e('Pending Payment', 'kiriminaja-official'); ?> <span class="count">(<?php echo esc_html(number_format_i18n((int) ($kiriof_statusCounts['wc-pending'] ?? 0))); ?>)</span></a></li>
            <li><a href="#" data-search-key="status" data-search-value="processed" <?php echo $kiriof_status_filter === 'processed' ? 'class="current" aria-current="page"' : ''; ?>><?php esc_html_e('Processed', 'kiriminaja-official'); ?> <span class="count">(<?php echo esc_html(number_format_i18n((int) ($kiriof_statusCounts['processed'] ?? 0))); ?>)</span></a></li>
            <li><a href="#" data-search-key="status" data-search-value="wc-cancelled" <?php echo $kiriof_status_filter === 'wc-cancelled' ? 'class="current" aria-current="page"' : ''; ?>><?php esc_html_e('Cancelled', 'kiriminaja-official'); ?> <span class="count">(<?php echo esc_html(number_format_i18n((int) ($kiriof_statusCounts['wc-cancelled'] ?? 0))); ?>)</span></a></li>
            <li><a href="#" data-search-key="status" data-search-value="order-issue" <?php echo $kiriof_status_filter === 'order-issue' ? 'class="current" aria-current="page"' : ''; ?> style="color:#d63638;"><?php esc_html_e('Order Issue', 'kiriminaja-official'); ?> <span class="count">(<?php echo esc_html(number_format_i18n((int) ($kiriof_statusCounts['order-issue'] ?? 0))); ?>)</span></a></li>
        </ul>
        <form class="search-form search-plugins" data-kj-search-form="1">
            <label class="screen-reader-text" for="kiriof-search-input"><?php esc_html_e('Search Orders', 'kiriminaja-official'); ?></label>
            <input type="search" id="kiriof-search-input" class="wp-filter-search" placeholder="<?php esc_attr_e('Search order…', 'kiriminaja-official'); ?>" value="<?php
                                                                                                                                                                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
                                                                                                                                                                        echo esc_attr(isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '');
                                                                                                                                                                        ?>">
            <label class="screen-reader-text" for="kiriof-search-by"><?php esc_html_e('Search by:', 'kiriminaja-official'); ?></label>
            <select id="kiriof-search-by" data-kj-search-by="1">
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
                    <a class="first-page button" href="#" data-page="1"><span>&laquo;</span></a>
                    <a class="prev-page button" href="#" data-page="<?php echo (int) ($kiriof_current_page - 1); ?>"><span>&lsaquo;</span></a>
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
                    <a class="next-page button" href="#" data-page="<?php echo (int) ($kiriof_current_page + 1); ?>"><span>&rsaquo;</span></a>
                    <a class="last-page button" href="#" data-page="<?php echo (int) $kiriof_total_pages; ?>"><span>&raquo;</span></a>
                <?php endif; ?>
            </span>
        </div>
        <br class="clear">
    </div>
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
                <th scope="col" class="manage-column column-thumb kiriof-col-shipto"><?php echo esc_html(__('Shipment Route', 'kiriminaja-official')); ?></th>
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
                $kiriof_recipientResolver = new \KiriminAjaOfficial\Services\TransactionProcessServices\RecipientDataResolver();
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

                    $kiriof_destinationSubDistrict = $kiriof_row->destination_sub_district ?? '';
                    $kiriof_originSnapshot = json_decode((string) ($kiriof_row->shipment_location_snapshot ?? '{}'), true);
                    $kiriof_originName = trim((string) ($kiriof_originSnapshot['origin_name'] ?? $kiriof_originSnapshot['name'] ?? ''));
                    if ('' === $kiriof_originName) {
                        $kiriof_originName = __('Default origin', 'kiriminaja-official');
                    }
                    $kiriof_wcOrder = function_exists('wc_get_order') ? wc_get_order($kiriof_row->wc_order_id) : false;
                    $kiriofBillingAddress = $kiriof_wcOrder && method_exists($kiriof_wcOrder, 'get_address') ? (array) $kiriof_wcOrder->get_address('billing') : [];
                    $kiriof_recipient = $kiriof_recipientResolver->resolve($kiriof_wcOrder, $kiriof_shippingData, $kiriof_row);
                    $kiriof_billingFirstName = trim((string) ($kiriofBillingAddress['first_name'] ?? ''));
                    $kiriof_billingLastName = trim((string) ($kiriofBillingAddress['last_name'] ?? ''));
                    $kiriofBillingName = trim($kiriof_billingFirstName . ' ' . $kiriof_billingLastName);
                    if ('' === $kiriofBillingName && $kiriof_wcOrder && method_exists($kiriof_wcOrder, 'get_formatted_billing_full_name')) {
                        $kiriofBillingName = trim((string) $kiriof_wcOrder->get_formatted_billing_full_name());
                    }
                    if ('' === $kiriofBillingName) {
                        $kiriofBillingName = trim($kiriof_recipient['first_name'] . ' ' . $kiriof_recipient['last_name']);
                    }
                    $kiriofShippingName = trim($kiriof_recipient['first_name'] . ' ' . $kiriof_recipient['last_name']);
                    if ('' === $kiriofShippingName && $kiriof_wcOrder && method_exists($kiriof_wcOrder, 'get_formatted_shipping_full_name')) {
                        $kiriofShippingName = trim((string) $kiriof_wcOrder->get_formatted_shipping_full_name());
                    }
                    if ('' === $kiriofShippingName) {
                        $kiriofShippingName = $kiriofBillingName;
                    }
                    $kiriof_shippingPhone = $kiriof_recipient['phone'];
                    $kiriof_shippingAddress1 = $kiriof_recipient['address_1'];
                    $kiriof_shippingAddress2 = $kiriof_recipient['address_2'];
                    $kiriof_shippingCity = $kiriof_recipient['city'];
                    $kiriof_shippingState = $kiriof_recipient['state'];
                    $kiriof_shippingCountry = $kiriof_recipient['country'];
                    $kiriof_shippingPostcode = $kiriof_recipient['postcode'];
                    $kiriof_paymentMethod = $kiriof_wcOrder ? $kiriof_wcOrder->get_payment_method() : ($kiriof_shippingData->_payment_method ?? '');
                    $kiriof_isCod = $kiriof_paymentMethod === 'cod';

                    // Split WC discount for column badges (loaded for every row).
                    $kiriof_colItemDiscount = 0.0;
                    $kiriof_colShipDiscount = max(0.0, $kiriof_discountAmount);
                    $kiriof_colItemCoupon   = '';
                    $kiriof_colShipCoupon   = '';
                    if ($kiriof_wcOrder) {
                        $kiriof_colItemDiscount = (float) $kiriof_wcOrder->get_discount_total();
                        $kiriof_colCoupons      = $kiriof_wcOrder->get_coupon_codes();
                        $kiriof_couponService   = new \KiriminAjaOfficial\Services\ShippingDiscountCouponService();
                        $kiriof_couponScopes    = $kiriof_couponService->splitCouponCodesByScope((array) $kiriof_colCoupons);
                        $kiriof_colItemCoupon   = $kiriof_couponScopes['item'][0] ?? '';
                        $kiriof_colShipCoupon   = $kiriof_couponScopes['shipping'][0] ?? '';
                        $kiriof_colShipDiscount = max(
                            0.0,
                            $kiriof_shippingCost - (float) $kiriof_wcOrder->get_shipping_total()
                        );
                    }
                    $kiriof_colPaidShipping = $kiriof_wcOrder
                        ? max(0.0, (float) $kiriof_wcOrder->get_shipping_total())
                        : max(0.0, $kiriof_shippingCost - $kiriof_colShipDiscount);
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
                    $kiriof_origin_label    = '';
                    $kiriof_origin_snapshot = array();
                    if ( ! empty( $kiriof_row->shipment_location_snapshot ) ) {
                        $kiriof_origin_snapshot = json_decode( $kiriof_row->shipment_location_snapshot, true );
                        $kiriof_origin_label    = is_array( $kiriof_origin_snapshot )
                            ? (string) ( $kiriof_origin_snapshot['origin_name'] ?? $kiriof_origin_snapshot['location_name'] ?? $kiriof_origin_snapshot['name'] ?? '' )
                            : '';
                    }
                    if ( '' === $kiriof_origin_label ) {
                        $kiriof_origin_label = __( 'Legacy default origin', 'kiriminaja-official' );
                    }
                    $kiriof_location_service = new \KiriminAjaOfficial\Services\ShipmentLocationService();
                    $kiriof_origin_location  = ! empty( $kiriof_row->shipment_location_id )
                        ? $kiriof_location_service->repository()->getById( (int) $kiriof_row->shipment_location_id )
                        : null;
                    $kiriof_origin_address = $kiriof_location_service->formatAddress(
                        ! empty( $kiriof_origin_snapshot ) && is_array( $kiriof_origin_snapshot )
                            ? $kiriof_origin_snapshot
                            : $kiriof_origin_location
                    );
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
                    $kiriof_wcTotal              = $kiriof_wcOrder ? (float) $kiriof_wcOrder->get_total() : $kiriof_shippingFee;
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

                    $kiriof_effectiveShippingCost = max(0.0, $kiriof_shippingCost - $kiriof_wcShippingDiscount);
                    $kiriof_effectiveCodPayout    = $kiriof_wcTotal - $kiriof_effectiveShippingCost - $kiriof_insuranceCost - $kiriof_codFee;
                    $kiriof_canRequestPickup      = $kiriof_isProcessable && (! $kiriof_isDeficitRow || $kiriof_effectiveCodPayout >= 0);

                    $kiriof_isProcessedFilter = ('processed' === $kiriof_status_filter);
                    $kiriof_isAllFilter = ('all' === $kiriof_status_filter);
                    $kiriof_canPrintRow = (($kiriof_isProcessedFilter || $kiriof_isAllFilter) && ! empty($kiriof_awb) && 'request_pickup' === $kiriof_row->status);
                    $kiriof_checkboxDisabled = ($kiriof_isProcessedFilter || $kiriof_isAllFilter)
                        ? (! $kiriof_canPrintRow && ! $kiriof_canRequestPickup)
                        : (! $kiriof_canRequestPickup);
                    $kiriof_checkboxTitle   = $kiriof_isDeficitRow && $kiriof_effectiveCodPayout < 0
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
                                                             <input type="checkbox" name="transaction_id[]" value="' . esc_attr($kiriof_orderIdKA) . '" data-can-pickup="' . ($kiriof_canRequestPickup ? '1' : '0') . '" data-can-print="' . ($kiriof_canPrintRow ? '1' : '0') . '"' . ($kiriof_checkboxDisabled ? ' disabled' : '') . ($kiriof_checkboxTitle ? ' title="' . esc_attr($kiriof_checkboxTitle) . '"' : '') . '>
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
                                                            <div class="kiriof-shipto-name" title="' . esc_attr($kiriof_originName) . '">' . esc_html($kiriof_originName) . '</div>
                                                            <div style="font-size:12px;color:#8c8f94;margin:2px 0">&#8595; ' . esc_html__('To', 'kiriminaja-official') . '</div>
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
                        // Buyer-paid shipping is the primary amount; raw shipping stays in the discount breakdown.
                        . '<div style="font-weight:600;margin-top:4px">Rp' . esc_html(kiriof_money_format($kiriof_colPaidShipping)) . '</div>'
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
                        ( $kiriof_isProcessable
                            ? ' <button type="button"'
                            . ' class="button kiriof-change-origin-button"'
                            . ' style="padding:4px;width:32px;height:32px;border:none;box-shadow:none;color:#2271b1"'
                             . ' data-ka-order-id="' . esc_attr($kiriof_orderIdKA) . '"'
                             . ' data-current-origin="' . esc_attr($kiriof_origin_label) . '"'
                             . ' data-current-origin-address="' . esc_attr($kiriof_origin_address) . '"'
                             . ' data-current-location-id="' . esc_attr((int) ($kiriof_origin_snapshot['location_id'] ?? $kiriof_origin_snapshot['id'] ?? ($kiriof_origin_location->id ?? 0))) . '"'
                            . ' data-nonce="' . esc_attr($kiriof_adj_nonce) . '"'
                            . ' title="' . esc_attr(__('Change Origin', 'kiriminaja-official')) . '"'
                            . ' aria-label="' . esc_attr(__('Change Origin', 'kiriminaja-official')) . '">'
                            . '<span class="dashicons dashicons-location" style="font-size:20px;width:20px;height:20px;line-height:20px;"></span></button>'
                            : '' ) .
                        (! empty($kiriof_isDeficitRow)
                            ? ' <button type="button"'
                            . ' class="button"'
                            . ' style="padding:4px;width:32px;height:32px;border:none;box-shadow:none;color:#d97706"'
                            . ' data-kj-action="cod-adjust"'
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
                            . ' data-kj-action="cancel-deficit"'
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
                                    ? ' <button class="button" style="color:#d63638;padding:4px;width:32px;height:32px;border:none;box-shadow:none" data-kj-action="cancel" data-order-id="' . esc_attr($kiriof_orderIdKA) . '" title="' . esc_attr(__('Cancel', 'kiriminaja-official')) . '" aria-label="' . esc_attr(__('Cancel', 'kiriminaja-official')) . '"><span class="dashicons dashicons-no-alt" style="font-size:20px;width:20px;height:20px;line-height:20px;"></span></button>'
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
                <th scope="col" class="manage-column column-thumb kiriof-col-shipto"><?php echo esc_html(__('Shipment Route', 'kiriminaja-official')); ?></th>
                <th scope="col" class="manage-column column-thumb kiriof-col-packages"><?php echo esc_html(__('Packages & Fee', 'kiriminaja-official')); ?></th>
                <th scope="col" class="manage-column column-thumb kiriof-col-action" style="width:7rem"><?php echo esc_html(__('Action', 'kiriminaja-official')); ?></th>
            </tr>
        </tfoot>
    </table>
    <br class="clear">
    <div class="tablenav bottom" data-kiriof-transactions-controls-fallback>
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
                    <a class="first-page button" href="#" data-page="1"><span>&laquo;</span></a>
                    <a class="prev-page button" href="#" data-page="<?php echo (int) ($kiriof_current_page - 1); ?>"><span>&lsaquo;</span></a>
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
                    <a class="next-page button" href="#" data-page="<?php echo (int) ($kiriof_current_page + 1); ?>"><span>&rsaquo;</span></a>
                    <a class="last-page button" href="#" data-page="<?php echo (int) $kiriof_total_pages; ?>"><span>&raquo;</span></a>
                <?php endif; ?>
            </span>
        </div>
        <br class="clear">
    </div>

</div>

<!--Table Search-->
