<?php

use PHPUnit\Framework\TestCase;

final class OrderMetaboxShippingRuntimeTest extends TestCase {
    private function render(array $payload = []): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/order-metabox-shipping-runtime.php') . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR)));
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_native_admin_fee_and_single_shipping_row_match_wc_order_on_both_screens(): void {
        foreach ([false, true] as $legacy) {
            $html = $this->render(['legacy' => $legacy])['html'];
            $this->assertSame(1, substr_count($html, '<td>Shipping</td>'));
            $this->assertStringNotContainsString('Total Shipping', $html);
            $this->assertStringNotContainsString('kiriof-mb-row-child', $html);
            $this->assertMatchesRegularExpression('/<td>Shipping<\/td>\s*<td>Rp11\.000<\/td>/', $html);
            $this->assertMatchesRegularExpression('/<td>Admin Fee<\/td>\s*<td>Rp1\.000<\/td>/', $html);
            $this->assertMatchesRegularExpression('/<td>Total<\/td>\s*<td>Rp32\.000<\/td>/', $html);
            $this->assertLessThan(strpos($html, '<td>Total</td>'), strpos($html, '<td>Admin Fee</td>'));
        }
    }

    public function test_removed_native_fee_does_not_resurrect_snapshot_or_unrelated_fee(): void {
        $html = $this->render(['row' => ['shipping_info' => '{"_kiriof_instant_admin_fee":9000}'], 'wc_order' => ['meta' => ['_kiriof_instant_admin_fee' => 8000], 'fees' => [['type' => 'cod_fee', 'total' => 500]]]])['html'];
        $this->assertStringNotContainsString('<td>Admin Fee</td>', $html);
    }

    public function test_breakdown_and_both_shipping_discounts_are_retained(): void {
        $html = $this->render(['row' => ['delivery_type' => 'express', 'service' => 'jne', 'insurance_cost' => 500, 'discount_amount' => 1000], 'wc_order' => ['shipping' => 8000, 'coupons' => ['ITEM', 'SHIP'], 'discount' => 500]])['html'];
        $this->assertStringContainsString('<td>Total Shipping</td>', $html);
        $this->assertStringContainsString('Rp11.500', $html);
        $this->assertStringContainsString('<td>Insurance</td>', $html);
        $this->assertStringContainsString('ITEM', $html);
        $this->assertStringContainsString('SHIP', $html);
        $this->assertStringContainsString('Shipping Discount (from KiriminAja)', $html);
        $this->assertStringContainsString('Rp8.000', $html);
        $different = $this->render(['wc_order' => ['shipping' => 9000]])['html'];
        $this->assertStringContainsString('<td>Total Shipping</td>', $different);
        $this->assertStringContainsString('kiriof-mb-row-child', $different);
    }

    public function test_instant_vehicle_replaces_cod_and_wc_payment_never_marks_carrier_paid(): void {
        $html = $this->render(['row' => ['cod_fee' => 500]])['html'];
        $this->assertSame(1, substr_count($html, '>Motor</span>'));
        $this->assertStringNotContainsString('kiriof-mb-badge--cod', $html);
        $this->assertStringNotContainsString('kiriof-mb-badge--paid', $html);
        $this->assertMatchesRegularExpression('/<td>Payment status<\/td>\s*<td>—<\/td>/', $html);
        $this->assertMatchesRegularExpression('/<td>Buyer Payment Status<\/td>\s*<td>Paid<\/td>/', $html);
        $html = $this->render(['row' => ['instant_payment_id' => 'PAY-123', 'instant_payment_status' => 'unpaid', 'instant_payment_method' => 'qris']])['html'];
        $this->assertStringContainsString('PAY-123', $html);
        $this->assertStringContainsString('kiriof-mb-badge--unpaid', $html);
        $this->assertStringNotContainsString('kiriof-mb-badge--paid', $html);
    }

    public function test_express_uses_actual_matching_carrier_payment_group_only(): void {
        $payload = ['row' => ['delivery_type' => 'express', 'service' => 'jne', 'pickup_number' => 'EXP-PAY'], 'carrier_payment' => ['pickup_number' => 'EXP-PAY', 'status' => 'paid', 'method' => 'qris']];
        $result = $this->render($payload);
        $this->assertSame(['EXP-PAY'], $result['payment_lookup']);
        $this->assertStringContainsString('kiriof-mb-badge--paid', $result['html']);
        $this->assertStringContainsString('<td>EXP-PAY</td>', $result['html']);
        $payload['carrier_payment']['pickup_number'] = 'OTHER';
        $html = $this->render($payload)['html'];
        $this->assertStringNotContainsString('kiriof-mb-badge--paid', $html);
        $this->assertStringNotContainsString('<td>OTHER</td>', $html);
        unset($payload['carrier_payment']);
        $this->assertStringNotContainsString('kiriof-mb-badge--paid', $this->render($payload)['html']);
    }
    public function test_instant_buyer_coupon_is_not_a_carrier_discount_even_after_scope_removal(): void {
        foreach ([['SHIP'], ['HISTORICAL'], []] as $codes) {
            foreach ([false, true] as $legacy) {
                $html = $this->render(['legacy' => $legacy, 'row' => ['shipping_cost' => 18000, 'discount_amount' => 4500], 'wc_order' => ['shipping' => 13500, 'total' => 34500, 'coupons' => $codes, 'fees' => [['type' => 'instant_admin_fee', 'total' => 1000]]]])['html'];
                $this->assertStringNotContainsString('Shipping Discount (from KiriminAja)', $html);
                $this->assertMatchesRegularExpression('/Shipping Discount.*?<\/td>\s*<td[^>]*>-Rp4\.500<\/td>/s', $html);
                $this->assertMatchesRegularExpression('/<td>Discounted Shipping<\/td>\s*<td>Rp13\.500<\/td>/', $html);
                $this->assertMatchesRegularExpression('/<td>Admin Fee<\/td>\s*<td>Rp1\.000<\/td>/', $html);
                $this->assertMatchesRegularExpression('/<td>Total<\/td>\s*<td>Rp34\.500<\/td>/', $html);
                $this->assertSame(in_array('SHIP', $codes, true) ? 1 : 0, substr_count($html, 'kiriof-mb-coupon-chip'));
            }
        }
    }

    public function test_fallback_uses_wc_shipping_when_carrier_rate_has_changed(): void {
        $result = $this->render(['mode' => 'fallback', 'row' => ['shipping_cost' => 20000, 'discount_amount' => 4500], 'wc_order' => ['shipping' => 13500, 'total' => 34500, 'fees' => [['type' => 'instant_admin_fee', 'total' => 1000]]]]);
        $costs = $result['transaction']['shipment']['costs'];
        $this->assertSame(13500, $costs['shipping']);
        $this->assertSame(6500, $costs['shippingDiscount']);
        $this->assertSame(14500, $costs['total']);
        $this->assertSame(34500, $costs['orderTotal']);
    }
    public function test_orderless_instant_uses_persisted_buyer_discount_without_inventing_a_coupon(): void {
        $html = $this->render(['legacy' => true, 'no_order' => true, 'row' => ['shipping_cost' => 18000, 'discount_amount' => 4500]])['html'];
        $this->assertStringNotContainsString('Shipping Discount (from KiriminAja)', $html);
        $this->assertMatchesRegularExpression('/<td>\s*Shipping Discount\s*<\/td>\s*<td[^>]*>-Rp4\.500<\/td>/s', $html);
        $this->assertMatchesRegularExpression('/<td>Discounted Shipping<\/td>\s*<td>Rp13\.500<\/td>/', $html);
        $costs = $this->render(['mode' => 'fallback', 'no_order' => true, 'row' => ['shipping_cost' => 18000, 'discount_amount' => 4500]])['transaction']['shipment']['costs'];
        $this->assertSame(13500, $costs['shipping']);
        $this->assertSame(4500, $costs['shippingDiscount']);
    }
}
