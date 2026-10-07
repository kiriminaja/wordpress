<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TransactionInstantUiTest extends TestCase {
    private function runFixture(string $fixture, array $payload): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/' . $fixture) . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR)));
        $this->assertNotNull($output);
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_instant_filters_persist_and_ignore_express_only_filters(): void {
        $filters = $this->runFixture('transaction-multi-filter-runtime.php', ['mode' => 'renderer', 'get' => ['delivery_type' => 'instant', 'cod' => '1', 'print_status' => '1', 'status' => 'processed']]);
        $this->assertSame('instant', $filters['delivery_type']);
        $this->assertSame('processed', $filters['status']);
        $this->assertSame('', $filters['cod']);
        $this->assertSame('', $filters['print_status']);
        foreach (['Instant', 'unknown', ['instant'], ''] as $value) {
            $filters = $this->runFixture('transaction-multi-filter-runtime.php', ['mode' => 'renderer', 'get' => ['delivery_type' => $value]]);
            $this->assertSame('express', $filters['delivery_type']);
        }
        $filters = $this->runFixture('transaction-multi-filter-runtime.php', ['mode' => 'renderer', 'get' => ['delivery_type' => 'instant', 'status' => 'order-issue']]);
        $this->assertSame('express', $filters['delivery_type']);
        $this->assertSame('order-issue', $filters['status']);
    }

    public function test_ineligible_instant_rows_disable_selection_and_express_actions_with_nullable_vehicle(): void {
        foreach ([['delivery_type' => 'instant'], ['delivery_type' => 'express', 'service' => 'gosend']] as $classification) {
            foreach (['new', 'request_pickup'] as $status) {
                $row = $this->runFixture('transaction-instant-ui-runtime.php', $classification + ['status' => $status, 'vehicle' => 'mobil', 'is_deficit' => 1]);
                $this->assertSame('instant', $row['deliveryType']);
                $this->assertSame('mobil', $row['vehicle']);
                $this->assertTrue($row['selection']['disabled']);
                $this->assertFalse($row['selection']['canPickup']);
                $this->assertFalse($row['selection']['canPrint']);
                $this->assertFalse($row['selection']['canProcess']);
                $this->assertFalse($row['actions']['process']);
                foreach (['changeOrigin', 'adjustDeficit', 'cancelDeficit', 'print', 'cancel'] as $action) {
                    $this->assertFalse($row['actions'][$action], $action);
                }
                $this->assertTrue($row['actions']['preview']);
                $this->assertFalse($row['status']['deficit']);
                $this->assertNotEmpty($row['status']['issue']);
                $this->assertNotSame('COD Deficit', $row['status']['label']);
            }
        }
        $row = $this->runFixture('transaction-instant-ui-runtime.php', ['delivery_type' => 'instant']);
        $this->assertNull($row['vehicle']);
        $this->assertSame('Waiting for Shipment', $row['status']['label']);
        $row = $this->runFixture('transaction-instant-ui-runtime.php', ['delivery_type' => 'instant', 'vehicle' => 'car']);
        $this->assertNull($row['vehicle']);
    }

    private function eligibleInstantPayload(bool $booked = false): array {
        return [
            'delivery_type' => 'instant', 'service' => 'gosend', 'vehicle' => 'motor',
            'status' => $booked ? 'request_pickup' : 'new',
            'awb' => $booked ? 'GOSEND-AWB-1' : '',
            'wc_order' => ['status' => 'processing', 'paid' => false],
            'shipment_location_snapshot' => json_encode(['origin_name' => 'Booked warehouse', 'origin_address' => 'Booked origin street'], JSON_THROW_ON_ERROR),
            'shipping_info' => json_encode([
                '_shipping_first_name' => 'Booked recipient', '_shipping_address_1' => 'Booked destination street',
                'instant_items' => [['name' => 'Persisted physical item', 'qty' => 2]],
            ], JSON_THROW_ON_ERROR),
        ];
    }

    private function assertNoExpressActions(array $row): void {
        $this->assertFalse($row['selection']['canPickup']);
        foreach (['changeOrigin', 'adjustDeficit', 'cancelDeficit'] as $action) {
            $this->assertFalse($row['actions'][$action], $action);
        }
        $this->assertTrue($row['actions']['preview']);
    }

    public function test_instant_processing_and_printing_are_separate_guarded_operations(): void {
        foreach (['gosend', 'grab_express'] as $service) {
            $new = $this->runFixture('transaction-instant-ui-runtime.php', array_replace($this->eligibleInstantPayload(), ['service' => $service]));
            $this->assertFalse($new['selection']['disabled']);
            $this->assertTrue($new['selection']['canProcess']);
            $this->assertTrue($new['actions']['process']);
            $this->assertFalse($new['selection']['canPrint']);
            $this->assertFalse($new['actions']['print']);
            $this->assertNoExpressActions($new);
            $this->assertFalse($new['actions']['cancel']);

            // The order fake has a deleted product: labels use dispatch snapshots,
            // not the mutable WC catalog. Both WC lookups must return an order.
            $booked = $this->runFixture('transaction-instant-ui-runtime.php', array_replace($this->eligibleInstantPayload(true), ['service' => $service]));
            $this->assertFalse($booked['selection']['disabled']);
            $this->assertFalse($booked['selection']['canProcess']);
            $this->assertFalse($booked['actions']['process']);
            $this->assertTrue($booked['selection']['canPrint']);
            $this->assertTrue($booked['actions']['print']);
            $this->assertNoExpressActions($booked);
            // An AWB alone does not confirm a cancelable Instant booking.
            $this->assertFalse($booked['actions']['cancel']);
        }
    }

    public function test_instant_process_guard_rejects_remote_state_and_ineligible_orders(): void {
        foreach ([
            ['awb' => 'AWB-1'], ['payment_id' => 'PAY-1'], ['instant_payment_id' => 'PAY-1'],
            ['instant_status_code' => 0], ['instant_status_code' => 100],
            ['status' => 'request_pickup'], ['service' => 'jne'], ['wc_order' => false],
            ['wc_order' => ['status' => 'pending', 'paid' => false]],
            ['wc_order' => ['status' => 'completed', 'paid' => true]],
            ['wc_order' => ['status' => 'cancelled', 'paid' => true]],
            ['wc_order' => ['status' => 'refunded', 'paid' => true]],
            ['wc_order' => ['status' => 'failed', 'paid' => true]],
            ['wc_order' => ['status' => 'trash', 'paid' => true]],
        ] as $invalid) {
            $row = $this->runFixture('transaction-instant-ui-runtime.php', array_replace($this->eligibleInstantPayload(), $invalid));
            $this->assertTrue($row['selection']['disabled'], json_encode($invalid));
            $this->assertFalse($row['selection']['canProcess']);
            $this->assertFalse($row['selection']['canPrint']);
            $this->assertNoExpressActions($row);
        }
        $row = $this->runFixture('transaction-instant-ui-runtime.php', array_replace($this->eligibleInstantPayload(), ['wc_order' => ['status' => 'on-hold', 'paid' => true]]));
        $this->assertTrue($row['selection']['canProcess']);
        // This is deliberately a cheap UI guard, not full coordinate validation.
        $row = $this->runFixture('transaction-instant-ui-runtime.php', array_replace($this->eligibleInstantPayload(), ['shipping_info' => '{}', 'shipment_location_snapshot' => '']));
        $this->assertTrue($row['selection']['canProcess']);
    }

    public function test_instant_print_guard_fails_closed_without_booked_snapshots(): void {
        foreach ([
            ['awb' => ''], ['awb' => '-'], ['instant_status_code' => 300],
            ['instant_status_code' => 302], ['instant_status_code' => 350],
            ['status' => 'canceled'], ['service' => 'jne'], ['wc_order' => false],
            ['wc_order' => ['status' => 'pending', 'paid' => true]],
            ['shipping_info' => '{}'],
            ['shipping_info' => '{"_shipping_first_name":"Name","_shipping_address_1":"Street","instant_items":[{"name":"Item","qty":0}]}'],
            ['shipping_info' => '{"instant_items":[{"name":"Item","qty":1}]}'],
            ['shipment_location_snapshot' => '{}'],
            ['wp_wc_order_stat_order_id' => 999],
        ] as $invalid) {
            $row = $this->runFixture('transaction-instant-ui-runtime.php', array_replace($this->eligibleInstantPayload(true), $invalid));
            $this->assertTrue($row['selection']['disabled'], json_encode($invalid));
            $this->assertFalse($row['selection']['canPrint']);
            $this->assertFalse($row['actions']['print']);
            $this->assertFalse($row['selection']['canProcess']);
            $this->assertNoExpressActions($row);
        }
        foreach (['request_pickup', 'shipped', 'finished'] as $status) {
            $row = $this->runFixture('transaction-instant-ui-runtime.php', array_replace($this->eligibleInstantPayload(true), ['status' => $status, 'wc_order' => ['status' => 'completed']]));
            $this->assertTrue($row['selection']['canPrint']);
        }
    }

    public function test_express_actions_remain_available(): void {
        $row = $this->runFixture('transaction-instant-ui-runtime.php', []);
        $this->assertSame('express', $row['deliveryType']);
        $this->assertTrue($row['selection']['canPickup']);
        $this->assertTrue($row['actions']['changeOrigin']);
        $this->assertTrue($row['actions']['cancel']);
        $this->assertFalse($row['actions']['track']);
        $this->assertFalse($row['actions']['reconcile']);
        $this->assertSame('', $row['actions']['liveTrackingUrl']);
        $row = $this->runFixture('transaction-instant-ui-runtime.php', ['status' => 'request_pickup']);
        $this->assertTrue($row['selection']['canPrint']);
        $this->assertTrue($row['actions']['print']);
        foreach (['shipped', 'finished', 'returned', 'return', 'canceled'] as $status) {
            $row = $this->runFixture('transaction-instant-ui-runtime.php', ['status' => $status]);
            $this->assertFalse($row['actions']['cancel']);
        }
        foreach (['list', 'detail'] as $mode) {
            $row = $this->runFixture('transaction-instant-ui-runtime.php', ['mode' => $mode, 'is_deficit' => 1]);
            $this->assertFalse($row['actions']['cancel']);
            $this->assertTrue($row['actions']['adjustDeficit']);
            $this->assertTrue($row['actions']['cancelDeficit']);
        }
    }

    public function test_workspace_navigation_and_ui_safety_contract(): void {
        $app = file_get_contents(PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte');
        $this->assertStringContainsString("{ value: 'instant', label: bootstrap.i18n.instantDelivery, count: bootstrap.deliveryCounts?.instant ?? 0 }", $app);
        $this->assertStringContainsString("url.searchParams.set('delivery_type', values.delivery_type || (isInstant ? 'instant' : 'express'))", $app);
        $this->assertStringContainsString("delivery_type: value === 'instant' ? 'instant' : 'express'", $app);
        $this->assertStringContainsString('selected = {};', $app);
        $this->assertStringContainsString('printPreviewOrderIds = [];', $app);
        $this->assertStringContainsString('row.vehicle || bootstrap.i18n.vehicleUnavailable', $app);
        $this->assertStringContainsString("{#if row.deliveryType !== 'instant'}", $app);
        $this->assertStringNotContainsString('bootstrap.i18n.instantNotice', $app);
        $renderer = file_get_contents(PLUGIN_DIR . '/inc/Services/TransactionListRenderService.php');
        $this->assertStringNotContainsString('Instant shipments require price and payment review before dispatch.', $renderer);
        $this->assertStringNotContainsString('disabled: true', $app);
    }

    public function test_instant_transaction_tab_is_enabled_and_connected_to_navigation(): void {
        $app = file_get_contents(PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte');
        $this->assertSame(1, preg_match('/const scopeTabs = \$derived\(\[([\s\S]*?)\]\);/', $app, $tabs));
        $this->assertSame(3, preg_match_all('/\{ value: \'([^\']+)\'[^\n]*\}/', $tabs[1], $entries));
        $this->assertSame(['regular', 'instant', 'order-issue'], $entries[1]);
        foreach ($entries[0] as $entry) {
            $this->assertDoesNotMatchRegularExpression('/\bdisabled\s*:/', $entry);
        }
        $this->assertStringContainsString('<WorkspaceTabs value={scopeValue} tabs={scopeTabs} onChange={changeScope} />', $app);
        $workspace = file_get_contents(PLUGIN_DIR . '/src/lib/ui/WorkspaceTabs.svelte');
        $this->assertStringContainsString('onValueChange={onChange}', $workspace);
        $this->assertStringContainsString('<Tabs.Trigger value={tab.value} disabled={tab.disabled}', $workspace);
    }
}
