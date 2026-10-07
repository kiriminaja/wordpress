<?php
use PHPUnit\Framework\TestCase;

final class ShippingRateLoggingRuntimeTest extends TestCase {
    private function runFixture(string $scenario): array {
        $output = []; $status = 0;
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/shipping-rate-logging-runtime.php') . ' ' . escapeshellarg($scenario) . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }
    public function test_success_fallback_and_expected_ineligibility_are_silent(): void {
        foreach (['success', 'scan', 'foreign', 'short', 'disabled', 'missing_customer', 'missing_district'] as $scenario) {
            $r = $this->runFixture($scenario);
            $this->assertSame([], $r['logs'], $scenario);
            $this->assertCount(in_array($scenario, ['success', 'scan'], true) ? 1 : 0, $r['rates'], $scenario);
        }
    }
    public function test_real_fallback_failure_keeps_only_fixed_error_without_trace_or_private_data(): void {
        $r = $this->runFixture('fallback_throw');
        $this->assertSame([], $r['rates']);
        $this->assertSame([['error', 'Express destination fallback failed.', ['code' => 'destination_fallback_failed', 'backtrace' => false]]], $r['logs']);
        $this->assertStringNotContainsString('Private', json_encode($r));
        $this->assertStringNotContainsString('secret-api-token', json_encode($r));
    }
}
