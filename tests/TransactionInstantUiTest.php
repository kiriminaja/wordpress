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

    public function test_instant_rows_are_read_only_with_actual_nullable_vehicle(): void {
        foreach ([['delivery_type' => 'instant'], ['delivery_type' => 'express', 'service' => 'gosend']] as $classification) {
            foreach (['new', 'request_pickup'] as $status) {
                $row = $this->runFixture('transaction-instant-ui-runtime.php', $classification + ['status' => $status, 'vehicle' => 'mobil', 'is_deficit' => 1]);
                $this->assertSame('instant', $row['deliveryType']);
                $this->assertSame('mobil', $row['vehicle']);
                $this->assertTrue($row['selection']['disabled']);
                $this->assertFalse($row['selection']['canPickup']);
                $this->assertFalse($row['selection']['canPrint']);
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

    public function test_express_actions_remain_available(): void {
        $row = $this->runFixture('transaction-instant-ui-runtime.php', []);
        $this->assertSame('express', $row['deliveryType']);
        $this->assertTrue($row['selection']['canPickup']);
        $this->assertTrue($row['actions']['changeOrigin']);
        $this->assertTrue($row['actions']['cancel']);
        $row = $this->runFixture('transaction-instant-ui-runtime.php', ['status' => 'request_pickup']);
        $this->assertTrue($row['selection']['canPrint']);
        $this->assertTrue($row['actions']['print']);
    }

    public function test_workspace_navigation_and_ui_safety_contract(): void {
        $app = file_get_contents(PLUGIN_DIR . '/src/lib/transactions/TransactionsApp.svelte');
        $this->assertStringContainsString("{ value: 'instant', label: bootstrap.i18n.instantDelivery }", $app);
        $this->assertStringContainsString("url.searchParams.set('delivery_type', values.delivery_type || (isInstant ? 'instant' : 'express'))", $app);
        $this->assertStringContainsString("delivery_type: value === 'instant' ? 'instant' : 'express'", $app);
        $this->assertStringContainsString('selected = {};', $app);
        $this->assertStringContainsString('printPreviewOrderIds = [];', $app);
        $this->assertStringContainsString('row.vehicle || bootstrap.i18n.vehicleUnavailable', $app);
        $this->assertStringContainsString("{#if row.deliveryType !== 'instant'}", $app);
        $this->assertStringContainsString('bootstrap.i18n.instantNotice', $app);
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
