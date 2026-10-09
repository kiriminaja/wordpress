<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionMultiFilterRuntimeTest extends TestCase
{
    // Renderer fixtures isolate normalization; database fixtures execute predicates.
    private function invokeFixture(array $payload): array
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/transaction-multi-filter-runtime.php') . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR));
        $output = shell_exec($cmd);
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('', $decoded['last_error'] ?? '', implode("\n", $decoded['queries'] ?? []));
        return $decoded;
    }

    private function filters(string $status): array
    {
        return ['key'=>'KA-10', 'month'=>'2025-02', 'status'=>$status, 'cod'=>'1', 'courier'=>'jne,pos', 'print_status'=>'0'];
    }

    #[Test]
    public function normalization_and_malformed_filters_keep_real_result_scopes_in_both_storages(): void
    {
        $all = [17, 21, 20, 19, 18, 16, 14, 12, 7, 6, 5, 4, 3, 2, 1];
        $cases = [
            'grouped-new' => [['status' => 'wc-processing,wc-on-hold,wc-pending'], [3, 2, 1]],
            'all-five' => [['status' => 'wc-processing,wc-on-hold,wc-pending,processed,wc-cancelled'], $all],
            'single' => [['status' => 'wc-processing'], [1]],
            'issue' => [['status' => 'order-issue'], [8]],
            'mixed-issue' => [['status' => 'order-issue,wc-processing'], [1]],
            'malformed-arrays' => [['status' => ['wc-processing'], 'courier' => ['jne']], $all],
            'courier-injection' => [['status' => 'wc-processing,unknown', 'courier' => 'jne, bad); DROP TABLE x; --,pos'], [1]],
            'unknown-status' => [['status' => 'unknown'], $all],
        ];
        $expected = $actual = [];
        foreach ([false, true] as $hpos) {
            $requests = array_map(static fn ($case) => ['filters' => $case[0]], $cases);
            $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/transaction-multi-filter-database.php') . ' ' . escapeshellarg(json_encode(['hpos' => $hpos, 'requests' => $requests], JSON_THROW_ON_ERROR));
            $responses = json_decode((string) shell_exec($command), true, 512, JSON_THROW_ON_ERROR);
            foreach (array_keys($cases) as $index => $case) {
                $key = ($hpos ? 'hpos' : 'legacy') . '/' . $case;
                $expected[$key] = ['ids' => $cases[$case][1], 'total' => count($cases[$case][1]), 'error' => ''];
                $actual[$key] = ['ids' => array_column($responses[$index]['page']['results'], 'wc_order_id'), 'total' => $responses[$index]['page']['total'], 'error' => $responses[$index]['last_error']];
            }
        }
        $this->assertSame($expected, $actual);
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
            $responses = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
            $expected_pages = $actual_pages = [];
            foreach ($expectations as $index => [$all_ids, $page_ids, $page_number, $per_page, $print_status]) {
                $key = json_encode($requests[$index], JSON_THROW_ON_ERROR);
                $expected_pages[$key] = ['error' => '', 'ids' => $page_ids, 'total' => count($all_ids), 'page' => $page_number, 'per_page' => $per_page, 'pages' => max(1, (int) ceil(count($all_ids) / $per_page))];
            }
            foreach ($responses as $index => $response) {
                $key = json_encode($requests[$index], JSON_THROW_ON_ERROR);
                $actual_pages[$key] = ['error' => $response['last_error'], 'ids' => array_column($response['page']['results'], 'wc_order_id'), 'total' => $response['page']['total'], 'page' => $response['page']['page'], 'per_page' => $response['page']['items_per_page'], 'pages' => $response['page']['total_pages']];
            }
            $this->assertSame($expected_pages, $actual_pages, $hpos ? 'HPOS' : 'legacy');
        }
    }

}
