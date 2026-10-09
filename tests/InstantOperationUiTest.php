<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use KiriminAjaOfficial\Services\InstantShipmentState;
use KiriminAjaOfficial\Services\InstantTrackingPresentation;

// The shared validators are pure apart from these WordPress URL helpers.
// Match the isolated renderer fixture without booting WordPress or its services.
if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url, $protocols = null): string {
        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), $protocols ?? ['http', 'https'], true) ? $url : '';
    }
}

final class InstantOperationUiTest extends TestCase {
    private function row(array $payload): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/transaction-instant-ui-runtime.php') . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR)));

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
        $valid = array(
            'service'             => array( 'gosend', 'grab_express' ),
            'status'              => array( 'pending', 'request_pickup' ),
            'instant_status_code' => array( 100, 101, 105, 110, '100', '101', '105', '110' ),
            'awb'                 => array( '', null, 'BOOKED-AWB' ),
        );
        $expectedStates = $actualStates = [];
        foreach ( $valid as $field => $values ) {
            foreach ( $values as $value ) {
                $payload = array_replace( $this->confirmedBooking(), array( $field => $value ) );
                $scenario = $field . ' = ' . json_encode($value);
                $expectedStates[$scenario] = true;
                $actualStates[$scenario] = InstantShipmentState::canCancel($payload);
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
            $scenario = 'not cancelable: ' . json_encode($invalid);
            $expectedStates[$scenario] = false;
            $actualStates[$scenario] = InstantShipmentState::canCancel(array_replace($this->confirmedBooking(), $invalid));
        }
        $this->assertSame($expectedStates, $actualStates, 'Cancellation lifecycle guards');
        foreach ( array( 'list', 'detail' ) as $mode ) {
            $row = $this->row( array_replace( $this->confirmedBooking(), array( 'mode' => $mode, 'is_deficit' => 1 ) ) );
            $terminal = $this->row(array_replace($this->confirmedBooking(), ['mode' => $mode, 'instant_status_code' => 200]));
            $this->assertSame(
                ['confirmed actions' => [true, false, false, false], 'terminal cancel' => false],
                ['confirmed actions' => array_map(static fn($action) => $row['actions'][$action], ['cancel', 'changeOrigin', 'adjustDeficit', 'cancelDeficit']), 'terminal cancel' => $terminal['actions']['cancel']],
                "$mode / cancellation actions"
            );
        }
    }

    public function test_only_uncertain_bookings_offer_reconciliation(): void {
        $payload = array_replace( $this->confirmedBooking(), array( 'status' => 'pending', 'instant_status_code' => null, 'instant_payment_id' => '', 'awb' => '' ) );
        foreach ( array( 'gosend', 'grab_express' ) as $service ) {
            $this->assertTrue( InstantShipmentState::canRecheck( array_replace( $payload, array( 'service' => $service ) ) ) );
        }
        foreach ( array( 'instant_status_code' => 0, 'instant_payment_id' => 'PAY-1', 'awb' => 'AWB-1', 'status' => 'new', 'order_id' => 'bad id', 'service' => 'jne' ) as $field => $value ) {
            $this->assertFalse( InstantShipmentState::canRecheck( array_replace( $payload, array( $field => $value ) ) ), $field );
        }
        foreach ( array( 'list', 'detail', 'fallback' ) as $mode ) {
            $this->assertSame(
                [
                'uncertain booking' => 'fallback' !== $mode,
                'payment already confirmed' => false,
                ],
                [
                'uncertain booking' => $this->row( $payload + array( 'mode' => $mode ) )['actions']['reconcile'],
                'payment already confirmed' => $this->row( array_replace( $payload, array( 'mode' => $mode, 'instant_payment_id' => 'PAY-1' ) ) )['actions']['reconcile'],
                ]
            );
        }
    }

    private const ROUTE = '_p~iF~ps|U_ulLnnqC_mqNvxq`@';
    private const TRACKING_URL = 'https://tracking.example.test/KA-1';

    private function trackingContract(array $row, string $mode): array {
        $contract = [
            'track' => $row['actions']['track'],
            'reconcile' => $row['actions']['reconcile'],
            'url' => $mode === 'list' ? $row['actions']['liveTrackingUrl'] : $row['shipment']['liveTrackingUrl'],
        ];
        if ($mode !== 'list') {
            $contract += ['supportsLiveTracking' => $row['supportsLiveTracking'], 'steps' => $row['steps']];
        }
        return $contract;
    }

    public function test_tracking_service_and_status_gates_in_list_and_detail(): void {
        // These renderers each own a gate. Vary one input at a time, not every
        // service/status pair; fallback has no remote actions (tested below).
        $cases = [];
        foreach (['gosend', 'grab_express', 'borzo', 'jne'] as $service) {
            $cases["service: $service"] = [['service' => $service], in_array($service, ['gosend', 'grab_express'], true)];
        }
        foreach (['new', 'pending', 'shipped', 'finished', 'canceled'] as $status) {
            $cases["status: $status"] = [['status' => $status], $status !== 'new'];
        }
        foreach (['list', 'detail'] as $mode) {
            foreach ($cases as $name => [$changes, $track]) {
                $row = $this->row(array_replace($this->confirmedBooking(), $changes, [
                    'mode' => $mode, 'live_tracking_url' => self::TRACKING_URL,
                    'instant_tracking_payload' => json_encode(['result' => ['polyline' => self::ROUTE]], JSON_THROW_ON_ERROR),
                ]));
                $expected = ['track' => $track, 'reconcile' => false, 'url' => self::TRACKING_URL];
                if ($mode === 'detail') {
                    $expected += ['supportsLiveTracking' => false, 'steps' => []];
                }
                $this->assertSame($expected, $this->trackingContract($row, $mode), "$mode / $name");
            }
        }
    }

    public function test_tracking_links_expose_only_valid_credential_free_http_urls(): void {
        $cases = [
            'HTTPS with query' => ['https://tracking.example.test/KA-1?token=abc', true],
            'HTTP' => ['http://tracking.example.test/KA-1', true],
            'username and password' => ['https://user:secret@tracking.example.test/KA-1', false],
            'username only' => ['https://user@tracking.example.test/KA-1', false],
            'script scheme' => ['javascript:alert(1)', false],
            'FTP scheme' => ['ftp://tracking.example.test/KA-1', false],
            'scheme relative' => ['//tracking.example.test/KA-1', false],
            'relative path' => ['/KA-1', false],
            'control byte' => ["https://tracking.example.test/KA-1\n", false],
            'space' => ['https://tracking.example.test/a b', false],
            'backslash host confusion' => ['https://tracking.example.test\\@evil.test/KA-1', false],
            'empty' => ['', false],
            'missing host' => ['https:///KA-1', false],
            'overlong URL' => ['https://tracking.example.test/' . str_repeat('a', 2048), false],
            'non-string' => [null, false],
        ];
        foreach ($cases as $name => [$url, $valid]) {
            // Every renderer delegates to this same presentation/URL validator.
            $row = (object) ['live_tracking_url' => $url, 'instant_tracking_payload' => ['polyline' => self::ROUTE]];
            $this->assertSame($valid ? $url : '', InstantTrackingPresentation::trackingUrl($row), $name);
        }
    }

    public function test_empty_or_malformed_routes_never_expose_tracking_even_with_a_safe_url(): void {
        $routes = [
            'missing polyline' => null, 'empty string' => '', 'whitespace' => ' ',
            'JSON object string' => '{}', 'JSON array string' => '[]', 'JSON null string' => 'null',
            'empty points' => [], 'one empty point' => [[]], 'empty point pair' => [[], []],
            'one point' => [[1, 2]], 'latitude out of bounds' => [[91, 2], [3, 4]],
            'longitude out of bounds' => [[1, 181], [3, 4]], 'non-numeric coordinate' => [[1, 'NaN'], [3, 4]],
            'numeric string' => [['1', 2], [3, 4]], 'non-finite coordinate' => [[INF, 2], [3, 4]],
            'non-list route' => ['start' => [1, 2], 'end' => [3, 4]],
            'scalar point' => [1, 2], 'missing longitude' => [['lat' => 1], ['lat' => 2]],
            'too many points' => array_fill(0, 10001, [1, 2]),
            'route URL' => 'https://tracking.example.test/route', 'arbitrary text' => 'not a route',
            'encoded one point' => '_p~iF~ps|U', 'truncated encoded point' => '_p~iF~ps|U_',
            'overlong encoding' => str_repeat('?', 100001), 'encoded point limit' => str_repeat('??', 10001),
            'encoded integer overflow' => str_repeat('~', 8) . '?',
        ];
        foreach ($routes as $name => $polyline) {
            $this->assertSame(
                ['points' => [], 'url' => ''],
                $this->presentationContract((object) ['live_tracking_url' => self::TRACKING_URL, 'instant_tracking_payload' => ['result' => ['polyline' => $polyline]]]),
                $name
            );
        }
        $payloads = [
            'malformed JSON' => '{bad json', 'null envelope' => '{"result":null}',
            'coordinates are not a route' => ['result' => ['courier' => ['coords' => [[1, 2], [3, 4]]], 'origin' => ['lat' => 1, 'long' => 2], 'destination' => ['lat' => 3, 'long' => 4]]],
            'envelope overrides top-level route' => ['polyline' => self::ROUTE, 'result' => ['polyline' => []]],
            'present null payload overrides snapshot' => null,
        ];
        foreach ($payloads as $name => $payload) {
            $this->assertSame(['points' => [], 'url' => ''], $this->presentationContract((object) [
                'instant_tracking_payload' => $payload, 'live_tracking_url' => self::TRACKING_URL,
                'shipping_info' => ['instant_route_points' => [[1, 2], [3, 4]]],
            ]), $name);
        }
    }

    private function presentationContract(object $row): array {
        return ['points' => InstantTrackingPresentation::routePoints($row), 'url' => InstantTrackingPresentation::trackingUrl($row)];
    }

    public function test_valid_routes_expose_only_the_persisted_safe_url(): void {
        $points = [[-7.8, 110.3], [-7.7, 110.4]];
        $cases = [
            'encoded polyline' => [['instant_tracking_payload' => json_encode(['result' => ['polyline' => self::ROUTE]])], [[38.5, -120.2], [40.7, -120.95], [43.252, -126.453]]],
            'point pairs' => [['instant_tracking_payload' => ['polyline' => $points]], $points],
            'lat/long points' => [['instant_tracking_payload' => ['polyline' => [['lat' => -7.8, 'long' => 110.3], ['lat' => -7.7, 'long' => 110.4]]]], $points],
            'object envelope and lat/lng points' => [['instant_tracking_payload' => (object) ['result' => (object) ['polyline' => [(object) ['lat' => -7.8, 'lng' => 110.3], (object) ['lat' => -7.7, 'lng' => 110.4]]]]], $points],
            'booking snapshot JSON' => [['shipping_info' => json_encode(['instant_route_points' => $points])], $points],
            'booking snapshot array' => [['shipping_info' => ['instant_route_points' => $points]], $points],
            'booking snapshot object' => [['shipping_info' => (object) ['instant_route_points' => $points]], $points],
        ];
        foreach ($cases as $name => [$fields, $expectedPoints]) {
            $this->assertSame(['points' => $expectedPoints, 'url' => self::TRACKING_URL], $this->presentationContract((object) ($fields + ['live_tracking_url' => self::TRACKING_URL])), $name);
        }
    }

    public function test_tracking_renderers_route_valid_and_invalid_presentation(): void {
        $cases = [
            'safe persisted URL' => [self::ROUTE, self::TRACKING_URL, true, self::TRACKING_URL],
            'unsafe persisted URL' => [self::ROUTE, 'https://user:secret@tracking.example.test/KA-1', true, ''],
            'invalid route despite safe URL' => ['not a route', self::TRACKING_URL, false, ''],
            'never use raw payload URL' => [self::ROUTE, null, true, ''],
        ];
        foreach (['list', 'detail', 'fallback'] as $mode) {
            foreach ($cases as $name => [$route, $url, $hasRoute, $expectedUrl]) {
                $payload = array_replace($this->confirmedBooking(), [
                    'mode' => $mode,
                    'instant_tracking_payload' => json_encode(['result' => ['polyline' => $route, 'live_tracking_url' => self::TRACKING_URL]], JSON_THROW_ON_ERROR),
                ]);
                if ($url !== null) {
                    $payload['live_tracking_url'] = $url;
                }
                $expected = ['track' => $mode !== 'fallback' && $hasRoute, 'reconcile' => false, 'url' => $expectedUrl];
                if ($mode !== 'list') {
                    $expected += ['supportsLiveTracking' => false, 'steps' => []];
                }
                $this->assertSame($expected, $this->trackingContract($this->row($payload), $mode), "$mode / $name");
            }
        }
    }

}
