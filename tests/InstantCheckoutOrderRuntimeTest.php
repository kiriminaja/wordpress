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
        $this->assertSame([], $r['locks']);
        $this->assertSame([20, 2], $r['hooks']['woocommerce_store_api_checkout_update_order_from_request']);
    }

    #[Test]
    public function final_checkout_rejects_changed_or_unsupported_context_without_remote_calls(): void {
        foreach (['price', 'fraction', 'pin', 'address', 'phone', 'service', 'expiry', 'cart', 'origin', 'disabled', 'zone', 'instance', 'cod', 'mixed', 'packages'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertNotEmpty($r['error'], $scenario);
            $this->assertSame([], $r['rows'], $scenario);
            $this->assertSame(1, $r['calls'], $scenario);
            $this->assertStringNotContainsString('secret', $r['error']);
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
    }
}
