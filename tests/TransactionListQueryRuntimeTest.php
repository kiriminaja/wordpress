<?php

declare(strict_types=1);

use KiriminAjaOfficial\Queries\WordPressTransactionListQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

require_once PLUGIN_DIR . '/inc/Contracts/TransactionListQueryInterface.php';
require_once PLUGIN_DIR . '/inc/Queries/WordPressTransactionListQuery.php';

final class TransactionListQueryRuntimeTest extends TestCase
{
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

        $sql = $wpdb->queries[3];
        $this->assertStringContainsString('FROM wp_posts as orders_tbl', $sql);
        $this->assertStringContainsString("kiriminaja_transactions.order_id LIKE '%KA-10%'", $sql);
        $this->assertStringContainsString("kiriminaja_transactions.service = 'jne'", $sql);
        $this->assertStringContainsString('kiriminaja_transactions.cod_fee > 0', $sql);
        $this->assertStringContainsString('kiriminaja_transactions.is_printed = 0', $sql);
        $this->assertStringContainsString("orders_tbl.post_date LIKE '2025-02%'", $sql);
        $this->assertStringContainsString("COALESCE(NULLIF(pm_var.meta_value, ''), pm_prod.meta_value, 'no') <> 'yes'", $sql);
        $this->assertStringContainsString('LIMIT 25 OFFSET 50', $sql);
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
        $this->assertStringContainsString('wp_wc_orders', file_get_contents(PLUGIN_DIR . '/inc/Queries/WordPressTransactionListQuery.php'));
    }

    #[Test]
    public function every_status_branch_keeps_its_original_predicate(): void
    {
        $expectations = array(
            'order-issue'  => 'kiriminaja_transactions.is_deficit = 1',
            'processed'    => 'INNER JOIN wp_kiriminaja_payments',
            'wc-cancelled' => "orders_tbl.post_status = 'wc-cancelled'",
            'all'          => "orders_tbl.post_status NOT IN ('trash','auto-draft')",
            'wc-on-hold'   => "orders_tbl.post_status = 'wc-on-hold'",
        );

        foreach ($expectations as $status => $predicate) {
            $wpdb = new TransactionListQueryWpdbFake();
            $query = new WordPressTransactionListQuery($wpdb);
            $query->getPage($this->filters($status), 1, 25);
            $this->assertStringContainsString($predicate, $wpdb->queries[1], $status);
        }
    }

    #[Test]
    public function template_is_only_an_access_check_and_render_boundary(): void
    {
        $template = file_get_contents(PLUGIN_DIR . '/templates/transaction-process/index.php');
        $query = file_get_contents(PLUGIN_DIR . '/inc/Queries/WordPressTransactionListQuery.php');
        $renderer = file_get_contents(PLUGIN_DIR . '/inc/Services/TransactionListRenderService.php');

        $this->assertStringNotContainsString('$wpdb', $template);
        $this->assertDoesNotMatchRegularExpression('/new\s+[^;]*Repository/', $template);
        $this->assertStringContainsString('TransactionListRenderService::renderDefault()', $template);
        $this->assertStringContainsString('implements TransactionListQueryInterface', $query);
        $this->assertStringContainsString('TransactionListQueryInterface $query', $renderer);
        $this->assertStringNotContainsString('TransactionRepository', $renderer);
        $this->assertStringNotContainsString('TransactionRepository', $template);
        $this->assertStringContainsString('wc_orders', $query);
        $this->assertStringContainsString('date_created_gmt', $query);
		$app = file_get_contents( PLUGIN_DIR . '/templates/transaction-process/app.php' );
		$this->assertStringContainsString( 'kiriof_transactions_bootstrap', $renderer );
		$this->assertFileExists( PLUGIN_DIR . '/src/entries/transactions-filters.ts' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' );
		$this->assertFileExists( PLUGIN_DIR . '/inc/Services/TransactionListViewModelFactory.php' );
		$this->assertStringContainsString( 'data-kiriof-transactions-root', $app );
		$this->assertStringContainsString( 'data-kiriof-transactions-payload', $app );
		$this->assertStringNotContainsString( 'data-kiriof-transactions-table-fallback', $app );
		$this->assertFileDoesNotExist( PLUGIN_DIR . '/templates/transaction-process/view/index.php' );
		$this->assertStringContainsString( "'transactions-filters': 'src/entries/transactions-filters.ts'", file_get_contents( PLUGIN_DIR . '/vite.config.ts' ) );
		$this->assertStringContainsString( 'kiriminaja-admin-list.css', file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' ) );
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
            'search_by' => 'ka_order_id',
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

    public function __construct()
    {
        $this->list_results = array((object) array('wc_order_id' => 10, 'order_id' => 'KA-10'));
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

    public function get_results($sql): array
    {
        $this->queries[] = $sql;
        return $this->list_results;
    }
}
