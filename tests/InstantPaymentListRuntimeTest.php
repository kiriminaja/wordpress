<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantPaymentListRuntimeTest extends TestCase {
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
