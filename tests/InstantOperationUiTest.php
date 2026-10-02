<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InstantOperationUiTest extends TestCase {
    private function source(string $path): string {
        return file_get_contents(PLUGIN_DIR . '/' . $path);
    }

    private function row(array $payload): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/transaction-instant-ui-runtime.php') . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR)));
        $this->assertNotNull($output);
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    private function confirmedBooking(): array {
        return [
            'delivery_type' => 'instant', 'service' => 'gosend', 'vehicle' => 'motor',
            'status' => 'request_pickup', 'order_id' => 'KA-1', 'awb' => '',
            'instant_status_code' => 100, 'instant_payment_id' => 'PAY-1',
            'instant_payment_method' => 'qris', 'instant_payment_status' => 'paid',
            'shipping_cost' => 12000,
            'shipment_location_snapshot' => json_encode(['origin_name' => 'Booked warehouse', 'origin_address' => 'Booked origin street'], JSON_THROW_ON_ERROR),
            'shipping_info' => json_encode([
                '_shipping_first_name' => 'Booked recipient', '_shipping_address_1' => 'Booked destination street',
                'instant_items' => [['name' => 'Persisted physical item', 'qty' => 2]],
            ], JSON_THROW_ON_ERROR),
        ];
    }

    public function test_confirmed_bookings_can_cancel_without_an_awb_but_unknown_and_terminal_states_cannot(): void {
        foreach (['gosend', 'grab_express'] as $service) {
            foreach (['pending', 'request_pickup'] as $status) {
                foreach ([100, 105, '100', '105'] as $code) {
                    foreach (['', null, 'BOOKED-AWB'] as $awb) {
                        foreach (['list', 'detail'] as $mode) {
                            $row = $this->row(array_replace($this->confirmedBooking(), compact('service', 'status', 'awb', 'mode'), ['instant_status_code' => $code, 'is_deficit' => 1]));
                            $this->assertTrue($row['actions']['cancel'], "$service/$status/$code/$mode");
                            foreach (['changeOrigin', 'adjustDeficit', 'cancelDeficit'] as $action) {
                                $this->assertFalse($row['actions'][$action]);
                            }
        foreach (['gosend', 'grab_express'] as $service) {
            foreach (['list', 'detail', 'fallback'] as $mode) {
                $payload = ['delivery_type'=>'instant', 'service'=>$service, 'status'=>'pending', 'instant_status_code'=>null, 'instant_payment_id'=>'', 'awb'=>'', 'mode'=>$mode];
                $row = $this->row($payload);
                $this->assertSame($mode !== 'fallback', $row['actions']['reconcile']);
                foreach (['instant_status_code'=>0, 'instant_payment_id'=>'PAY-1', 'awb'=>'AWB-1', 'status'=>'new', 'order_id'=>'bad id', 'service'=>'jne'] as $field=>$value) {
                    $this->assertFalse($this->row(array_replace($payload, [$field=>$value]))['actions']['reconcile']);
                }
            }
        }
                        }
                    }
                }
            }
        }
        foreach ([
            ['status' => 'pending', 'instant_status_code' => null],
            ['status' => 'pending', 'instant_status_code' => 999],
            ['instant_payment_id' => null], ['instant_payment_id' => ''],
            ['instant_payment_status' => 'refunded'], ['order_id' => ''], ['awb' => 'invalid awb'],
            ['instant_status_code' => 106], ['instant_status_code' => 200],
            ['instant_status_code' => 300], ['instant_status_code' => 350],
            ['status' => 'shipped'], ['status' => 'finished'], ['status' => 'canceled'],
            ['service' => 'borzo'], ['status' => 'new'],
        ] as $invalid) {
            foreach (['list', 'detail'] as $mode) {
                $row = $this->row(array_replace($this->confirmedBooking(), $invalid, ['mode' => $mode]));
                $this->assertFalse($row['actions']['cancel'], json_encode($invalid) . "/$mode");
            }
        }
    }

    public function test_tracking_requires_route_and_reconciliation_is_never_available(): void {
        foreach (['gosend', 'grab_express', 'borzo', 'jne'] as $service) {
            foreach (['new', 'pending', 'request_pickup', 'shipped', 'finished', 'canceled'] as $status) {
                foreach (['list', 'detail', 'fallback'] as $mode) {
                    $row = $this->row(array_replace($this->confirmedBooking(), compact('service', 'status', 'mode'), ['instant_tracking_payload' => json_encode(['result' => ['polyline' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@']])]));
                    $expected = $mode !== 'fallback' && $status !== 'new' && in_array($service, ['gosend', 'grab_express'], true);
                    $this->assertSame($expected, $row['actions']['track'], "$service/$status/$mode");
                    $this->assertFalse($row['actions']['reconcile'], "$service/$status/$mode");
                    if ($mode !== 'list') {
                        $this->assertFalse($row['supportsLiveTracking']);
                        $this->assertSame([], $row['steps']);
                    }
                }
            }
        }
    }

    public function test_tracking_links_expose_only_valid_credential_free_http_urls(): void {
        foreach ([
            'https://tracking.example.test/KA-1?token=abc' => true,
            'http://tracking.example.test/KA-1' => true,
            'https://user:secret@tracking.example.test/KA-1' => false,
            'https://user@tracking.example.test/KA-1' => false,
            'javascript:alert(1)' => false, 'ftp://tracking.example.test/KA-1' => false,
            '//tracking.example.test/KA-1' => false, '/KA-1' => false,
            "https://tracking.example.test/KA-1\n" => false,
            'https://tracking.example.test/a b' => false,
            'https://tracking.example.test\\@evil.test/KA-1' => false,
            '' => false,
        ] as $url => $valid) {
            foreach (['list', 'detail', 'fallback'] as $mode) {
                $row = $this->row(array_replace($this->confirmedBooking(), ['mode' => $mode, 'live_tracking_url' => $url, 'instant_tracking_payload' => json_encode(['result' => ['polyline' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@']])]));
                $actual = $mode === 'list' ? $row['actions']['liveTrackingUrl'] : $row['shipment']['liveTrackingUrl'];
                $this->assertSame($valid ? $url : '', $actual, "$mode/$url");
            }
        }
    }

    public function test_empty_or_malformed_routes_never_expose_tracking_even_with_a_safe_url(): void {
        foreach ([null, '', ' ', '{}', '[]', 'null', [], [[]], [[], []], [[1, 2]], [[91, 2], [3, 4]], [[1, 'NaN'], [3, 4]], 'https://tracking.example.test/route', 'not a route', '_p~iF~ps|U'] as $polyline) {
            foreach (['list', 'detail', 'fallback'] as $mode) {
                $row = $this->row(array_replace($this->confirmedBooking(), [
                    'mode' => $mode, 'live_tracking_url' => 'https://tracking.example.test/KA-1',
                    'instant_tracking_payload' => json_encode(['result' => ['polyline' => $polyline]]),
                ]));
                $this->assertFalse($row['actions']['track'], json_encode($polyline) . "/$mode");
                $this->assertSame('', $mode === 'list' ? $row['actions']['liveTrackingUrl'] : $row['shipment']['liveTrackingUrl']);
            }
        }
        foreach (['{bad json', '{"result":null}', '{"result":{"courier":{"coords":[[1,2],[3,4]]},"origin":{"lat":1,"long":2},"destination":{"lat":3,"long":4}}}', '{"polyline":"_p~iF~ps|U_ulLnnqC_mqNvxq`@","result":{"polyline":[]}}'] as $payload) {
            $row = $this->row(array_replace($this->confirmedBooking(), ['instant_tracking_payload' => $payload, 'live_tracking_url' => 'https://tracking.example.test/KA-1']));
            $this->assertFalse($row['actions']['track']);
            $this->assertSame('', $row['actions']['liveTrackingUrl']);
        }
    }

    public function test_valid_routes_expose_only_the_persisted_safe_url(): void {
        foreach (['_p~iF~ps|U_ulLnnqC_mqNvxq`@', [[-7.8, 110.3], [-7.7, 110.4]], [['lat' => -7.8, 'long' => 110.3], ['lat' => -7.7, 'long' => 110.4]]] as $polyline) {
            foreach (['list', 'detail', 'fallback'] as $mode) {
                $payload = array_replace($this->confirmedBooking(), ['mode' => $mode, 'instant_tracking_payload' => json_encode(['result' => ['polyline' => $polyline, 'live_tracking_url' => 'https://user:secret@tracking.example.test/KA-1']])]);
                $row = $this->row($payload + ['live_tracking_url' => 'https://tracking.example.test/KA-1']);
                $this->assertSame($mode !== 'fallback', $row['actions']['track']);
                $this->assertSame('https://tracking.example.test/KA-1', $mode === 'list' ? $row['actions']['liveTrackingUrl'] : $row['shipment']['liveTrackingUrl']);
                $row = $this->row($payload);
                $this->assertSame('', $mode === 'list' ? $row['actions']['liveTrackingUrl'] : $row['shipment']['liveTrackingUrl'], 'Never fall back to raw payload URLs');
            }
        }
    }

    public function test_recheck_is_targeted_to_uncertain_bookings_and_uses_existing_reconcile_endpoint(): void {
        foreach (['src/lib/transactions/TransactionsApp.svelte', 'src/lib/transaction-detail/TransactionDetail.svelte', 'src/lib/transactions/InstantOperationDialog.svelte'] as $path) {
            $source = $this->source($path);
            $this->assertStringContainsString('reconcile', $source);
            $this->assertStringContainsString('instantRecheck', $source);
            $this->assertStringNotContainsString('Check remote status', $source);
        }
        foreach (['inc/Services/TransactionListRenderService.php', 'inc/Services/TransactionDetailPageData.php'] as $path) {
            $this->assertStringNotContainsString('"instantReconcile"', $this->source($path));
        }
    }

    public function test_operations_are_explicit_single_order_and_not_processing(): void {
        $dialog = $this->source('src/lib/transactions/InstantOperationDialog.svelte');
        $this->assertStringContainsString('onclick={() => void run()}', $dialog);
        $this->assertStringContainsString('orderIds.length !== 1', $dialog);
        $this->assertStringContainsString("if (mode === 'cancel') values.confirmed = 'yes'", $dialog);
        $this->assertStringContainsString('order_ids: JSON.stringify(orderIds)', $dialog);
        $this->assertStringContainsString('`kiriof_instant_${mode}`', $dialog);
        $this->assertStringNotContainsString('$effect', $dialog);
        $this->assertStringNotContainsString('kiriof_instant_dispatch', $dialog);
        $this->assertStringNotContainsString('setInterval', $dialog);
        $this->assertStringContainsString('instantOperationUnknown', $dialog);
        $this->assertStringContainsString('rows !== null', $dialog);
    }

    public function test_close_is_blocked_busy_and_unmount_aborts(): void {
        $dialog = $this->source('src/lib/transactions/InstantOperationDialog.svelte');
        $this->assertStringContainsString('if (busy) return;', $dialog);
        $this->assertStringContainsString('controller?.abort()', $dialog);
        $this->assertStringContainsString("escapeKeydownBehavior={busy ? 'ignore' : 'close'}", $dialog);
        $this->assertStringContainsString("interactOutsideBehavior={busy ? 'ignore' : 'close'}", $dialog);
        $this->assertStringContainsString('showCloseButton={!busy}', $dialog);
        $this->assertStringContainsString('signal: controller.signal', $dialog);
    }

    public function test_urls_and_local_guards_are_shared(): void {
        $types = $this->source('src/lib/transactions/types.ts');
        $this->assertStringContainsString("['http:', 'https:'].includes(url.protocol)", $types);
        $this->assertStringContainsString('!url.username', $types);
        $this->assertStringContainsString('!url.password', $types);
        $this->assertStringContainsString('\\x00-\\x20\\x7f', $types);
        foreach (['src/lib/transactions/TransactionsApp.svelte', 'src/lib/transaction-detail/TransactionDetail.svelte', 'src/lib/transactions/InstantOperationDialog.svelte'] as $path) {
            $source = $this->source($path);
            $this->assertStringContainsString('InstantOperation', $source);
            $this->assertStringContainsString('safeInstantTrackingUrl', $source);
            $this->assertStringContainsString('rel="noopener noreferrer"', $source);
        }
        $list = $this->source('inc/Services/TransactionListViewModelFactory.php');
        $this->assertStringContainsString('InstantShipmentState::canCancel( $row )', $list);
        $this->assertStringContainsString('InstantTrackingPresentation::trackingUrl( $row )', $list);
        $detail = $this->source('inc/Services/TransactionDetailPageData.php');
        $this->assertStringContainsString('InstantLabelService::canPrint($transaction)', $detail);
        $this->assertStringContainsString('InstantShipmentState::canCancel($transaction)', $detail);
        $this->assertStringContainsString('"supportsLiveTracking" => $is_express', $detail);
        foreach (['inc/Services/TransactionListRenderService.php', 'inc/Services/TransactionDetailPageData.php'] as $path) {
            $source = $this->source($path);
            foreach (['instantCancelTerms', 'instantOperationUnknown', 'instantResult_cancel_requested', 'instantResult_canceled'] as $key) {
                $this->assertStringContainsString('"' . $key . '" => __(', $source);
            }
            $this->assertStringContainsString('does not cancel the WooCommerce order or issue a WooCommerce refund', $source);
        }
    }
}
