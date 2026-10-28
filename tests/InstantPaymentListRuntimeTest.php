<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantPaymentListRuntimeTest extends TestCase {
    #[Test]
    public function empty_database_returns_zero_counts_and_skips_membership_lookup(): void {
        $result = $this->runDatabase([]);
        $this->assertSame(['all'=>0, 'unpaid'=>0, 'paid'=>0, 'pending'=>0, 'refunded'=>0], $result['counts']);
        $this->assertSame([], $result['pages'][0]['results']);
        $this->assertSame(0, $result['pages'][0]['total']);
        $this->assertSame(0, $result['pages'][0]['total_pages']);
        $this->assertNull($result['oldest']);
        $this->assertCount(4, $result['queries']);
    }

    #[Test]
    public function many_instant_groups_load_complete_sorted_membership_once_per_displayed_page(): void {
        $transactions = [];
        $expected = [];
        $identities = ["quote'_%\\", '0', '001', '1', 'x) OR 1=1 --'];
        for ($group = 0; $group < 24; ++$group) {
            $identity = $identities[$group] ?? 'group-' . $group;
            $expected[$identity] = [];
            for ($member = 179; $member >= 0; --$member) {
                $order = sprintf('%03d-%012d', $group, $member);
                $expected[$identity][] = $order;
                $transactions[] = ['order_id'=>$order, 'delivery_type'=>'instant', 'instant_payment_id'=>$identity, 'created_at'=>'2025-03-01', 'instant_payment_status'=>'paid', 'shipping_cost'=>1];
            }
            sort($expected[$identity], SORT_STRING);
        }
        // A non-Instant row sharing an identity must never become a member.
        $transactions[] = ['order_id'=>'unrelated', 'delivery_type'=>'express', 'instant_payment_id'=>'0'];
        $result = $this->runDatabase(['transactions'=>$transactions, 'requests'=>[[], ['page'=>2]]]);
        $this->assertSame(['all'=>24, 'unpaid'=>0, 'paid'=>24, 'pending'=>0, 'refunded'=>0], $result['counts']);
        $this->assertCount(8, $result['queries']);
        $membership = array_values(array_filter($result['queries'], static fn($sql) => str_contains($sql, ' AS instant_payment_id, order_id FROM ')));
        $this->assertCount(2, $membership);
        foreach ($membership as $index=>$sql) {
            $this->assertSame(count($result['pages'][$index]['results']), substr_count($sql, ' AS instant_payment_id, order_id FROM '));
            $this->assertStringNotContainsString('JOIN', $sql);
            $this->assertStringEndsWith(' ORDER BY order_id ASC', $sql);
        }
        $this->assertCount(20, $result['pages'][0]['results']);
        $this->assertCount(4, $result['pages'][1]['results']);
        $seen = [];
        foreach ($result['pages'] as $page) {
            foreach ($page['results'] as $row) {
                $this->assertSame($expected[$row['instant_payment_id']], $row['order_ids']);
                $this->assertSame('paid', $row['status']);
                $this->assertSame(180, $row['order_amt']);
                $seen[] = $row['instant_payment_id'];
            }
        }
        $this->assertCount(24, array_unique($seen));
    }

    #[Test]
    public function case_insensitive_literal_and_numeric_identities_keep_distinct_complete_memberships(): void {
        $identities = ["quote'_%\\", '0', '001', '1', 'x) OR 1=1 --'];
        $transactions = [];
        $expected = [];
        foreach ($identities as $group=>$identity) {
            $key = strtolower($identity);
            $expected[$key] = [];
            for ($member = 9; $member >= 0; --$member) {
                $order = sprintf('%02d-%04d', $group, $member);
                $expected[$key][] = $order;
                $transactions[] = ['order_id'=>$order, 'delivery_type'=>'instant', 'instant_payment_id'=>0 === $member % 2 ? strtoupper($identity) : $identity, 'created_at'=>'2025-03-01', 'instant_payment_status'=>'paid', 'shipping_cost'=>1];
            }
            sort($expected[$key], SORT_STRING);
        }
        $result = $this->runDatabase(['transactions'=>$transactions, 'instant_payment_id_collation'=>'NOCASE']);
        $this->assertSame(5, $result['pages'][0]['total']);
        $this->assertCount(5, $result['pages'][0]['results']);
        $this->assertCount(5, $result['queries']);
        foreach ($result['pages'][0]['results'] as $row) {
            $this->assertSame($expected[strtolower($row['instant_payment_id'])], $row['order_ids']);
            $this->assertSame(10, $row['order_amt']);
        }
        $this->assertSame(4, substr_count($result['queries'][2], ' UNION ALL '));
        $this->assertStringNotContainsString('JOIN', $result['queries'][2]);
    }

    #[Test]
    public function case_insensitive_payment_group_keeps_all_members_under_the_selected_identity(): void {
        $transactions = [];
        foreach (['Pay123', 'pay123', 'PAY123'] as $index=>$identity) {
            $transactions[] = ['order_id'=>sprintf('%04d', 3 - $index), 'delivery_type'=>'instant', 'instant_payment_id'=>$identity, 'created_at'=>'2025-03-01', 'instant_payment_status'=>'paid', 'shipping_cost'=>1];
        }
        $transactions[] = ['order_id'=>'unrelated', 'delivery_type'=>'express', 'instant_payment_id'=>'pay123'];
        $result = $this->runDatabase(['transactions'=>$transactions, 'instant_payment_id_collation'=>'NOCASE']);
        $this->assertSame(1, $result['pages'][0]['total']);
        $this->assertCount(1, $result['pages'][0]['results']);
        $row = $result['pages'][0]['results'][0];
        $this->assertContains($row['instant_payment_id'], ['Pay123', 'pay123', 'PAY123']);
        $this->assertSame(['0001', '0002', '0003'], $row['order_ids']);
        $this->assertSame(3, $row['order_amt']);
        $this->assertSame(3.0, (float) $row['cost']);
        $this->assertSame($row['instant_payment_id'], $result['bootstrap']['rows'][0]['identity']);
        $this->assertCount(5, $result['queries']);
        $literal = "'" . $row['instant_payment_id'] . "'";
        $this->assertSame("SELECT {$literal} AS instant_payment_id, order_id FROM wp_kiriminaja_transactions WHERE delivery_type = 'instant' AND instant_payment_id = {$literal} ORDER BY order_id ASC", $result['queries'][2]);

        $default = $this->runDatabase(['transactions'=>$transactions]);
        $this->assertSame(3, $default['pages'][0]['total']);
        foreach ($default['pages'][0]['results'] as $separate) {
            $this->assertCount(1, $separate['order_ids']);
        }
    }

    private function runDatabase(array $payload): array {
        // Keep large group fixtures off the command line's platform-specific size limit.
        $file = tempnam(sys_get_temp_dir(), 'payment-list-input-');
        try {
            file_put_contents($file, json_encode($payload, JSON_THROW_ON_ERROR));
            $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/payment-list-database-runtime.php') . ' ' . escapeshellarg('@' . $file));
            return json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function union_groups_before_filter_sort_pagination_and_bootstraps_display_only_identities(): void {
        $payments = [
            ['pickup_number'=>'same', 'created_at'=>'2025-02-01 12:00:00', 'pickup_schedule'=>'2099-01-01', 'order_amt'=>2, 'method'=>'qris', 'status'=>'unpaid'],
            ['pickup_number'=>'legacy', 'created_at'=>'2024-02-01', 'pickup_schedule'=>'2024-02-01', 'order_amt'=>1, 'method'=>'top', 'status'=>'unpaid'],
        ];
        $transactions = [];
        foreach (['same', 'same', 'legacy'] as $pickup) {
            $transactions[] = ['pickup_number'=>$pickup, 'delivery_type'=>null, 'cod_fee'=>0, 'shipping_cost'=>1000, 'discount_amount'=>100, 'insurance_cost'=>50];
        }
        // COD fees remain excluded from Express cost.
        $transactions[] = ['pickup_number'=>'same', 'cod_fee'=>200, 'shipping_cost'=>9000, 'insurance_cost'=>100];
        foreach (['same'=>'paid', 'waiting'=>'unpaid', 'refund'=>'refunded', 'unknown'=>'bogus', 'conflict'=>'paid'] as $id=>$status) {
            $transactions[] = ['delivery_type'=>'instant', 'instant_payment_id'=>$id, 'created_at'=>'2024-01-01', 'request_pickup_at'=>'2025-03-01 12:00:00', 'instant_payment_status'=>$status, 'instant_payment_method'=>'credit', 'shipping_cost'=>2000, 'discount_amount'=>100, 'insurance_cost'=>200];
        }
        $transactions[] = ['delivery_type'=>'instant', 'instant_payment_id'=>'same', 'request_pickup_at'=>'2025-03-02', 'instant_payment_status'=>'paid', 'instant_payment_method'=>'credit', 'shipping_cost'=>3000, 'insurance_cost'=>100];
        $transactions[] = ['delivery_type'=>'instant', 'instant_payment_id'=>'conflict', 'request_pickup_at'=>'2025-03-02', 'instant_payment_status'=>'refunded', 'shipping_cost'=>3000];
        $transactions[] = ['delivery_type'=>'instant', 'instant_payment_id'=>'', 'shipping_cost'=>999999];
        $requests = [[]];
        foreach (['paid', 'unpaid', 'pending', 'refunded'] as $status) { $requests[] = ['filters'=>['status'=>$status]]; }
        $requests[] = ['filters'=>['key'=>'same']];
        $requests[] = ['filters'=>['month'=>'2025-03'], 'page'=>99, 'per_page'=>2];
        $payload = compact('payments', 'transactions', 'requests');
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/payment-list-database-runtime.php') . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR)));
        $result = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['all'=>7, 'unpaid'=>2, 'paid'=>2, 'pending'=>2, 'refunded'=>1], $result['counts']);
        $this->assertSame('2024-02-01', $result['oldest']);
        $this->assertSame(7, $result['pages'][0]['total']);
        foreach ([1=>2, 2=>2, 3=>2, 4=>1, 5=>2, 6=>5] as $page=>$total) { $this->assertSame($total, $result['pages'][$page]['total']); }
        $this->assertSame(3, $result['pages'][6]['page']);
        $this->assertCount(1, $result['pages'][6]['results']);
        $rows = array_column($result['bootstrap']['rows'], null, 'rowKey');
        $this->assertSame('Rp. 5,200', $rows['instant:same']['fees']);
        $this->assertSame('Rp. 1,900', $rows['express:same']['fees']);
        $this->assertSame('', $rows['instant:same']['pickupNumber']);
        $this->assertSame('same', $rows['instant:same']['identity']);
        $this->assertSame('—', $rows['instant:same']['schedule']);
        foreach ($rows as $key=>$row) {
            if (str_starts_with($key, 'instant:')) {
                $this->assertSame(['details'], array_column($row['actions'], 'type'));
                $this->assertStringContainsString('key=ipid%3A' . $row['identity'], $row['actions'][0]['href']);
                $this->assertStringContainsString('delivery_type=instant', $row['actions'][0]['href']);
                $this->assertStringContainsString('status=all', $row['actions'][0]['href']);
            }
        }
        $this->assertSame(['pay','details'], array_column($rows['express:same']['actions'], 'type'));
        $this->assertSame('paid', $rows['express:legacy']['status']);
        $this->assertSame(['details'], array_column($rows['express:legacy']['actions'], 'type'));
    }
}
