<?php

namespace KiriminAjaOfficial\Queries;

use KiriminAjaOfficial\Contracts\TransactionListQueryInterface;
use KiriminAjaOfficial\Services\TransactionDeliveryType;
use KiriminAjaOfficial\Services\ListDateRangeFilter;

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
    /** @var string Current list partition, also used by interface-less counts. */
    private $delivery_type = 'express';

    public function __construct( $wpdb = null ) {
        if ( null === $wpdb ) {
            global $wpdb;
        }

        $this->wpdb = $wpdb;
    }

    public function getPage( array $filters, int $page, int $items_per_page ): array {
        $this->delivery_type = TransactionDeliveryType::normalize( $filters['delivery_type'] ?? 'express' );
        if ( 'order-issue' === self::normalizeStatusFilter( $filters['status'] ?? '' ) ) {
            $this->delivery_type = 'express';
        }
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
        $filters      = array_merge( $filters, ListDateRangeFilter::normalize( $filters ) );
        $month        = $filters['month'];
        $status       = $this->normalizeStatusFilter( $filters['status'] ?? '' );
        $cod          = $filters['cod'];
        $courier      = $this->normalizeCourierFilter( $filters['courier'] ?? '' );
        $print_status = $filters['print_status'];
        $month_like   = '';
        if ( '' !== $month ) {
            $month_like = $wpdb->esc_like( $month ) . '%';
        }

        $isMultiStatus = is_array( $status );
        $singleStatus = $isMultiStatus ? '' : $status;
        $isProcessedFilter  = ('processed' === $singleStatus);
        $isCancelledFilter  = ('wc-cancelled' === $singleStatus);
        $isAllFilter = ('all' === $singleStatus);
        $isDeficitFilter    = ('order-issue' === $singleStatus);
        $delivery_clause = $this->getDeliveryTypeClause( 'kiriminaja_transactions' );
        $regular_issue_clause = $delivery_clause;
        if ( 'express' === $this->delivery_type ) {
            $regular_issue_clause .= ' AND kiriminaja_transactions.is_deficit = 0';
        }

        if ( $isProcessedFilter && 'instant' === $this->delivery_type ) {
            // Reuse the regular list query without joining Express payments.
            $regular_issue_clause .= ' AND ' . $this->getInstantProcessedClause( 'kiriminaja_transactions' );
            $isProcessedFilter = false;
            $isAllFilter = true;
        }

        $cod_clause = '';
        if ('1' === $cod) {
            $cod_clause = 'AND kiriminaja_transactions.cod_fee > 0';
        } elseif ('0' === $cod) {
            $cod_clause = 'AND kiriminaja_transactions.cod_fee = 0';
        }

        $courier_clause = '';
        if ( is_array( $courier ) && count( $courier ) > 1 ) {
            $placeholders = implode( ', ', array_fill( 0, count( $courier ), '%s' ) );
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder count is derived from the normalized courier list.
            $courier_clause = $wpdb->prepare( "AND kiriminaja_transactions.service IN ({$placeholders})", ...$courier );
        } elseif ( is_array( $courier ) && count( $courier ) === 1 ) {
            $courier_clause = $wpdb->prepare( 'AND kiriminaja_transactions.service = %s', $courier[0] );
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
        // Preserve existing date semantics: HPOS uses GMT; legacy posts use post_date.
        $date_clause = ListDateRangeFilter::sql( $wpdb, "orders_tbl.{$o['date']}", $filters );
        $shippable_order_clause = $this->getShippableOrderExistsSql( "orders_tbl.{$o['id']}" );

        $key_clause = '';
        if ('' !== $key) {
            if ( 'instant' === $this->delivery_type && 0 === strpos( $key, 'ipid:' ) ) {
                $key_clause = $wpdb->prepare(
                    'AND kiriminaja_transactions.instant_payment_id = %s',
                    sanitize_text_field( substr( $key, 5 ) )
                );
            } elseif (0 === strpos($key, 'pid:')) {
                $pickup_number = sanitize_text_field(substr($key, 4));
                $key_clause = $wpdb->prepare(
                    'AND kiriminaja_transactions.pickup_number = %s',
                    $pickup_number
                );
            } else {
                $key_escaped       = $wpdb->esc_like($key);
                $key_prefix        = $key_escaped . '%';
                $key_contains      = '%' . $key_escaped . '%';
                if ( ctype_digit( $key ) ) {
                    $key_clause = $wpdb->prepare(
                        "AND (orders_tbl.{$o['id']} = %d OR kiriminaja_transactions.awb LIKE %s OR kiriminaja_transactions.order_id LIKE %s)",
                        (int) $key,
                        $key_prefix,
                        $key_contains
                    );
                } else {
                    $key_clause = $wpdb->prepare(
                        "AND (orders_tbl.{$o['id']} = %s OR kiriminaja_transactions.awb LIKE %s OR kiriminaja_transactions.order_id LIKE %s)",
                        $key,
                        $key_prefix,
                        $key_contains
                    );
                }
            }
        }

        // Keep each selected status branch grouped. This prevents the processed
        // branch from weakening the shipment/status guard of regular orders.
        if ( is_array( $status ) ) {
            $status_parts = array();
            $status_args  = array();
            $payment_join = '';
            $regular_statuses = array_values( array_diff( $status, array( 'all', 'order-issue' ) ) );
            $wc_statuses = array_values( array_intersect( $regular_statuses, array( 'wc-processing', 'wc-on-hold', 'wc-pending' ) ) );
            if ( $wc_statuses ) {
                $status_parts[] = "(orders_tbl.{$o['status']} IN (" . implode( ', ', array_fill( 0, count( $wc_statuses ), '%s' ) ) . ") AND kiriminaja_transactions.status = %s)";
                $status_args = array_merge( $status_args, $wc_statuses, array( 'new' ) );
            }
            if ( in_array( 'processed', $regular_statuses, true ) && 'express' === $this->delivery_type ) {
                // Match the working Processed-only payment join. LEFT JOIN keeps
                // cancelled/new rows without a payment when another status is selected.
                $payment_join = "LEFT JOIN {$wpdb->prefix}kiriminaja_payments multi_pay ON kiriminaja_transactions.pickup_number = multi_pay.pickup_number";
                $status_parts[] = "(kiriminaja_transactions.status != 'canceled' AND multi_pay.pickup_number IS NOT NULL)";
            }
            if ( in_array( 'processed', $regular_statuses, true ) && 'instant' === $this->delivery_type ) {
                $status_parts[] = '(' . $this->getInstantProcessedClause( 'kiriminaja_transactions' ) . ')';
            }
            if ( in_array( 'wc-cancelled', $regular_statuses, true ) ) {
                $status_parts[] = "(orders_tbl.{$o['status']} = %s)";
                $status_args[] = 'wc-cancelled';
            }
            if ( $status_parts ) {
                $base_where = "WHERE orders_tbl.{$o['trash_field']} NOT IN ('trash','auto-draft') {$regular_issue_clause} {$cod_clause} {$courier_clause} {$print_status_clause} {$key_clause} {$shippable_order_clause} AND (" . implode( ' OR ', $status_parts ) . "){$date_clause} AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )";
                $from = "FROM {$o['table']} as orders_tbl INNER JOIN {$wpdb->prefix}kiriminaja_transactions as kiriminaja_transactions ON orders_tbl.{$o['id']} = kiriminaja_transactions.wp_wc_order_stat_order_id {$payment_join}";
                // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Status branches and their placeholders are assembled together from normalized values.
                $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT orders_tbl.{$o['id']}) {$from} {$base_where}", ...array_merge( $status_args, array( $month, $month_like ) ) ) );
                // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Status branches and their placeholders are assembled together from normalized values.
                $results = $wpdb->get_results( $wpdb->prepare( "SELECT orders_tbl.{$o['id']} as wc_order_id, orders_tbl.{$o['date']} as wc_date_created, orders_tbl.{$o['status']} as wc_status, orders_tbl.{$o['status']} as post_status, kiriminaja_transactions.* {$from} {$base_where} GROUP BY orders_tbl.{$o['id']} ORDER BY orders_tbl.{$o['date']} DESC LIMIT %d OFFSET %d", ...array_merge( $status_args, array( $month, $month_like, $per_page, $offset ) ) ) );
                $this->logDatabaseError();
                return array( 'results' => $results, 'total' => $total );
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
                        {$delivery_clause}
                        {$cod_clause}
                        {$courier_clause}
                        {$print_status_clause}
                        {$key_clause}
                        {$shippable_order_clause}
                        {$date_clause} AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )",
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
                        {$delivery_clause}
                        {$cod_clause}
                        {$courier_clause}
                        {$print_status_clause}
                        {$key_clause}
                        {$shippable_order_clause}
                        {$date_clause} AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )
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
                        {$date_clause} AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )",
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
                        {$date_clause} AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )
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
                        {$date_clause} AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )",
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
                        {$date_clause} AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )
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
                        {$date_clause} AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )",
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
                    {$date_clause} AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )
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
                        {$date_clause} AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )",
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
                    {$date_clause} AND ( %s = '' OR orders_tbl.{$o['date']} LIKE %s )
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


    /** Normalize CSV without accepting array-shaped request parameters. */
    public static function normalizeStatusFilter( $value ) {
        $regular = array( 'wc-processing', 'wc-on-hold', 'wc-pending', 'processed', 'wc-cancelled' );
        if ( ! is_string( $value ) ) {
            return 'all';
        }
        $tokens = array_values( array_unique( array_map( 'trim', explode( ',', substr( $value, 0, 2048 ), 64 ) ) ) );
        if ( in_array( 'all', $tokens, true ) ) {
            return 'all';
        }
        // The issue tab is a separate scope, never a member of a regular OR.
        if ( array( 'order-issue' ) === $tokens ) {
            return 'order-issue';
        }
        $selected = array_values( array_intersect( $tokens, $regular ) );
        if ( ! $selected || count( $selected ) === count( $regular ) ) {
            return 'all';
        }
        return count( $selected ) === 1 ? $selected[0] : $selected;
    }

    /** Bound query size and reject malformed courier codes rather than rewriting them. */
    public static function normalizeCourierFilter( $value ): array {
        if ( ! is_string( $value ) ) {
            return array();
        }
        $tokens = array_map( 'trim', explode( ',', substr( $value, 0, 4096 ), 51 ) );
        $codes = array();
        foreach ( array_slice( $tokens, 0, 50 ) as $token ) {
            if ( preg_match( '/\A[a-zA-Z0-9_-]{1,64}\z/', $token ) ) {
                $codes[] = $token;
            }
        }
        return array_values( array_unique( $codes ) );
    }

    /** Global pending delivery badges, independent of active list filters. */
    public function getDeliveryCounts(): array {
        $counts = ( new WordPressTransactionBadgeQuery( $this->wpdb ) )->getCounts();
        return array( 'regular' => $counts['regular'], 'instant' => $counts['instant'], 'issue' => $counts['issue'] );
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
        $cache_key = 'kiriof_distinct_couriers_' . $this->delivery_type;
        if ( function_exists( 'get_transient' ) ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return (array) $cached;
            }
        }

        $table = $this->wpdb->prefix . 'kiriminaja_transactions';
        $clause = $this->getShippableOrderExistsSql( 't.wp_wc_order_stat_order_id' );
        $results = $this->wpdb->get_results( "SELECT DISTINCT service FROM {$table} t WHERE service IS NOT NULL AND service != '' {$this->getDeliveryTypeClause( 't' )} {$clause} ORDER BY service ASC" );
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
        $delivery_clause = $this->getDeliveryTypeClause( 't' );
        if ( null === $status || '' === $status || 'all' === $status ) {
            $count = $this->wpdb->get_var( $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT p.{$o['id']}) FROM {$o['table']} p INNER JOIN {$table} t ON p.{$o['id']} = t.wp_wc_order_stat_order_id WHERE p.{$o['type_col']} = %s AND p.{$o['trash_field']} NOT IN ('trash','auto-draft') {$this->getRegularIssueClause()} {$delivery_clause} {$clause}",
                $o['type_value']
            ) );
        } else {
            $count = $this->wpdb->get_var( $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT p.{$o['id']}) FROM {$o['table']} p INNER JOIN {$table} t ON p.{$o['id']} = t.wp_wc_order_stat_order_id WHERE p.{$o['status']} = %s AND t.status = %s {$this->getRegularIssueClause()} {$delivery_clause} {$clause}",
                $status,
                'new'
            ) );
        }
        $this->logDatabaseError();
        return (int) $count;
    }
    private function getCountProcessed(): int {
        $o = $this->getOrdersTable();
        $table = $this->wpdb->prefix . 'kiriminaja_transactions';
        $payments = $this->wpdb->prefix . 'kiriminaja_payments';
        $clause = $this->getShippableOrderExistsSql( "p.{$o['id']}" );
        $payment_join = 'instant' === $this->delivery_type ? '' : "INNER JOIN {$payments} pay ON t.pickup_number = pay.pickup_number";
        $processed_clause = 'instant' === $this->delivery_type ? $this->getInstantProcessedClause( 't' ) : "t.status != 'canceled'";
        $count = $this->wpdb->get_var( "SELECT COUNT(DISTINCT p.{$o['id']}) FROM {$o['table']} p INNER JOIN {$table} t ON p.{$o['id']} = t.wp_wc_order_stat_order_id {$payment_join} WHERE p.{$o['trash_field']} NOT IN ('trash','auto-draft') AND {$processed_clause} {$this->getRegularIssueClause()} {$this->getDeliveryTypeClause( 't' )} {$clause}" );
        $this->logDatabaseError();
        return (int) $count;
    }

    private function getCountCancelled(): int {
        $o = $this->getOrdersTable();
        $table = $this->wpdb->prefix . 'kiriminaja_transactions';
        $clause = $this->getShippableOrderExistsSql( "p.{$o['id']}" );
        $count = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(DISTINCT p.{$o['id']}) FROM {$o['table']} p INNER JOIN {$table} t ON p.{$o['id']} = t.wp_wc_order_stat_order_id WHERE p.{$o['status']} = %s {$this->getRegularIssueClause()} {$this->getDeliveryTypeClause( 't' )} {$clause}", 'wc-cancelled' ) );
        $this->logDatabaseError();
        return (int) $count;
    }

    private function getCountDeficit(): int {
        $o = $this->getOrdersTable();
        $table = $this->wpdb->prefix . 'kiriminaja_transactions';
        $clause = $this->getShippableOrderExistsSql( "p.{$o['id']}" );
        $count = $this->wpdb->get_var( "SELECT COUNT(DISTINCT p.{$o['id']}) FROM {$o['table']} p INNER JOIN {$table} t ON p.{$o['id']} = t.wp_wc_order_stat_order_id WHERE p.{$o['trash_field']} NOT IN ('trash','auto-draft') AND t.is_deficit = 1 {$this->getDeliveryTypeClause( 't', 'express' )} {$clause}" );
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

    /** Booking evidence is separate from lifecycle mapping: status code zero is valid. */
    private function getInstantProcessedClause( string $alias ): string {
        return "{$alias}.status IN ('request_pickup','shipped','finished','return','returned','rejected') AND {$alias}.instant_payment_id IS NOT NULL AND {$alias}.instant_payment_id != '' AND {$alias}.instant_status_code IS NOT NULL";
    }

    private function getRegularIssueClause(): string {
        return 'express' === $this->delivery_type ? 'AND t.is_deficit = 0' : '';
    }

    private function getDeliveryTypeClause( string $alias, ?string $delivery_type = null ): string {
        return $this->wpdb->prepare( "AND {$alias}.delivery_type = %s", $delivery_type ?? $this->delivery_type );
    }
}
