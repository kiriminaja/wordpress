<?php

namespace KiriminAjaOfficial\Queries;

use KiriminAjaOfficial\Contracts\TransactionListQueryInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- HPOS/legacy identifiers and the shippable-product EXISTS clause are generated from fixed internal maps, never request input.
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
        $search_by    = $filters['search_by'];
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
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic column identifier comes from trusted getOrdersTable() map.
                    $key_clause = $wpdb->prepare( "AND orders_tbl.{$o['id']} LIKE %s", $key_like );
                    break;
            }
        }

        if ($isDeficitFilter) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
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
            // phpcs:enable ...
        } elseif ($isProcessedFilter) {
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
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        }

        if (!empty($wpdb->last_error)) {
            $this->logDatabaseError();
        }

        return ['results' => $results, 'total' => $total];
    }
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter


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

    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- HPOS/legacy identifiers and the shippable-product EXISTS clause are generated from fixed internal maps, never request input.
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
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
                "SELECT COUNT(DISTINCT p.{$o['id']}) FROM {$o['table']} p INNER JOIN {$table} t ON p.{$o['id']} = t.wp_wc_order_stat_order_id WHERE p.{$o['type_col']} = %s AND p.{$o['trash_field']} NOT IN ('trash','auto-draft') {$clause}",
                $o['type_value']
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT p.{$o['id']}) FROM {$o['table']} p INNER JOIN {$table} t ON p.{$o['id']} = t.wp_wc_order_stat_order_id WHERE p.{$o['status']} = %s AND t.status = %s {$clause}",
                $status,
                'new'
            );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $count = $this->wpdb->get_var( $sql );
        $this->logDatabaseError();
        return (int) $count;
    }
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

    private function getCountProcessed(): int {
        $o = $this->getOrdersTable();
        $table = $this->wpdb->prefix . 'kiriminaja_transactions';
        $payments = $this->wpdb->prefix . 'kiriminaja_payments';
        $clause = $this->getShippableOrderExistsSql( "p.{$o['id']}" );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $count = $this->wpdb->get_var( "SELECT COUNT(DISTINCT p.{$o['id']}) FROM {$o['table']} p INNER JOIN {$table} t ON p.{$o['id']} = t.wp_wc_order_stat_order_id INNER JOIN {$payments} pay ON t.pickup_number = pay.pickup_number WHERE p.{$o['trash_field']} NOT IN ('trash','auto-draft') AND t.status != 'canceled' {$clause}" );
        $this->logDatabaseError();
        return (int) $count;
    }

    private function getCountCancelled(): int {
        $o = $this->getOrdersTable();
        $table = $this->wpdb->prefix . 'kiriminaja_transactions';
        $clause = $this->getShippableOrderExistsSql( "p.{$o['id']}" );
        $sql = $this->wpdb->prepare( "SELECT COUNT(DISTINCT p.{$o['id']}) FROM {$o['table']} p INNER JOIN {$table} t ON p.{$o['id']} = t.wp_wc_order_stat_order_id WHERE p.{$o['status']} = %s {$clause}", 'wc-cancelled' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $count = $this->wpdb->get_var( $sql );
        $this->logDatabaseError();
        return (int) $count;
    }

    private function getCountDeficit(): int {
        $o = $this->getOrdersTable();
        $table = $this->wpdb->prefix . 'kiriminaja_transactions';
        $clause = $this->getShippableOrderExistsSql( "p.{$o['id']}" );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
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
