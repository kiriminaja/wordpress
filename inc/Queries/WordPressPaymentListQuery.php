<?php

namespace KiriminAjaOfficial\Queries;

use KiriminAjaOfficial\Contracts\PaymentListQueryInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This read model uses prepared values and trusted plugin-owned table identifiers; raw SQL is required for the payment/transaction aggregate.

/**
 * WordPress database read model for the Payments admin list.
 */
class WordPressPaymentListQuery implements PaymentListQueryInterface {
    /** @var object WordPress database connection. */
    private $wpdb;

    /**
     * @param object|null $wpdb WordPress database connection.
     */
    public function __construct( $wpdb = null ) {
        if ( null === $wpdb ) {
            global $wpdb;
        }

        $this->wpdb = $wpdb;
    }

    /** {@inheritDoc} */
    public function getPage( array $filters, int $page, int $items_per_page ): array {
        $wpdb = $this->wpdb;
        $page = max( 1, $page );
        $items_per_page = max( 1, $items_per_page );
        $groups = $this->getPaymentGroupsSql();
        $status = in_array( $filters['status'], array( 'unpaid', 'paid', 'pending', 'refunded' ), true ) ? $filters['status'] : '';
        $args = array(
            '' !== $filters['key'] ? 1 : 0,
            '%' . $wpdb->esc_like( $filters['key'] ) . '%',
            '' !== $filters['month'] ? 1 : 0,
            $wpdb->esc_like( $filters['month'] ) . '%',
            '' !== $status ? 1 : 0,
            $status,
        );
        $where = 'WHERE ( %d = 0 OR payment_identity LIKE %s ) AND ( %d = 0 OR created_at LIKE %s ) AND ( %d = 0 OR status = %s )';
        $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ({$groups}) payment_groups {$where}", ...$args ) );
        $total_pages = (int) ceil( $total / $items_per_page );
        if ( $total_pages > 0 ) {
            $page = min( $page, $total_pages );
        }
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM ({$groups}) payment_groups {$where} ORDER BY created_at DESC, row_key ASC LIMIT %d, %d",
            ...array_merge( $args, array( ( $page - 1 ) * $items_per_page, $items_per_page ) )
        ) );
        $this->logDatabaseError();
        return array( 'results' => $results, 'page' => $page, 'items_per_page' => $items_per_page, 'total_pages' => $total_pages, 'total' => $total );
    }

    /** {@inheritDoc} */
    public function getStatusCounts(): array {
        $counts = array();
        $groups = $this->getPaymentGroupsSql();
        foreach ( array( 'all', 'unpaid', 'paid', 'pending', 'refunded' ) as $status ) {
            $counts[ $status ] = (int) $this->wpdb->get_var( $this->wpdb->prepare(
                "SELECT COUNT(*) FROM ({$groups}) payment_groups WHERE ( %s = 'all' OR status = %s )",
                $status,
                $status
            ) );
        }
        $this->logDatabaseError();
        return $counts;
    }

    /** {@inheritDoc} */
    public function getOldestCreatedAt(): ?string {
        $groups = $this->getPaymentGroupsSql();
        $created_at = $this->wpdb->get_var( "SELECT created_at FROM ({$groups}) payment_groups WHERE created_at IS NOT NULL ORDER BY created_at ASC LIMIT 1" );
        $this->logDatabaseError();
        return null === $created_at || '' === (string) $created_at ? null : (string) $created_at;
    }

    /**
     * One read-only identity per payment, before any list filtering or pagination.
     * Legacy payments retain their original join (including historical delivery
     * types). Instant never borrows a pickup number or a remote payment amount.
     * Conflicting/unknown group states are pending, not inferred to be paid.
     */
    private function getPaymentGroupsSql(): string {
        return $this->wpdb->prepare(
            "SELECT CONCAT('express:', kiriminaja_payments.pickup_number) AS row_key,
                'express' AS delivery_type, kiriminaja_payments.pickup_number AS payment_identity,
                kiriminaja_payments.pickup_number, '' AS instant_payment_id,
                MIN(kiriminaja_payments.created_at) AS created_at,
                MAX(kiriminaja_payments.pickup_schedule) AS pickup_schedule,
                MAX(kiriminaja_payments.order_amt) AS order_amt,
                MAX(kiriminaja_payments.method) AS method,
                CASE WHEN MAX(LOWER(kiriminaja_payments.method)) = 'top' THEN 'paid' ELSE MAX(kiriminaja_payments.status) END AS status,
                SUM(CASE WHEN kiriminaja_transactions.cod_fee = 0 THEN kiriminaja_transactions.shipping_cost - COALESCE(kiriminaja_transactions.discount_amount, 0) + kiriminaja_transactions.insurance_cost ELSE 0 END) AS cost
            FROM %i AS kiriminaja_payments
            INNER JOIN %i AS kiriminaja_transactions ON kiriminaja_payments.pickup_number = kiriminaja_transactions.pickup_number
            GROUP BY kiriminaja_payments.pickup_number
            UNION ALL
            SELECT CONCAT('instant:', t.instant_payment_id) AS row_key,
                'instant' AS delivery_type, t.instant_payment_id AS payment_identity,
                '' AS pickup_number, t.instant_payment_id,
                MIN(COALESCE(t.request_pickup_at, t.created_at)) AS created_at,
                NULL AS pickup_schedule, COUNT(*) AS order_amt,
                CASE WHEN COUNT(DISTINCT COALESCE(t.instant_payment_method, '')) = 1 THEN MAX(t.instant_payment_method) ELSE '' END AS method,
                CASE WHEN COUNT(DISTINCT COALESCE(t.instant_payment_status, '')) = 1
                    AND MAX(t.instant_payment_status) IN ('paid','unpaid','pending','refunded')
                    THEN MAX(t.instant_payment_status) ELSE 'pending' END AS status,
                SUM(t.shipping_cost - COALESCE(t.discount_amount, 0) + COALESCE(t.insurance_cost, 0)) AS cost
            FROM %i AS t
            WHERE t.delivery_type = 'instant' AND t.instant_payment_id IS NOT NULL AND t.instant_payment_id != ''
            GROUP BY t.instant_payment_id",
            $this->wpdb->prefix . 'kiriminaja_payments',
            $this->wpdb->prefix . 'kiriminaja_transactions',
            $this->wpdb->prefix . 'kiriminaja_transactions'
        );
    }

    /**
     * Log database failures without leaking the database dependency to the template.
     */
    private function logDatabaseError(): void {
        if ( empty( $this->wpdb->last_error ) ) {
            return;
        }

        if ( function_exists( 'kiriof_log' ) ) {
            kiriof_log( 'error', (string) $this->wpdb->last_error );
        }
    }
}
