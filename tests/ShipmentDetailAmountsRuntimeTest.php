<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ShipmentDetailAmountsRuntimeTest extends TestCase {
    private function detail(array $payload, string $mode): array {
        $payload = array_merge(['delivery_type' => 'instant', 'service' => 'gosend', 'shipping_cost' => 12000, 'insurance_cost' => 200, 'cod_fee' => 300], $payload, ['mode' => $mode]);
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/shipment-detail-amounts-runtime.php') . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR)));
        $this->assertNotNull($output);
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_native_tagged_fee_wins_over_snapshots_and_other_fees_in_both_paths(): void {
        foreach (['detail', 'fallback'] as $mode) {
            $data = $this->detail([
                'shipping_info' => json_encode(['_kiriof_instant_admin_fee' => 9999]),
                'wc_order' => ['meta' => ['_kiriof_instant_admin_fee' => 8000], 'fees' => [
                    ['type' => 'instant_admin_fee', 'name' => 'Renamed', 'total' => 1500],
                    ['type' => 'insurance', 'name' => 'Admin Fee', 'total' => 500],
                    ['name' => 'Admin Fee', 'total' => 600],
                    ['type' => 'cod_fee', 'total' => 100],
                ]],
            ], $mode);
            $costs = $data['transaction']['shipment']['costs'];
            $this->assertEquals(1500, $costs['adminFee']);
            $this->assertEquals(12500, $costs['totalShipping']);
            $this->assertEquals(14000, $costs['total']);
            $this->assertEquals(62000, $costs['orderTotal']);
            $this->assertSame('Admin Fee', $data['i18n']['adminFee']);
        }
    }

    public function test_present_order_removed_or_zero_fee_never_resurrects_snapshot(): void {
        foreach (['detail', 'fallback'] as $mode) {
            foreach ([[], [['type' => 'instant_admin_fee', 'total' => 0]], [['type' => 'instant_admin_fee', 'total' => -50]], [['name' => 'Other admin fee', 'total' => 123]]] as $fees) {
                $data = $this->detail(['shipping_info' => json_encode(['_kiriof_instant_admin_fee' => 9000]), 'wc_order' => ['meta' => ['_kiriof_instant_admin_fee' => 8000], 'fees' => $fees]], $mode);
                $this->assertEquals(0, $data['transaction']['shipment']['costs']['adminFee']);
            }
        }
    }

    public function test_legacy_exact_name_requires_order_provenance_and_uses_current_charge(): void {
        foreach (['detail', 'fallback'] as $mode) {
            foreach ([[], ['_kiriof_instant_admin_fee' => 8000]] as $meta) {
                $data = $this->detail(['wc_order' => ['meta' => $meta, 'fees' => [['name' => 'Admin Fee', 'total' => 1700]]]], $mode);
                $this->assertEquals($meta ? 1700 : 0, $data['transaction']['shipment']['costs']['adminFee']);
            }
        }
    }

    public function test_only_unavailable_native_collection_uses_persisted_order_amount(): void {
        foreach (['detail', 'fallback'] as $mode) {
            $data = $this->detail(['shipping_info' => json_encode(['_kiriof_instant_admin_fee' => 9999]), 'wc_order' => ['fees_unavailable' => true, 'meta' => ['_kiriof_instant_admin_fee' => 1800]]], $mode);
            $this->assertEquals(1800, $data['transaction']['shipment']['costs']['adminFee']);
            $data = $this->detail(['shipping_info' => json_encode(['_kiriof_instant_admin_fee' => 9999]), 'wc_order' => ['fees_unavailable' => true]], $mode);
            $this->assertEquals(0, $data['transaction']['shipment']['costs']['adminFee']);
        }
    }

    public function test_orderless_snapshot_included_once_with_discount_and_carrier_total_preserved(): void {
        foreach (['express', 'instant'] as $type) {
            foreach (['detail', 'fallback'] as $mode) {
                $data = $this->detail(['delivery_type' => $type, 'discount_amount' => 2000, 'shipping_info' => json_encode(['_kiriof_instant_admin_fee' => 1500])], $mode);
                $costs = $data['transaction']['shipment']['costs'];
                $this->assertEquals(1500, $costs['adminFee']);
                $this->assertEquals(12500, $costs['totalShipping']);
                $this->assertEquals(12000, $costs['orderTotal']);
                $this->assertEquals(12000, $costs['total']);
            }
        }
    }

    public function test_malformed_or_unrelated_snapshot_never_infers_fee(): void {
        foreach (['detail', 'fallback'] as $mode) {
            foreach (['{broken', '{}', json_encode(['admin_fee' => 3000, '_kiriof_instant_shipping_total' => 99999]), json_encode(['_kiriof_instant_admin_fee' => -10]), json_encode(['_kiriof_instant_admin_fee' => true]), json_encode(['_kiriof_instant_admin_fee' => 'NaN'])] as $snapshot) {
                $data = $this->detail(['shipping_info' => $snapshot], $mode);
                $this->assertEquals(0, $data['transaction']['shipment']['costs']['adminFee']);
            }
        }
    }
}
