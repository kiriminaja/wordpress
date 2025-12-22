<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class TransactionProcessIndex
{
    function __construct()
    {
        global $results, $page, $items_per_page, $total_pages, $next_page_link, $prev_page_link, $monthOptions, $locale;

        /** WP Setting language */
        $locale = get_locale();

        /** Page Query */
        $pageQuery = $this->pageQuery();
        $results = $pageQuery['results'];
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$results', [$results]);

        /** Month Options */
        $monthOptions = $this->getTransactionsDateFilterOptionArray();
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$monthOptions', [$monthOptions]);

        /** Return vars and view */
        include 'view/index.php';
    }

    private function pageQuery()
    {
        global $wpdb;

        /** Build query parts and prepare values */
        $query_parts = [];
        $prepare_values = [];
        
        // Base conditions
        $query_parts[] = 'wc_order_stats.status = %s';
        $prepare_values[] = 'wc-processing';
        
        $query_parts[] = 'kiriminaja_transactions.status = %s';
        $prepare_values[] = 'new';
        
        $query_parts[] = 'posts.post_status != %s';
        $prepare_values[] = 'trash';
        
        // Dynamic filters
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query parameter for filtering
        if (!empty(sanitize_text_field(wp_unslash($_GET['key'] ?? '')))) {
            $key = sanitize_text_field(wp_unslash($_GET['key'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $query_parts[] = 'wc_order_stats.order_id LIKE %s';
            $prepare_values[] = '%' . $wpdb->esc_like($key) . '%';
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query parameter for filtering
        if (!empty(sanitize_text_field(wp_unslash($_GET['month'] ?? '')))) {
            $month = sanitize_text_field(wp_unslash($_GET['month'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $query_parts[] = 'wc_order_stats.date_created LIKE %s';
            $prepare_values[] = '%' . $wpdb->esc_like($month) . '%';
        }
        
        $where_clause = implode(' AND ', $query_parts);

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
                WHERE {$where_clause}
                GROUP BY wc_order_stats.order_id
                ORDER BY wc_order_stats.date_created DESC",
                ...$prepare_values
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

new TransactionProcessIndex();
