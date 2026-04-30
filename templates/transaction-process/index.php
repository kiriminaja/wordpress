<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'manage_woocommerce' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kiriminaja-official' ) );
}


class Kiriof_TransactionProcessIndex
{
    function __construct()
    {
        /** WP Setting language */
        $locale = get_locale();

        /** Page Query */
        $pageQuery = $this->pageQuery();
        $kiriof_results = $pageQuery['results'];
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
            'all'           => $kiriof_transactionRepo->getCountByPostStatus( null ),
            'wc-processing' => $kiriof_transactionRepo->getCountByPostStatus( 'wc-processing' ),
            'wc-on-hold'    => $kiriof_transactionRepo->getCountByPostStatus( 'wc-on-hold' ),
            'wc-pending'    => $kiriof_transactionRepo->getCountByPostStatus( 'wc-pending' ),
        ];

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
        $kiriof_status_filter = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
        if ( ! in_array( $kiriof_status_filter, [ 'wc-processing', 'wc-on-hold', 'wc-pending' ], true ) ) {
            $kiriof_status_filter = 'wc-processing';
        }

        /** Return vars and view */
        include 'view/index.php';
    }

    private function pageQuery()
    {
        global $wpdb;

        // Build optional filters as values, but keep SQL placeholders static.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query parameter for filtering
        $key = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query parameter for filtering
        $month = sanitize_text_field( wp_unslash( $_GET['month'] ?? '' ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query parameter for filtering
        $status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );

        // Whitelist of post_status values exposed by the pill row in the view.
        // Anything outside the whitelist (or empty / 'all') falls back to the
        // legacy default of 'wc-processing' to preserve existing behavior.
        $allowedStatuses = [ 'wc-processing', 'wc-on-hold', 'wc-pending' ];
        if ( ! in_array( $status, $allowedStatuses, true ) ) {
            $status = 'wc-processing';
        }

        $key_like   = '';
        $month_like = '';
        if ( '' !== $key ) {
            $key_like = '%' . $wpdb->esc_like( $key ) . '%';
        }
        if ( '' !== $month ) {
            $month_like = '%' . $wpdb->esc_like( $month ) . '%';
        }

        /**
         * Main Query - source of truth = wp_posts (the WooCommerce shop_order CPT).
         *
         * We intentionally do NOT join wp_wc_order_stats here. That table is
         * the WooCommerce Analytics mirror and is populated asynchronously by
         * a scheduled action after an order is created, so a brand-new order
         * would be missing from the list for several minutes while still
         * being counted by the badge. Joining wp_posts directly keeps the
         * list consistent with what the merchant just placed.
         */
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    posts.ID as wc_order_id,
                    posts.post_date as wc_date_created,
                    posts.post_status as wc_status,
                    posts.post_status,
                    kiriminaja_transactions.*
                FROM {$wpdb->prefix}posts as posts
                INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
                    ON posts.ID = kiriminaja_transactions.wp_wc_order_stat_order_id
                WHERE posts.post_status = %s
                    AND kiriminaja_transactions.status = %s
                    AND ( %s = '' OR posts.ID LIKE %s )
                    AND ( %s = '' OR posts.post_date LIKE %s )
                GROUP BY posts.ID
                ORDER BY posts.post_date DESC",
                $status,
                'new',
                $key,
                $key_like,
                $month,
                $month_like
            )
        );

        if (!empty($wpdb->last_error)) {
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('last_error', $wpdb->last_error);
        }

        return ['results' => $results];
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
