<?php

namespace KiriminAjaOfficial\Queries;

use KiriminAjaOfficial\Contracts\PaymentListQueryInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
        $page           = max( 1, $page );
        $items_per_page = max( 1, $items_per_page );
        $where          = $this->buildWhereCondition( $filters );
        $payment_table  = $this->wpdb->prefix . 'kiriminaja_payments';
        $transaction_table = $this->wpdb->prefix . 'kiriminaja_transactions';

        $count_sql = "SELECT
            kiriminaja_payments.id, kiriminaja_payments.pickup_number
            FROM {$payment_table} as kiriminaja_payments
            INNER JOIN {$transaction_table} as kiriminaja_transactions
            ON kiriminaja_payments.pickup_number = kiriminaja_transactions.pickup_number
            {$where}
            GROUP BY kiriminaja_payments.pickup_number";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Dynamic conditions are individually prepared in buildWhereCondition().
        $total       = count( (array) $this->wpdb->get_results( $count_sql ) );
        $total_pages = (int) ceil( $total / $items_per_page );

        if ( $page > $total_pages && $total_pages > 0 ) {
            $page = $total_pages;
        }

        $offset = ( $page - 1 ) * $items_per_page;
        $list_sql = "SELECT
            kiriminaja_payments.*,
            SUM(CASE WHEN kiriminaja_transactions.cod_fee = 0 THEN kiriminaja_transactions.shipping_cost - COALESCE(kiriminaja_transactions.discount_amount, 0) + kiriminaja_transactions.insurance_cost ELSE 0 END) AS cost
            FROM {$payment_table} as kiriminaja_payments
            INNER JOIN {$transaction_table} as kiriminaja_transactions
            ON kiriminaja_payments.pickup_number = kiriminaja_transactions.pickup_number
            {$where}
            GROUP BY kiriminaja_payments.pickup_number
            ORDER BY kiriminaja_payments.created_at DESC
            LIMIT %d, %d";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- SQL conditions are prepared above; pagination placeholders are prepared here.
        $results = $this->wpdb->get_results( $this->wpdb->prepare( $list_sql, $offset, $items_per_page ) );

        $this->logDatabaseError();

        return array(
            'results'        => $results,
            'page'           => $page,
            'items_per_page' => $items_per_page,
            'total_pages'    => $total_pages,
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
        $payment_table = $this->wpdb->prefix . 'kiriminaja_payments';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $created_at = $this->wpdb->get_var( "SELECT created_at FROM {$payment_table} WHERE created_at IS NOT NULL ORDER BY created_at ASC LIMIT 1" );
        $this->logDatabaseError();

        return null === $created_at || '' === (string) $created_at ? null : (string) $created_at;
    }

    /**
     * Build prepared filters shared by the count and list queries.
     *
     * @param array{key:string,month:string,status:string} $filters List filters.
     */
    private function buildWhereCondition( array $filters ): string {
        $conditions = array();

        if ( '' !== $filters['key'] ) {
            $conditions[] = $this->wpdb->prepare(
                'kiriminaja_payments.pickup_number LIKE %s',
                '%' . $this->wpdb->esc_like( $filters['key'] ) . '%'
            );
        }

        if ( '' !== $filters['month'] ) {
            $conditions[] = $this->wpdb->prepare(
                'kiriminaja_payments.created_at LIKE %s',
                '%' . $this->wpdb->esc_like( $filters['month'] ) . '%'
            );
        }

        if ( in_array( $filters['status'], array( 'unpaid', 'paid' ), true ) ) {
            $conditions[] = $this->wpdb->prepare( 'kiriminaja_payments.status = %s', $filters['status'] );
        }

        return empty( $conditions ) ? '' : 'WHERE ' . implode( ' AND ', $conditions );
    }

    /**
     * Count distinct payments by status.
     */
    private function getCountByStatus( ?string $status ): int {
        $payment_table = $this->wpdb->prefix . 'kiriminaja_payments';

        if ( null === $status ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $count = $this->wpdb->get_var( "SELECT COUNT(DISTINCT pickup_number) FROM {$payment_table}" );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $count = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "SELECT COUNT(DISTINCT pickup_number) FROM {$payment_table} WHERE status = %s",
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
