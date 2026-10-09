<?php

declare(strict_types=1);

use KiriminAjaOfficial\Repositories\PaymentRepository;
use KiriminAjaOfficial\Services\ShipmentDetailAmounts;
use KiriminAjaOfficial\Services\ShipmentDetailPayment;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

require_once PLUGIN_DIR . '/vendor/autoload.php';

if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}

// Only the WooCommerce read contracts used by the real amount resolver. No WC
// boot, global class aliases, database or production constructor is needed.
interface ShipmentContractOrder {
    public function get_meta($key, $single = true);
    public function get_items($type);
}
interface ShipmentContractFee {
    public function get_meta($key, $single = true);
    public function get_total();
    public function get_name();
}

final class ShipmentDetailContractsTest extends TestCase {
    use MockeryPHPUnitIntegration;

    private const EMPTY_PAYMENT = ['id' => '', 'status' => '', 'method' => ''];

    private function fee($amount, string $type = 'instant_admin_fee', string $name = 'Renamed'): ShipmentContractFee {
        $fee = Mockery::mock(ShipmentContractFee::class);
        $fee->shouldReceive('get_meta')->once()->with('_kiriof_fee_type', true)->andReturn($type);
        $fee->shouldReceive('get_total')->once()->withNoArgs()->andReturn($amount);
        if ($type === '') {
            $fee->shouldReceive('get_name')->once()->withNoArgs()->andReturn($name);
        } else {
            $fee->shouldReceive('get_name')->never();
        }
        return $fee;
    }

    public function test_native_charges_and_provenance_control_amounts_without_resurrecting_removed_fees(): void {
        $snapshot = (object) ['shipping_info' => ['_kiriof_instant_admin_fee' => 9999]];
        $cases = [
            // Tagged charge beats stale metadata, legacy labels and unrelated fees.
            [8000, [$this->fee(1500), $this->fee(500, 'insurance'), $this->fee(600, '', 'Admin Fee'), $this->fee(100, 'cod_fee')], 1500.0],
            [8000, [], 0.0],
            [8000, [$this->fee(0)], 0.0],
            [8000, [$this->fee(-50)], 0.0],
            [8000, [$this->fee(123, '', 'Other admin fee')], 0.0],
            [8000, [$this->fee(1700, '', 'Admin Fee')], 1700.0],
        ];
        foreach ([true, 'NaN', INF, NAN, '1e999'] as $invalid) {
            $cases[] = [8000, [$this->fee($invalid)], 0.0];
        }
        foreach ($cases as [$persisted, $fees, $expected]) {
            $order = Mockery::mock(ShipmentContractOrder::class);
            $order->shouldReceive('get_meta')->once()->with('_kiriof_instant_admin_fee', true)->andReturn($persisted);
            $order->shouldReceive('get_items')->once()->with('fee')->andReturn($fees);
            $this->assertSame($expected, ShipmentDetailAmounts::adminFee($order, $snapshot));
        }
        // An exact historical label alone is not plugin provenance.
        $fee = Mockery::mock(ShipmentContractFee::class);
        $fee->shouldReceive('get_meta')->once()->with('_kiriof_fee_type', true)->andReturn('');
        $fee->shouldReceive('get_total')->once()->withNoArgs()->andReturn(1700);
        $fee->shouldReceive('get_name')->never();
        $order = Mockery::mock(ShipmentContractOrder::class);
        $order->shouldReceive('get_meta')->once()->with('_kiriof_instant_admin_fee', true)->andReturn('');
        $order->shouldReceive('get_items')->once()->with('fee')->andReturn([$fee]);
        $this->assertSame(0.0, ShipmentDetailAmounts::adminFee($order, $snapshot));

        foreach ([1800 => 1800.0, '' => 0.0] as $persisted => $expected) {
            $order = Mockery::mock(ShipmentContractOrder::class);
            $order->shouldReceive('get_meta')->once()->with('_kiriof_instant_admin_fee', true)->andReturn($persisted);
            $order->shouldReceive('get_items')->once()->with('fee')->andThrow(new RuntimeException('Unavailable'));
            $this->assertSame($expected, ShipmentDetailAmounts::adminFee($order, $snapshot));
        }
        foreach ([1500, -10, true, 'NaN', INF, NAN, '1e999'] as $amount) {
            $this->assertSame($amount === 1500 ? 1500.0 : 0.0, ShipmentDetailAmounts::adminFee(false, (object) ['shipping_info' => ['_kiriof_instant_admin_fee' => $amount]]));
        }
        foreach (['{broken', '{}', '{"admin_fee":3000,"_kiriof_instant_shipping_total":99999}'] as $info) {
            $this->assertSame(0.0, ShipmentDetailAmounts::adminFee(null, (object) ['shipping_info' => $info]));
        }
    }

    public function test_express_requires_matching_carrier_group_and_fails_open_or_skips_empty_groups(): void {
        $row = (object) ['delivery_type' => 'express', 'service' => 'jne', 'pickup_number' => 'GROUP-1', 'instant_payment_id' => 'IN-WRONG', 'payment_id' => 'WC-WRONG'];
        foreach ([(object) ['pickup_number' => 'GROUP-1', 'status' => 'unpaid', 'method' => 'bank_transfer'], false, [], (object) ['pickup_number' => 'OTHER', 'status' => 'paid'], new RuntimeException('secret credentials')] as $index => $result) {
            // Mocking the real class without constructor arguments bypasses its wpdb constructor.
            $repository = Mockery::mock(PaymentRepository::class);
            $lookup = $repository->shouldReceive('getPaymentByPaymentId')->once()->with('GROUP-1');
            if ($result instanceof Throwable) {
                $lookup->andThrow($result);
            } else {
                $lookup->andReturn($result);
            }
            $this->assertSame($index === 0 ? ['id' => 'GROUP-1', 'status' => 'unpaid', 'method' => 'bank_transfer'] : self::EMPTY_PAYMENT, ShipmentDetailPayment::forTransaction($row, $repository));
        }
        foreach (['', '-', '   ', ' - '] as $pickup) {
            $repository = Mockery::mock(PaymentRepository::class);
            $repository->shouldReceive('getPaymentByPaymentId')->never();
            $this->assertSame(self::EMPTY_PAYMENT, ShipmentDetailPayment::forTransaction((object) ['pickup_number' => $pickup], $repository));
        }
    }

    public function test_instant_uses_exact_persisted_evidence_and_never_queries_even_for_legacy_rows(): void {
        foreach ([['delivery_type' => 'instant', 'service' => 'jne'], ['delivery_type' => 'express', 'service' => 'GoSend']] as $classification) {
            foreach ([['instant_payment_id' => 'IN-1', 'instant_payment_status' => 'pending', 'instant_payment_method' => 'KA Credit'], []] as $evidence) {
                $repository = Mockery::mock(PaymentRepository::class);
                $repository->shouldReceive('getPaymentByPaymentId')->never();
                $row = (object) array_merge($classification, ['pickup_number' => 'WRONG', 'payment_id' => 'WC-WRONG'], $evidence);
                $this->assertSame($evidence ? ['id' => 'IN-1', 'status' => 'pending', 'method' => 'KA Credit'] : self::EMPTY_PAYMENT, ShipmentDetailPayment::forTransaction($row, $repository));
            }
        }
    }

    public function test_fallback_preserves_exact_formula_actual_charge_and_indirect_payment_evidence(): void {
        // One integration contract remains: the formula is outside the helpers.
        // Normal-path rendering, Express/Instant parity and metabox presentation
        // are exercised by tests/e2e/tests/shipment-detail.e2e.ts.
        $payload = ['mode' => 'fallback', 'delivery_type' => 'instant', 'service' => 'gosend', 'shipping_cost' => 12000, 'insurance_cost' => 200, 'cod_fee' => 300, 'discount_amount' => 2000,
            'instant_payment_id' => 'IN-1', 'instant_payment_status' => 'pending', 'instant_payment_method' => 'qris',
            'shipping_info' => json_encode(['_kiriof_instant_admin_fee' => 9999]),
            'wc_order' => ['paid' => true, 'meta' => ['_kiriof_instant_admin_fee' => 8000], 'fees' => [['type' => 'instant_admin_fee', 'total' => 1500]]]];
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/shipment-detail-amounts-runtime.php') . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR)));
        $shipment = json_decode($output, true, 512, JSON_THROW_ON_ERROR)['transaction']['shipment'];
        $this->assertSame([62000, 12500, 12000, 0, 12000, 200, 300, 1500, 14000], array_map(static fn($key) => $shipment['costs'][$key], ['orderTotal', 'totalShipping', 'actualShipping', 'shippingDiscount', 'shipping', 'insurance', 'codFee', 'adminFee', 'total']));
        $this->assertSame(['IN-1', 'pending', 'qris', 'Paid'], array_map(static fn($key) => $shipment[$key], ['paymentId', 'paymentStatus', 'paymentMethod', 'buyerPaymentStatus']));
    }
}
