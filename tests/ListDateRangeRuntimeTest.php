<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListDateRangeRuntimeTest extends TestCase {
    #[Test]
    public function renderer_exposes_normalized_range_and_retains_fail_closed_marker(): void {
        $filters = $this->runFixture('transaction-multi-filter-runtime', ['mode'=>'renderer', 'get'=>['month'=>'2020-01', 'date_from'=>'2024-02-29', 'date_to'=>'2024-03-01']]);
        $this->assertSame('', $filters['month']);
        $this->assertSame('2024-02-29', $filters['date_from']);
        $this->assertSame('2024-03-01', $filters['date_to']);
        $this->assertFalse($filters['date_range_invalid']);
        $invalid = $this->runFixture('transaction-multi-filter-runtime', ['mode'=>'renderer', 'get'=>['month'=>'2020-01', 'date_from'=>['bad']]]);
        $this->assertTrue($invalid['date_range_invalid']);
        $this->assertSame('', $invalid['month']);
        $this->assertSame('', $invalid['date_from']);
        $payment = $this->runFixture('payment-list-database-runtime', ['get'=>['month'=>'2020-01', 'date_from'=>'2024-02-29', 'date_to'=>'2024-03-01']]);
        $this->assertSame($filters['date_from'], $payment['bootstrap']['filters']['date_from']);
        $this->assertSame($filters['date_to'], $payment['bootstrap']['filters']['date_to']);
        $this->assertSame('', $payment['bootstrap']['filters']['month']);
    }

    #[Test]
    public function renderer_sanitization_never_repairs_malformed_date_endpoints(): void {
        foreach (['date_from', 'date_to'] as $name) {
            foreach ([' 2024-02-29', '2024-02-29 ', '<b>2024-02-29</b>', "2024-02-29\n", '2025-02-29', ['2024-02-29']] as $value) {
                $get = ['month'=>'2020-01', $name=>$value];
                $transaction = $this->runFixture('transaction-multi-filter-runtime', ['mode'=>'renderer', 'get'=>$get]);
                $payment = $this->runFixture('payment-list-database-runtime', ['get'=>$get])['bootstrap']['filters'];
                foreach ([$transaction, $payment] as $filters) {
                    $this->assertTrue($filters['date_range_invalid']);
                    $this->assertSame('', $filters['month']);
                    $this->assertSame('', $filters[$name]);
                }
            }
        }
    }

    private function runFixture(string $name, array $input): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/' . $name . '.php') . ' ' . escapeshellarg(json_encode($input, JSON_THROW_ON_ERROR)));
        return json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function every_storage_status_and_partition_filters_rows_and_contextual_count_not_global_counters(): void {
        $scopes = [
            'express' => ['all' => [6, 5, 4], 'processed' => [6, 4], 'order-issue' => [], 'wc-cancelled' => [6, 5], 'wc-processing' => [], 'wc-on-hold' => [], 'wc-pending' => [], 'processed,wc-processing,wc-cancelled' => [6, 5, 4]],
            'instant' => ['all' => [6, 5, 4], 'processed' => [6, 5], 'order-issue' => [], 'wc-cancelled' => [], 'wc-processing' => [], 'wc-on-hold' => [], 'wc-pending' => [4], 'processed,wc-processing,wc-cancelled' => [6, 5]],
        ];
        $expected = $actual = [];
        foreach ([false, true] as $hpos) {
            foreach ($scopes as $delivery => $statuses) {
                $requests = [];
                foreach ($statuses as $status => $ids) {
                    $requests[] = ['filters' => ['delivery_type' => $delivery, 'status' => $status, 'month' => '2024-01', 'date_from' => '2025-02-04', 'date_to' => '2025-02-06']];
                }
                $responses = $this->runFixture('transaction-multi-filter-database', ['hpos' => $hpos, 'scenario' => 'instant' === $delivery ? 'instant-print' : '', 'requests' => $requests]);
                foreach (array_keys($statuses) as $index => $status) {
                    $key = ($hpos ? 'hpos' : 'legacy') . '/' . $delivery . '/' . $status;
                    $expected[$key] = ['ids' => $statuses[$status], 'total' => count($statuses[$status]), 'error' => ''];
                    $actual[$key] = ['ids' => array_column($responses[$index]['page']['results'], 'wc_order_id'), 'total' => $responses[$index]['page']['total'], 'error' => $responses[$index]['last_error']];
                }
                $badges = $this->runFixture('transaction-badge-counts-runtime', ['hpos' => $hpos, 'requests' => [
                    ['filters' => ['delivery_type' => $delivery]],
                    ['filters' => ['delivery_type' => $delivery, 'date_from' => '2025-02-04', 'date_to' => '2025-02-06']],
                ]]);
                $key = ($hpos ? 'hpos' : 'legacy') . '/' . $delivery . '/global-counts';
                $expected[$key] = [$badges[0]['counts'], $badges[0]['status_counts']];
                $actual[$key] = [$badges[1]['counts'], $badges[1]['status_counts']];
            }
        }
        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function malformed_inverted_and_array_endpoints_fail_closed_and_maximum_day_does_not_overflow(): void {
        $cases = [
            'invalid-leap-day' => ['date_from' => '2025-02-29'],
            'non-padded' => ['date_to' => '2025-2-01'],
            'array' => ['date_from' => ['2025-01-01']],
            'injection' => ['date_to' => "2025-01-01' OR 1=1"],
            'inverted' => ['date_from' => '2025-03-01', 'date_to' => '2025-02-28'],
            'maximum' => ['date_to' => '9999-12-31'],
        ];
        $expected = $actual = [];
        foreach ([false, true] as $hpos) {
            $requests = array_map(static fn ($filters) => ['filters' => $filters + ['month' => '2024-01']], $cases);
            $responses = $this->runFixture('transaction-multi-filter-database', ['hpos' => $hpos, 'requests' => $requests]);
            foreach (array_keys($cases) as $index => $case) {
                $key = ($hpos ? 'hpos' : 'legacy') . '/' . $case;
                $expected[$key] = ['total' => 'maximum' === $case ? 15 : 0, 'error' => ''];
                $actual[$key] = ['total' => $responses[$index]['page']['total'], 'error' => $responses[$index]['last_error']];
            }
        }
        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function actual_payment_sql_includes_last_day_microseconds_and_preserves_combined_group_aggregates(): void {
        $payments = [];
        $transactions = [];
        foreach (['before'=>'2024-02-28 23:59:59', 'first'=>'2024-02-29 00:00:00', 'last'=>'2024-03-01 23:59:59.999999', 'after'=>'2024-03-02 00:00:00'] as $id=>$date) {
            $payments[] = ['pickup_number'=>$id, 'created_at'=>$date, 'method'=>'qris', 'status'=>'unpaid'];
            $transactions[] = ['pickup_number'=>$id, 'shipping_cost'=>100, 'insurance_cost'=>0, 'cod_fee'=>0];
            $transactions[] = ['order_id'=>$id, 'delivery_type'=>'instant', 'instant_payment_id'=>$id, 'created_at'=>$date, 'shipping_cost'=>200, 'instant_payment_status'=>'paid'];
        }
        // Membership beyond the range stays in the last group's amount and IDs.
        $transactions[] = ['order_id'=>'extra', 'delivery_type'=>'instant', 'instant_payment_id'=>'last', 'created_at'=>'2024-03-03', 'shipping_cost'=>300, 'instant_payment_status'=>'paid'];
        $filters = ['date_from'=>'2024-02-29', 'date_to'=>'2024-03-01', 'month'=>'2020-01'];
        $result = $this->runFixture('payment-list-database-runtime', compact('payments','transactions') + ['requests'=>[
            ['filters'=>$filters, 'page'=>99, 'per_page'=>3],
            ['filters'=>['date_to'=>'2024-02-29']],
            ['filters'=>['date_from'=>'2024-03-02']],
            ['filters'=>['month'=>'2024-02']],
            ['filters'=>['date_from'=>'2024-03-02','date_to'=>'2024-02-29']],
        ]]);
        $this->assertSame(4, $result['pages'][0]['total']);
        $this->assertSame(2, $result['pages'][0]['page']);
        $all = $this->runFixture('payment-list-database-runtime', compact('payments','transactions') + ['requests'=>[['filters'=>$filters]]]);
        $rows = array_column($all['pages'][0]['results'], null, 'row_key');
        $this->assertEquals(500, $rows['instant:last']['cost']);
        $this->assertSame(['extra','last'], $rows['instant:last']['order_ids']);
        $this->assertSame(4, $result['pages'][1]['total']);
        $this->assertSame(2, $result['pages'][2]['total']);
        $this->assertSame(4, $result['pages'][3]['total']);
        $this->assertSame(0, $result['pages'][4]['total']);
        $this->assertSame(8, $result['counts']['all']);
    }
}
