<?php
use PHPUnit\Framework\TestCase;

final class PaymentCallbackLoggingRuntimeTest extends TestCase {
    private function runFixture(string $scenario): array {
        exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=' . (E_ALL & ~E_DEPRECATED) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/payment-callback-logging-runtime.php') . ' ' . escapeshellarg($scenario) . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_poll_reads_and_paid_transitions_are_silent_without_changing_persistence(): void {
        $result = $this->runFixture('poll');
        $this->assertSame([], $result['logs']);
        $this->assertSame([], $result['debug']);
        $this->assertSame([[200, 'unpaid'], [200, 'paid'], [200, 'paid'], [200, 'unpaid']], $result['responses']);
        $this->assertSame(['paid', 'unpaid'], array_column(array_column($result['writes'], 'changes'), 'status'));
    }

    public function test_pickup_keeps_only_safe_payment_creation_audit(): void {
        $result = $this->runFixture('pickup');
        $this->assertSame(200, $result['status']);
        $this->assertSame([], $result['debug']);
        $this->assertCount(1, $result['logs']);
        $this->assertSame('Request pickup local payment created.', $result['logs'][0][1]);
        $this->assertFalse($result['logs'][0][2]['backtrace']);
        $this->assertSame('unpaid', $result['created']['status']);
        $this->assertTrue($result['data']['open_payment']);
        $this->assertCount(1, $result['writes']);
        $this->assertStringNotContainsString('private', json_encode($result['logs']));
        $this->assertStringNotContainsString('08123456789', json_encode($result['logs']));
    }

    public function test_pickup_failure_remains_visible_without_raw_api_response(): void {
        $result = $this->runFixture('failure');
        $this->assertNotSame(200, $result['status']);
        $this->assertSame([], $result['writes']);
        $this->assertNull($result['created']);
        $this->assertSame([], $result['debug']);
        $this->assertCount(1, $result['logs']);
        $this->assertSame('warning', $result['logs'][0][0]);
        $this->assertSame('PIN_INVALID', $result['logs'][0][2]['error_code']);
        $this->assertStringNotContainsString('private', json_encode($result['logs']));
    }
}
