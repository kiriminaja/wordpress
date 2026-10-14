<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InstantDetailMapDataRuntimeTest extends TestCase {
    public function test_durable_booking_points_render_and_only_enable_a_nonempty_live_url(): void {
        $points = [[38.5, -120.2], [40.7, -120.95], [43.252, -126.453]];
        $expected = $actual = [];
        foreach (['detail', 'fallback'] as $mode) {
            foreach (['no URL' => '', 'live URL' => 'https://tracking.example.test/shipment'] as $name => $url) {
                $detail = $this->detail(['shipping_info' => json_encode(['instant_route_points' => $points]), 'live_tracking_url' => $url], $mode);
                $expected[$mode][$name] = ['mode' => 'recorded', 'points' => $points, 'live URL' => $url];
                $actual[$mode][$name] = ['mode' => $detail['shipment']['routeMap']['mode'], 'points' => $detail['shipment']['routeMap']['points'], 'live URL' => $detail['shipment']['liveTrackingUrl']];
            }
        }
        $this->assertSame($expected, $actual);
    }

    public function test_invalid_booking_points_or_present_invalid_tracking_payload_never_enable_tracking(): void {
        $expected = $actual = [];
        $pointCases = ['null' => null, 'empty string' => '', 'encoded instead of points' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@', 'empty array' => [], 'single point' => [[0, 0]], 'latitude out of range' => [[91, 0], [0, 1]], 'string coordinate' => [['0', 0], [1, 1]], 'too many points' => array_fill(0, 10001, [0, 0])];
        $payloadCases = ['null' => null, 'empty' => '', 'broken JSON' => '{broken', 'invalid result' => json_encode(['polyline' => [[0, 0], [1, 1]], 'result' => null])];
        foreach (['detail', 'fallback'] as $mode) {
            foreach ($pointCases as $name => $points) {
                $detail = $this->detail(['shipping_info' => json_encode(['instant_route_points' => $points]), 'live_tracking_url' => 'https://tracking.example.test/shipment'], $mode);
                $expected[$mode]['points/' . $name] = ['mode' => 'unavailable', 'live URL' => '', 'track' => false];
                $actual[$mode]['points/' . $name] = ['mode' => $detail['shipment']['routeMap']['mode'], 'live URL' => $detail['shipment']['liveTrackingUrl'], 'track' => $detail['actions']['track']];
            }
            foreach ($payloadCases as $name => $payload) {
                $detail = $this->detail(['shipping_info' => json_encode(['instant_route_points' => [[0, 0], [1, 1]]]), 'instant_tracking_payload' => $payload, 'live_tracking_url' => 'https://tracking.example.test/shipment'], $mode);
                $expected[$mode]['payload/' . $name] = ['mode' => 'unavailable', 'track' => false];
                $actual[$mode]['payload/' . $name] = ['mode' => $detail['shipment']['routeMap']['mode'], 'track' => $detail['actions']['track']];
            }
        }
        $this->assertSame($expected, $actual);
    }

    private function detail(array $payload, string $mode = 'detail'): array {
        $payload = array_merge(['delivery_type' => 'instant', 'service' => 'gosend'], $payload, ['mode' => $mode]);
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/transaction-instant-ui-runtime.php') . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR)));
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    private function saved(): array {
        return [
            'shipment_location_snapshot' => json_encode(['origin_latitude' => '0', 'origin_longitude' => '110.25', 'private_token' => 'do-not-expose']),
            'destination_latitude' => '-6.2', 'destination_longitude' => '106.8',
            'live_tracking_url' => 'https://tracking.example.test/shipment',
        ];
    }

    public function test_saved_coordinates_illustrate_without_enabling_tracking_in_prepare_and_fallback(): void {
        $expected = $actual = [];
        foreach (['detail', 'fallback'] as $mode) {
            $detail = $this->detail($this->saved(), $mode);
            $map = $detail['shipment']['routeMap'];
            $expected[$mode] = ['fields' => ['origin', 'destination', 'points', 'mode'], 'mode' => 'illustration', 'origin' => ['latitude' => 0, 'longitude' => 110.25], 'points' => [[0, 110.25], [-6.2, 106.8]], 'live URL' => '', 'track' => false, 'private token exposed' => false];
            $actual[$mode] = ['fields' => array_keys($map), 'mode' => $map['mode'], 'origin' => $map['origin'], 'points' => $map['points'], 'live URL' => $detail['shipment']['liveTrackingUrl'], 'track' => $detail['actions']['track'], 'private token exposed' => str_contains(json_encode($map), 'do-not-expose')];
        }
        $this->assertSame($expected, $actual);
    }

    public function test_authoritative_invalid_pairs_do_not_use_aliases_or_other_snapshots(): void {
        $alternatives = json_encode(['instant_destination' => ['latitude' => -7, 'longitude' => 108], 'instant_request_snapshot' => ['origin' => ['latitude' => -8, 'longitude' => 109]]]);
        $expected = $actual = [];
        foreach (['null' => null, 'empty' => '', 'boolean' => true, 'boolean string' => 'true', 'hex' => '0x10', 'out of range' => '91', 'array' => [], 'NaN' => 'NaN'] as $name => $invalid) {
            foreach (['detail', 'fallback'] as $mode) {
                $payload = array_merge($this->saved(), ['destination_latitude' => $invalid, 'shipping_info' => $alternatives]);
                $destination = $this->detail($payload, $mode)['shipment']['routeMap']['destination'];
                $payload = array_merge($this->saved(), ['shipment_location_snapshot' => json_encode(['origin_latitude' => $invalid, 'origin_longitude' => 110, 'latitude' => -6, 'longitude' => 107]), 'shipping_info' => $alternatives]);
                $map = $this->detail($payload, $mode)['shipment']['routeMap'];
                $expected[$mode][$name] = ['destination' => null, 'origin' => null, 'mode' => 'unavailable', 'points' => []];
                $actual[$mode][$name] = ['destination' => $destination, 'origin' => $map['origin'], 'mode' => $map['mode'], 'points' => $map['points']];
            }
        }
        foreach (['empty object' => '{}', 'broken JSON' => '{broken', 'partial pair' => '{"origin_latitude":0}'] as $name => $snapshot) {
            $map = $this->detail(array_merge($this->saved(), ['shipment_location_snapshot' => $snapshot, 'shipping_info' => $alternatives]))['shipment']['routeMap'];
            $expected['snapshot'][$name] = null;
            $actual['snapshot'][$name] = $map['origin'];
        }
        $this->assertSame($expected, $actual);
    }

    public function test_recorded_polyline_can_render_without_saved_delivery_endpoints(): void {
        foreach (['detail', 'fallback'] as $mode) {
            $map = $this->detail(['instant_tracking_payload' => json_encode(['result' => ['polyline' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@']])], $mode)['shipment']['routeMap'];
            $this->assertSame(['mode' => 'recorded', 'origin' => null, 'destination' => null, 'points' => [[38.5, -120.2], [40.7, -120.95], [43.252, -126.453]]], ['mode' => $map['mode'], 'origin' => $map['origin'], 'destination' => $map['destination'], 'points' => $map['points']]);
        }
    }

    public function test_empty_or_invalid_route_uses_only_saved_illustration(): void {
        $actual = [];
        foreach (['empty array' => [], 'empty string' => '', 'invalid encoding' => 'not-a-polyline', 'out of range' => [[91, 100], [0, 0]], 'associative coordinate' => [['lat' => '0', 'lng' => 1], [2, 3]]] as $name => $polyline) {
            $map = $this->detail(array_merge($this->saved(), ['instant_tracking_payload' => json_encode(['polyline' => $polyline])]))['shipment']['routeMap'];
            $actual[$name] = $map['mode'];
        }
        $map = $this->detail(array_merge($this->saved(), ['instant_tracking_payload' => json_encode(['polyline' => [[0, 0], [1, 1]], 'result' => null])]))['shipment']['routeMap'];
        $actual['invalid result'] = $map['mode'];
        $this->assertSame(array_fill_keys(['empty array', 'empty string', 'invalid encoding', 'out of range', 'associative coordinate', 'invalid result'], 'illustration'), $actual);
    }

    public function test_snapshots_are_used_only_when_higher_priority_pairs_are_absent(): void {
        $payload = ['instant_request_snapshot' => ['origin' => ['latitude' => 0, 'longitude' => 0]], 'shipping_info' => json_encode(['instant_destination' => ['latitude' => 0, 'longitude' => 1]])];
        $map = $this->detail($payload)['shipment']['routeMap'];
        $payload['destination_latitude'] = 0;
        $this->assertSame(['mode' => 'illustration', 'points' => [[0, 0], [0, 1]], 'partial destination' => null, 'empty mode' => 'unavailable'], ['mode' => $map['mode'], 'points' => $map['points'], 'partial destination' => $this->detail($payload)['shipment']['routeMap']['destination'], 'empty mode' => $this->detail([])['shipment']['routeMap']['mode']]);
    }

    public function test_express_has_no_route_map_even_with_route_and_coordinates(): void {
        foreach (['detail', 'fallback'] as $mode) {
            $payload = array_merge($this->saved(), ['delivery_type' => 'express', 'service' => 'jne', 'instant_tracking_payload' => json_encode(['polyline' => [[0, 0], [1, 1]]])]);
            $this->assertNull($this->detail($payload, $mode)['shipment']['routeMap']);
        }
    }
}
