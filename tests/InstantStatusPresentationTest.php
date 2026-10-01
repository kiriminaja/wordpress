<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InstantStatusPresentationTest extends TestCase {
    private function row(array $payload): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/transaction-instant-ui-runtime.php') . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR)));
        $this->assertNotNull($output);
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_supported_booking_metadata_and_status_are_preserved_in_list_detail_and_fallback(): void {
        foreach ([100, 105, 106, 200, 300, 350] as $code) {
            $payload = [
                'delivery_type' => 'instant', 'service' => 'grab_express', 'vehicle' => 'motor',
                'status' => 'request_pickup', 'awb' => '', 'order_id' => 'KA-BOOKED',
                'instant_status_code' => $code, 'instant_payment_id' => 'PAY-BOOKED',
                'instant_payment_method' => 'qris', 'instant_payment_status' => 'paid',
                'shipping_cost' => 12000, 'live_tracking_url' => 'https://tracking.example.test/KA-BOOKED',
                'shipping_info' => json_encode(['_shipping_first_name' => 'Booked recipient', '_shipping_address_1' => 'Booked street', 'instant_items' => [['name' => 'Booked item', 'qty' => 2]]], JSON_THROW_ON_ERROR),
                'shipment_location_snapshot' => json_encode(['origin_name' => 'Booked warehouse', 'origin_address' => 'Booked origin'], JSON_THROW_ON_ERROR),
            ];
            $list = $this->row($payload);
            $this->assertSame(['method' => 'qris', 'status' => 'paid', 'id' => 'PAY-BOOKED'], $list['instantPayment']);
            $this->assertSame(in_array($code, [100, 105], true), $list['actions']['cancel']);
            foreach (['detail', 'fallback'] as $mode) {
                $detail = $this->row($payload + ['mode' => $mode]);
                foreach (['key', 'label', 'tone', 'tooltip', 'issue'] as $field) {
                    $this->assertSame($list['status'][$field], $detail['status'][$field], "$code/$mode/$field");
                }
                $this->assertSame('instant', $detail['deliveryType']);
                $this->assertSame('motor', $detail['vehicle']);
                $this->assertSame('KA-BOOKED', $detail['orderId']);
                $this->assertSame('KA-BOOKED', $detail['shipment']['trackingOrder']);
                $this->assertSame('', $detail['shipment']['awb']);
                $this->assertSame('PAY-BOOKED', $detail['shipment']['paymentId']);
                $this->assertSame('qris', $detail['shipment']['paymentMethod']);
                $this->assertSame('paid', $detail['shipment']['paymentStatus']);
                $this->assertSame(12000, $detail['shipment']['costs']['actualShipping']);
                $this->assertSame('https://tracking.example.test/KA-BOOKED', $detail['shipment']['liveTrackingUrl']);
                $this->assertSame($mode === 'detail' && in_array($code, [100, 105], true), $detail['actions']['cancel']);
                $this->assertSame($mode === 'detail', $detail['actions']['track']);
                $this->assertSame($mode === 'detail', $detail['actions']['reconcile']);
            }
        }
    }

    public function test_instant_remote_status_is_shared_by_list_detail_and_fallback(): void {
        foreach ([100, 101, 105, 106, 110, 200, 300, 350, 401, 400, 701, 999] as $code) {
            $payload = ['delivery_type' => 'instant', 'instant_status_code' => $code, 'instant_payment_status' => 'paid', 'vehicle' => 'mobil'];
            $list = $this->row($payload);
            $this->assertFalse($list['selection']['canProcess']);
            $this->assertFalse($list['selection']['canPrint']);
            $this->assertTrue($list['selection']['disabled']);
            foreach (['detail', 'fallback'] as $mode) {
                $detail = $this->row($payload + ['mode' => $mode]);
                foreach (['key', 'label', 'tone', 'tooltip', 'issue'] as $field) {
                    $this->assertSame($list['status'][$field], $detail['status'][$field], "$code/$mode/$field");
                }
                $this->assertSame('instant', $detail['deliveryType']);
                $this->assertSame('mobil', $detail['vehicle']);
                $this->assertSame([], $detail['steps']);
                $this->assertFalse($detail['supportsLiveTracking']);
                $this->assertSame('', $detail['shipment']['printUrl']);
                foreach (['changeOrigin', 'adjustDeficit', 'cancelDeficit', 'cancel'] as $action) {
                    $this->assertFalse($detail['actions'][$action]);
                }
            }
        }
    }

    public function test_instant_metadata_is_not_woocommerce_cod_payment(): void {
        $list = $this->row(['delivery_type' => 'instant', 'instant_payment_method' => 'qris', 'instant_payment_status' => 'paid', 'instant_payment_id' => 'PAY-1']);
        $this->assertSame(['method' => 'qris', 'status' => 'paid', 'id' => 'PAY-1'], $list['instantPayment']);
        foreach (['detail', 'fallback'] as $mode) {
            $row = $this->row(['mode' => $mode, 'delivery_type' => 'instant', 'cod_fee' => 100, 'instant_payment_method' => 'QRIS', 'instant_payment_status' => 'unpaid', 'instant_payment_id' => 'PAY-1', 'shipping_cost' => 12000]);
            $this->assertFalse($row['isCod']);
            $this->assertSame('QRIS', $row['paymentLabel']);
            $this->assertSame('QRIS', $row['shipment']['paymentMethod']);
            $this->assertSame('unpaid', $row['shipment']['paymentStatus']);
            $this->assertSame('PAY-1', $row['shipment']['paymentId']);
            $this->assertSame('KA-1', $row['orderId']);
            $this->assertSame('AWB-1', $row['shipment']['awb']);
            $this->assertSame(12000, $row['shipment']['costs']['actualShipping']);
        }
    }

    public function test_local_instant_issue_never_becomes_cod_deficit(): void {
        foreach (['list', 'detail', 'fallback'] as $mode) {
            $row = $this->row(['mode' => $mode, 'delivery_type' => 'instant', 'is_deficit' => 1, 'vehicle' => 'invalid']);
            $this->assertSame('Waiting for Shipment', $row['status']['label']);
            $this->assertNotEmpty($row['status']['issue']);
            $this->assertNull($row['vehicle']);
        }
        $express = $this->row(['is_deficit' => 1]);
        $this->assertSame('COD Deficit', $express['status']['label']);
        $this->assertTrue($express['status']['deficit']);
    }

    public function test_find_new_driver_is_informational_and_no_express_timeline_is_rendered(): void {
        $row = $this->row(['delivery_type' => 'instant', 'instant_status_code' => 101]);
        $this->assertSame('Find New Driver', $row['status']['label']);
        $this->assertNotEmpty($row['status']['tooltip']);
        $this->assertTrue(empty($row['status']['issue']));
        foreach (['transactions/TransactionsApp.svelte', 'transaction-detail/TransactionDetail.svelte'] as $file) {
            $source = file_get_contents(PLUGIN_DIR . '/src/lib/' . $file);
            $this->assertStringContainsString('instantStatusIcon', $source);
            $this->assertStringContainsString('status.tooltip', $source);
            $this->assertStringContainsString('<ActionTooltip', $source);
            $this->assertStringNotContainsString('live_tracking_url', $source);
        }
        $detail = file_get_contents(PLUGIN_DIR . '/src/lib/transaction-detail/TransactionDetail.svelte');
        $this->assertStringContainsString("transaction.deliveryType === 'express' && transaction.steps.length > 0", $detail);
        $this->assertStringContainsString('String(transaction.status.issue)', $detail);
    }
}
