<?php

use KiriminAjaOfficial\Repositories\ShipmentLocationRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', PLUGIN_DIR . '/' );
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $value ) {
        return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : '';
    }
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $value ) {
        return sanitize_text_field( $value );
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type ) {
        return '2026-08-10 12:00:00';
    }
}

require_once PLUGIN_DIR . '/inc/Repositories/ShipmentLocationRepository.php';

final class ShipmentLocationRepositoryRuntimeTest extends TestCase {
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
    public function stale_default_id_is_rejected_without_clearing_current_default(): void {
        global $wpdb;
        $wpdb = new ShipmentLocationWpdbFake(
            array(
                1 => array( 'id' => 1, 'name' => 'Primary', 'is_default' => 1, 'is_active' => 1 ),
                2 => array( 'id' => 2, 'name' => 'Secondary', 'is_default' => 0, 'is_active' => 1 ),
            )
        );

        $repository = new ShipmentLocationRepository();

        $this->assertFalse( $repository->setDefault( 999 ) );
        $this->assertSame( 1, $wpdb->rows[1]['is_default'] );
        $this->assertSame( 0, $wpdb->rows[2]['is_default'] );
        $this->assertNotContains( 'UPDATE_DEFAULTS', $wpdb->events, true );
    }

    #[Test]
    public function all_inactive_rows_are_reported_as_invalid_default_state(): void {
        global $wpdb;
        $wpdb = new ShipmentLocationWpdbFake(
            array(
                1 => array( 'id' => 1, 'name' => 'Inactive', 'is_default' => 0, 'is_active' => 0 ),
            )
        );

        $repository = new ShipmentLocationRepository();

        $this->assertFalse( $repository->ensureDefaultExists() );
    }

    #[Test]
    public function default_location_cannot_be_deleted(): void {
        global $wpdb;
        $wpdb = new ShipmentLocationWpdbFake(
            array(
                1 => array( 'id' => 1, 'name' => 'Primary', 'is_default' => 1, 'is_active' => 1 ),
                2 => array( 'id' => 2, 'name' => 'Secondary', 'is_default' => 0, 'is_active' => 1 ),
            )
        );

        $repository = new ShipmentLocationRepository();

        $this->assertFalse( $repository->delete( 1 ) );
        $this->assertArrayHasKey( 1, $wpdb->rows );
        $this->assertSame( 1, $wpdb->rows[1]['is_default'] );
    }

    #[Test]
    public function ensure_default_promotes_and_flags_first_active_location(): void {
        global $wpdb;
        $wpdb = new ShipmentLocationWpdbFake(
            array(
                3 => array( 'id' => 3, 'name' => 'Inactive', 'is_default' => 0, 'is_active' => 0 ),
                4 => array( 'id' => 4, 'name' => 'Active', 'is_default' => 0, 'is_active' => 1 ),
            )
        );

        $repository = new ShipmentLocationRepository();

        $this->assertTrue( $repository->ensureDefaultExists() );
        $this->assertSame( 1, $wpdb->rows[4]['is_default'] );
        $this->assertSame( 0, $wpdb->rows[3]['is_default'] );
    }

    #[Test]
    public function clear_default_failure_rolls_back_and_reports_failure(): void {
        global $wpdb;
        $wpdb = new ShipmentLocationWpdbFake(
            array(
                1 => array( 'id' => 1, 'name' => 'Primary', 'is_default' => 1, 'is_active' => 1 ),
                2 => array( 'id' => 2, 'name' => 'Secondary', 'is_default' => 0, 'is_active' => 1 ),
            ),
            true
        );

        $repository = new ShipmentLocationRepository();

        $this->assertFalse( $repository->setDefault( 2 ) );
        $this->assertContains( 'ROLLBACK', $wpdb->events, true );
    }

    #[Test]
    public function zero_row_update_for_missing_location_is_failure(): void {
        global $wpdb;
        $wpdb = new ShipmentLocationWpdbFake( array() );

        $repository = new ShipmentLocationRepository();

        $this->assertFalse( $repository->update( 55, array( 'name' => 'Missing' ) ) );
    }

    #[Test]
    public function default_location_cannot_be_deactivated_through_repository(): void {
        global $wpdb;
        $wpdb = new ShipmentLocationWpdbFake(
            array(
                1 => array( 'id' => 1, 'name' => 'Primary', 'is_default' => 1, 'is_active' => 1 ),
            )
        );

        $repository = new ShipmentLocationRepository();

        $this->assertFalse( $repository->update( 1, array( 'is_active' => 0 ) ) );
        $this->assertSame( 1, $wpdb->rows[1]['is_active'] );
    }

    #[Test]
    public function ordinary_location_update_does_not_touch_default_flags(): void {
        global $wpdb;
        $wpdb = new ShipmentLocationWpdbFake(
            array(
                1 => array( 'id' => 1, 'name' => 'Primary', 'is_default' => 1, 'is_active' => 1 ),
                2 => array( 'id' => 2, 'name' => 'Secondary', 'is_default' => 0, 'is_active' => 1 ),
            )
        );

        $repository = new ShipmentLocationRepository();

        $this->assertTrue( $repository->update( 2, array( 'name' => 'Renamed' ) ) );
        $this->assertSame( 'Renamed', $wpdb->rows[2]['name'] );
        $this->assertSame( 1, $wpdb->rows[1]['is_default'] );
        $this->assertNotContains( 'UPDATE_DEFAULTS', $wpdb->events, true );
    }
}

final class ShipmentLocationWpdbFake {
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public array $rows;
    public array $events = array();
    private bool $failDefaultClear;

    public function __construct( array $rows, bool $failDefaultClear = false ) {
        $this->rows             = $rows;
        $this->failDefaultClear = $failDefaultClear;
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

        $rows = $this->rows;
        ksort( $rows );
        foreach ( $rows as $row ) {
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

    public function update( $table, $changes, $condition ) {
        $id = (int) $condition['id'];
        if ( ! isset( $this->rows[$id] ) ) {
            return 0;
        }
        $this->rows[$id] = array_merge( $this->rows[$id], $changes );
        return 1;
    }

    public function delete( $table, $condition ) {
        $id = (int) $condition['id'];
        if ( ! isset( $this->rows[$id] ) ) {
            return 0;
        }
        unset( $this->rows[$id] );
        return 1;
    }

    public function query( $query ) {
        if ( 'START TRANSACTION' === $query || 'COMMIT' === $query || 'ROLLBACK' === $query ) {
            $this->events[] = $query;
            return 1;
        }
        if ( str_contains( $query, 'SET is_default = 0' ) ) {
            $this->events[] = 'UPDATE_DEFAULTS';
            if ( $this->failDefaultClear ) {
                return false;
            }
            preg_match( '/id != (\d+)/', $query, $matches );
            $except = isset( $matches[1] ) ? (int) $matches[1] : 0;
            foreach ( $this->rows as $id => $row ) {
                if ( $id !== $except ) {
                    $this->rows[$id]['is_default'] = 0;
                }
            }
            return 1;
        }
        return 1;
    }

    public function get_var( $query ) {
        return count( $this->rows );
    }
}
