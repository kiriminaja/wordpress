<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use KiriminAjaOfficial\Services\InstantDeliveryCoverage;

final class InstantDeliveryCoverageRuntimeTest extends TestCase {
    public static function setUpBeforeClass(): void {
        if (!defined('ABSPATH')) { define('ABSPATH', PLUGIN_DIR); }
        require_once PLUGIN_DIR . '/inc/Services/BuyerDestination.php';
        require_once PLUGIN_DIR . '/inc/Services/InstantDeliveryCoverage.php';
    }

    #[Test]
    public function fixed_haversine_limit_is_inclusive_and_handles_zero_and_antimeridian(): void {
        $boundary = rad2deg(40000 / 6371000);
        $this->assertSame(40000, InstantDeliveryCoverage::MAX_METERS);
        $this->assertEqualsWithDelta(40000, InstantDeliveryCoverage::distanceMeters(0, 0, $boundary, 0), 0.000001);
        $this->assertTrue(InstantDeliveryCoverage::covers(0, 0, $boundary, 0));
        $this->assertTrue(InstantDeliveryCoverage::covers(0, 0, rad2deg(39999 / 6371000), 0));
        $this->assertFalse(InstantDeliveryCoverage::covers(0, 0, rad2deg(40001 / 6371000), 0));
        $this->assertSame(0.0, InstantDeliveryCoverage::distanceMeters('0', '-0', 0, 0));
        $this->assertTrue(InstantDeliveryCoverage::covers(0, 179.9, 0, -179.9));
        $this->assertFalse(InstantDeliveryCoverage::covers(0, 179.7, 0, -179.7));
        $this->assertEqualsWithDelta(M_PI * 6371000, InstantDeliveryCoverage::distanceMeters(0, 0, 0, 180), 0.001);
        $this->assertTrue(InstantDeliveryCoverage::covers(-6.2, 106.8, -6.3, 106.9));
        $this->assertFalse(InstantDeliveryCoverage::covers(-7, 106.8, -6.3, 106.9));
    }

    #[Test]
    public function every_axis_rejects_missing_malformed_nonfinite_and_out_of_range_pins(): void {
        foreach ([null, '', ' ', true, false, [], new stdClass(), INF, NAN, '1e2', ' 0', '+0', '00', 'NaN'] as $invalid) {
            foreach (range(0, 3) as $axis) {
                $pins = [0, 0, 0, 0];
                $pins[$axis] = $invalid;
                try {
                    InstantDeliveryCoverage::distanceMeters(...$pins);
                    $this->fail('Malformed pin accepted at axis ' . $axis);
                } catch (InvalidArgumentException $error) {
                    $this->assertNotEmpty($error->getMessage());
                }
            }
        }
        foreach ([[91, 0, 0, 0], [0, 181, 0, 0], [0, 0, -91, 0], [0, 0, 0, -181]] as $pins) {
            try {
                InstantDeliveryCoverage::covers(...$pins);
                $this->fail('Out-of-range pin accepted');
            } catch (InvalidArgumentException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
    }
}
