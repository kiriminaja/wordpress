<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Isolated route augmentation without shared dispatch fixture or WordPress state. */
final class InstantBookingRouteRuntimeTest extends TestCase {
    private function booking(array $remote, array $snapshot = ['custom' => 'keep', 'instant_items' => [['name' => 'Reviewed item']]]): array {
        $code = <<<'PHP'
define('ABSPATH', __DIR__);
function wp_json_encode($value) { return json_encode($value); }
$root = $argv[1];
spl_autoload_register(static function ($class) use ($root) {
    $prefix = 'KiriminAjaOfficial\\';
    if (str_starts_with($class, $prefix)) {
        require $root . '/inc/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
$input = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
$class = new ReflectionClass(\KiriminAjaOfficial\Services\InstantDispatchService::class);
$method = $class->getMethod('bookingChanges');
try {
    $metadata = ['shipping_info' => json_encode($input['snapshot']), 'vehicle' => 'motor'];
    $changes = $method->invoke($class->newInstanceWithoutConstructor(), ['package' => ['order_id' => 'KA-1', 'service' => 'gosend', 'service_type' => 'instant'], 'price' => 15000], $input['remote'], $metadata);
    echo json_encode(['changes' => $changes], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    echo json_encode(['error' => get_class($error)], JSON_THROW_ON_ERROR);
}
PHP;
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' ' . escapeshellarg(PLUGIN_DIR) . ' ' . escapeshellarg(json_encode(['remote' => $remote, 'snapshot' => $snapshot], JSON_THROW_ON_ERROR)));
        $this->assertNotNull($output);
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    private function identity(): array {
        return ['order_id' => 'KA-1', 'service' => 'gosend', 'service_type' => 'instant'];
    }

    public function test_verified_booking_augments_only_reviewed_snapshot_with_decoded_route(): void {
        foreach (['poly_line', 'polyline'] as $field) {
            $result = $this->booking($this->identity() + [$field => '_p~iF~ps|U_ulLnnqC_mqNvxq`@', 'live_track_url' => '', 'message' => 'private upstream message', 'recipient' => 'private recipient']);
            $snapshot = json_decode($result['changes']['shipping_info'], true, 512, JSON_THROW_ON_ERROR);
            $this->assertEquals([[38.5, -120.2], [40.7, -120.95], [43.252, -126.453]], $snapshot['instant_route_points']);
            $this->assertSame('keep', $snapshot['custom']);
            $this->assertSame([['name' => 'Reviewed item']], $snapshot['instant_items']);
            $this->assertSame(15000, $result['changes']['shipping_cost']);
            $this->assertArrayNotHasKey('instant_tracking_payload', $result['changes']);
            foreach (['poly_line', 'polyline', 'private upstream message', 'private recipient', '_p~iF'] as $raw) {
                $this->assertStringNotContainsString($raw, json_encode($result));
            }
        }
    }

    public function test_malformed_and_excessive_routes_do_not_invalidate_booking_or_change_snapshot(): void {
        foreach ([null, '', 'malformed', '_p~iF~ps|U_', [[91, 0], [0, 1]], array_fill(0, 10001, [0, 0]), str_repeat('??', 10001)] as $route) {
            $result = $this->booking($this->identity() + ['poly_line' => $route]);
            $this->assertArrayHasKey('changes', $result);
            $snapshot = json_decode($result['changes']['shipping_info'], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(['custom' => 'keep', 'instant_items' => [['name' => 'Reviewed item']]], $snapshot);
        }
        $this->assertArrayHasKey('changes', $this->booking($this->identity()));
        $result = $this->booking($this->identity() + ['polyline' => array_fill(0, 10000, [0, 0])]);
        $this->assertCount(10000, json_decode($result['changes']['shipping_info'], true)['instant_route_points']);
    }

    public function test_route_cannot_bypass_booking_identity_verification(): void {
        foreach (['order_id', 'service', 'service_type'] as $field) {
            $remote = $this->identity() + ['poly_line' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@'];
            $remote[$field] = 'wrong';
            $this->assertSame(['error' => RuntimeException::class], $this->booking($remote));
        }
    }
}
