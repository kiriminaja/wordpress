<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantCheckoutOrderRuntimeTest extends TestCase {
    private function fixture(string $scenario = ''): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/instant-checkout-order-runtime.php') . ' ' . escapeshellarg('{}') . ' ' . escapeshellarg($scenario));
        return json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function validated_quote_creates_one_unbooked_instant_transaction(): void {
        $r = $this->fixture();
        $this->assertSame('', $r['error']);
        $this->assertSame('', $r['processed_error']);
        $this->assertCount(1, $r['rows']);
        $row = $r['rows'][0];
        $this->assertSame('instant', $row['delivery_type']);
        $this->assertSame('new', $row['status']);
        $this->assertSame('motor', $row['vehicle']);
        $this->assertSame('gosend', $row['service']);
        $this->assertSame('GO-INSTANT', $row['service_name']);
        $this->assertSame(18000, $row['shipping_cost']);
        $this->assertSame(0, $row['insurance_cost']);
        $this->assertSame(0, $row['cod_fee']);
        $this->assertSame(100000, $row['transaction_value']);
        $this->assertSame('-6.3', $row['destination_latitude']);
        $this->assertSame('Merchant Full Name', json_decode($row['shipment_location_snapshot'], true)['origin_name']);
        $this->assertSame('Buyer', json_decode($row['shipping_info'], true)['_shipping_first_name']);
        $this->assertArrayHasKey('_kiriof_instant_checkout_snapshot', $r['meta']);
        $this->assertSame(1, $r['calls']);
        $this->assertSame(1, $r['invoice_calls']);
        $this->assertSame('KiriminAjaOfficial\\Services\\InstantCheckoutQuoteService', $r['production_quote_service']);
        $this->assertSame(['latitude' => '-6.3', 'longitude' => '106.9'], $r['meta']['_kiriof_buyer_destination_coordinates']);
        $this->assertSame([], $r['locks']);
        $this->assertSame([20, 2], $r['hooks']['woocommerce_store_api_checkout_update_order_from_request']);
    }

    #[Test]
    public function final_checkout_rejects_changed_or_unsupported_context_without_remote_calls(): void {
        foreach (['price', 'fraction', 'pin', 'address', 'phone', 'service', 'expiry', 'cart', 'origin', 'disabled', 'zone', 'instance', 'cod', 'mixed', 'packages', 'missing_rates', 'rate_vehicle', 'private_error'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertNotEmpty($r['error'], $scenario);
            $this->assertSame([], $r['rows'], $scenario);
            $this->assertSame(1, $r['calls'], $scenario);
            $this->assertStringNotContainsString('secret', $r['error']);
            $this->assertSame(400, $r['error_status'], $scenario);
            $this->assertStringNotContainsString('secret', json_encode($r['logs']));
            $token = $r['meta']['_kiriof_instant_checkout_snapshot']['rate']['quote_token'] ?? '';
            if ($token !== '') { $this->assertStringNotContainsString($token, json_encode($r['logs'])); }
            $this->assertSame('kiriminaja_instant', $r['logs'][0]['source']);
        }
    }

    #[Test]
    public function express_is_not_intercepted_and_classic_uses_the_same_contract(): void {
        $r = $this->fixture('express');
        $this->assertSame('', $r['error']);
        $this->assertSame([], $r['rows']);
        $this->assertSame([], $r['meta']);
        $r = $this->fixture('classic');
        $this->assertSame('', $r['error']);
        $this->assertCount(1, $r['rows']);
    }

    #[Test]
    public function failed_insert_is_visible_and_durable_snapshot_allows_retry_without_session(): void {
        $r = $this->fixture('insert');
        $this->assertNotEmpty($r['processed_error']);
        $this->assertCount(1, $r['rows']);
        $this->assertSame([], $r['locks']);
        $this->assertSame(1, $r['calls']);
        $this->assertArrayHasKey('_kiriof_instant_checkout_snapshot', $r['meta']);
        $this->assertSame(1, $r['invoice_calls']);
    }

    #[Test]
    public function processing_retries_use_durable_selection_and_recover_expired_locks(): void {
        foreach (['processed_expiry', 'stale_lock'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertSame('', $r['error'], $scenario);
            $this->assertSame('', $r['processed_error'], $scenario);
            $this->assertCount(1, $r['rows'], $scenario);
            $this->assertSame(1, $r['invoice_calls'], $scenario);
            $this->assertSame([], $r['locks'], $scenario);
        }
        foreach (['snapshot_edit', 'busy_lock', 'conflict'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertSame('', $r['error'], $scenario);
            $this->assertNotEmpty($r['processed_error'], $scenario);
            $this->assertCount($scenario === 'conflict' ? 1 : 0, $r['rows'], $scenario);
            $this->assertSame($scenario === 'snapshot_edit' ? 'snapshot_changed' : ($scenario === 'busy_lock' ? 'checkout_busy' : 'transaction_conflict'), $r['logs'][0]['context']['code']);
        }
    }
    #[Test]
    public function customer_total_and_admin_fee_are_pinned_without_double_charging(): void {
        foreach (['example_total', 'insurance', 'session_insurance', 'opaque_price_meta'] as $scenario) {
            $r = $this->fixture($scenario);
            $raw = $scenario === 'example_total' ? 54000 : 18000;
            $this->assertSame('', $r['error'], $scenario);
            $this->assertSame('', $r['processed_error'], $scenario);
            $this->assertCount(1, $r['rows']);
            $this->assertSame($raw + 1000, $r['shipping_total']);
            $this->assertSame($raw, $r['rows'][0]['shipping_cost']);
            $this->assertSame($raw + 1000, $r['meta']['_kiriof_instant_customer_shipping_total']);
            $this->assertSame(1000, $r['meta']['_kiriof_instant_admin_fee']);
            $rate = $r['meta']['_kiriof_instant_checkout_snapshot']['rate'];
            $this->assertSame($raw, $rate['shipping_costs']);
            $this->assertSame($raw + 1000, $rate['total_price']);
            $shipping = json_decode($r['rows'][0]['shipping_info'], true);
            $this->assertSame($raw, $shipping['_kiriof_instant_shipping_cost']);
            $this->assertSame($raw + 1000, $shipping['_kiriof_instant_shipping_total']);
            $this->assertSame(1000, $shipping['_kiriof_instant_admin_fee']);
            $this->assertSame([], $r['fee_lines']);
            $this->assertSame(0, $r['rows'][0]['insurance_cost']);
            $this->assertSame(0, $r['rows'][0]['cod_fee']);
        }
    }

    #[Test]
    public function durable_breakdown_and_receipt_tampering_cannot_create_transactions(): void {
        foreach (['snapshot_fee_edit', 'receipt_fee_edit', 'receipt_total_edit'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertSame('', $r['error'], $scenario);
            $this->assertNotEmpty($r['processed_error'], $scenario);
            $this->assertSame([], $r['rows'], $scenario);
            $this->assertSame(1, $r['calls'], $scenario);
        }
    }

}

