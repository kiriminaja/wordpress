<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ExpressCancellationEligibilityRuntimeTest extends TestCase {
    private function fixture(string $name, array $input): array {
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/' . $name . '.php') . ' ' . escapeshellarg(json_encode($input, JSON_THROW_ON_ERROR)) . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_forged_unpaid_and_unconfirmed_requests_never_call_carrier_or_write(): void {
        foreach (['pending', 'new', 'unconfirmed'] as $status) {
            foreach (['AWB-1', '', null] as $awb) {
                $result = $this->fixture('express-cancellation-runtime', ['status' => $status, 'awb' => $awb]);
                $this->assertSame(400, $result['status']);
                $this->assertSame([], $result['calls']);
                $this->assertSame([], $result['writes']);
                $this->assertSame(0, $result['woo_reads']);
                $this->assertSame([], $result['woo_writes']);
            }
        }
    }

    public function test_list_and_detail_hide_pending_cancellation_even_with_an_awb(): void {
        foreach (['pending', 'new', 'unconfirmed', 'shipped', 'finished', 'return', 'returned', 'canceled'] as $status) {
            foreach (['', 'detail'] as $mode) {
                $result = $this->fixture('shipment-detail-amounts-runtime', ['delivery_type' => 'express', 'status' => $status, 'awb' => 'AWB-1', 'mode' => $mode]);
                $transaction = 'detail' === $mode ? $result['transaction'] : $result;
                $this->assertFalse($transaction['actions']['cancel'], $status . '/' . $mode);
            }
        }
    }

    public function test_request_pickup_remains_cancelable_in_ui_and_server(): void {
        foreach (['', 'detail'] as $mode) {
            $result = $this->fixture('shipment-detail-amounts-runtime', ['delivery_type' => 'express', 'status' => 'request_pickup', 'awb' => 'AWB-1', 'mode' => $mode]);
            $transaction = 'detail' === $mode ? $result['transaction'] : $result;
            $this->assertTrue($transaction['actions']['cancel']);
        }
        $result = $this->fixture('express-cancellation-runtime', ['status' => 'request_pickup']);
        $this->assertSame(200, $result['status']);
        $this->assertCount(1, $result['calls']);
        $this->assertSame('AWB-1', $result['calls'][0][0]);
        $this->assertSame('canceled', $result['writes'][0]['changes']['status']);
        $this->assertSame('cancelled', $result['woo_writes'][0][0]);
    }

    public function test_terminal_shipments_and_missing_awb_stay_blocked(): void {
        foreach (['shipped', 'finished', 'return', 'returned', 'canceled'] as $status) {
            $result = $this->fixture('express-cancellation-runtime', ['status' => $status]);
            $this->assertSame(400, $result['status']);
            $this->assertSame([], $result['calls']);
            $this->assertSame([], $result['writes']);
        }
        $result = $this->fixture('express-cancellation-runtime', ['status' => 'request_pickup', 'awb' => '']);
        $this->assertSame(400, $result['status']);
        $this->assertSame([], $result['calls']);
    }
}
