<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InstantDetailMapDataRuntimeTest extends TestCase {
    public function test_durable_booking_points_render_and_only_enable_a_nonempty_live_url(): void {
        $points = [[38.5, -120.2], [40.7, -120.95], [43.252, -126.453]];
        foreach (['detail', 'fallback'] as $mode) {
            foreach (['', 'https://tracking.example.test/shipment'] as $url) {
                $detail = $this->detail(['shipping_info' => json_encode(['instant_route_points' => $points]), 'live_tracking_url' => $url], $mode);
                $this->assertSame('recorded', $detail['shipment']['routeMap']['mode']);
                $this->assertEquals($points, $detail['shipment']['routeMap']['points']);
                $this->assertSame($url, $detail['shipment']['liveTrackingUrl']);
            }
        }
    }

    public function test_invalid_booking_points_or_present_invalid_tracking_payload_never_enable_tracking(): void {
        foreach (['detail', 'fallback'] as $mode) {
            foreach ([null, '', '_p~iF~ps|U_ulLnnqC_mqNvxq`@', [], [[0, 0]], [[91, 0], [0, 1]], [['0', 0], [1, 1]], array_fill(0, 10001, [0, 0])] as $points) {
                $detail = $this->detail(['shipping_info' => json_encode(['instant_route_points' => $points]), 'live_tracking_url' => 'https://tracking.example.test/shipment'], $mode);
                $this->assertSame('unavailable', $detail['shipment']['routeMap']['mode']);
                $this->assertSame('', $detail['shipment']['liveTrackingUrl']);
                $this->assertFalse($detail['actions']['track']);
            }
            foreach ([null, '', '{broken', json_encode(['polyline' => [[0, 0], [1, 1]], 'result' => null])] as $payload) {
                $detail = $this->detail(['shipping_info' => json_encode(['instant_route_points' => [[0, 0], [1, 1]]]), 'instant_tracking_payload' => $payload, 'live_tracking_url' => 'https://tracking.example.test/shipment'], $mode);
                $this->assertSame('unavailable', $detail['shipment']['routeMap']['mode']);
                $this->assertFalse($detail['actions']['track']);
            }
        }
    }

    private function detail(array $payload, string $mode = 'detail'): array {
        $payload = array_merge(['delivery_type' => 'instant', 'service' => 'gosend'], $payload, ['mode' => $mode]);
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/transaction-instant-ui-runtime.php') . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR)));
        $this->assertNotNull($output);
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
        foreach (['detail', 'fallback'] as $mode) {
            $detail = $this->detail($this->saved(), $mode);
            $map = $detail['shipment']['routeMap'];
            $this->assertSame(['origin', 'destination', 'points', 'mode'], array_keys($map));
            $this->assertSame('illustration', $map['mode']);
            $this->assertEquals(['latitude' => 0, 'longitude' => 110.25], $map['origin']);
            $this->assertEquals([[0, 110.25], [-6.2, 106.8]], $map['points']);
            $this->assertSame('', $detail['shipment']['liveTrackingUrl']);
            $this->assertFalse($detail['actions']['track']);
            $this->assertStringNotContainsString('do-not-expose', json_encode($map));
        }
    }

    public function test_authoritative_invalid_pairs_do_not_use_aliases_or_other_snapshots(): void {
        $alternatives = json_encode(['instant_destination' => ['latitude' => -7, 'longitude' => 108], 'instant_request_snapshot' => ['origin' => ['latitude' => -8, 'longitude' => 109]]]);
        foreach ([null, '', true, 'true', '0x10', '91', [], 'NaN'] as $invalid) {
            foreach (['detail', 'fallback'] as $mode) {
                $payload = array_merge($this->saved(), ['destination_latitude' => $invalid, 'shipping_info' => $alternatives]);
                $this->assertNull($this->detail($payload, $mode)['shipment']['routeMap']['destination']);
                $payload = array_merge($this->saved(), ['shipment_location_snapshot' => json_encode(['origin_latitude' => $invalid, 'origin_longitude' => 110, 'latitude' => -6, 'longitude' => 107]), 'shipping_info' => $alternatives]);
                $map = $this->detail($payload, $mode)['shipment']['routeMap'];
                $this->assertNull($map['origin']);
                $this->assertSame('unavailable', $map['mode']);
                $this->assertSame([], $map['points']);
            }
        }
        foreach (['{}', '{broken', '{"origin_latitude":0}'] as $snapshot) {
            $map = $this->detail(array_merge($this->saved(), ['shipment_location_snapshot' => $snapshot, 'shipping_info' => $alternatives]))['shipment']['routeMap'];
            $this->assertNull($map['origin']);
        }
    }

    public function test_recorded_polyline_can_render_without_saved_delivery_endpoints(): void {
        foreach (['detail', 'fallback'] as $mode) {
            $map = $this->detail(['instant_tracking_payload' => json_encode(['result' => ['polyline' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@']])], $mode)['shipment']['routeMap'];
            $this->assertSame('recorded', $map['mode']);
            $this->assertNull($map['origin']);
            $this->assertNull($map['destination']);
            $this->assertEquals([[38.5, -120.2], [40.7, -120.95], [43.252, -126.453]], $map['points']);
        }
    }

    public function test_empty_or_invalid_route_uses_only_saved_illustration(): void {
        foreach ([[], '', 'not-a-polyline', [[91, 100], [0, 0]], [['lat' => '0', 'lng' => 1], [2, 3]]] as $polyline) {
            $map = $this->detail(array_merge($this->saved(), ['instant_tracking_payload' => json_encode(['polyline' => $polyline])]))['shipment']['routeMap'];
            $this->assertSame('illustration', $map['mode']);
        }
        $map = $this->detail(array_merge($this->saved(), ['instant_tracking_payload' => json_encode(['polyline' => [[0, 0], [1, 1]], 'result' => null])]))['shipment']['routeMap'];
        $this->assertSame('illustration', $map['mode']);
    }

    public function test_snapshots_are_used_only_when_higher_priority_pairs_are_absent(): void {
        $payload = ['instant_request_snapshot' => ['origin' => ['latitude' => 0, 'longitude' => 0]], 'shipping_info' => json_encode(['instant_destination' => ['latitude' => 0, 'longitude' => 1]])];
        $map = $this->detail($payload)['shipment']['routeMap'];
        $this->assertSame('illustration', $map['mode']);
        $this->assertEquals([[0, 0], [0, 1]], $map['points']);
        $payload['destination_latitude'] = 0;
        $this->assertNull($this->detail($payload)['shipment']['routeMap']['destination']);
        $this->assertSame('unavailable', $this->detail([])['shipment']['routeMap']['mode']);
    }

    public function test_express_has_no_route_map_even_with_route_and_coordinates(): void {
        foreach (['detail', 'fallback'] as $mode) {
            $payload = array_merge($this->saved(), ['delivery_type' => 'express', 'service' => 'jne', 'instant_tracking_payload' => json_encode(['polyline' => [[0, 0], [1, 1]]])]);
            $this->assertNull($this->detail($payload, $mode)['shipment']['routeMap']);
        }
    }
}
