<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TransactionInstantUiTest extends TestCase {
    public function test_bootstrap_pending_issue_count_is_independent_of_historical_list_totals_and_filters(): void {
        $expectedCases = $actualCases = [];
        // Run the real renderer and read its public JSON payload from the real template.
        $runtime = <<<'PHP'
namespace KiriminAjaOfficial\Services {
    class TransactionListViewModelFactory { public function createRows($rows, $status): array { return []; } }
    class KiriminajaApiService { public function getCourierNameMap(): array { return []; } }
    class ShipmentLocationService {
        public function repository() { return $this; }
        public function getAll($active): array { return []; }
    }
    class PluginUpdateNoticeService { public function get_toolbar_update() { return null; } }
    class RevampAnnouncementService { public static function attach_announcement($toolbar) { return $toolbar; } }
}
namespace {
    define('ABSPATH', __DIR__);
    define('KIRIOF_DIR', $argv[1] . '/');
    define('KIRIOF_URL', 'https://fixture.test/');
    define('KIRIOF_NONCE', 'fixture');
    function current_user_can($capability) { return $capability === 'manage_woocommerce'; }
    function esc_attr__($text, $domain) { return htmlspecialchars($text, ENT_QUOTES); }
    function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES); }
    function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags | JSON_THROW_ON_ERROR); }
    function get_locale() { return 'en_US'; }
    function wp_get_current_user() { return (object) ['ID' => 1]; }
    function get_user_meta(...$args) { return 25; }
    function update_user_meta(...$args) {}
    function sanitize_text_field($value) { return trim(strip_tags($value)); }
    function wp_unslash($value) { return $value; }
    function admin_url($value) { return 'https://fixture.test/' . $value; }
    function __($value, $domain) { return $value; }
    function wp_create_nonce($value) { return 'nonce'; }
    $root = $argv[1];
    require $root . '/inc/Contracts/TransactionListQueryInterface.php';
    require $root . '/inc/Services/TransactionDeliveryType.php';
    require $root . '/inc/Services/ListDateRangeFilter.php';
    require $root . '/inc/Queries/WordPressTransactionListQuery.php';
    require $root . '/inc/Services/TransactionListRenderService.php';
    class HistoricalQuery implements \KiriminAjaOfficial\Contracts\TransactionListQueryInterface {
        public function getPage(array $filters, int $page, int $items_per_page): array {
            return ['results' => [], 'total' => $filters['key'] ? 1 : 47, 'page' => $page, 'items_per_page' => $items_per_page, 'total_pages' => 2];
        }
        public function getStatusCounts(): array { return ['all' => 200, 'order-issue' => 47, 'wc-processing' => 89]; }
        public function getCouriers(): array { return []; }
        public function getOldestCreatedAt(): ?string { return null; }
    }
    class PendingQuery extends HistoricalQuery {
        public function getDeliveryCounts(): array { return ['regular' => '3', 'instant' => '2', 'issue' => '4']; }
    }
    class LegacyDeliveryQuery extends HistoricalQuery {
        public function getDeliveryCounts(): array { return ['regular' => 3, 'instant' => 2]; }
    }
    $_GET = json_decode($argv[3], true);
    $query_class = $argv[2];
    $query = new $query_class();
    ob_start();
    (new \KiriminAjaOfficial\Services\TransactionListRenderService($query))->render();
    $html = ob_get_clean();
    if (!preg_match('/<script type="application\/json" data-kiriof-transactions-payload>(.*?)<\/script>/s', $html, $match)) {
        throw new \RuntimeException('Transaction payload missing from rendered page');
    }
    echo $match[1];
}
PHP;
        foreach (['PendingQuery' => 4, 'LegacyDeliveryQuery' => 0, 'HistoricalQuery' => 0] as $query => $pending_issue) {
            foreach (['', 'filtered order'] as $key) {
                $get = ['status' => 'order-issue', 'key' => $key, 'courier' => 'jne', 'cod' => '1', 'print_status' => '1', 'date_from' => '2026-01-01', 'date_to' => '2026-01-31'];
                $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($runtime) . ' ' . escapeshellarg(PLUGIN_DIR) . ' ' . escapeshellarg($query) . ' ' . escapeshellarg(json_encode($get, JSON_THROW_ON_ERROR));
                $output = shell_exec($command);

                $bootstrap = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
                $contractCase = 'case ' . count( $expectedCases );
                $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                    '1: bootstrap[deliveryCounts][issue]' => $pending_issue,
                    '2: bootstrap[filters][status]' => 'order-issue',
                    '3: bootstrap[pagination][total]' => $key ? 1 : 47,
                    ];
                $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                    '1: bootstrap[deliveryCounts][issue]' => $bootstrap['deliveryCounts']['issue'],
                    '2: bootstrap[filters][status]' => $bootstrap['filters']['status'],
                    '3: bootstrap[pagination][total]' => $bootstrap['pagination']['total'],
                    ];
                $counts = array_column($bootstrap['statusOptions'], 'count', 'value');
                $contractCase = 'case ' . count( $expectedCases );
                $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                    '1: counts[order-issue]' => 47,
                    '2: counts[wc-processing]' => 89,
                    '3: counts[all]' => 200,
                    ];
                $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                    '1: counts[order-issue]' => $counts['order-issue'],
                    '2: counts[wc-processing]' => $counts['wc-processing'],
                    '3: counts[all]' => $counts['all'],
                    ];
            }
        }
        $this->assertSame( $expectedCases, $actualCases );
    }

    private function runFixture(string $fixture, array $payload): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/' . $fixture) . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR)));

        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_instant_filters_persist_print_status_and_ignore_express_only_cod(): void {
        $expectedCases = $actualCases = [];
        $filters = $this->runFixture('transaction-multi-filter-runtime.php', ['mode' => 'renderer', 'get' => ['delivery_type' => 'instant', 'cod' => '1', 'print_status' => '1', 'status' => 'processed']]);
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: filters[delivery_type]' => 'instant',
            '2: filters[status]' => 'processed',
            '3: filters[cod]' => '',
            '4: filters[print_status]' => '1',
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: filters[delivery_type]' => $filters['delivery_type'],
            '2: filters[status]' => $filters['status'],
            '3: filters[cod]' => $filters['cod'],
            '4: filters[print_status]' => $filters['print_status'],
            ];
        foreach (['0', '1', '', 'all', 'wat', '2', ['1']] as $print_status) {
            $filters = $this->runFixture('transaction-multi-filter-runtime.php', ['mode' => 'renderer', 'get' => ['delivery_type' => 'instant', 'cod' => '0', 'print_status' => $print_status]]);
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                '1: filters[print_status]' => in_array($print_status, ['0', '1'], true) ? $print_status : '',
                '2: filters[cod]' => '',
                '3: filters[status]' => 'all',
                '4: filters[delivery_type]' => 'instant',
                ];
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                '1: filters[print_status]' => $filters['print_status'],
                '2: filters[cod]' => $filters['cod'],
                '3: filters[status]' => $filters['status'],
                '4: filters[delivery_type]' => $filters['delivery_type'],
                ];
        }
        $defaults = $this->runFixture('transaction-multi-filter-runtime.php', ['mode' => 'renderer', 'get' => ['delivery_type' => 'instant']]);
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: defaults[print_status]' => '',
            '2: defaults[status]' => 'all',
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: defaults[print_status]' => $defaults['print_status'],
            '2: defaults[status]' => $defaults['status'],
            ];
        foreach (['Instant', 'unknown', ['instant'], ''] as $value) {
            $filters = $this->runFixture('transaction-multi-filter-runtime.php', ['mode' => 'renderer', 'get' => ['delivery_type' => $value]]);
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = 'express';
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $filters['delivery_type'];
        }
        $filters = $this->runFixture('transaction-multi-filter-runtime.php', ['mode' => 'renderer', 'get' => ['delivery_type' => 'instant', 'status' => 'order-issue']]);
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: filters[delivery_type]' => 'express',
            '2: filters[status]' => 'order-issue',
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: filters[delivery_type]' => $filters['delivery_type'],
            '2: filters[status]' => $filters['status'],
            ];
        $this->assertSame( $expectedCases, $actualCases );
    }

    public function test_ineligible_instant_rows_disable_selection_and_express_actions_with_nullable_vehicle(): void {
        $expectedCases = $actualCases = [];
        foreach ([['delivery_type' => 'instant'], ['delivery_type' => 'express', 'service' => 'gosend']] as $classification) {
            foreach (['new', 'request_pickup'] as $status) {
                $row = $this->runFixture('transaction-instant-ui-runtime.php', $classification + ['status' => $status, 'vehicle' => 'mobil', 'is_deficit' => 1]);
                $contractCase = 'case ' . count( $expectedCases );
                $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                    '1: row[deliveryType]' => 'instant',
                    '2: row[vehicle]' => 'mobil',
                    '3: row[selection][disabled]' => true,
                    '4: row[selection][canPickup]' => false,
                    '5: row[selection][canPrint]' => false,
                    '6: row[selection][canProcess]' => false,
                    '7: row[actions][process]' => false,
                    ];
                $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                    '1: row[deliveryType]' => $row['deliveryType'],
                    '2: row[vehicle]' => $row['vehicle'],
                    '3: row[selection][disabled]' => $row['selection']['disabled'],
                    '4: row[selection][canPickup]' => $row['selection']['canPickup'],
                    '5: row[selection][canPrint]' => $row['selection']['canPrint'],
                    '6: row[selection][canProcess]' => $row['selection']['canProcess'],
                    '7: row[actions][process]' => $row['actions']['process'],
                    ];
                foreach (['changeOrigin', 'adjustDeficit', 'cancelDeficit', 'print', 'cancel'] as $action) {
                    $this->assertFalse($row['actions'][$action], $action);
                }
                $contractCase = 'case ' . count( $expectedCases );
                $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                    '1: row[actions][preview]' => true,
                    '2: row[status][deficit]' => false,
                    '3: ! empty( row[status][issue] )' => true,
                    '4: (COD Deficit) !== (row[status][label])' => true,
                    ];
                $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                    '1: row[actions][preview]' => $row['actions']['preview'],
                    '2: row[status][deficit]' => $row['status']['deficit'],
                    '3: ! empty( row[status][issue] )' => ! empty( $row['status']['issue'] ),
                    '4: (COD Deficit) !== (row[status][label])' => ('COD Deficit') !== ($row['status']['label']),
                    ];
            }
        }
        $row = $this->runFixture('transaction-instant-ui-runtime.php', ['delivery_type' => 'instant']);
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: row[vehicle]' => null,
            '2: row[status][label]' => 'Waiting for Shipment',
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: row[vehicle]' => $row['vehicle'],
            '2: row[status][label]' => $row['status']['label'],
            ];
        $row = $this->runFixture('transaction-instant-ui-runtime.php', ['delivery_type' => 'instant', 'vehicle' => 'car']);
        $this->assertNull($row['vehicle']);
        $this->assertSame( $expectedCases, $actualCases );
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
        $this->assertSame(
            ['pickup' => false, 'changeOrigin' => false, 'adjustDeficit' => false, 'cancelDeficit' => false, 'preview' => true],
            ['pickup' => $row['selection']['canPickup'], 'changeOrigin' => $row['actions']['changeOrigin'], 'adjustDeficit' => $row['actions']['adjustDeficit'], 'cancelDeficit' => $row['actions']['cancelDeficit'], 'preview' => $row['actions']['preview']]
        );
    }

    public function test_instant_processing_and_printing_are_separate_guarded_operations(): void {
        $expectedCases = $actualCases = [];
        foreach (['gosend', 'grab_express'] as $service) {
            $new = $this->runFixture('transaction-instant-ui-runtime.php', array_replace($this->eligibleInstantPayload(), ['service' => $service]));
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                '1: new[selection][disabled]' => false,
                '2: new[selection][canProcess]' => true,
                '3: new[actions][process]' => true,
                '4: new[selection][canPrint]' => false,
                '5: new[actions][print]' => false,
                ];
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                '1: new[selection][disabled]' => $new['selection']['disabled'],
                '2: new[selection][canProcess]' => $new['selection']['canProcess'],
                '3: new[actions][process]' => $new['actions']['process'],
                '4: new[selection][canPrint]' => $new['selection']['canPrint'],
                '5: new[actions][print]' => $new['actions']['print'],
                ];
            $this->assertNoExpressActions($new);
            $this->assertFalse($new['actions']['cancel']);

            // The order fake has a deleted product: labels use dispatch snapshots,
            // not the mutable WC catalog. Both WC lookups must return an order.
            $booked = $this->runFixture('transaction-instant-ui-runtime.php', array_replace($this->eligibleInstantPayload(true), ['service' => $service]));
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                '1: booked[selection][disabled]' => false,
                '2: booked[selection][canProcess]' => false,
                '3: booked[actions][process]' => false,
                '4: booked[selection][canPrint]' => true,
                '5: booked[actions][print]' => true,
                ];
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                '1: booked[selection][disabled]' => $booked['selection']['disabled'],
                '2: booked[selection][canProcess]' => $booked['selection']['canProcess'],
                '3: booked[actions][process]' => $booked['actions']['process'],
                '4: booked[selection][canPrint]' => $booked['selection']['canPrint'],
                '5: booked[actions][print]' => $booked['actions']['print'],
                ];
            $this->assertNoExpressActions($booked);
            // An AWB alone does not confirm a cancelable Instant booking.
            $this->assertFalse($booked['actions']['cancel']);
        }
        $this->assertSame( $expectedCases, $actualCases );
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
            $this->assertSame(
                [
                '1: row[selection][disabled]' => true,
                '2: row[selection][canProcess]' => false,
                '3: row[selection][canPrint]' => false,
                ],
                [
                '1: row[selection][disabled]' => $row['selection']['disabled'],
                '2: row[selection][canProcess]' => $row['selection']['canProcess'],
                '3: row[selection][canPrint]' => $row['selection']['canPrint'],
                ]
            );
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
            $this->assertSame(
                [
                '1: row[selection][disabled]' => true,
                '2: row[selection][canPrint]' => false,
                '3: row[actions][print]' => false,
                '4: row[selection][canProcess]' => false,
                ],
                [
                '1: row[selection][disabled]' => $row['selection']['disabled'],
                '2: row[selection][canPrint]' => $row['selection']['canPrint'],
                '3: row[actions][print]' => $row['actions']['print'],
                '4: row[selection][canProcess]' => $row['selection']['canProcess'],
                ]
            );
            $this->assertNoExpressActions($row);
        }
        foreach (['request_pickup', 'shipped', 'finished'] as $status) {
            $row = $this->runFixture('transaction-instant-ui-runtime.php', array_replace($this->eligibleInstantPayload(true), ['status' => $status, 'wc_order' => ['status' => 'completed']]));
            $this->assertTrue($row['selection']['canPrint']);
        }
    }

    public function test_express_actions_remain_available(): void {
        $expectedCases = $actualCases = [];
        $row = $this->runFixture('transaction-instant-ui-runtime.php', []);
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: row[deliveryType]' => 'express',
            '2: row[selection][canPickup]' => true,
            '3: row[actions][changeOrigin]' => true,
            '4: row[actions][cancel]' => true,
            '5: row[actions][track]' => false,
            '6: row[actions][reconcile]' => false,
            '7: row[actions][liveTrackingUrl]' => '',
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: row[deliveryType]' => $row['deliveryType'],
            '2: row[selection][canPickup]' => $row['selection']['canPickup'],
            '3: row[actions][changeOrigin]' => $row['actions']['changeOrigin'],
            '4: row[actions][cancel]' => $row['actions']['cancel'],
            '5: row[actions][track]' => $row['actions']['track'],
            '6: row[actions][reconcile]' => $row['actions']['reconcile'],
            '7: row[actions][liveTrackingUrl]' => $row['actions']['liveTrackingUrl'],
            ];
        $row = $this->runFixture('transaction-instant-ui-runtime.php', ['status' => 'request_pickup']);
        $contractCase = 'case ' . count( $expectedCases );
        $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
            '1: row[selection][canPrint]' => true,
            '2: row[actions][print]' => true,
            ];
        $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
            '1: row[selection][canPrint]' => $row['selection']['canPrint'],
            '2: row[actions][print]' => $row['actions']['print'],
            ];
        foreach (['shipped', 'finished', 'returned', 'return', 'canceled'] as $status) {
            $row = $this->runFixture('transaction-instant-ui-runtime.php', ['status' => $status]);
            $this->assertFalse($row['actions']['cancel']);
        }
        foreach (['list', 'detail'] as $mode) {
            $row = $this->runFixture('transaction-instant-ui-runtime.php', ['mode' => $mode, 'is_deficit' => 1]);
            $contractCase = 'case ' . count( $expectedCases );
            $expectedCases[$contractCase . " / " . count( $expectedCases )] = [
                '1: row[actions][cancel]' => false,
                '2: row[actions][adjustDeficit]' => true,
                '3: row[actions][cancelDeficit]' => true,
                ];
            $actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
                '1: row[actions][cancel]' => $row['actions']['cancel'],
                '2: row[actions][adjustDeficit]' => $row['actions']['adjustDeficit'],
                '3: row[actions][cancelDeficit]' => $row['actions']['cancelDeficit'],
                ];
        }
        $this->assertSame( $expectedCases, $actualCases );
    }

}
