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
        $this->assertStringContainsString("orders_tbl.ID = 'KA-10'", $sql);
        $this->assertStringContainsString("kiriminaja_transactions.awb LIKE 'KA-10%'", $sql);
        $this->assertStringContainsString("kiriminaja_transactions.order_id LIKE '%KA-10%'", $sql);
        $this->assertStringContainsString('kiriminaja_transactions.is_deficit = 0', $sql);
        $this->assertStringContainsString("kiriminaja_transactions.service = 'jne'", $sql);
        $this->assertStringContainsString('kiriminaja_transactions.cod_fee > 0', $sql);
        $this->assertStringContainsString('kiriminaja_transactions.is_printed = 0', $sql);
        $this->assertStringContainsString("orders_tbl.post_date LIKE '2025-02%'", $sql);
        $this->assertStringContainsString("COALESCE(NULLIF(pm_var.meta_value, ''), pm_prod.meta_value, 'no') <> 'yes'", $sql);
        $this->assertStringContainsString('LIMIT 25 OFFSET 50', $sql);
    }

    #[Test]
    public function numeric_keyword_uses_an_exact_order_number_comparison(): void
    {
        $wpdb = new TransactionListQueryWpdbFake();
        $query = new WordPressTransactionListQuery($wpdb);
        $filters = $this->filters('all');
        $filters['key'] = '10';

        $query->getPage($filters, 1, 25);

        $this->assertStringContainsString('orders_tbl.ID = 10', $wpdb->queries[1]);
        $this->assertStringContainsString("kiriminaja_transactions.awb LIKE '10%'", $wpdb->queries[1]);
        $this->assertStringContainsString("kiriminaja_transactions.order_id LIKE '%10%'", $wpdb->queries[1]);
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
		$this->assertFileExists( PLUGIN_DIR . '/src/entries/admin-workspace.ts' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/admin-list/DataTableFooter.svelte' );
		$this->assertStringContainsString( 'DataTableFooter', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringNotContainsString( 'kiriof-transactions-meta', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/components/ui/tooltip/index.ts' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/ui/ActionTooltip.svelte' );
		$this->assertStringContainsString( 'ActionTooltip', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringNotContainsString( 'title=', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( 'bg-foreground text-background', file_get_contents( PLUGIN_DIR . '/src/lib/components/ui/tooltip/tooltip-content.svelte' ) );
		$this->assertStringContainsString( 'TooltipPrimitive.Arrow', file_get_contents( PLUGIN_DIR . '/src/lib/components/ui/tooltip/tooltip-content.svelte' ) );
		$this->assertStringContainsString( '!rounded-md', file_get_contents( PLUGIN_DIR . '/src/styles/toolbar.css' ) );
		$this->assertStringContainsString( 'sideOffset = 5', file_get_contents( PLUGIN_DIR . '/src/lib/components/ui/tooltip/tooltip-content.svelte' ) );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/transactions/courier-images.ts' );
		$this->assertFileExists( PLUGIN_DIR . '/src/assets/images/kiriminaja-kurir/ninja_inter.png' );
		$this->assertFileDoesNotExist( PLUGIN_DIR . '/src/assets/images/kiriminaja-kurir/ninja_inter.svg' );
		$this->assertStringContainsString( 'spx: shopeeExpress', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/courier-images.ts' ) );
		$this->assertStringContainsString( 'formatPhone(row.customer.phone)', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( 'courierImage(row.courier.code, row.courier.service)', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/ui/CopyableValue.svelte' );
		$this->assertStringContainsString( 'navigator.clipboard.writeText(value)', file_get_contents( PLUGIN_DIR . '/src/lib/ui/CopyableValue.svelte' ) );
		$this->assertStringContainsString( 'CopyableValue', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( 'kiriof-package-fees', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( 'row.package.actualShipping', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( 'Math.abs(row.package.actualShipping - row.package.paidShipping) > 0.01', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( 'row.package.codValue', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( 'items-baseline', file_get_contents( PLUGIN_DIR . '/src/styles/admin-list.css' ) );
		$this->assertStringContainsString( 'kiriof-payment-type', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( 'statusIcon(row.status.tone', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( 'IconCircleCheck', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( 'border', file_get_contents( PLUGIN_DIR . '/src/styles/admin-list.css' ) );
		$this->assertStringNotContainsString( '<div class="kiriof-fee-pills">', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertFileExists( PLUGIN_DIR . '/inc/Services/TransactionListViewModelFactory.php' );
		$this->assertStringContainsString( 'data-kiriof-transactions-root', $app );
		$this->assertStringContainsString( 'kiriof-workspace-shell', $app );
		$this->assertStringContainsString( 'data-kiriof-transactions-payload', $app );
		$this->assertStringNotContainsString( 'data-kiriof-transactions-table-fallback', $app );
		$this->assertFileDoesNotExist( PLUGIN_DIR . '/templates/transaction-process/view/index.php' );
		$this->assertStringContainsString( "'admin-workspace': 'src/entries/admin-workspace.ts'", file_get_contents( PLUGIN_DIR . '/vite.config.ts' ) );
		$this->assertStringContainsString( 'kiriminaja-admin-workspace.css', file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' ) );
		$this->assertStringContainsString( 'kiriminaja-admin-workspace.js', file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' ) );
		$this->assertStringContainsString( '$this->enqueue_workspace_style();', file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' ) );
		$this->assertStringContainsString( "'kiriof-admin-workspace-style'", file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' ) );
		$this->assertStringContainsString( "'kiriof-workspace-admin-list-style'", file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' ) );
		$this->assertStringContainsString( "'kiriof-var-style'", file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' ) );
		$this->assertStringContainsString( "'kiriof-component-style'", file_get_contents( PLUGIN_DIR . '/inc/Base/Enqueue.php' ) );
		$this->assertStringContainsString( 'screen_options_show_screen', file_get_contents( PLUGIN_DIR . '/inc/Pages/Admin.php' ) );
		$this->assertStringContainsString( '#screen-meta-links', file_get_contents( PLUGIN_DIR . '/src/styles/admin-list.css' ) );
		$this->assertStringContainsString( '!pl-0', file_get_contents( PLUGIN_DIR . '/src/styles/admin-list.css' ) );
		$this->assertStringContainsString( '!pb-0', file_get_contents( PLUGIN_DIR . '/src/styles/admin-list.css' ) );
		$this->assertStringNotContainsString( '<input type="checkbox" name="transaction_id[]"', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/components/ui/table/index.ts' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/components/ui/button-group/index.ts' );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/transactions/CourierCombobox.svelte' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/transactions/RequestPickupDialog.svelte' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/components/ui/radio-group/index.ts' );
		$this->assertStringContainsString( "import * as RadioGroup from '\$lib/components/ui/radio-group'", file_get_contents( PLUGIN_DIR . '/src/lib/transactions/RequestPickupDialog.svelte' ) );
		$this->assertStringContainsString( 'paymentOptions.length >= 1', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/RequestPickupDialog.svelte' ) );
		$this->assertStringNotContainsString( '<Select.Item value="qris">', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/RequestPickupDialog.svelte' ) );
		$this->assertStringContainsString( 'variant="ghost" onclick={close}', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/RequestPickupDialog.svelte' ) );
		$this->assertStringContainsString( 'buttonVariants({ variant: "ghost", size: "icon-sm" })', file_get_contents( PLUGIN_DIR . '/src/lib/components/ui/dialog/dialog-content.svelte' ) );
        $this->assertFileExists( PLUGIN_DIR . '/src/lib/components/ui/dialog/index.ts' );
		$this->assertStringContainsString( '<Table.Root', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( '<InputGroup.Root', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( '<div class="kiriof-row-actions">', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringNotContainsString( '<ButtonGroup.Root class="kiriof-row-actions">', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
        $this->assertStringContainsString( 'Order Issue', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
        $this->assertStringNotContainsString( 'filters.search_by', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
        $this->assertStringNotContainsString( 'kiriof-search-prefix', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/ui/WorkspaceTabs.svelte' );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/components/ui/tabs/index.ts' );
		$this->assertStringContainsString( '<WorkspaceTabs', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( "filters.status === 'order-issue' ? 'order-issue' : 'regular'", file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( '<div class="kiriof-row-actions">', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( "[data-slot='checkbox'][data-state='checked']", file_get_contents( PLUGIN_DIR . '/src/styles/admin-list.css' ) );
		$this->assertStringContainsString( '.kiriof-row-actions [data-slot=\'button\']', file_get_contents( PLUGIN_DIR . '/src/styles/admin-list.css' ) );
		$this->assertStringContainsString( "input[aria-hidden='true']", file_get_contents( PLUGIN_DIR . '/src/styles/admin-list.css' ) );
        $this->assertStringContainsString( '<InputGroup.Addon align="inline-start"><IconSearch', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( 'hideIcon', file_get_contents( PLUGIN_DIR . '/src/lib/components/ui/select/select-trigger.svelte' ) );
		$this->assertStringContainsString( 'IconCalendar', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( 'IconCash', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( 'kiriof-courier-trigger', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/CourierCombobox.svelte' ) );
		$this->assertStringContainsString( 'window.setTimeout(applyFilters, 350)', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( 'onValueChange={applySelectFilter}', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( '{#if hasActiveFilters}', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringNotContainsString( 'aria-label={bootstrap.i18n.apply}', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( '<Toolbar toolbar={bootstrap.toolbar}>', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( "import toolbarStyles from '../styles/toolbar.css?inline'", file_get_contents( PLUGIN_DIR . '/src/entries/admin-workspace.ts' ) );
		$this->assertStringContainsString( '.kiriof-workspace-shell .kiriof-app-toolbar', file_get_contents( PLUGIN_DIR . '/src/styles/toolbar.css' ) );
		$this->assertStringContainsString( '!flex', file_get_contents( PLUGIN_DIR . '/src/styles/admin-list.css' ) );
		$this->assertStringContainsString( '{selectedPrintCount} of {selectedCount}', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
        $this->assertStringContainsString( '{selectedPickupCount} of {selectedCount}', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
        $this->assertStringContainsString( 'onclick={openPickupDialog}', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
        $this->assertStringContainsString( 'disabled={!row.selection.canPrint && !row.selection.canPickup}', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( 'isOrderIssue', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringNotContainsString( 'International Delivery', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( "bootstrap.i18n.status", file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( ':has(.kiriof-clear-filters)', file_get_contents( PLUGIN_DIR . '/src/styles/admin-list.css' ) );
		$this->assertStringContainsString( '{selectedCount} selected', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( '!bg-foreground', file_get_contents( PLUGIN_DIR . '/src/styles/admin-list.css' ) );
		$workspace = file_get_contents( PLUGIN_DIR . '/src/entries/admin-workspace.ts' );
		$this->assertStringContainsString( 'history.pushState', $workspace );
		$this->assertStringContainsString( "window.addEventListener('popstate'", $workspace );
		$this->assertStringContainsString( 'fetch(url', $workspace );
		$this->assertStringContainsString( 'kiriminaja-transaction', $workspace );
		$this->assertStringContainsString( 'kiriminaja-request-pickup', $workspace );
		$this->assertStringContainsString( 'kiriminaja-setting', $workspace );
		$this->assertStringContainsString( 'kiriminaja-request-pickup-detail', $workspace );
		$this->assertStringNotContainsString( 'legacy_coupon_menu', $workspace );
		$this->assertStringContainsString( 'workspacePages.has', $workspace );
		$this->assertStringContainsString( 'data-kiriof-transactions-payload', $workspace );
		$this->assertStringContainsString( 'data-kiriof-payments-payload', $workspace );
		$this->assertStringContainsString( 'data-kiriof-settings-payload', $workspace );
		$this->assertStringContainsString( 'installWorkspaceStyles', $workspace );
		$this->assertStringContainsString( 'data-kiriof-loading-indicator', $workspace );
		$this->assertStringContainsString( 'startLoadingIndicator()', $workspace );
		$this->assertStringContainsString( 'finishLoadingIndicator()', $workspace );
		$this->assertStringContainsString( '.kiriof-loading-indicator', file_get_contents( PLUGIN_DIR . '/src/styles/toolbar.css' ) );
		$this->assertStringContainsString( 'prefers-reduced-motion: reduce', file_get_contents( PLUGIN_DIR . '/src/styles/toolbar.css' ) );
		$this->assertStringContainsString( "admin-list.css?inline", $workspace );
		$this->assertStringContainsString( 'AutoRefresh', file_get_contents( PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte' ) );
		$this->assertStringContainsString( "'autoRefresh'", $renderer );
		$this->assertStringContainsString( "'refreshLabels'", $renderer );
		$this->assertFileExists( PLUGIN_DIR . '/src/lib/ui/AutoRefresh.svelte' );
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

final class TransactionDetailOriginResolutionTest extends TestCase
{
    #[Test]
    public function transaction_detail_uses_the_same_snapshot_or_location_origin_resolution_as_the_list(): void
    {
        $detail = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionDetailPageData.php' );

        $this->assertStringContainsString( "if ( empty( \$snapshot ) && ! empty( \$transaction->shipment_location_id ) )", $detail );
        $this->assertStringContainsString( "\$source = ! empty( \$snapshot ) ? \$snapshot : \$location;", $detail );
        $this->assertStringContainsString( "\$address = \$this->location_service->formatAddress( \$source );", $detail );
        $this->assertStringContainsString( "'changeOrigin'  => 'new' === \$status", $detail );
    }
}
