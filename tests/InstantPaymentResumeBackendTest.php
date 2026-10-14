<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantPaymentResumeBackendTest extends TestCase {
    #[Test]
    public function transaction_payment_link_is_server_gated(): void {
        foreach (['unpaid', 'pending', 'paid', 'refunded', 'unknown'] as $status) {
            foreach (['qris', 'credit', 'top'] as $method) {
                foreach (['PAY-1', '', 'bad/id'] as $id) {
                    $input = ['delivery_type'=>'instant','service'=>'gosend','instant_payment_id'=>$id,'instant_payment_method'=>$method,'instant_payment_status'=>$status];
                    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/transaction-instant-ui-runtime.php') . ' ' . escapeshellarg(json_encode($input, JSON_THROW_ON_ERROR)));
                    $row = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
                    if ('qris' === $method && 'PAY-1' === $id && in_array($status, ['pending','unpaid'], true)) {
                        $this->assertStringContainsString('page=kiriminaja-request-pickup&key=PAY-1&instant_payment_id=PAY-1&open_payment=1', $row['actions']['paymentUrl']);
                    } else {
                        $this->assertSame('', $row['actions']['paymentUrl']);
                    }
                }
            }
        }
    }

    private function dispatch(array $input): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/instant-dispatch-runtime.php') . ' ' . escapeshellarg(json_encode($input, JSON_THROW_ON_ERROR)));
        return json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function refresh_requires_exact_remote_identity_before_merging(): void {
        foreach ([[], ['status_code'=>0], ['payment_id'=>'OTHER'], ['id'=>'PAY-1','payment_id'=>'OTHER'], ['id'=>null,'payment_id'=>'PAY-1'], ['id'=>'PAY-1','payment_id'=>null]] as $data) {
            $result = $this->dispatch(['refresh'=>true, 'refresh_data'=>$data]);
            $this->assertSame('The Instant payment response is invalid.', $result['error']);
            $this->assertSame('unpaid', $result['rows'][0]['instant_payment_status']);
            $this->assertCount(1, $result['books']);
        }
        $result = $this->dispatch(['refresh'=>true, 'refresh_data'=>['payment_id'=>'PAY-1','status_code'=>9,'amount'=>45678,'qr_content'=>'REMOTE-QR']]);
        $this->assertSame('', $result['error']);
        $this->assertSame(45678, $result['refresh']['amount']);
        $this->assertSame('REMOTE-QR', $result['refresh']['qr_content']);
        $this->assertCount(1, $result['books']);
        $result = $this->dispatch(['refresh'=>true, 'refresh_data'=>['payment_id'=>'PAY-1','status_code'=>9]]);
        $this->assertSame(18000, $result['refresh']['amount']);
        $this->assertSame('000201-QR', $result['refresh']['qr_content']);
    }

    #[Test]
    public function cache_is_private_bounded_and_only_for_confirmed_qris(): void {
        $key = 'kiriof_instant_payment_qr_' . hash('sha256', 'PAY-1');
        $result = $this->dispatch([]);
        $this->assertSame(['qr_content', 'amount', 'expires'], array_keys($result['transients'][$key]));
        $this->assertSame(86400, $result['transient_ttls'][$key]);
        $this->assertStringNotContainsString('000201-QR', json_encode([$result['rows'], $result['logs'], $result['writes']]));
        foreach ([['method'=>'credit','pin'=>'123456','echo_pin_qr'=>true], ['profile'=>'TOP','method'=>'top'], ['write_fail'=>true], ['duplicate'=>true], ['payment_status'=>0]] as $input) {
            $result = $this->dispatch($input);
            $this->assertArrayNotHasKey($key, $result['transients']);
        }
        $result = $this->dispatch(['count'=>2,'missing'=>true]);
        $this->assertSame(['KA-2'], $result['dispatch']['payments'][0]['order_ids']);
        $this->assertArrayHasKey($key, $result['transients']);
        $result = $this->dispatch(['count'=>11,'reject_group'=>2]);
        $this->assertSame([$key], array_keys($result['transients']));
        $this->assertCount(10, $result['dispatch']['payments'][0]['order_ids']);
    }

    #[Test]
    public function refresh_replaces_qr_preserves_remote_zero_and_clears_terminal_cache(): void {
        $key = 'kiriof_instant_payment_qr_' . hash('sha256', 'PAY-1');
        $result = $this->dispatch(['refresh_sequence'=>[
            ['id'=>'PAY-1','status_code'=>9,'qr_content'=>'NEW-QR','amount'=>0],
            ['id'=>'PAY-1','status_code'=>9],
        ]]);
        foreach ($result['refreshes'] as $payment) {
            $this->assertSame('NEW-QR', $payment['qr_content']);
            $this->assertSame(0, $payment['amount']);
        }
        foreach (['paid','refunded'] as $status) {
            $result = $this->dispatch(['refresh_sequence'=>[
                ['id'=>'PAY-1','status'=>$status,'qr_content'=>'DO-NOT-SHOW'],
                ['id'=>'PAY-1','status_code'=>9],
            ]]);
            $this->assertArrayNotHasKey($key, $result['transients']);
            foreach ($result['refreshes'] as $payment) { $this->assertSame('', $payment['qr_content']); }
        }
        $result = $this->dispatch(['refresh'=>true,'before_refresh_statuses'=>['paid'],'refresh_data'=>['id'=>'PAY-1','status_code'=>9]]);
        $this->assertSame('paid', $result['refresh']['status']);
        $this->assertSame('', $result['refresh']['qr_content']);
        $this->assertArrayNotHasKey($key, $result['transients']);
    }

    #[Test]
    public function absent_expired_or_unauthorized_cache_never_exposes_qr(): void {
        foreach (['clear_payment_cache','expire_payment_cache'] as $flag) {
            $result = $this->dispatch([$flag=>true,'refresh'=>true,'refresh_data'=>['id'=>'PAY-1','status_code'=>9]]);
            $this->assertSame('', $result['refresh']['qr_content']);
            $this->assertNull($result['refresh']['amount']);
        }
        foreach ([['response_pid'=>'OTHER'], ['refresh_pid'=>'OTHER'], ['count'=>2,'missing'=>true,'refresh_ids'=>['KA-1']]] as $input) {
            $result = $this->dispatch($input + ['refresh'=>true]);
            $this->assertNotSame('', $result['error']);
            $this->assertArrayNotHasKey('refresh', $result);
        }
        $result = $this->dispatch(['method'=>'credit','pin'=>'123456','refresh'=>true,'refresh_data'=>['id'=>'PAY-1','status_code'=>9,'qr_content'=>'REMOTE-QR']]);
        $this->assertSame('', $result['refresh']['qr_content']);
        $this->assertSame([], $result['transients']);
    }

    #[Test]
    public function payment_rows_keep_complete_membership_and_gate_qris_actions(): void {
        $transactions = [];
        foreach (['waiting'=>['qris','unpaid',2], 'pending'=>['qris','pending',1], 'paid'=>['qris','paid',1], 'credit'=>['credit','unpaid',1], 'top'=>['top','unpaid',1], 'bad/id'=>['qris','unpaid',1], 'large'=>['qris','unpaid',51]] as $payment=>$settings) {
            for ($index=0; $index<$settings[2]; ++$index) {
                $transactions[] = ['order_id'=>$payment . '-' . $index, 'delivery_type'=>'instant', 'instant_payment_id'=>$payment, 'instant_payment_method'=>$settings[0], 'instant_payment_status'=>$settings[1], 'created_at'=>'2025-01-01', 'shipping_cost'=>1000];
            }
        }
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/payment-list-database-runtime.php') . ' ' . escapeshellarg(json_encode(['transactions'=>$transactions], JSON_THROW_ON_ERROR)));
        $result = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        $rows = array_column($result['bootstrap']['rows'], null, 'identity');
        $this->assertSame(['waiting-0','waiting-1'], $rows['waiting']['orderIds']);
        $this->assertCount(51, $rows['large']['orderIds']);
        foreach ($rows as $identity=>$row) {
            $this->assertSame(in_array($identity, ['waiting','pending'], true) ? ['pay','details'] : ['details'], array_column($row['actions'], 'type'));
        }
        $this->assertSame('Regular', $result['bootstrap']['i18n']['regular']);
        $this->assertSame('Instant', $result['bootstrap']['i18n']['instant']);
    }
}
