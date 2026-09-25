<?php

namespace KiriminAjaOfficial\Queries;

use KiriminAjaOfficial\Contracts\TransactionListQueryInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This read model supports both legacy posts and HPOS tables. Dynamic identifiers and EXISTS fragments come exclusively from fixed internal maps, while request values are prepared separately.

/**
 * WordPress database read model for the Transactions admin list.
 */
class WordPressTransactionListQuery implements TransactionListQueryInterface {
    /** @var object WordPress database connection. */
    private $wpdb;

    public function __construct( $wpdb = null ) {
        if ( null === $wpdb ) {
            global $wpdb;
        }
        $this->wpdb = $wpdb;
    }

    public function getPage( array $filters, int $page, int $items_per_page ): array {
        $page           = max( 1, $page );
        $items_per_page = max( 1, $items_per_page );
        $page_data      = $this->queryPage( $filters, $items_per_page, $page );
        $total_pages    = (int) ceil( $page_data['total'] / $items_per_page );

        if ( $page > $total_pages && $total_pages > 0 ) {
            $page      = $total_pages;
            $page_data = $this->queryPage( $filters, $items_per_page, $page );
        }

        return array(
            'results'        => $page_data['results'],
            'total'          => $page_data['total'],
            'page'           => $page,
            'items_per_page' => $items_per_page,
            'total_pages'    => $total_pages,
        );
    }

    private function queryPage( array $filters, int $per_page, int $current_page )
    {
        $wpdb = $this->wpdb;

        $offset = ( $current_page - 1 ) * $per_page;

        $key          = $filters['key'];
        $month        = $filters['month'];
        $status       = $filters['status'];
        $cod          = $filters['cod'];
        $courier      = $filters['courier'];
        $print_status = $filters['print_status'];
        $month_like   = '';
        if ( '' !== $month ) {
            $month_like = $wpdb->esc_like( $month ) . '%';
        }

        // Whitelist of post_status values exposed by the pill row in the view.
        // Anything outside the whitelist (including empty) falls back to all.
        $allowedStatuses = ['all', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-cancelled', 'processed', 'order-issue'];
        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }
        $isProcessedFilter  = ('processed' === $status);
        $isCancelledFilter  = ('wc-cancelled' === $status);
        $isAllFilter = ('all' === $status);
        $isDeficitFilter    = ('order-issue' === $status);
        $regular_issue_clause = 'AND kiriminaja_transactions.is_deficit = 0';

        $cod_clause = '';
        if ('1' === $cod) {
            $cod_clause = 'AND kiriminaja_transactions.cod_fee > 0';
        } elseif ('0' === $cod) {
            $cod_clause = 'AND kiriminaja_transactions.cod_fee = 0';
        }

        $courier_clause = '';
        if ('' !== $courier) {
            $courier_clause = $wpdb->prepare( "AND kiriminaja_transactions.service = %s", $courier );
        }

        $print_status_clause = '';
        if ('1' === $print_status) {
            $print_status_clause = $wpdb->prepare('AND kiriminaja_transactions.is_printed = %d', 1);
        } elseif ('0' === $print_status) {
            $print_status_clause = $wpdb->prepare('AND kiriminaja_transactions.is_printed = %d', 0);
        }

        /**
         * Main Query - uses the active WooCommerce orders storage.
         *
         * Supports both legacy (wp_posts / shop_order CPT) and HPOS
         * (wp_wc_orders custom table). The local storage map returns the
         * correct table name and column aliases.
         */
        $o = $this->getOrdersTable();
        $shippable_order_clause = $this->getShippableOrderExistsSql( "orders_tbl.{$o['id']}" );

        $key_clause = '';
        if ('' !== $key) {
            if (0 === strpos($key, 'pid:')) {
                $pickup_number = sanitize_text_field(substr($key, 4));
                $key_clause = $wpdb->prepare(
                    'AND kiriminaja_transactions.pickup_number = %s',
                    $pickup_number
                );
            } else {
                $key_escaped       = $wpdb->esc_like($key);
                $key_prefix        = $key_escaped . '%';
                $key_contains      = '%' . $key_escaped . '%';
                $order_number_type = ctype_digit($key) ? '%d' : '%s';
                $order_number      = ctype_digit($key) ? (int) $key : $key;
                $key_clause        = $wpdb->prepare(
                    "AND (orders_tbl.{$o['id']} = {$order_number_type} OR kiriminaja_transactions.awb LIKE %s OR kiriminaja_transactions.order_id LIKE %s)",
                    $order_number,
                    $key_prefix,
                    $key_contains
                );
            }
        }

        if ($isDeficitFilter) {
            $total = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT orders_tbl.{$o['id']})
                    FROM {$o['table']} as orders_tbl
                    INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
                        ON orders_tbl.{$o['id']} = kiriminaja_transactions.wp_wc_order_stat_order_id
                    WHERE orders_tbl.{$o['trash_field']} NOT IN ('trash','auto-draft')
                        AND kiriminaja_transactions.is_deficit = 1
                        {$cod_clause}
                        {$courier_clause}
                        {$print_status_clause}
                        {$key_clause}
                        {$shippable_order_clause}
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
                        AND kiriminaja_transactions.is_deficit = 1
                        {$cod_clause}
                        {$courier_clause}
                        {$print_status_clause}
                        {$key_clause}
                        {$shippable_order_clause}
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
        } elseif ($isProcessedFilter) {
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
                        {$regular_issue_clause}
                        {$cod_clause}
                        {$courier_clause}
                        {$print_status_clause}
                        {$key_clause}
                        {$shippable_order_clause}
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
                        {$regular_issue_clause}
                        {$cod_clause}
                        {$courier_clause}
                        {$print_status_clause}
                        {$key_clause}
                        {$shippable_order_clause}
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
        } elseif ($isCancelledFilter) {
            $total = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT orders_tbl.{$o['id']})
                    FROM {$o['table']} as orders_tbl
                    INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
                        ON orders_tbl.{$o['id']} = kiriminaja_transactions.wp_wc_order_stat_order_id
                    WHERE orders_tbl.{$o['status']} = %s
                        {$regular_issue_clause}
                        {$cod_clause}
                        {$courier_clause}
                        {$print_status_clause}
                        {$key_clause}
                        {$shippable_order_clause}
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
                        {$regular_issue_clause}
                        {$cod_clause}
                        {$courier_clause}
                        {$print_status_clause}
                        {$key_clause}
                        {$shippable_order_clause}
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
        } elseif ($isAllFilter) {
            $total = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT orders_tbl.{$o['id']})
                    FROM {$o['table']} as orders_tbl
                    INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
                        ON orders_tbl.{$o['id']} = kiriminaja_transactions.wp_wc_order_stat_order_id
                    WHERE orders_tbl.{$o['trash_field']} NOT IN ('trash','auto-draft')
                        {$regular_issue_clause}
                        {$cod_clause}
                        {$courier_clause}
                        {$print_status_clause}
                        {$key_clause}
                        {$shippable_order_clause}
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
                    {$regular_issue_clause}
                    {$cod_clause}
                    {$courier_clause}
                    {$print_status_clause}
                    {$key_clause}
                    {$shippable_order_clause}
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
            $total = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT orders_tbl.{$o['id']})
                    FROM {$o['table']} as orders_tbl
                    INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions
                        ON orders_tbl.{$o['id']} = kiriminaja_transactions.wp_wc_order_stat_order_id
                    WHERE orders_tbl.{$o['status']} = %s
                        AND kiriminaja_transactions.status = %s
                        {$regular_issue_clause}
                        {$cod_clause}
                        {$courier_clause}
                        {$print_status_clause}
                        {$key_clause}
                        {$shippable_order_clause}
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
                    {$regular_issue_clause}
                    {$cod_clause}
                    {$courier_clause}
                    {$print_status_clause}
                    {$key_clause}
                    {$shippable_order_clause}
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
        }

        if (!empty($wpdb->last_error)) {
            $this->logDatabaseError();
        }

        return ['results' => $results, 'total' => $total];
    }


    public function getStatusCounts(): array {
        return array(
            'all'           => $this->getCountByPostStatus( null ),
            'wc-processing' => $this->getCountByPostStatus( 'wc-processing' ),
            'wc-on-hold'    => $this->getCountByPostStatus( 'wc-on-hold' ),
            'wc-pending'    => $this->getCountByPostStatus( 'wc-pending' ),
            'wc-cancelled'  => $this->getCountCancelled(),
            'processed'     => $this->getCountProcessed(),
            'order-issue'   => $this->getCountDeficit(),
        );
    }

    public function getCouriers(): array {
        $cache_key = 'kiriof_distinct_couriers';
        if ( function_exists( 'get_transient' ) ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return (array) $cached;
            }
        }

        $table = $this->wpdb->prefix . 'kiriminaja_transactions';
        $clause = $this->getShippableOrderExistsSql( 't.wp_wc_order_stat_order_id' );
        $results = $this->wpdb->get_results( "SELECT DISTINCT service FROM {$table} t WHERE service IS NOT NULL AND service != '' {$clause} ORDER BY service ASC" );
        $this->logDatabaseError();
        if ( empty( $this->wpdb->last_error ) && function_exists( 'set_transient' ) ) {
            set_transient( $cache_key, $results, HOUR_IN_SECONDS );
        }
        return (array) $results;
    }

    public function getOldestCreatedAt(): ?string {
        $table = $this->wpdb->prefix . 'kiriminaja_transactions';
        $clause = $this->getShippableOrderExistsSql( 'wp_wc_order_stat_order_id' );
        $created_at = $this->wpdb->get_var( "SELECT created_at FROM {$table} WHERE created_at IS NOT NULL {$clause} ORDER BY created_at ASC LIMIT 1" );
        $this->logDatabaseError();
        return null === $created_at || '' === (string) $created_at ? null : (string) $created_at;
    }

    private function getCountByPostStatus( ?string $status ): int {
        $o = $this->getOrdersTable();
        $table = $this->wpdb->prefix . 'kiriminaja_transactions';
        $clause = $this->getShippableOrderExistsSql( "p.{$o['id']}" );
        if ( null === $status || '' === $status || 'all' === $status ) {
            $sql = $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT p.{$o['id']}) FROM {$o['table']} p INNER JOIN {$table} t ON p.{$o['id']} = t.wp_wc_order_stat_order_id WHERE p.{$o['type_col']} = %s AND p.{$o['trash_field']} NOT IN ('trash','auto-draft') AND t.is_deficit = 0 {$clause}",
                $o['type_value']
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT p.{$o['id']}) FROM {$o['table']} p INNER JOIN {$table} t ON p.{$o['id']} = t.wp_wc_order_stat_order_id WHERE p.{$o['status']} = %s AND t.status = %s AND t.is_deficit = 0 {$clause}",
                $status,
                'new'
            );
        }
        $count = $this->wpdb->get_var( $sql );
        $this->logDatabaseError();
        return (int) $count;
    }
    private function getCountProcessed(): int {
        $o = $this->getOrdersTable();
        $table = $this->wpdb->prefix . 'kiriminaja_transactions';
        $payments = $this->wpdb->prefix . 'kiriminaja_payments';
        $clause = $this->getShippableOrderExistsSql( "p.{$o['id']}" );
        $count = $this->wpdb->get_var( "SELECT COUNT(DISTINCT p.{$o['id']}) FROM {$o['table']} p INNER JOIN {$table} t ON p.{$o['id']} = t.wp_wc_order_stat_order_id INNER JOIN {$payments} pay ON t.pickup_number = pay.pickup_number WHERE p.{$o['trash_field']} NOT IN ('trash','auto-draft') AND t.status != 'canceled' AND t.is_deficit = 0 {$clause}" );
        $this->logDatabaseError();
        return (int) $count;
    }

    private function getCountCancelled(): int {
        $o = $this->getOrdersTable();
        $table = $this->wpdb->prefix . 'kiriminaja_transactions';
        $clause = $this->getShippableOrderExistsSql( "p.{$o['id']}" );
        $sql = $this->wpdb->prepare( "SELECT COUNT(DISTINCT p.{$o['id']}) FROM {$o['table']} p INNER JOIN {$table} t ON p.{$o['id']} = t.wp_wc_order_stat_order_id WHERE p.{$o['status']} = %s AND t.is_deficit = 0 {$clause}", 'wc-cancelled' );
        $count = $this->wpdb->get_var( $sql );
        $this->logDatabaseError();
        return (int) $count;
    }

    private function getCountDeficit(): int {
        $o = $this->getOrdersTable();
        $table = $this->wpdb->prefix . 'kiriminaja_transactions';
        $clause = $this->getShippableOrderExistsSql( "p.{$o['id']}" );
        $count = $this->wpdb->get_var( "SELECT COUNT(DISTINCT p.{$o['id']}) FROM {$o['table']} p INNER JOIN {$table} t ON p.{$o['id']} = t.wp_wc_order_stat_order_id WHERE p.{$o['trash_field']} NOT IN ('trash','auto-draft') AND t.is_deficit = 1 {$clause}" );
        $this->logDatabaseError();
        return (int) $count;
    }

    private function getOrdersTable(): array {
        $prefix = $this->wpdb->prefix;
        if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
            return array( 'table' => "{$prefix}wc_orders", 'id' => 'id', 'date' => 'date_created_gmt', 'status' => 'status', 'type_col' => 'type', 'type_value' => 'shop_order', 'trash_field' => 'status' );
        }
        return array( 'table' => "{$prefix}posts", 'id' => 'ID', 'date' => 'post_date', 'status' => 'post_status', 'type_col' => 'post_type', 'type_value' => 'shop_order', 'trash_field' => 'post_status' );
    }

    private function getShippableOrderExistsSql( string $order_id_expression ): string {
        $order_items = $this->wpdb->prefix . 'woocommerce_order_items';
        $itemmeta = $this->wpdb->prefix . 'woocommerce_order_itemmeta';
        $postmeta = $this->wpdb->postmeta;
        return "AND EXISTS (SELECT 1 FROM {$order_items} oi LEFT JOIN {$itemmeta} oim_var ON oim_var.order_item_id = oi.order_item_id AND oim_var.meta_key = '_variation_id' LEFT JOIN {$postmeta} pm_var ON pm_var.post_id = oim_var.meta_value AND pm_var.meta_key = '_virtual' LEFT JOIN {$itemmeta} oim_prod ON oim_prod.order_item_id = oi.order_item_id AND oim_prod.meta_key = '_product_id' LEFT JOIN {$postmeta} pm_prod ON pm_prod.post_id = oim_prod.meta_value AND pm_prod.meta_key = '_virtual' WHERE oi.order_id = {$order_id_expression} AND oi.order_item_type = 'line_item' AND COALESCE(NULLIF(pm_var.meta_value, ''), pm_prod.meta_value, 'no') <> 'yes')";
    }

    private function logDatabaseError(): void {
        if ( ! empty( $this->wpdb->last_error ) && function_exists( 'kiriof_log' ) ) {
            kiriof_log( 'error', (string) $this->wpdb->last_error );
        }
    }
}
