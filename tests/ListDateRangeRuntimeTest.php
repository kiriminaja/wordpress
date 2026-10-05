<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListDateRangeRuntimeTest extends TestCase {
    private function runFixture(string $name, array $input): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/' . $name . '.php') . ' ' . escapeshellarg(json_encode($input, JSON_THROW_ON_ERROR)));
        return json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function every_storage_status_and_partition_filters_rows_and_contextual_count_not_global_counters(): void {
        foreach ([false, true] as $hpos) {
            foreach (['express', 'instant'] as $delivery) {
                foreach (['all', 'processed', 'order-issue', 'wc-cancelled', 'wc-processing', 'wc-on-hold', 'wc-pending', 'processed,wc-processing,wc-cancelled'] as $status) {
                    $result = $this->runFixture('transaction-delivery-runtime', ['hpos'=>$hpos, 'filters'=>['delivery_type'=>$delivery, 'status'=>$status, 'month'=>'2024-01', 'date_from'=>'2025-02-28', 'date_to'=>'2025-03-01']]);
                    $column = $hpos ? 'date_created_gmt' : 'post_date';
                    foreach (array_slice($result['queries'], 0, 4) as $sql) {
                        $this->assertStringContainsString("orders_tbl.$column >= '2025-02-28 00:00:00'", $sql);
                        $this->assertStringContainsString("orders_tbl.$column < '2025-03-02 00:00:00'", $sql);
                        $this->assertStringNotContainsString('2024-01', $sql);
                        $this->assertStringNotContainsString('BETWEEN', $sql);
                    }
                    foreach (array_slice($result['queries'], 4) as $sql) {
                        $this->assertStringNotContainsString('2025-02-28', $sql);
                    }
                    $args = array_merge(...array_column($result['prepared'], 1));
                    $this->assertContains('2025-02-28 00:00:00', $args);
                    $this->assertContains('2025-03-02 00:00:00', $args);
                }
            }
        }
    }

    #[Test]
    public function malformed_inverted_and_array_endpoints_fail_closed_and_maximum_day_does_not_overflow(): void {
        foreach ([['date_from'=>'2025-02-29'], ['date_to'=>'2025-2-01'], ['date_from'=>['2025-01-01']], ['date_to'=>"2025-01-01' OR 1=1"], ['date_from'=>'2025-03-01','date_to'=>'2025-02-28']] as $filters) {
            $result = $this->runFixture('transaction-delivery-runtime', ['filters'=>$filters + ['month'=>'2024-01']]);
            $this->assertStringContainsString('AND 1 = 0', $result['queries'][0]);
            $this->assertStringNotContainsString('2024-01', $result['queries'][0]);
        }
        $result = $this->runFixture('transaction-delivery-runtime', ['filters'=>['date_to'=>'9999-12-31']]);
        $this->assertStringNotContainsString('10000', implode(' ', $result['queries']));
        $this->assertStringContainsString('orders_tbl.post_date IS NOT NULL', $result['queries'][0]);
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
