<?php

declare(strict_types=1);

use KiriminAjaOfficial\Queries\WordPressTransactionListQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

require_once PLUGIN_DIR . '/inc/Contracts/TransactionListQueryInterface.php';
require_once PLUGIN_DIR . '/inc/Services/TransactionDeliveryType.php';
require_once PLUGIN_DIR . '/inc/Services/ListDateRangeFilter.php';
require_once PLUGIN_DIR . '/inc/Queries/WordPressTransactionBadgeQuery.php';
require_once PLUGIN_DIR . '/inc/Queries/WordPressTransactionListQuery.php';
require_once PLUGIN_DIR . '/inc/Contracts/TransactionPrintRepositoryInterface.php';
require_once PLUGIN_DIR . '/inc/Repositories/TransactionRepository.php';

final class TransactionListQueryRuntimeTest extends TestCase
{
    #[Test]
    public function sidebar_total_equals_the_sum_of_shared_pending_delivery_badges(): void {
        $previous_wpdb = $GLOBALS['wpdb'] ?? null;
        $wpdb = new TransactionListQueryWpdbFake();
        $GLOBALS['wpdb'] = $wpdb;
        try {
            $repository = new \KiriminAjaOfficial\Repositories\TransactionRepository();
            $counts = ( new WordPressTransactionListQuery( $wpdb ) )->getDeliveryCounts();
            $this->assertSame( array_sum( $counts ), $repository->getCountTransactionProcessNew() );
            $this->assertSame( $wpdb->queries[0], $wpdb->queries[1] );
        } finally {
            $GLOBALS['wpdb'] = $previous_wpdb;
        }
    }

    #[Test]
    public function missing_or_failed_pending_badge_results_return_zero_counts(): void {
        $wpdb = new TransactionListQueryWpdbFake();
        $wpdb->badge_result = null;
        $query = new \KiriminAjaOfficial\Queries\WordPressTransactionBadgeQuery( $wpdb );
        $empty = array( 'regular' => 0, 'instant' => 0, 'issue' => 0, 'total' => 0 );
        $this->assertSame( $empty, $query->getCounts() );
        $wpdb->badge_result = (object) array( 'regular' => 3, 'instant' => 2, 'issue' => 1, 'total' => 6 );
        $wpdb->last_error = 'Database unavailable';
        $this->assertSame( $empty, $query->getCounts() );
    }

    #[Test]
    public function delivery_tab_counts_share_pending_order_scope_and_preserve_active_partition(): void {
        $wpdb = new TransactionListQueryWpdbFake();
        $query = new WordPressTransactionListQuery( $wpdb );
        $query->getPage( $this->filters('all') + array( 'delivery_type' => 'instant' ), 1, 25 );
        $before = count( $wpdb->queries );
        $this->assertSame( array( 'regular' => 3, 'instant' => 2, 'issue' => 1 ), $query->getDeliveryCounts() );
        $sql = array_slice( $wpdb->queries, $before );
        $this->assertCount( 1, $sql );
        // Executed database tests pin pending scope and partition predicates.
        // This fake checks that requesting badges does not mutate instance scope.
        $query->getStatusCounts();
        $this->assertStringContainsString( "delivery_type = 'instant'", $wpdb->queries[$before + 1] );
    }


    #[Test]
    public function all_filter_preserves_filters_legacy_storage_and_page_clamping(): void
    {
        $wpdb = new TransactionListQueryWpdbFake();
        $query = new WordPressTransactionListQuery($wpdb);

        $page = $query->getPage($this->filters('all'), 9, 25);

        $this->assertSame(3, $page['page']);
        $this->assertSame(55, $page['total']);
        $this->assertSame(3, $page['total_pages']);
        $this->assertSame($wpdb->list_results, $page['results']);
        $this->assertCount(4, $wpdb->queries, 'An out-of-range page must rerun count and rows at the clamped page.');

        // Real SQL tests cover filters, physical products and storage selection.
        $this->assertStringContainsString('LIMIT 25 OFFSET 50', $wpdb->queries[3]);
    }


    #[Test]
    public function status_counts_couriers_and_oldest_date_are_exposed_by_the_read_model(): void
    {
        $wpdb = new TransactionListQueryWpdbFake();
        $query = new WordPressTransactionListQuery($wpdb);

        $this->assertSame(
            array(
                'all' => 55,
                'wc-processing' => 55,
                'wc-on-hold' => 55,
                'wc-pending' => 55,
                'wc-cancelled' => 55,
                'processed' => 55,
                'order-issue' => 55,
            ),
            $query->getStatusCounts()
        );
        $this->assertSame($wpdb->list_results, $query->getCouriers());
        $this->assertSame('2024-03-12 08:00:00', $query->getOldestCreatedAt());

        $all_sql = implode("\n", $wpdb->queries);
        $this->assertStringContainsString('SELECT DISTINCT service', $all_sql);
        $this->assertStringContainsString('ORDER BY created_at ASC LIMIT 1', $all_sql);
    }

    private function filters(string $status): array
    {
        return array(
            'key' => 'KA-10',
            'month' => '2025-02',
            'status' => $status,
            'cod' => '1',
            'courier' => 'jne',
            'print_status' => '0',
        );
    }
}

final class TransactionListQueryWpdbFake
{
    public string $prefix = 'wp_';
    public string $postmeta = 'wp_postmeta';
    public string $last_error = '';
    public array $queries = array();
    public array $list_results;
    public ?object $badge_result;

    public function __construct()
    {
        $this->list_results = array((object) array('wc_order_id' => 10, 'order_id' => 'KA-10'));
        $this->badge_result = (object) array( 'regular' => '3', 'instant' => '2', 'issue' => '1', 'total' => '6' );
    }

    public function esc_like($value): string
    {
        return addcslashes((string) $value, '_%\\');
    }

    public function prepare($sql, ...$values): string
    {
        foreach ($values as $value) {
            $position = strpos($sql, is_int($value) ? '%d' : '%s');
            if (false === $position) {
                continue;
            }
            $placeholder = substr($sql, $position, 2);
            $replacement = '%d' === $placeholder ? (string) $value : "'" . str_replace("'", "''", (string) $value) . "'";
            $sql = substr_replace($sql, $replacement, $position, 2);
        }
        return $sql;
    }

    public function get_var($sql)
    {
        $this->queries[] = $sql;
        if (false !== strpos($sql, 'ORDER BY created_at ASC')) {
            return '2024-03-12 08:00:00';
        }
        return '55';
    }

    public function get_row($sql)
    {
        $this->queries[] = $sql;
        return $this->badge_result;
    }

    public function get_results($sql): array
    {
        $this->queries[] = $sql;
        return $this->list_results;
    }
}
