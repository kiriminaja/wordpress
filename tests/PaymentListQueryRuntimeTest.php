<?php

declare(strict_types=1);

use KiriminAjaOfficial\Queries\WordPressPaymentListQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

require_once PLUGIN_DIR . '/inc/Contracts/PaymentListQueryInterface.php';
require_once PLUGIN_DIR . '/inc/Queries/WordPressPaymentListQuery.php';

final class PaymentListQueryRuntimeTest extends TestCase
{
    #[Test]
    public function filtered_page_preserves_join_cost_aggregation_and_pagination_shape(): void
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
        $this->assertCount( 2, $wpdb->result_queries );

        $count_sql = $wpdb->result_queries[0];
        $list_sql  = $wpdb->result_queries[1];

        $this->assertStringContainsString( 'INNER JOIN wp_kiriminaja_transactions', $count_sql );
        $this->assertStringContainsString( "pickup_number LIKE '%PU-10%'", $count_sql );
        $this->assertStringContainsString( "created_at LIKE '%2025-02%'", $count_sql );
        $this->assertStringContainsString( "status = 'unpaid'", $count_sql );
        $this->assertStringContainsString( 'GROUP BY kiriminaja_payments.pickup_number', $count_sql );
        $this->assertStringContainsString(
            'shipping_cost - COALESCE(kiriminaja_transactions.discount_amount, 0) + kiriminaja_transactions.insurance_cost',
            $list_sql
        );
        $this->assertStringContainsString( 'ORDER BY kiriminaja_payments.created_at DESC', $list_sql );
        $this->assertStringContainsString( 'LIMIT 20, 20', $list_sql );
    }

    #[Test]
    public function payment_search_shares_the_tools_row_with_matching_height_controls(): void
    {
        $list = file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' );
        $styles = file_get_contents( PLUGIN_DIR . '/src/styles/payments-list.css' );

        $this->assertStringContainsString( 'kiriof-payments-scopes', $list );
        $this->assertStringContainsString( "<div class=\"kiriof-admin-list-tools\">\n          <form class=\"kiriof-payments-filterrow\"", $list );
        $this->assertStringContainsString( "<Select.Root type=\"single\" value={month}", $list );
        $this->assertStringContainsString( '.kiriof-payments-scopes .kiriof-auto-refresh,', $styles );
        $this->assertStringContainsString( '@apply !h-9 !min-h-9 !box-border;', $styles );
        $this->assertStringContainsString( '@apply !h-full !min-h-0;', $styles );
        $this->assertStringContainsString( '@media (max-width: 782px)', $styles );
    }

    #[Test]
    public function status_counts_and_oldest_date_are_exposed_by_the_read_model(): void
    {
        $wpdb  = new PaymentListQueryWpdbFake();
        $query = new WordPressPaymentListQuery( $wpdb );

        $this->assertSame(
            array( 'all' => 7, 'unpaid' => 4, 'paid' => 3 ),
            $query->getStatusCounts()
        );
        $this->assertSame( '2024-03-12 08:00:00', $query->getOldestCreatedAt() );
        $this->assertStringContainsString( 'COUNT(DISTINCT pickup_number)', $wpdb->var_queries[0] );
        $this->assertStringContainsString( "status = 'unpaid'", $wpdb->var_queries[1] );
        $this->assertStringContainsString( "status = 'paid'", $wpdb->var_queries[2] );
        $this->assertStringContainsString( 'ORDER BY created_at ASC LIMIT 1', $wpdb->var_queries[3] );
    }

    #[Test]
    public function template_is_only_an_access_check_and_render_boundary(): void
    {
        $template = file_get_contents( PLUGIN_DIR . '/templates/request-pickup/index.php' );
        $query     = file_get_contents( PLUGIN_DIR . '/inc/Queries/WordPressPaymentListQuery.php' );
        $renderer  = file_get_contents( PLUGIN_DIR . '/inc/Services/PaymentListRenderService.php' );

        $this->assertStringNotContainsString( '$wpdb', $template );
        $this->assertDoesNotMatchRegularExpression( '/new\s+[^;]*Repository/', $template );
        $this->assertStringContainsString( 'PaymentListRenderService::renderDefault()', $template );
        $this->assertStringContainsString( 'PaymentListQueryInterface', $query );
        $this->assertStringContainsString( 'PaymentListQueryInterface $query', $renderer );
        $this->assertStringContainsString( 'public function render(): void', $renderer );
        $this->assertStringNotContainsString( 'PaymentRepository', $renderer );
		$this->assertStringContainsString( 'prepareSvelteBootstrap', $renderer );
		$this->assertStringContainsString( 'kiriof_payments_bootstrap', file_get_contents( PLUGIN_DIR . '/templates/request-pickup/view/index.php' ) );
		$this->assertFileExists( PLUGIN_DIR . '/src/entries/admin-workspace.ts' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' );
		$this->assertStringContainsString( 'ActionTooltip', file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' ) );
		$this->assertStringNotContainsString( 'title=', file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' ) );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/ui/WorkspaceTabs.svelte' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/admin-list/ListPagination.svelte' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/components/ui/pagination/index.ts' );
		$this->assertFileExists( PLUGIN_DIR . '/src/styles/payments-list.css' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/admin-list/DataTableFooter.svelte' );
		$this->assertStringContainsString( 'DataTableFooter', file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' ) );
		$this->assertStringContainsString( 'WorkspaceTabs', file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' ) );
		$this->assertStringNotContainsString( 'StatusTabs', file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' ) );
		$this->assertStringContainsString( 'KiriofCard', file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' ) );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/ui/KiriofCard.svelte' );
		$this->assertStringContainsString( 'kiriof-admin-list-table', file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' ) );
		$this->assertStringContainsString( 'onValueChange={changeMonth}', file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' ) );
		$this->assertStringNotContainsString( 'bootstrap.i18n.apply', file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' ) );
		$this->assertStringNotContainsString( '<ListPagination', file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' ) );
		$this->assertStringContainsString( "'total' => \$total", $renderer );
		$this->assertStringContainsString( 'import * as Pagination from \'$lib/components/ui/pagination\'', file_get_contents( PLUGIN_DIR . '/src/lib/admin-list/ListPagination.svelte' ) );
		$this->assertStringContainsString( 'm-0 gap-0.5', file_get_contents( PLUGIN_DIR . '/src/lib/components/ui/pagination/pagination-content.svelte' ) );
		$this->assertStringContainsString( 'cn("mb-0", className)', file_get_contents( PLUGIN_DIR . '/src/lib/components/ui/pagination/pagination-item.svelte' ) );
		$this->assertStringContainsString( 'ml-auto mr-0 w-auto justify-end', file_get_contents( PLUGIN_DIR . '/src/lib/admin-list/ListPagination.svelte' ) );
		$this->assertStringContainsString( '![margin:0_0_0_auto]', file_get_contents( PLUGIN_DIR . '/src/styles/admin-list.css' ) );
		$this->assertStringContainsString( '.kiriof-payments-table', file_get_contents( PLUGIN_DIR . '/src/styles/payments-list.css' ) );
		$this->assertStringContainsString( "body:has([data-kiriof-payments-page]) .update-nag", file_get_contents( PLUGIN_DIR . '/src/styles/payments-list.css' ) );
		$this->assertStringContainsString( 'kiriof-workspace-shell', file_get_contents( PLUGIN_DIR . '/templates/request-pickup/view/index.php' ) );
		$this->assertStringContainsString( '.kiriof-workspace-shell .kiriof-app-toolbar', file_get_contents( PLUGIN_DIR . '/src/styles/toolbar.css' ) );
		$this->assertStringContainsString( 'kiriof-workspace-shell', file_get_contents( PLUGIN_DIR . '/templates/request-pickup/view/index.php' ) );
		$this->assertStringContainsString( '.kiriof-workspace-shell .kiriof-app-toolbar', file_get_contents( PLUGIN_DIR . '/src/styles/toolbar.css' ) );
		$this->assertStringContainsString( "'admin-workspace': 'src/entries/admin-workspace.ts'", file_get_contents( PLUGIN_DIR . '/vite.config.ts' ) );
		$this->assertStringContainsString( 'AutoRefresh', file_get_contents( PLUGIN_DIR . '/src/lib/payments/PaymentsList.svelte' ) );
		$this->assertStringContainsString( "'autoRefresh'", $renderer );
		$this->assertStringContainsString( "'refreshLabels'", $renderer );
    }
}

final class PaymentListQueryWpdbFake
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public array $result_queries = array();
    public array $var_queries = array();
    public array $list_results;

    public function __construct()
    {
        $this->list_results = array( (object) array( 'pickup_number' => 'PU-20', 'cost' => '12500' ) );
    }

    public function esc_like( $value ): string
    {
        return addcslashes( (string) $value, '_%\\' );
    }

    public function prepare( $sql, ...$values ): string
    {
        foreach ( $values as $value ) {
            $position = preg_match( '/%[ids]/', $sql, $match, PREG_OFFSET_CAPTURE ) ? $match[0][1] : false;
            if ( false === $position ) {
                continue;
            }

            $placeholder = substr( $sql, $position, 2 );
            $replacement = '%d' === $placeholder
                ? (string) $value
                : ( '%i' === $placeholder ? (string) $value : "'" . str_replace( "'", "''", (string) $value ) . "'" );
            $sql         = substr_replace( $sql, $replacement, $position, 2 );
        }

        return $sql;
    }

    public function get_results( $sql ): array
    {
        $this->result_queries[] = $sql;

        if ( false === strpos( $sql, 'LIMIT' ) ) {
            return array_fill( 0, 35, (object) array( 'pickup_number' => 'counted' ) );
        }

        return $this->list_results;
    }

    public function get_var( $sql )
    {
        $this->var_queries[] = $sql;

        if ( false !== strpos( $sql, 'ORDER BY created_at ASC' ) ) {
            return '2024-03-12 08:00:00';
        }
        if ( false !== strpos( $sql, "status = 'unpaid'" ) ) {
            return '4';
        }
        if ( false !== strpos( $sql, "status = 'paid'" ) ) {
            return '3';
        }

        return '7';
    }
}
