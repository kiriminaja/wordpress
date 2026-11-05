<?php

declare(strict_types=1);

use KiriminAjaOfficial\Queries\WordPressPaymentListQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

require_once PLUGIN_DIR . '/inc/Contracts/PaymentListQueryInterface.php';
require_once PLUGIN_DIR . '/inc/Services/ListDateRangeFilter.php';
require_once PLUGIN_DIR . '/inc/Queries/WordPressPaymentListQuery.php';

final class PaymentListQueryRuntimeTest extends TestCase
{
    #[Test]
    public function composed_group_sql_exceptions_target_the_outer_database_call_lines(): void
    {
        $source = file_get_contents( PLUGIN_DIR . '/inc/Queries/WordPressPaymentListQuery.php' );

        // Plugin Check reports the outer call, while WordPressCS reports its SQL argument.
        foreach ( array(
            '$total = (int) $wpdb->get_var( $wpdb->prepare(',
            '$results = $wpdb->get_results( $wpdb->prepare(',
        ) as $call ) {
            $this->assertMatchesRegularExpression(
                '/\/\/ phpcs:ignore PluginCheck\.Security\.DirectDB\.UnescapedDBParameter -- [^\r\n]+\R\h*' . preg_quote( $call, '/' ) . '/',
                $source,
                'The justified Plugin Check exception must apply to the outer database call, not only the SQL argument.'
            );
        }
        $this->assertMatchesRegularExpression(
            '/\/\/ phpcs:ignore [^\r\n]*PluginCheck\.Security\.DirectDB\.UnescapedDBParameter -- [^\r\n]+\R\h*\$rows = \$wpdb->get_results\(/',
            $source
        );
        $this->assertStringContainsString( '$members = $wpdb->get_results( $wpdb->prepare(', $source );
        $this->assertStringContainsString( 'SELECT %s AS instant_payment_id, order_id FROM %i WHERE delivery_type = %s AND instant_payment_id = %s', $source );
        $this->assertStringNotContainsString( 'AS selected', $source );
        $this->assertStringNotContainsString( 'INNER JOIN (', $source );
        $this->assertStringNotContainsString( 'instant_payment_id IN (', $source );
        $this->assertStringNotContainsString( 'GROUP_CONCAT(', $source );
        $this->assertStringNotContainsString( 'phpcs:disable PluginCheck.Security.DirectDB', $source );
    }

    #[Test]
    public function filtered_page_clamps_pagination_and_returns_query_results(): void
    {
        $wpdb  = new PaymentListQueryWpdbFake();
        $query = new WordPressPaymentListQuery( $wpdb );

        $page = $query->getPage(
            array(
                'key'    => 'PU-10',
                'month'  => '2025-02',
                'status' => 'unpaid',
            ),
            5,
            20
        );

        $this->assertSame( 2, $page['page'], 'Requested pages beyond the result set should clamp to the last page.' );
        $this->assertSame( 20, $page['items_per_page'] );
        $this->assertSame( 2, $page['total_pages'] );
        $this->assertSame( 35, $page['total'] );
        $this->assertSame( $wpdb->list_results, $page['results'] );
        $this->assertCount( 1, $wpdb->result_queries );

        $list_sql  = $wpdb->result_queries[0];

        $this->assertStringContainsString( 'ORDER BY created_at DESC, row_key ASC', $list_sql );
        $this->assertStringContainsString( 'LIMIT 20, 20', $list_sql );
    }

    #[Test]
    public function separately_prepared_date_bounds_preserve_filter_and_pagination_arguments(): void
    {
        $wpdb = new PaymentListQueryWpdbFake();
        $query = new WordPressPaymentListQuery( $wpdb );
        $query->getPage(
            array( 'key' => "PU'_%", 'month' => '2020-01', 'status' => 'paid', 'date_from' => '2025-02-28', 'date_to' => '2025-03-01' ),
            2,
            20
        );

        foreach ( array( $wpdb->var_queries[0], $wpdb->result_queries[0] ) as $sql ) {
            $this->assertStringContainsString( "payment_identity LIKE '%PU''\\_\\%%'", $sql );
            $this->assertStringContainsString( "( 0 = 0 OR created_at LIKE '%' )", $sql );
            $this->assertStringContainsString( "( 1 = 0 OR status = 'paid' )", $sql );
            $this->assertStringContainsString( "created_at >= '2025-02-28 00:00:00'", $sql );
            $this->assertStringContainsString( "created_at < '2025-03-02 00:00:00'", $sql );
        }
        $this->assertStringContainsString( 'LIMIT 20, 20', $wpdb->result_queries[0] );

        $query->getPage( array( 'key' => '', 'month' => '', 'status' => '', 'date_from' => '2025-02-30' ), 1, 20 );
        $this->assertStringContainsString( ' AND 1 = 0', $wpdb->var_queries[1] );
        $this->assertStringContainsString( ' AND 1 = 0 ORDER BY', $wpdb->result_queries[1] );

    }

    #[Test]
    public function status_counts_and_oldest_date_are_exposed_by_the_read_model(): void
    {
        $wpdb  = new PaymentListQueryWpdbFake();
        $query = new WordPressPaymentListQuery( $wpdb );

        $this->assertSame(
            array( 'all' => 7, 'unpaid' => 4, 'paid' => 3, 'pending' => 2, 'refunded' => 1 ),
            $query->getStatusCounts()
        );
        $this->assertSame( '2024-03-12 08:00:00', $query->getOldestCreatedAt() );
        $this->assertCount( 1, $wpdb->result_queries );
        $this->assertCount( 1, $wpdb->var_queries );
    }

    #[Test]
    public function empty_page_and_empty_aggregate_keep_zero_defaults_without_membership_queries(): void
    {
        $wpdb = new PaymentListQueryWpdbFake();
        $wpdb->list_results = array();
        $wpdb->total = 0;
        $wpdb->count_results = array();
        $query = new WordPressPaymentListQuery( $wpdb );
        $page = $query->getPage( array( 'key' => '', 'month' => '', 'status' => '' ), 1, 20 );
        $this->assertSame( array(), $page['results'] );
        $this->assertSame( 0, $page['total'] );
        $this->assertSame( 0, $page['total_pages'] );
        $this->assertSame( array( 'all' => 0, 'unpaid' => 0, 'paid' => 0, 'pending' => 0, 'refunded' => 0 ), $query->getStatusCounts() );
        $this->assertCount( 2, $wpdb->result_queries );
        $this->assertCount( 1, $wpdb->var_queries );
    }

    #[Test]
    public function displayed_instant_memberships_use_one_prepared_query_for_literal_identities(): void
    {
        $wpdb = new PaymentListQueryWpdbFake();
        $identities = array( "quote'_%\\", '0', '001', '1', 'x) OR 1=1 --' );
        $wpdb->list_results = array();
        foreach ( $identities as $identity ) {
            $wpdb->list_results[] = (object) array( 'delivery_type' => 'instant', 'instant_payment_id' => $identity );
            $wpdb->members[] = (object) array( 'instant_payment_id' => $identity, 'order_id' => '0007' );
        }
        $wpdb->list_results[] = (object) array( 'delivery_type' => 'express' );
        $query = new WordPressPaymentListQuery( $wpdb );
        $page = $query->getPage( array( 'key' => '', 'month' => '', 'status' => '' ), 1, 20 );
        $this->assertCount( 2, $wpdb->result_queries );
        $this->assertCount( 1, $wpdb->var_queries );
        $sql = $wpdb->result_queries[1];
        $branches = array();
        foreach ( $identities as $identity ) {
            $literal = "'" . str_replace( "'", "''", $identity ) . "'";
            $branches[] = "SELECT {$literal} AS instant_payment_id, order_id FROM wp_kiriminaja_transactions WHERE delivery_type = 'instant' AND instant_payment_id = {$literal}";
        }
        $this->assertSame( implode( ' UNION ALL ', $branches ) . ' ORDER BY order_id ASC', $sql );
        $this->assertStringNotContainsString( 'JOIN', $sql );
        $this->assertStringNotContainsString( 'COLLATE', $sql );
        foreach ( array_slice( $page['results'], 0, count( $identities ) ) as $row ) {
            $this->assertSame( array( '0007' ), $row->order_ids );
        }
        $this->assertSame( array(), $page['results'][5]->order_ids );
    }
}

final class PaymentListQueryWpdbFake
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public array $result_queries = array();
    public array $var_queries = array();
    public array $list_results;
    public int $total = 35;
    public array $members = array();
    public array $count_results;

    public function __construct()
    {
        $this->list_results = array( (object) array( 'delivery_type' => 'express', 'pickup_number' => 'PU-20', 'cost' => '12500' ) );
        $this->count_results = array( (object) array( 'total' => '7', 'unpaid' => '4', 'paid' => '3', 'pending' => '2', 'refunded' => '1' ) );
    }

    public function esc_like( $value ): string
    {
        return addcslashes( (string) $value, '_%\\' );
    }

    public function prepare( $sql, ...$values ): string
    {
        if ( 1 === count( $values ) && is_array( $values[0] ) ) {
            $values = $values[0];
        }
        preg_match_all( '/%[ids]/', $sql, $placeholders );
        if ( count( $placeholders[0] ) !== count( $values ) ) {
            throw new RuntimeException( 'Prepare placeholder and argument counts must match.' );
        }
        $index = 0;
        $sql = preg_replace_callback( '/%[ids]/', static function ( $match ) use ( $values, &$index ) {
            $value = $values[ $index++ ];
            return '%d' === $match[0]
                ? (string) (int) $value
                : ( '%i' === $match[0] ? (string) $value : "'" . str_replace( "'", "''", (string) $value ) . "'" );
        }, $sql );

        return $sql;
    }

    public function get_results( $sql ): array
    {
        $this->result_queries[] = $sql;

        if ( str_contains( $sql, 'COUNT(*) AS total' ) ) {
            return $this->count_results;
        }
        if ( str_contains( $sql, ' AS instant_payment_id, order_id FROM ' ) ) {
            return $this->members;
        }

        return $this->list_results;
    }

    public function get_var( $sql )
    {
        $this->var_queries[] = $sql;

        if ( false !== strpos( $sql, 'ORDER BY created_at ASC' ) ) {
            return '2024-03-12 08:00:00';
        }
        if ( str_contains( $sql, 'payment_identity LIKE' ) ) { return (string) $this->total; }
        if ( false !== strpos( $sql, "status = 'pending'" ) ) { return '2'; }
        if ( false !== strpos( $sql, "status = 'refunded'" ) ) { return '1'; }
        if ( false !== strpos( $sql, "status = 'unpaid'" ) ) {
            return '4';
        }
        if ( false !== strpos( $sql, "status = 'paid'" ) ) {
            return '3';
        }

        return '7';
    }
}
