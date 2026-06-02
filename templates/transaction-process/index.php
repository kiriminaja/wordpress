<?php
// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kiriminaja-official' ) );
}

/**
 * @var array $kiriof_results
 * @var array $kiriof_monthOptions
 * @var array $kiriof_statusCounts
 * @var array $kiriof_couriers
 * @var string $kiriof_search_by
 * @var string $kiriof_status_filter
 * @var string $kiriof_cod_filter
 * @var string $kiriof_courier_filter
 * @var int $kiriof_total_pages
 * @var int $kiriof_total
 * @var int $kiriof_per_page    
 * @var string $locale
 * @var string $status
 * @var string $key
 */


class Kiriof_TransactionProcessIndex
{
    function __construct()
    {
        /** WP Setting language */
        $locale = get_locale();

        $user = wp_get_current_user();
        $kiriof_per_page = (int) get_user_meta( $user->ID, 'kiriof_transactions_per_page', true );
        if ( $kiriof_per_page < 1 ) {
            $kiriof_per_page = 25;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $kiriof_per_page_get = isset( $_GET['per_page'] ) ? (int) $_GET['per_page'] : 0;
        if ( $kiriof_per_page_get > 0 && $kiriof_per_page_get !== $kiriof_per_page ) {
            $kiriof_per_page = $kiriof_per_page_get;
            update_user_meta( $user->ID, 'kiriof_transactions_per_page', $kiriof_per_page );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $kiriof_current_page = isset( $_GET['cpage'] ) ? max( 1, (int) $_GET['cpage'] ) : 1;

        /** Page Query */
        $pageQuery = $this->pageQuery( $kiriof_per_page, $kiriof_current_page );
        $kiriof_results = $pageQuery['results'];
        $kiriof_total   = $pageQuery['total'];
        $kiriof_total_pages = (int) ceil( $kiriof_total / $kiriof_per_page );
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$kiriof_results', [$kiriof_results]);

        /** Month Options */
        $kiriof_monthOptions = $this->getTransactionsDateFilterOptionArray();
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$kiriof_monthOptions', [$kiriof_monthOptions]);

        /**
         * Status filter counts (powers the "Processing / On Hold / Pending
         * Payment" pill row in view/index.php). All four counts are computed
         * up front so the totals stay stable regardless of which pill is
         * currently selected — same UX as WooCommerce's order list.
         */
        $kiriof_transactionRepo = new \KiriminAjaOfficial\Repositories\TransactionRepository();
        $kiriof_statusCounts = [
            'all'           => $kiriof_transactionRepo->getCountByPostStatus(null),
            'wc-processing' => $kiriof_transactionRepo->getCountByPostStatus('wc-processing'),
            'wc-on-hold'    => $kiriof_transactionRepo->getCountByPostStatus('wc-on-hold'),
            'wc-pending'    => $kiriof_transactionRepo->getCountByPostStatus('wc-pending'),
            'wc-cancelled'  => $kiriof_transactionRepo->getCountCancelled(),
            'processed'     => $kiriof_transactionRepo->getCountProcessed(),
        ];

        $kiriof_couriers = $kiriof_transactionRepo->getDistinctCouriers();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
        $kiriof_search_by = isset( $_GET['search_by'] ) ? sanitize_text_field( wp_unslash( $_GET['search_by'] ) ) : 'wc_order_id';
        if ( ! in_array( $kiriof_search_by, array( 'wc_order_id', 'ka_order_id', 'awb' ), true ) ) {
            $kiriof_search_by = 'wc_order_id';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
        $kiriof_status_filter = sanitize_text_field(wp_unslash($_GET['status'] ?? ''));
        if (! in_array($kiriof_status_filter, ['all', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-cancelled', 'processed'], true)) {
            $kiriof_status_filter = 'all';
        }

        /** Return vars and view */
        include 'view/index.php';
    }

    private function pageQuery( $per_page = 25, $current_page = 1 )
    {
        global $wpdb;

        $offset = ( $current_page - 1 ) * $per_page;

        // Build optional filters as values, but keep SQL placeholders static.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query parameter for filtering
        $key = sanitize_text_field(wp_unslash($_GET['key'] ?? ''));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query parameter for filtering
        $month = sanitize_text_field(wp_unslash($_GET['month'] ?? ''));
        $month_like = '';
        if ('' !== $month) {
            $month_like = $wpdb->esc_like($month) . '%';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query parameter for filtering
        $status = sanitize_text_field(wp_unslash($_GET['status'] ?? ''));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query parameter for filtering
        $cod    = sanitize_text_field(wp_unslash($_GET['cod'] ?? ''));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query parameter for filtering
        $courier = sanitize_text_field(wp_unslash($_GET['courier'] ?? ''));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query parameter for filtering
        $search_by = sanitize_text_field(wp_unslash($_GET['search_by'] ?? 'wc_order_id'));

        // Whitelist of post_status values exposed by the pill row in the view.
        // Anything outside the whitelist (including empty) falls back to all.
        $allowedStatuses = ['all', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-cancelled', 'processed'];
        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }
        $isProcessedFilter  = ('processed' === $status);
        $isCancelledFilter  = ('wc-cancelled' === $status);
        $isAllFilter = ('all' === $status);

        $cod_clause = '';
        if ('1' === $cod) {
            $cod_clause = 'AND kiriminaja_transactions.cod_fee > 0';
        } elseif ('0' === $cod) {
            $cod_clause = 'AND kiriminaja_transactions.cod_fee = 0';
        }

        $courier_clause = '';
        if ('' !== $courier) {
            $courier_clause = "AND kiriminaja_transactions.service = '" . esc_sql($courier) . "'";
        }

        /**
         * Main Query - uses the active WooCommerce orders storage.
         *
         * Supports both legacy (wp_posts / shop_order CPT) and HPOS
         * (wp_wc_orders custom table). The helper on TransactionRepository
         * returns the correct table name and column aliases.
         */
        $o = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getOrdersTable();

        $key_clause = '';
        if ('' !== $key) {
            $key_escaped = $wpdb->esc_like($key);
            $key_like    = '%' . $key_escaped . '%';
        } else {
            $key_like = '';
        }

        if ('' !== $key) {
            switch ( $search_by ) {
                case 'ka_order_id':
                    $key_clause = $wpdb->prepare( 'AND kiriminaja_transactions.order_id LIKE %s', $key_like );
                    break;
                case 'awb':
                    $key_clause = $wpdb->prepare( 'AND kiriminaja_transactions.awb LIKE %s', $key_like );
                    break;
                default:
                    $key_clause = $wpdb->prepare( "AND orders_tbl.{$o['id']} LIKE %s", $key_like );
                    break;
            }
        }

        if ($isProcessedFilter) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $total = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT orders_tbl.{$o['id']})
                    FROM {$o['table']} as orders_tbl
                    INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
                        ON orders_tbl.{$o['id']} = kiriminaja_transactions.wp_wc_order_stat_order_id
                    INNER JOIN {$wpdb->prefix}kiriminaja_payments as kiriminaja_payments
                        ON kiriminaja_transactions.pickup_number = kiriminaja_payments.pickup_number
                    WHERE orders_tbl.{$o['trash_field']} NOT IN ('trash','auto-draft')
                        AND kiriminaja_transactions.status != 'canceled'
                        {$cod_clause}
                        {$courier_clause}
                        {$key_clause}
                        AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )",
                    $month,
                    $month_like
                )
            );
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT 
                        orders_tbl.{$o['id']} as wc_order_id,
                        orders_tbl.{$o['date']} as wc_date_created,
                        orders_tbl.{$o['status']} as wc_status,
                        orders_tbl.{$o['status']} as post_status,
                        kiriminaja_transactions.*
                    FROM {$o['table']} as orders_tbl
                    INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
                        ON orders_tbl.{$o['id']} = kiriminaja_transactions.wp_wc_order_stat_order_id
                    INNER JOIN {$wpdb->prefix}kiriminaja_payments as kiriminaja_payments
                        ON kiriminaja_transactions.pickup_number = kiriminaja_payments.pickup_number
                    WHERE orders_tbl.{$o['trash_field']} NOT IN ('trash','auto-draft')
                        AND kiriminaja_transactions.status != 'canceled'
                        {$cod_clause}
                        {$courier_clause}
                        {$key_clause}
                        AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )
                    GROUP BY orders_tbl.{$o['id']}
                    ORDER BY orders_tbl.{$o['date']} DESC
                    LIMIT %d OFFSET %d",
                    $month,
                    $month_like,
                    $per_page,
                    $offset
                )
            );
            // phpcs:enable ...
        } elseif ($isCancelledFilter) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $total = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT orders_tbl.{$o['id']})
                    FROM {$o['table']} as orders_tbl
                    INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
                        ON orders_tbl.{$o['id']} = kiriminaja_transactions.wp_wc_order_stat_order_id
                    WHERE orders_tbl.{$o['status']} = %s
                        {$cod_clause}
                        {$courier_clause}
                        {$key_clause}
                        AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )",
                    'wc-cancelled',
                    $month,
                    $month_like
                )
            );
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT 
                        orders_tbl.{$o['id']} as wc_order_id,
                        orders_tbl.{$o['date']} as wc_date_created,
                        orders_tbl.{$o['status']} as wc_status,
                        orders_tbl.{$o['status']} as post_status,
                        kiriminaja_transactions.*
                    FROM {$o['table']} as orders_tbl
                    INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
                        ON orders_tbl.{$o['id']} = kiriminaja_transactions.wp_wc_order_stat_order_id
                    WHERE orders_tbl.{$o['status']} = %s
                        {$cod_clause}
                        {$courier_clause}
                        {$key_clause}
                        AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )
                    GROUP BY orders_tbl.{$o['id']}
                    ORDER BY orders_tbl.{$o['date']} DESC
                    LIMIT %d OFFSET %d",
                    'wc-cancelled',
                    $month,
                    $month_like,
                    $per_page,
                    $offset
                )
            );
            // phpcs:enable ...
        } elseif ($isAllFilter) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $total = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT orders_tbl.{$o['id']})
                    FROM {$o['table']} as orders_tbl
                    INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
                        ON orders_tbl.{$o['id']} = kiriminaja_transactions.wp_wc_order_stat_order_id
                    WHERE orders_tbl.{$o['trash_field']} NOT IN ('trash','auto-draft')
                        {$cod_clause}
                        {$courier_clause}
                        {$key_clause}
                        AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )",
                    $month,
                    $month_like
                )
            );
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT 
                    orders_tbl.{$o['id']} as wc_order_id,
                    orders_tbl.{$o['date']} as wc_date_created,
                    orders_tbl.{$o['status']} as wc_status,
                    orders_tbl.{$o['status']} as post_status,
                    kiriminaja_transactions.*
                FROM {$o['table']} as orders_tbl
                INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
                    ON orders_tbl.{$o['id']} = kiriminaja_transactions.wp_wc_order_stat_order_id
                WHERE orders_tbl.{$o['trash_field']} NOT IN ('trash','auto-draft')
                    {$cod_clause}
                    {$courier_clause}
                    {$key_clause}
                    AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )
                GROUP BY orders_tbl.{$o['id']}
                ORDER BY orders_tbl.{$o['date']} DESC
                LIMIT %d OFFSET %d",
                    $month,
                    $month_like,
                    $per_page,
                    $offset
                )
            );
        } else {
            // Status-specific filter (wc-processing, wc-on-hold, wc-pending)
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $total = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT orders_tbl.{$o['id']})
                    FROM {$o['table']} as orders_tbl
                    INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
                        ON orders_tbl.{$o['id']} = kiriminaja_transactions.wp_wc_order_stat_order_id
                    WHERE orders_tbl.{$o['status']} = %s
                        AND kiriminaja_transactions.status = %s
                        {$cod_clause}
                        {$courier_clause}
                        {$key_clause}
                        AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )",
                    $status,
                    'new',
                    $month,
                    $month_like
                )
            );
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT 
                    orders_tbl.{$o['id']} as wc_order_id,
                    orders_tbl.{$o['date']} as wc_date_created,
                    orders_tbl.{$o['status']} as wc_status,
                    orders_tbl.{$o['status']} as post_status,
                    kiriminaja_transactions.*
                FROM {$o['table']} as orders_tbl
                INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
                    ON orders_tbl.{$o['id']} = kiriminaja_transactions.wp_wc_order_stat_order_id
                WHERE orders_tbl.{$o['status']} = %s
                    AND kiriminaja_transactions.status = %s
                    {$cod_clause}
                    {$courier_clause}
                    {$key_clause}
                    AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )
                GROUP BY orders_tbl.{$o['id']}
                ORDER BY orders_tbl.{$o['date']} DESC
                LIMIT %d OFFSET %d",
                    $status,
                    'new',
                    $month,
                    $month_like,
                    $per_page,
                    $offset
                )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        }

        if (!empty($wpdb->last_error)) {
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('last_error', $wpdb->last_error);
        }

        return ['results' => $results, 'total' => $total];
    }

    private function getTransactionsDateFilterOptionArray()
    {
        $oldestTransactionDateQuery = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransactionByOldestDate();
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$oldestTransactionDateQuery', [$oldestTransactionDateQuery]);

        $oldestMonth = new DateTime($oldestTransactionDateQuery->created_at ?? 'now');
        $currentMonth = new DateTime('now');
        $interval = $oldestMonth->diff($currentMonth);
        $totalMonths = ($interval->y * 12) + $interval->m + 1;

        $monthOptions = [];
        for ($i = 0; $i < $totalMonths; $i++) {
            $date = (clone $currentMonth)->modify("-$i months");
            $monthOptions[$date->format('Y-m')] = $date->format('Y F');
        }

        return $monthOptions;
    }
}

new Kiriof_TransactionProcessIndex();
