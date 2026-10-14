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
    public function pre_discount_receipts_replay_without_mutation_or_pricing_session(): void {
        $r = $this->fixture('legacy_valid');
        $this->assertSame(
            [
                'error' => '',
                'processed_error' => '',
                'count(r.rows)' => 1,
                'calls' => 1,
                'invoice_calls' => 1,
                'absent fields: r.meta._kiriof_instant_checkout_snapshot, .customer_pricing\' => true' => [],
                'absent fields: r.meta, ._kiriof_instant_customer_shipping_cost\' => true' => [],
                'meta._kiriof_instant_customer_shipping_total' => 19000,
            ],
            [
                'error' => $r['error'],
                'processed_error' => $r['processed_error'],
                'count(r.rows)' => count($r['rows']),
                'calls' => $r['calls'],
                'invoice_calls' => $r['invoice_calls'],
                'absent fields: r.meta._kiriof_instant_checkout_snapshot, .customer_pricing\' => true' => array_intersect_key($r['meta']['_kiriof_instant_checkout_snapshot'], ['customer_pricing' => true]),
                'absent fields: r.meta, ._kiriof_instant_customer_shipping_cost\' => true' => array_intersect_key($r['meta'], ['_kiriof_instant_customer_shipping_cost' => true]),
                'meta._kiriof_instant_customer_shipping_total' => $r['meta']['_kiriof_instant_customer_shipping_total'],
            ],
            __FUNCTION__
        );
        $shipping = json_decode($r['rows'][0]['shipping_info'], true);
        $this->assertArrayNotHasKey('_kiriof_instant_customer_shipping_cost', $shipping);
        $this->assertEquals(0, $r['rows'][0]['discount_amount']);
        foreach (['new_missing_pricing', 'legacy_wrong_cost', 'legacy_wrong_discount', 'legacy_empty_meta'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertSame('', $r['error'], $scenario);
            $this->assertNotEmpty($r['processed_error'], $scenario);
            $this->assertSame(
                [
                    'rows' => [],
                    'invoice_calls' => 0,
                ],
                [
                    'rows' => $r['rows'],
                    'invoice_calls' => $r['invoice_calls'],
                ],
                $scenario
            );
        }
    }

    #[Test]
    public function fractional_percentage_matches_wc_precision_and_keeps_raw_quote(): void {
        $r = $this->fixture('coupon_percent_fractional');
        $this->assertSame(
            [
                'error' => '',
                'processed_error' => '',
                'shipping_total' => 15661,
            ],
            [
                'error' => $r['error'],
                'processed_error' => $r['processed_error'],
                'shipping_total' => $r['shipping_total'],
            ],
            __FUNCTION__
        );
        $this->assertEquals(2340, $r['rows'][0]['discount_amount']);
        $this->assertSame(
            [
                'rows.0.shipping_cost' => 18001,
                'meta._kiriof_instant_customer_shipping_total' => 16661,
                'meta._kiriof_instant_checkout_snapshot.rate.total_price' => 19001,
                'calls' => 1,
                'invoice_calls' => 1,
                'count(r.cart_fees)' => 1,
            ],
            [
                'rows.0.shipping_cost' => $r['rows'][0]['shipping_cost'],
                'meta._kiriof_instant_customer_shipping_total' => $r['meta']['_kiriof_instant_customer_shipping_total'],
                'meta._kiriof_instant_checkout_snapshot.rate.total_price' => $r['meta']['_kiriof_instant_checkout_snapshot']['rate']['total_price'],
                'calls' => $r['calls'],
                'invoice_calls' => $r['invoice_calls'],
                'count(r.cart_fees)' => count($r['cart_fees']),
            ],
            __FUNCTION__
        );
        $r = $this->fixture('coupon_percent_decimal');
        $this->assertSame(
            [
                'error' => '',
                'processed_error' => '',
                'shipping_total' => 8250.75,
                'rows.0.discount_amount' => 2750.25,
                'rows.0.shipping_cost' => 11001,
                'meta._kiriof_instant_customer_shipping_total' => 9250.75,
                'calls' => 1,
            ],
            [
                'error' => $r['error'],
                'processed_error' => $r['processed_error'],
                'shipping_total' => $r['shipping_total'],
                'rows.0.discount_amount' => $r['rows'][0]['discount_amount'],
                'rows.0.shipping_cost' => $r['rows'][0]['shipping_cost'],
                'meta._kiriof_instant_customer_shipping_total' => $r['meta']['_kiriof_instant_customer_shipping_total'],
                'calls' => $r['calls'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function shipping_coupons_keep_raw_booking_cost_and_bind_durable_buyer_charge(): void {
        foreach (['fixed' => 5000, 'percent' => 4500, 'free' => 18000] as $kind => $discount) {
            $r = $this->fixture('coupon_' . $kind);
            $this->assertSame(
                [
                    'error' => '',
                    'processed_error' => '',
                    'rows.0.shipping_cost' => 18000,
                ],
                [
                    'error' => $r['error'],
                    'processed_error' => $r['processed_error'],
                    'rows.0.shipping_cost' => $r['rows'][0]['shipping_cost'],
                ],
                __FUNCTION__
            );
            $this->assertEquals($discount, $r['rows'][0]['discount_amount']);
            $this->assertEquals(18000 - $discount, $r['meta']['_kiriof_instant_customer_shipping_cost']);
            $this->assertEquals(19000 - $discount, $r['meta']['_kiriof_instant_customer_shipping_total']);
            $this->assertSame(
                [
                    'meta._kiriof_instant_checkout_snapshot.rate.total_price' => 19000,
                    'count(r.cart_fees)' => 1,
                    'calls' => 1,
                ],
                [
                    'meta._kiriof_instant_checkout_snapshot.rate.total_price' => $r['meta']['_kiriof_instant_checkout_snapshot']['rate']['total_price'],
                    'count(r.cart_fees)' => count($r['cart_fees']),
                    'calls' => $r['calls'],
                ],
                __FUNCTION__
            );
        }
        foreach (['coupon_removed', 'coupon_tamper'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertNotEmpty($r['error']);
            $this->assertSame(
                [
                    'rows' => [],
                    'cart_fees' => [],
                ],
                [
                    'rows' => $r['rows'],
                    'cart_fees' => $r['cart_fees'],
                ],
                __FUNCTION__
            );
        }
        $r = $this->fixture('coupon_durable_tamper');
        $this->assertNotEmpty($r['processed_error']);
        $this->assertSame([], $r['rows']);
    }

    #[Test]
    public function classic_inherited_phone_is_bound_to_fees_snapshot_and_durable_replay(): void {
        $r = $this->fixture('classic_billing_phone');
        $this->assertSame(
            [
                'error' => '',
                'processed_error' => '',
                'order_address.phone' => '081234567890',
                'order_address.first_name' => 'Buyer',
                'count(r.cart_fees)' => 1,
                'count(r.rows)' => 1,
                'json_decode(r.rows.0.shipping_info, true)._shipping_phone' => '081234567890',
                'calls' => 1,
            ],
            [
                'error' => $r['error'],
                'processed_error' => $r['processed_error'],
                'order_address.phone' => $r['order_address']['phone'],
                'order_address.first_name' => $r['order_address']['first_name'],
                'count(r.cart_fees)' => count($r['cart_fees']),
                'count(r.rows)' => count($r['rows']),
                'json_decode(r.rows.0.shipping_info, true)._shipping_phone' => json_decode($r['rows'][0]['shipping_info'], true)['_shipping_phone'],
                'calls' => $r['calls'],
            ],
            __FUNCTION__
        );
        foreach (['classic_billing_phone_changed_name', 'blocks_empty_phone'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertNotEmpty($r['error'], $scenario);
            $this->assertSame([], $r['rows']);
        }
    }

    #[Test]
    public function validated_quote_creates_one_unbooked_instant_transaction(): void {
        $r = $this->fixture();
        $this->assertSame(
            [
                'error' => '',
                'processed_error' => '',
                'count(r.rows)' => 1,
            ],
            [
                'error' => $r['error'],
                'processed_error' => $r['processed_error'],
                'count(r.rows)' => count($r['rows']),
            ],
            __FUNCTION__
        );
        $row = $r['rows'][0];
        $this->assertSame(
            [
                'delivery_type' => 'instant',
                'status' => 'new',
                'vehicle' => 'motor',
                'service' => 'gosend',
                'service_name' => 'GO-INSTANT',
                'shipping_cost' => 18000,
                'insurance_cost' => 0,
                'cod_fee' => 0,
                'transaction_value' => 100000,
                'destination_latitude' => '-6.3',
                'json_decode(row.shipment_location_snapshot, true).origin_name' => 'Merchant Full Name',
                'json_decode(row.shipping_info, true)._shipping_first_name' => 'Buyer',
                'present fields: r.meta, ._kiriof_instant_checkout_snapshot\' => true' => ['_kiriof_instant_checkout_snapshot'],
                'calls' => 1,
                'invoice_calls' => 1,
                'production_quote_service' => 'KiriminAjaOfficial\\Services\\InstantCheckoutQuoteService',
                'meta._kiriof_buyer_destination_coordinates' => ['latitude' => '-6.3', 'longitude' => '106.9'],
                'locks' => [],
                'hooks.woocommerce_store_api_checkout_update_order_from_request' => [20, 2],
            ],
            [
                'delivery_type' => $row['delivery_type'],
                'status' => $row['status'],
                'vehicle' => $row['vehicle'],
                'service' => $row['service'],
                'service_name' => $row['service_name'],
                'shipping_cost' => $row['shipping_cost'],
                'insurance_cost' => $row['insurance_cost'],
                'cod_fee' => $row['cod_fee'],
                'transaction_value' => $row['transaction_value'],
                'destination_latitude' => $row['destination_latitude'],
                'json_decode(row.shipment_location_snapshot, true).origin_name' => json_decode($row['shipment_location_snapshot'], true)['origin_name'],
                'json_decode(row.shipping_info, true)._shipping_first_name' => json_decode($row['shipping_info'], true)['_shipping_first_name'],
                'present fields: r.meta, ._kiriof_instant_checkout_snapshot\' => true' => array_keys(array_intersect_key($r['meta'], ['_kiriof_instant_checkout_snapshot' => true])),
                'calls' => $r['calls'],
                'invoice_calls' => $r['invoice_calls'],
                'production_quote_service' => $r['production_quote_service'],
                'meta._kiriof_buyer_destination_coordinates' => $r['meta']['_kiriof_buyer_destination_coordinates'],
                'locks' => $r['locks'],
                'hooks.woocommerce_store_api_checkout_update_order_from_request' => $r['hooks']['woocommerce_store_api_checkout_update_order_from_request'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function final_checkout_rejects_changed_or_unsupported_context_without_remote_calls(): void {
        foreach (['currency', 'taxed_shipping', 'price', 'fraction', 'pin', 'address', 'phone', 'service', 'expiry', 'cart', 'origin', 'disabled', 'zone', 'instance', 'cod', 'mixed', 'packages', 'missing_rates', 'rate_vehicle', 'private_error', 'missing_fee', 'duplicate_fee', 'renamed_duplicate_fee', 'tampered_fee', 'taxed_fee', 'untagged_fee', 'wrong_selection', 'missing_selection'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertNotEmpty($r['error'], $scenario);
            $this->assertSame(
                [
                    'rows' => [],
                    'calls' => 1,
                    'redaction: r.error, \'secret\'' => 0,
                    'error_status' => 400,
                    'redaction: json_encode(r.logs), \'secret\'' => 0,
                ],
                [
                    'rows' => $r['rows'],
                    'calls' => $r['calls'],
                    'redaction: r.error, \'secret\'' => substr_count($r['error'], 'secret'),
                    'error_status' => $r['error_status'],
                    'redaction: json_encode(r.logs), \'secret\'' => substr_count(json_encode($r['logs']), 'secret'),
                ],
                $scenario
            );
            $token = $r['meta']['_kiriof_instant_checkout_snapshot']['rate']['quote_token'] ?? '';
            if ($token !== '') { $this->assertStringNotContainsString($token, json_encode($r['logs'])); }
            $this->assertSame('kiriminaja_instant', $r['logs'][0]['source']);
        }
    }

    #[Test]
    public function express_is_not_intercepted_and_classic_uses_the_same_contract(): void {
        $r = $this->fixture('express');
        $this->assertSame(
            [
                'error' => '',
                'rows' => [],
                'meta' => [],
            ],
            [
                'error' => $r['error'],
                'rows' => $r['rows'],
                'meta' => $r['meta'],
            ],
            __FUNCTION__
        );
        $r = $this->fixture('classic');
        $this->assertSame(
            [
                'error' => '',
                'count(r.rows)' => 1,
            ],
            [
                'error' => $r['error'],
                'count(r.rows)' => count($r['rows']),
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function classic_final_post_snapshot_must_match_the_validated_session_pin(): void {
        $r = $this->fixture('classic_snapshot');
        $this->assertSame(
            [
                'error' => '',
                'count(r.rows)' => 1,
            ],
            [
                'error' => $r['error'],
                'count(r.rows)' => count($r['rows']),
            ],
            __FUNCTION__
        );
        foreach (array('classic_clear','classic_tamper') as $scenario) {
            $r=$this->fixture($scenario);
            $this->assertNotEmpty($r['error']);
            $this->assertSame(array(),$r['rows']);
        }
    }

    #[Test]
    public function failed_insert_is_visible_and_durable_snapshot_allows_retry_without_session(): void {
        $r = $this->fixture('insert');
        $this->assertNotEmpty($r['processed_error']);
        $this->assertSame(
            [
                'count(r.rows)' => 1,
                'locks' => [],
                'calls' => 1,
                'present fields: r.meta, ._kiriof_instant_checkout_snapshot\' => true' => ['_kiriof_instant_checkout_snapshot'],
                'invoice_calls' => 1,
            ],
            [
                'count(r.rows)' => count($r['rows']),
                'locks' => $r['locks'],
                'calls' => $r['calls'],
                'present fields: r.meta, ._kiriof_instant_checkout_snapshot\' => true' => array_keys(array_intersect_key($r['meta'], ['_kiriof_instant_checkout_snapshot' => true])),
                'invoice_calls' => $r['invoice_calls'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function processing_retries_use_durable_selection_and_recover_expired_locks(): void {
        foreach (['processed_expiry', 'stale_lock'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertSame(
                [
                    'error' => '',
                    'processed_error' => '',
                    'count(r.rows)' => 1,
                    'invoice_calls' => 1,
                    'locks' => [],
                ],
                [
                    'error' => $r['error'],
                    'processed_error' => $r['processed_error'],
                    'count(r.rows)' => count($r['rows']),
                    'invoice_calls' => $r['invoice_calls'],
                    'locks' => $r['locks'],
                ],
                $scenario
            );
        }
        foreach (['snapshot_edit', 'busy_lock', 'conflict'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertSame('', $r['error'], $scenario);
            $this->assertNotEmpty($r['processed_error'], $scenario);
            $this->assertSame(
                [
                    'count(r.rows)' => $scenario === 'conflict' ? 1 : 0,
                    'logs.0.context.code' => $scenario === 'snapshot_edit' ? 'snapshot_changed' : ($scenario === 'busy_lock' ? 'checkout_busy' : 'transaction_conflict'),
                ],
                [
                    'count(r.rows)' => count($r['rows']),
                    'logs.0.context.code' => $r['logs'][0]['context']['code'],
                ],
                $scenario
            );
        }
    }
    #[Test]
    public function customer_total_and_admin_fee_are_pinned_without_double_charging(): void {
        foreach (['example_total', 'insurance', 'session_insurance', 'opaque_price_meta'] as $scenario) {
            $r = $this->fixture($scenario);
            $raw = $scenario === 'example_total' ? 54000 : 18000;
            $this->assertSame(
                [
                    'error' => '',
                    'processed_error' => '',
                    'count(r.rows)' => 1,
                    'shipping_total' => $raw,
                    'rows.0.shipping_cost' => $raw,
                    'meta._kiriof_instant_customer_shipping_total' => $raw + 1000,
                    'meta._kiriof_instant_admin_fee' => 1000,
                ],
                [
                    'error' => $r['error'],
                    'processed_error' => $r['processed_error'],
                    'count(r.rows)' => count($r['rows']),
                    'shipping_total' => $r['shipping_total'],
                    'rows.0.shipping_cost' => $r['rows'][0]['shipping_cost'],
                    'meta._kiriof_instant_customer_shipping_total' => $r['meta']['_kiriof_instant_customer_shipping_total'],
                    'meta._kiriof_instant_admin_fee' => $r['meta']['_kiriof_instant_admin_fee'],
                ],
                $scenario
            );
            $rate = $r['meta']['_kiriof_instant_checkout_snapshot']['rate'];
            $this->assertSame(
                [
                    'rate.shipping_costs' => $raw,
                    'rate.total_price' => $raw + 1000,
                ],
                [
                    'rate.shipping_costs' => $rate['shipping_costs'],
                    'rate.total_price' => $rate['total_price'],
                ],
                __FUNCTION__
            );
            $shipping = json_decode($r['rows'][0]['shipping_info'], true);
            $this->assertSame(
                [
                    '_kiriof_instant_shipping_cost' => $raw,
                    '_kiriof_instant_shipping_total' => $raw + 1000,
                    '_kiriof_instant_admin_fee' => 1000,
                    'count(r.fee_lines)' => 1,
                    'fee_lines.0.total' => 1000,
                    'fee_lines.0.meta._kiriof_fee_type' => 'instant_admin_fee',
                    'rows.0.insurance_cost' => 0,
                    'rows.0.cod_fee' => 0,
                ],
                [
                    '_kiriof_instant_shipping_cost' => $shipping['_kiriof_instant_shipping_cost'],
                    '_kiriof_instant_shipping_total' => $shipping['_kiriof_instant_shipping_total'],
                    '_kiriof_instant_admin_fee' => $shipping['_kiriof_instant_admin_fee'],
                    'count(r.fee_lines)' => count($r['fee_lines']),
                    'fee_lines.0.total' => $r['fee_lines'][0]['total'],
                    'fee_lines.0.meta._kiriof_fee_type' => $r['fee_lines'][0]['meta']['_kiriof_fee_type'],
                    'rows.0.insurance_cost' => $r['rows'][0]['insurance_cost'],
                    'rows.0.cod_fee' => $r['rows'][0]['cod_fee'],
                ],
                __FUNCTION__
            );
        }
    }

    #[Test]
    public function durable_breakdown_and_receipt_tampering_cannot_create_transactions(): void {
        foreach (['snapshot_fee_edit', 'receipt_fee_edit', 'receipt_total_edit', 'processed_fee_edit', 'processed_fee_missing'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertSame('', $r['error'], $scenario);
            $this->assertNotEmpty($r['processed_error'], $scenario);
            $this->assertSame(
                [
                    'rows' => [],
                    'calls' => 1,
                ],
                [
                    'rows' => $r['rows'],
                    'calls' => $r['calls'],
                ],
                $scenario
            );
        }
    }

    #[Test]
    public function durable_checkout_receipts_cannot_bypass_radius_after_session_expiry(): void {
        foreach (['durable_outside_radius', 'durable_missing_origin_pin', 'durable_malformed_origin_pin'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertSame('', $r['error'], $scenario);
            $this->assertNotEmpty($r['processed_error'], $scenario);
            $this->assertSame(
                [
                    'rows' => [],
                    'calls' => 1,
                    'invoice_calls' => 0,
                ],
                [
                    'rows' => $r['rows'],
                    'calls' => $r['calls'],
                    'invoice_calls' => $r['invoice_calls'],
                ],
                $scenario
            );
        }
    }

    #[Test]
    public function native_cart_fee_is_validated_exactly_once_and_zero_fee_does_not_clutter(): void {
        foreach (['', 'insurance', 'session_insurance', 'opaque_price_meta', 'example_total'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertSame(
                [
                    'count(r.cart_fees)' => 1,
                    'cart_fees.kiriof_instant_admin_fee' => ['id' => 'kiriof_instant_admin_fee', 'name' => 'Admin Fee', 'amount' => 1000, 'taxable' => false],
                    'calls' => 1,
                ],
                [
                    'count(r.cart_fees)' => count($r['cart_fees']),
                    'cart_fees.kiriof_instant_admin_fee' => $r['cart_fees']['kiriof_instant_admin_fee'],
                    'calls' => $r['calls'],
                ],
                $scenario
            );
        }
        foreach (['express', 'wrong_selection', 'missing_selection', 'missing_rates', 'rate_vehicle', 'expiry', 'cart', 'origin', 'disabled', 'cod', 'packages', 'zero_admin', 'zero_price'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertSame(
                [
                    'cart_fees' => [],
                    'calls' => 1,
                ],
                [
                    'cart_fees' => $r['cart_fees'],
                    'calls' => $r['calls'],
                ],
                $scenario
            );
            if (str_starts_with($scenario, 'zero_')) {
                $this->assertSame(
                    [
                        'error' => '',
                        'processed_error' => '',
                        'fee_lines' => [],
                        'count(r.rows)' => 1,
                    ],
                    [
                        'error' => $r['error'],
                        'processed_error' => $r['processed_error'],
                        'fee_lines' => $r['fee_lines'],
                        'count(r.rows)' => count($r['rows']),
                    ],
                    __FUNCTION__
                );
            }
        }
    }

    #[Test]
    public function reloaded_raw_packages_keep_the_selected_quote_fee_and_transaction_snapshot(): void {
        foreach (['reload_uniform_type' => 2, 'reload_mixed_type' => 7] as $scenario => $type) {
            $r = $this->fixture($scenario);
            $this->assertSame(
                [
                    'error' => '',
                    'processed_error' => '',
                    'production_quote_service' => 'KiriminAjaOfficial\\Services\\InstantCheckoutQuoteService',
                    'reload.quoted_package_type_id' => $type,
                    'reload.raw_package_has_type' => false,
                    'reload.product_types' => ['cart-key' => 2, 'other-key' => $type === 2 ? 2 : 3],
                    'reload.fresh_rate' => true,
                    'reload.fresh_session' => true,
                    'reload.selected_method' => 'kiriminaja-instant:4:gosend:GO-INSTANT',
                ],
                [
                    'error' => $r['error'],
                    'processed_error' => $r['processed_error'],
                    'production_quote_service' => $r['production_quote_service'],
                    'reload.quoted_package_type_id' => $r['reload']['quoted_package_type_id'],
                    'reload.raw_package_has_type' => $r['reload']['raw_package_has_type'],
                    'reload.product_types' => $r['reload']['product_types'],
                    'reload.fresh_rate' => $r['reload']['fresh_rate'],
                    'reload.fresh_session' => $r['reload']['fresh_session'],
                    'reload.selected_method' => $r['reload']['selected_method'],
                ],
                $scenario
            );
            $this->assertNotEmpty($r['reload']['quote_token']);
            $this->assertSame(
                [
                    'reload.calls_after_quote' => 1,
                    'reload.calls_after_fees' => 1,
                    'calls' => 1,
                    'count(r.cart_fees)' => 1,
                    'cart_fees.kiriof_instant_admin_fee' => ['id' => 'kiriof_instant_admin_fee', 'name' => 'Admin Fee', 'amount' => 1000, 'taxable' => false],
                    'count(r.fee_lines)' => 1,
                    'fee_lines.0.total' => 1000,
                    'fee_lines.0.tax' => 0,
                    'fee_lines.0.meta._kiriof_fee_type' => 'instant_admin_fee',
                ],
                [
                    'reload.calls_after_quote' => $r['reload']['calls_after_quote'],
                    'reload.calls_after_fees' => $r['reload']['calls_after_fees'],
                    'calls' => $r['calls'],
                    'count(r.cart_fees)' => count($r['cart_fees']),
                    'cart_fees.kiriof_instant_admin_fee' => $r['cart_fees']['kiriof_instant_admin_fee'],
                    'count(r.fee_lines)' => count($r['fee_lines']),
                    'fee_lines.0.total' => $r['fee_lines'][0]['total'],
                    'fee_lines.0.tax' => $r['fee_lines'][0]['tax'],
                    'fee_lines.0.meta._kiriof_fee_type' => $r['fee_lines'][0]['meta']['_kiriof_fee_type'],
                ],
                __FUNCTION__
            );
            $snapshot = $r['meta']['_kiriof_instant_checkout_snapshot'];
            $this->assertSame(
                [
                    'context.package_type_id' => $type,
                    'rate.quote_token' => $r['reload']['quote_token'],
                    'rate.shipping_costs' => 18000,
                    'rate.admin_fee' => 1000,
                    'rate.total_price' => 19000,
                    'order_address.first_name' => 'Buyer',
                    'order_address.phone' => '081234567890',
                    'meta._kiriof_buyer_destination_coordinates' => ['latitude' => '-6.3', 'longitude' => '106.9'],
                    'count(r.rows)' => 1,
                    'rows.0.delivery_type' => 'instant',
                    'rows.0.status' => 'new',
                    'rows.0.service' => 'gosend',
                    'rows.0.service_name' => 'GO-INSTANT',
                    'rows.0.shipping_cost' => 18000,
                    'rows.0.transaction_value' => 150000,
                ],
                [
                    'context.package_type_id' => $snapshot['context']['package_type_id'],
                    'rate.quote_token' => $snapshot['rate']['quote_token'],
                    'rate.shipping_costs' => $snapshot['rate']['shipping_costs'],
                    'rate.admin_fee' => $snapshot['rate']['admin_fee'],
                    'rate.total_price' => $snapshot['rate']['total_price'],
                    'order_address.first_name' => $r['order_address']['first_name'],
                    'order_address.phone' => $r['order_address']['phone'],
                    'meta._kiriof_buyer_destination_coordinates' => $r['meta']['_kiriof_buyer_destination_coordinates'],
                    'count(r.rows)' => count($r['rows']),
                    'rows.0.delivery_type' => $r['rows'][0]['delivery_type'],
                    'rows.0.status' => $r['rows'][0]['status'],
                    'rows.0.service' => $r['rows'][0]['service'],
                    'rows.0.service_name' => $r['rows'][0]['service_name'],
                    'rows.0.shipping_cost' => $r['rows'][0]['shipping_cost'],
                    'rows.0.transaction_value' => $r['rows'][0]['transaction_value'],
                ],
                __FUNCTION__
            );
            $shipping = json_decode($r['rows'][0]['shipping_info'], true);
            $this->assertSame(
                [
                    '_kiriof_instant_admin_fee' => 1000,
                    '_kiriof_instant_shipping_total' => 19000,
                    '_shipping_phone' => '081234567890',
                    'invoice_calls' => 1,
                    'locks' => [],
                    'logs' => [],
                ],
                [
                    '_kiriof_instant_admin_fee' => $shipping['_kiriof_instant_admin_fee'],
                    '_kiriof_instant_shipping_total' => $shipping['_kiriof_instant_shipping_total'],
                    '_shipping_phone' => $shipping['_shipping_phone'],
                    'invoice_calls' => $r['invoice_calls'],
                    'locks' => $r['locks'],
                    'logs' => $r['logs'],
                ],
                __FUNCTION__
            );
        }
    }

    #[Test]
    public function durable_receipts_recheck_shipping_tax_and_order_currency(): void {
        foreach (['processed_currency', 'processed_shipping_tax', 'durable_missing_currency'] as $scenario) {
            $r = $this->fixture($scenario);
            $this->assertSame('', $r['error'], $scenario);
            $this->assertNotEmpty($r['processed_error'], $scenario);
            $this->assertSame(
                [
                    'rows' => [],
                    'calls' => 1,
                ],
                [
                    'rows' => $r['rows'],
                    'calls' => $r['calls'],
                ],
                $scenario
            );
        }
        $r = $this->fixture('processed_expiry');
        $this->assertSame(
            [
                'meta._kiriof_instant_checkout_snapshot.context.currency' => 'IDR',
                'count(r.rows)' => 1,
            ],
            [
                'meta._kiriof_instant_checkout_snapshot.context.currency' => $r['meta']['_kiriof_instant_checkout_snapshot']['context']['currency'],
                'count(r.rows)' => count($r['rows']),
            ],
            __FUNCTION__
        );
    }

}

