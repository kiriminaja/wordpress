<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantWebhookRuntimeTest extends TestCase {
    private function runFixture(array $input = []): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/instant-webhook-runtime.php') . ' ' . escapeshellarg(json_encode($input, JSON_THROW_ON_ERROR)));
        return json_decode((string)$output, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function metadata_is_joined_by_exact_order_id_and_event_timestamp_is_preserved(): void {
        $result = $this->runFixture();
        $this->assertSame(200, $result['status']);
        $this->assertSame('A-AWB', $result['calls'][0]['package']['awb']);
        $this->assertSame('B-AWB', $result['calls'][1]['package']['awb']);
        $this->assertSame('2026-07-30 10:00:00', $result['calls'][0]['package']['shipped_at']);
        $this->assertSame('shipped_packages', $result['calls'][0]['method']);
        $this->assertSame([], $result['writes']);
        $this->assertSame(0, $result['payment_calls']);
    }

    #[Test]
    public function authentication_precedes_all_reads_and_writes(): void {
        $result = $this->runFixture(['token'=>'bad']);
        $this->assertSame(401, $result['status']);
        $this->assertSame(0, $result['reads']);
        $this->assertSame([], $result['calls']);
    }

    #[Test]
    public function mixed_and_unsupported_courier_batches_cannot_fall_through_to_express(): void {
        foreach (['jne', 'borzo'] as $service) {
            $result = $this->runFixture(['rows'=>[['order_id'=>'A', 'service'=>'gosend'], ['order_id'=>'B', 'service'=>$service]]]);
            $this->assertSame(400, $result['status']);
            $this->assertSame([], $result['writes']);
            $this->assertSame([], $result['calls']);
            $this->assertSame(0, $result['payment_calls']);
        }
    }

    #[Test]
    public function unsupported_express_methods_are_rejected_for_instant(): void {
        foreach (['processed_packages', 'validated_packages', 'returned_packages', 'rejected_packages', 'return_finished_packages'] as $method) {
            $result = $this->runFixture(['body'=>['method'=>$method, 'data'=>[['order_id'=>'A'], ['order_id'=>'B']]]]);
            $this->assertSame(400, $result['status']);
            $this->assertSame([], $result['calls']);
            $this->assertSame([], $result['writes']);
        }
    }

    #[Test]
    public function all_metadata_is_prevalidated_before_the_first_mutation(): void {
        $valid = ['method'=>'finished_packages', 'data'=>[['order_id'=>'A'], ['order_id'=>'B']]];
        $cases = [
            array_merge($valid, ['data'=>[['order_id'=>'A'], ['order_id'=>'A'], ['order_id'=>'B']]]),
            array_merge($valid, ['packages'=>[['order_id'=>'B', 'status'=>105]]]),
            array_merge($valid, ['packages'=>[['order_id'=>'UNKNOWN']]]),
            array_merge($valid, ['packages'=>[['order_id'=>'B'], ['order_id'=>'B']]]),
            array_merge($valid, ['packages'=>'invalid']),
            array_merge($valid, ['data'=>[['order_id'=>'A'], ['order_id'=>'B'], null]]),
            array_merge($valid, ['data'=>[['order_id'=>'A', 'awb'=>'ONE'], ['order_id'=>'B']], 'packages'=>[['order_id'=>'A', 'awb'=>'OTHER']]]),
            array_merge($valid, ['packages'=>[['order_id'=>'B', 'status'=>null]]]),
            array_merge($valid, ['payment'=>null]),
        ];
        foreach ($cases as $body) {
            $result = $this->runFixture(['body'=>$body]);
            $this->assertSame(400, $result['status'], json_encode($body));
            $this->assertSame([], $result['calls']);
        }
    }

    #[Test]
    public function missing_metadata_is_supported_and_unverified_state_is_retryable(): void {
        foreach (['shipped_packages', 'canceled_packages', 'finished_packages'] as $method) {
            $result = $this->runFixture(['body'=>['method'=>$method, 'data'=>[['order_id'=>'A'], ['order_id'=>'B']]]]);
            $this->assertSame(200, $result['status']);
            $this->assertCount(2, $result['calls']);
        }
        $this->assertSame(503, $this->runFixture(['fail'=>'B'])['status']);
    }

    #[Test]
    public function repeated_handler_calls_do_not_reuse_old_packages_or_matches(): void {
        $result = $this->runFixture(['repeat'=>[['method'=>'finished_packages'], ['method'=>'finished_packages', 'data'=>[['order_id'=>'A']]]]]);
        $this->assertSame([200, 400, 200], $result['responses']);
        $this->assertCount(3, $result['calls']);
        $this->assertSame('A', $result['calls'][2]['id']);
    }
    #[Test]
    public function real_service_name_and_null_awbs_match_documented_api_contract(): void {
        $body = ['method'=>'shipped_packages', 'data'=>[['order_id'=>'A', 'awb'=>'A-AWB', 'shipped_at'=>'2025-01-02T10:30:00.123456Z'], ['order_id'=>'B', 'awb'=>null]], 'packages'=>[['order_id'=>'B', 'service'=>'grab_express', 'service_type'=>'instant', 'status'=>106, 'awb'=>null], ['order_id'=>'A', 'service'=>'gosend', 'service_type'=>'instant', 'status'=>106, 'awb'=>null]]];
        $r = $this->runFixture(['body'=>$body]);
        $this->assertSame(200, $r['status']);
        $this->assertSame('A-AWB', $r['calls'][0]['package']['awb']);
        $this->assertArrayNotHasKey('awb', $r['calls'][1]['package']);
        $this->assertSame('2025-01-02T10:30:00.123456Z', $r['calls'][0]['package']['shipped_at']);
        $r = $this->runFixture(['body'=>$body, 'rows'=>[['order_id'=>'A', 'service'=>'gosend', 'service_name'=>'same_day', 'service_type'=>'instant'], ['order_id'=>'B', 'service'=>'grab_express']]]);
        $this->assertSame(400, $r['status']);
        $this->assertSame([], $r['calls']);
        $r = $this->runFixture(['body'=>$body, 'rows'=>[['order_id'=>'A', 'service'=>'gosend', 'service_name'=>null, 'service_type'=>'instant'], ['order_id'=>'B', 'service'=>'grab_express']]]);
        $this->assertSame(200, $r['status']);
    }

}
