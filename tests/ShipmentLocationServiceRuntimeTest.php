<?php

use KiriminAjaOfficial\Services\ShipmentLocationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $key, $default = '' ) {
        return $default;
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type ) {
        return '2026-09-14 12:00:00';
    }
}

require_once PLUGIN_DIR . '/inc/Repositories/ShipmentLocationRepository.php';
require_once PLUGIN_DIR . '/inc/Services/ShipmentLocationService.php';

final class ShipmentLocationServiceRuntimeTest extends TestCase {
    private $previousWpdb;

    protected function setUp(): void {
        global $wpdb;
        $this->previousWpdb = $wpdb ?? null;
    }

    protected function tearDown(): void {
        global $wpdb;
        $wpdb = $this->previousWpdb;
    }

    #[Test]
    public function active_selected_location_is_returned_in_canonical_origin_shape(): void {
        global $wpdb;
        $wpdb = new ShipmentLocationServiceWpdbFake(
            array(
                1 => $this->location( 1, 'Default Warehouse', 1, 1 ),
                2 => $this->location( 2, 'Bandung Hub', 0, 1 ),
            )
        );

        $origin = ( new ShipmentLocationService() )->originForLocation( 2 );

        $this->assertSame( 2, $origin['id'] );
        $this->assertSame( 2, $origin['location_id'] );
        $this->assertSame( 'Bandung Hub', $origin['origin_name'] );
        $this->assertSame( 3201010, $origin['origin_sub_district_id'] );
        $this->assertSame( '40123', $origin['origin_zip_code'] );
        $this->assertSame( '-6.91', $origin['origin_latitude'] );
        $this->assertSame( '107.61', $origin['origin_longitude'] );
    }

    #[Test]
    public function missing_or_inactive_location_falls_back_to_active_default(): void {
        global $wpdb;
        $wpdb = new ShipmentLocationServiceWpdbFake(
            array(
                1 => $this->location( 1, 'Default Warehouse', 1, 1 ),
                2 => $this->location( 2, 'Inactive Hub', 0, 0 ),
            )
        );

        $service = new ShipmentLocationService();

        $this->assertSame( 1, $service->originForLocation( 999 )['id'] );
        $this->assertSame( 1, $service->originForLocation( 2 )['id'] );
        $this->assertSame( 1, $service->originForLocation( 0 )['id'] );
    }

    #[Test]
    public function canonical_snapshot_keeps_all_pickup_fields_and_address_formatter_is_stable(): void {
        $location = (object) $this->location( 7, 'Surabaya Hub', 0, 1 );
        $service  = new ShipmentLocationService();
        $origin   = $service->locationToOrigin( $location );

        $this->assertSame(
            array(
                'id',
                'location_id',
                'location_name',
                'origin_name',
                'origin_phone',
                'origin_address',
                'origin_address_2',
                'origin_sub_district',
                'origin_city',
                'origin_state',
                'origin_country',
                'origin_sub_district_id',
                'origin_zip_code',
                'origin_latitude',
                'origin_longitude',
            ),
            array_keys( $origin )
        );
        $this->assertSame(
            'Jl. Example 7 · Lantai 2 · Sukolilo, Surabaya, Jawa Timur, 40123',
            $service->formatAddress( $location )
        );
        $this->assertSame(
            'Jl. Example 7 · Lantai 2 · Sukolilo, Surabaya, Jawa Timur, 40123',
            $service->formatAddress( $origin )
        );
    }

    #[Test]
    public function invalid_location_maps_to_empty_origin_without_php_notices(): void {
        $service = new ShipmentLocationService();

        $this->assertSame( array(), $service->locationToOrigin( null ) );
        $this->assertSame( array(), $service->locationToOrigin( array( 'id' => 1 ) ) );
    }

    private function location( int $id, string $name, int $default, int $active ): array {
        return array(
            'id'                => $id,
            'name'              => $name,
            'phone'             => '08123456789',
            'address'           => 'Jl. Example ' . $id,
            'address_2'         => 'Lantai 2',
            'sub_district_name' => 'Sukolilo',
            'city'              => 'Surabaya',
            'state'             => 'Jawa Timur',
            'country'           => 'ID',
            'sub_district_id'   => 3201010,
            'zip_code'          => '40123',
            'latitude'          => '-6.91',
            'longitude'         => '107.61',
            'is_default'        => $default,
            'is_active'         => $active,
        );
    }
}

final class ShipmentLocationServiceWpdbFake {
    public string $prefix = 'wp_';
    public string $last_error = '';
    public array $rows;

    public function __construct( array $rows ) {
        $this->rows = $rows;
    }

    public function prepare( $query, ...$values ) {
        foreach ( $values as $value ) {
            $query = preg_replace( '/%d/', (string) (int) $value, $query, 1 );
        }
        return $query;
    }

    public function get_row( $query ) {
        if ( preg_match( '/WHERE id = (\d+)/', $query, $matches ) ) {
            $id = (int) $matches[1];
            return isset( $this->rows[$id] ) ? (object) $this->rows[$id] : null;
        }

        foreach ( $this->rows as $row ) {
            if ( str_contains( $query, 'is_default = 1' ) && 1 !== (int) $row['is_default'] ) {
                continue;
            }
            if ( str_contains( $query, 'is_active = 1' ) && 1 !== (int) $row['is_active'] ) {
                continue;
            }
            return (object) $row;
        }
        return null;
    }

    public function get_var( $query ) {
        return count( $this->rows );
    }

    public function update( $table, $changes, $condition ) {
        $id = (int) ( $condition['id'] ?? 0 );
        if ( ! isset( $this->rows[$id] ) ) {
            return 0;
        }
        $this->rows[$id] = array_merge( $this->rows[$id], $changes );
        return 1;
    }

    public function query( $query ) {
        return 1;
    }
}
