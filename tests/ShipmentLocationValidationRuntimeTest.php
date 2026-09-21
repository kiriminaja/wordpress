<?php

use KiriminAjaOfficial\Controllers\SettingController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

require_once PLUGIN_DIR . '/inc/Controllers/SettingController.php';

final class ShipmentLocationValidationRuntimeTest extends TestCase {
    #[Test]
    public function complete_location_payload_is_valid(): void {
        $this->assertTrue( $this->validate( $this->validPayload() ) );
    }

    #[Test]
    #[DataProvider( 'invalidPayloadProvider' )]
    public function incomplete_or_invalid_location_payload_is_rejected( array $changes ): void {
        $this->assertFalse( $this->validate( array_merge( $this->validPayload(), $changes ) ) );
    }

    public static function invalidPayloadProvider(): array {
        return array(
            'missing name'          => array( array( 'name' => '' ) ),
            'missing phone'         => array( array( 'phone' => '' ) ),
            'missing address'       => array( array( 'address' => '' ) ),
            'missing area'          => array( array( 'sub_district_id' => 0 ) ),
            'missing postcode'      => array( array( 'zip_code' => '' ) ),
            'non-numeric latitude'  => array( array( 'latitude' => 'north' ) ),
            'latitude too high'     => array( array( 'latitude' => '91' ) ),
            'latitude too low'      => array( array( 'latitude' => '-91' ) ),
            'non-numeric longitude' => array( array( 'longitude' => 'east' ) ),
            'longitude too high'    => array( array( 'longitude' => '181' ) ),
            'longitude too low'     => array( array( 'longitude' => '-181' ) ),
        );
    }

    private function validate( array $payload ): bool {
        $controller = new SettingController();
        $method     = new ReflectionMethod( SettingController::class, 'isValidShipmentLocationData' );
        $method->setAccessible( true );

        return (bool) $method->invoke( $controller, $payload );
    }

    private function validPayload(): array {
        return array(
            'name'            => 'Main Warehouse',
            'phone'           => '081234567890',
            'address'         => 'Jalan Utama 1',
            'sub_district_id' => 123,
            'zip_code'        => '12345',
            'latitude'        => '-6.200000',
            'longitude'       => '106.816666',
        );
    }
}
