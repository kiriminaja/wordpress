<?php

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
        (new \Inc\Base\BaseInit())->logThis('$results', [$results]);

        /** Month Options */
        $monthOptions = $this->getTransactionsDateFilterOptionArray();
        (new \Inc\Base\BaseInit())->logThis('$monthOptions', [$monthOptions]);

        /** Return vars and view */
        include 'view/index.php';
    }

    private function pageQuery()
    {
        global $wpdb;

        /** Tables */
        $wcOrderTable = $wpdb->prefix . 'wc_order_stats';
        $transactionTable = $wpdb->prefix . 'kiriminaja_transactions';
        $postTable = $wpdb->prefix . 'posts';

        /** Where Condition */
        $whereConditions = [];
        if (!empty($_GET['key'] ?? '')) {
            $whereConditions[] = $wpdb->prepare("`$wcOrderTable`.order_id LIKE %s", '%' . $_GET['key'] . '%');
        }
        if (!empty($_GET['month'] ?? '')) {
            $whereConditions[] = $wpdb->prepare("`$wcOrderTable`.date_created LIKE %s", '%' . $_GET['month'] . '%');
        }
        $whereCondition = !empty($whereConditions) ? ' AND ' . implode(' AND ', $whereConditions) : '';

        /** Main Query */
        $query = "
            SELECT 
                $wcOrderTable.order_id as wc_order_id,
                $wcOrderTable.date_created as wc_date_created,
                $wcOrderTable.status as wc_status,
                $postTable.post_status,
                $transactionTable.*
            FROM $wcOrderTable
            INNER JOIN $transactionTable
                ON $wcOrderTable.order_id = $transactionTable.wp_wc_order_stat_order_id
            INNER JOIN $postTable
                ON $wcOrderTable.order_id = $postTable.ID
            WHERE $wcOrderTable.status = 'wc-processing'
                AND $transactionTable.status = 'new'
                AND $postTable.post_status != 'trash'
                $whereCondition
            GROUP BY $wcOrderTable.order_id
            ORDER BY $wcOrderTable.date_created DESC
        ";

        $results = $wpdb->get_results($query);

        if (!empty($wpdb->last_error)) {
            (new \Inc\Base\BaseInit())->logThis('last_error', $wpdb->last_error);
        }

        return ['results' => $results];
    }

    private function getTransactionsDateFilterOptionArray()
    {
        $oldestTransactionDateQuery = (new \Inc\Repositories\TransactionRepository())->getTransactionByOldestDate();
        (new \Inc\Base\BaseInit())->logThis('$oldestTransactionDateQuery', [$oldestTransactionDateQuery]);

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
