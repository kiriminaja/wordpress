<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
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

        $key_like   = '';
        $month_like = '';
        if ( '' !== $key ) {
            $key_like = '%' . $wpdb->esc_like( $key ) . '%';
        }
        if ( '' !== $month ) {
            $month_like = '%' . $wpdb->esc_like( $month ) . '%';
        }

        /** Main Query - Single prepare() call for efficiency and compliance */
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    wc_order_stats.order_id as wc_order_id,
                    wc_order_stats.date_created as wc_date_created,
                    wc_order_stats.status as wc_status,
                    posts.post_status,
                    kiriminaja_transactions.*
                FROM {$wpdb->prefix}wc_order_stats as wc_order_stats
                INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
                    ON wc_order_stats.order_id = kiriminaja_transactions.wp_wc_order_stat_order_id
                INNER JOIN {$wpdb->prefix}posts as posts
                    ON wc_order_stats.order_id = posts.ID
                WHERE wc_order_stats.status = %s
                    AND kiriminaja_transactions.status = %s
                    AND posts.post_status != %s
                    AND ( %s = '' OR wc_order_stats.order_id LIKE %s )
                    AND ( %s = '' OR wc_order_stats.date_created LIKE %s )
                GROUP BY wc_order_stats.order_id
                ORDER BY wc_order_stats.date_created DESC",
                'wc-processing',
                'new',
                'trash',
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
