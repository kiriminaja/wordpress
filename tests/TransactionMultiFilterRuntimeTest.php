<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionMultiFilterRuntimeTest extends TestCase
{
    // These tests execute production PHP, not a SQL engine. Fake rows/counts
    // exercise pagination; predicate assertions verify SQL generation only.
    private function invokeFixture(array $payload): array
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/transaction-multi-filter-runtime.php') . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR));
        $output = shell_exec($cmd);
        $this->assertNotSame(null, $output);
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('', $decoded['last_error'] ?? '', implode("\n", $decoded['queries'] ?? []));
        return $decoded;
    }

    private function filters(string $status): array
    {
        return ['key'=>'KA-10', 'month'=>'2025-02', 'status'=>$status, 'cod'=>'1', 'courier'=>'jne,pos', 'print_status'=>'0'];
    }

    #[Test]
    public function grouped_multiple_statuses_use_one_or_scope_in_legacy_and_hpos(): void
    {
        foreach ([false, true] as $hpos) {
            $result = $this->invokeFixture(['hpos'=>$hpos, 'filters'=>$this->filters('wc-processing,wc-on-hold,wc-pending'), 'page'=>1, 'per_page'=>25]);
            $sql = $result['queries'][1];
            $table = $hpos ? 'wp_wc_orders' : 'wp_posts';
            $this->assertStringContainsString("FROM {$table} as orders_tbl", $sql);
            $this->assertStringContainsString("orders_tbl." . ($hpos ? 'status' : 'post_status') . " IN ('wc-processing', 'wc-on-hold', 'wc-pending')", $sql);
            $this->assertStringContainsString("kiriminaja_transactions.status = 'new'", $sql);
            $this->assertStringContainsString('COUNT(DISTINCT orders_tbl.' . ($hpos ? 'id' : 'ID') . ')', $result['queries'][0]);
            $this->assertStringContainsString('GROUP BY orders_tbl.' . ($hpos ? 'id' : 'ID'), $sql);
            $this->assertStringNotContainsString('INNER JOIN wp_kiriminaja_payments', $sql);
            $this->assertStringNotContainsString('JOIN wp_kiriminaja_payments', $sql);
            $this->assertStringContainsString('KA-10', $sql);
            $this->assertStringContainsString('2025-02%', $sql);
            $this->assertStringContainsString('cod_fee > 0', $sql);
            $this->assertStringContainsString("service IN ('jne', 'pos')", $sql);
            $this->assertStringContainsString('is_printed = 0', $sql);
        }
    }

    #[Test]
    public function processed_cancelled_and_new_are_independent_grouped_or_branches(): void
    {
        $result = $this->invokeFixture(['filters'=>$this->filters('wc-processing,processed,wc-cancelled'), 'page'=>1, 'per_page'=>25]);
        $sql = $result['queries'][1];
        $this->assertStringContainsString("(orders_tbl.post_status IN ('wc-processing') AND kiriminaja_transactions.status = 'new')", $sql);
        $this->assertStringContainsString("(kiriminaja_transactions.status != 'canceled' AND multi_pay.pickup_number IS NOT NULL)", $sql);
        $this->assertStringContainsString("(orders_tbl.post_status = 'wc-cancelled')", $sql);
        $this->assertStringContainsString("AND ((orders_tbl.post_status IN ('wc-processing') AND kiriminaja_transactions.status = 'new') OR (kiriminaja_transactions.status != 'canceled' AND multi_pay.pickup_number IS NOT NULL) OR (orders_tbl.post_status = 'wc-cancelled')) AND (", $sql);
        $this->assertSame(0, substr_count($sql, 'INNER JOIN wp_kiriminaja_payments'));
        $this->assertSame(1, substr_count($sql, 'INNER JOIN wp_kiriminaja_transactions'));
        $this->assertSame(1, substr_count($sql, 'LEFT JOIN wp_kiriminaja_payments multi_pay'));
        $this->assertStringContainsString('ON kiriminaja_transactions.pickup_number = multi_pay.pickup_number', $sql);
        $this->assertStringContainsString('is_deficit = 0', $sql);
        $this->assertStringContainsString('GROUP BY orders_tbl.ID', $sql);
    }

    #[Test]
    public function all_five_regular_statuses_normalize_to_all_and_single_branch_stays_exact(): void
    {
        $all = $this->invokeFixture(['filters'=>$this->filters('wc-processing,wc-on-hold,wc-pending,processed,wc-cancelled')]);
        $this->assertStringContainsString("post_status NOT IN ('trash','auto-draft')", $all['queries'][1]);
        $this->assertStringNotContainsString('status IN', $all['queries'][1]);
        $single = $this->invokeFixture(['filters'=>$this->filters('wc-processing')]);
        $this->assertStringContainsString("post_status = 'wc-processing'", $single['queries'][1]);
        $this->assertStringContainsString("kiriminaja_transactions.status = 'new'", $single['queries'][1]);
    }

    #[Test]
    public function issue_scope_is_separate_and_mixed_issue_is_excluded(): void
    {
        $issue = $this->invokeFixture(['filters'=>$this->filters('order-issue')]);
        $this->assertStringContainsString('is_deficit = 1', $issue['queries'][1]);
        $mixed = $this->invokeFixture(['filters'=>$this->filters('order-issue,wc-processing')]);
        $this->assertStringContainsString("post_status = 'wc-processing'", $mixed['queries'][1]);
        $this->assertStringNotContainsString('is_deficit = 1', $mixed['queries'][1]);
    }

    #[Test]
    public function malformed_status_arrays_unknown_values_and_courier_injection_are_safe(): void
    {
        $bad = $this->invokeFixture(['filters'=>['key'=>'','month'=>'','status'=>['wc-processing'], 'cod'=>'', 'courier'=>['jne'], 'print_status'=>'']]);
        $this->assertStringContainsString("post_status NOT IN ('trash','auto-draft')", $bad['queries'][1]);
        $injection = $this->invokeFixture(['filters'=>['key'=>'','month'=>'','status'=>'wc-processing,unknown', 'cod'=>'', 'courier'=>'jne, bad); DROP TABLE x; --,pos', 'print_status'=>'']]);
        $sql = $injection['queries'][1];
        $this->assertStringContainsString("service IN ('jne', 'pos')", $sql);
        $this->assertStringNotContainsString('DROP TABLE', $sql);
        $this->assertStringNotContainsString('%s', $sql);
        $unknown = $this->invokeFixture(['filters'=>['key'=>'','month'=>'','status'=>'unknown', 'cod'=>'', 'courier'=>'', 'print_status'=>'']]);
        $this->assertStringContainsString("post_status NOT IN ('trash','auto-draft')", $unknown['queries'][1]);
        $this->assertStringNotContainsString('unknown', $unknown['queries'][1]);
    }

    #[Test]
    public function out_of_range_page_requeries_count_and_rows_at_clamped_page(): void
    {
        $result = $this->invokeFixture(['filters'=>$this->filters('wc-processing,wc-on-hold'), 'page'=>9, 'per_page'=>25]);
        $this->assertSame(3, $result['page']['page']);
        $this->assertCount(4, $result['queries']);
        $this->assertStringContainsString('LIMIT 25 OFFSET 50', $result['queries'][3]);
    }

    #[Test]
    public function renderer_normalizes_csv_status_courier_and_print_values(): void
    {
        $result = $this->invokeFixture(['mode'=>'renderer', 'get'=>['status'=>'wc-processing, wc-on-hold,unknown', 'courier'=>'jne, bad code,pos', 'print_status'=>'wat']]);
        $this->assertSame('wc-processing,wc-on-hold', $result['status']);
        $this->assertSame('jne,pos', $result['courier']);
        $this->assertSame('', $result['print_status']);
        $arrays = $this->invokeFixture(['mode'=>'renderer', 'get'=>['key'=>['oops'], 'status'=>['wc-processing'], 'courier'=>['jne'], 'print_status'=>['1']]]);
        $this->assertSame(['key'=>'', 'month'=>'', 'status'=>'all', 'cod'=>'', 'courier'=>'', 'print_status'=>'', 'delivery_type'=>'express', 'date_from'=>'', 'date_to'=>'', 'date_range_invalid'=>false], $arrays);
        $all = $this->invokeFixture(['mode'=>'renderer', 'get'=>['status'=>'wc-processing,wc-on-hold,wc-pending,processed,wc-cancelled', 'courier'=>' jne,jne,pos ', 'print_status'=>'1']]);
        $this->assertSame('all', $all['status']);
        $this->assertSame('jne,pos', $all['courier']);
        $this->assertSame('1', $all['print_status']);
    }
    #[Test]
    public function instant_print_filters_execute_real_legacy_and_hpos_sql_without_widening_scope(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('PDO SQLite is required for real query execution.');
        }
        $scopes = [
            'all' => [14, 13, 8, 7, 6, 5, 4, 3, 2, 1],
            'processed' => [8, 6, 5],
            'wc-cancelled' => [8, 7],
            'wc-processing' => [2, 1],
            'wc-processing,wc-on-hold,wc-pending' => [4, 3, 2, 1],
            'wc-processing,processed,wc-cancelled' => [8, 7, 6, 5, 2, 1],
        ];
        $printed_ids = [2, 4, 6, 8, 14];
        foreach ([false, true] as $hpos) {
            $requests = [];
            $expectations = [];
            foreach ($scopes as $status => $ids) {
                foreach (['', '0', '1'] as $print_status) {
                    $expected = array_values(array_filter($ids, static fn ($id): bool => '' === $print_status || in_array($id, $printed_ids, true) === ('1' === $print_status)));
                    $filters = [
                        'delivery_type' => 'instant', 'status' => $status,
                        'courier' => 'gosend,grab_express', 'print_status' => $print_status,
                        'date_from' => '2025-02-01', 'date_to' => '2025-02-28',
                    ];
                    $requests[] = ['filters' => $filters];
                    $expectations[] = [$expected, $expected, 1, 100, $print_status];
                    // Real count/row execution must clamp an out-of-range page after filtering.
                    $last_page = max(1, (int) ceil(count($expected) / 2));
                    $requests[] = ['filters' => $filters, 'page' => 99, 'per_page' => 2];
                    $expectations[] = [$expected, array_slice($expected, ($last_page - 1) * 2, 2), $last_page, 2, $print_status];
                }
            }
            $payload = ['scenario' => 'instant-print', 'hpos' => $hpos, 'requests' => $requests];
            $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/transaction-multi-filter-database.php') . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR));
            $output = shell_exec($command);
            $this->assertNotNull($output);
            $responses = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            $this->assertCount(count($expectations), $responses);
            foreach ($responses as $index => $response) {
                [$all_ids, $page_ids, $page_number, $per_page, $print_status] = $expectations[$index];
                $context = json_encode($requests[$index], JSON_THROW_ON_ERROR);
                $this->assertSame('', $response['last_error'], $context);
                $this->assertSame($page_ids, array_column($response['page']['results'], 'wc_order_id'), $context);
                $this->assertSame(count($all_ids), $response['page']['total'], $context);
                $this->assertSame($page_number, $response['page']['page'], $context);
                $this->assertSame($per_page, $response['page']['items_per_page'], $context);
                $this->assertSame(max(1, (int) ceil(count($all_ids) / $per_page)), $response['page']['total_pages'], $context);
                foreach ($response['queries'] as $sql) {
                    $this->assertStringNotContainsString('JOIN wp_kiriminaja_payments', $sql, $context);
                    if ('' !== $print_status) {
                        $this->assertStringContainsString('is_printed = ' . $print_status, $sql, $context);
                    }
                }
            }
        }
    }

}
