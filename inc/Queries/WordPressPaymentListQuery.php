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

    /**
     * {@inheritDoc}
     */
    public function getPage( array $filters, int $page, int $items_per_page ): array {
        $wpdb = $this->wpdb;
        $page           = max( 1, $page );
        $items_per_page = max( 1, $items_per_page );
        $payment_table  = $this->wpdb->prefix . 'kiriminaja_payments';
        $transaction_table = $this->wpdb->prefix . 'kiriminaja_transactions';
        $key_enabled    = '' !== $filters['key'] ? 1 : 0;
        $key_like       = '%' . $wpdb->esc_like( $filters['key'] ) . '%';
        $month_enabled  = '' !== $filters['month'] ? 1 : 0;
        $month_like     = '%' . $wpdb->esc_like( $filters['month'] ) . '%';
        $status_enabled = in_array( $filters['status'], array( 'unpaid', 'paid' ), true ) ? 1 : 0;
        $status         = $status_enabled ? $filters['status'] : '';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only plugin-owned admin list query; results are request-specific and immediately paginated.
        $total = count(
            (array) $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT
                    kiriminaja_payments.id, kiriminaja_payments.pickup_number
                    FROM %i as kiriminaja_payments
                    INNER JOIN %i as kiriminaja_transactions
                    ON kiriminaja_payments.pickup_number = kiriminaja_transactions.pickup_number
                    WHERE ( %d = 0 OR kiriminaja_payments.pickup_number LIKE %s )
                        AND ( %d = 0 OR kiriminaja_payments.created_at LIKE %s )
                        AND ( %d = 0 OR kiriminaja_payments.status = %s )
                    GROUP BY kiriminaja_payments.pickup_number",
                    $payment_table,
                    $transaction_table,
                    $key_enabled,
                    $key_like,
                    $month_enabled,
                    $month_like,
                    $status_enabled,
                    $status
                )
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_pages = (int) ceil( $total / $items_per_page );

        if ( $page > $total_pages && $total_pages > 0 ) {
            $page = $total_pages;
        }

        $offset = ( $page - 1 ) * $items_per_page;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only plugin-owned admin list query.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                kiriminaja_payments.*,
                SUM(CASE WHEN kiriminaja_transactions.cod_fee = 0 THEN kiriminaja_transactions.shipping_cost - COALESCE(kiriminaja_transactions.discount_amount, 0) + kiriminaja_transactions.insurance_cost ELSE 0 END) AS cost
                FROM %i as kiriminaja_payments
                INNER JOIN %i as kiriminaja_transactions
                ON kiriminaja_payments.pickup_number = kiriminaja_transactions.pickup_number
                WHERE ( %d = 0 OR kiriminaja_payments.pickup_number LIKE %s )
                    AND ( %d = 0 OR kiriminaja_payments.created_at LIKE %s )
                    AND ( %d = 0 OR kiriminaja_payments.status = %s )
                GROUP BY kiriminaja_payments.pickup_number
                ORDER BY kiriminaja_payments.created_at DESC
                LIMIT %d, %d",
                $payment_table,
                $transaction_table,
                $key_enabled,
                $key_like,
                $month_enabled,
                $month_like,
                $status_enabled,
                $status,
                $offset,
                $items_per_page
            )
        );

        $this->logDatabaseError();

        return array(
            'results'        => $results,
            'page'           => $page,
            'items_per_page' => $items_per_page,
            'total_pages'    => $total_pages,
            'total'          => $total,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getStatusCounts(): array {
        return array(
            'all'    => $this->getCountByStatus( null ),
            'unpaid' => $this->getCountByStatus( 'unpaid' ),
            'paid'   => $this->getCountByStatus( 'paid' ),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getOldestCreatedAt(): ?string {
        $wpdb = $this->wpdb;
        $payment_table = $this->wpdb->prefix . 'kiriminaja_payments';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only plugin-owned table query.
        $created_at = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT created_at FROM %i WHERE created_at IS NOT NULL ORDER BY created_at ASC LIMIT 1',
                $payment_table
            )
        );
        $this->logDatabaseError();

        return null === $created_at || '' === (string) $created_at ? null : (string) $created_at;
    }

    /**
     * Count distinct payments by status.
     */
    private function getCountByStatus( ?string $status ): int {
        $wpdb = $this->wpdb;
        $payment_table = $this->wpdb->prefix . 'kiriminaja_payments';

        if ( null === $status ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only plugin-owned table query.
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(DISTINCT pickup_number) FROM %i',
                    $payment_table
                )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        } else {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only plugin-owned table query.
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(DISTINCT pickup_number) FROM %i WHERE status = %s',
                    $payment_table,
                    $status
                )
            );
        }

        $this->logDatabaseError();

        return (int) $count;
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
