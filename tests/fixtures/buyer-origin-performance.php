<?php
/** Real SQLite execution of the production buyer origin service and repository. */

declare(strict_types=1);

use KiriminAjaOfficial\Repositories\ShipmentLocationRepository;
use KiriminAjaOfficial\Services\ShipmentLocationService;

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

function get_option( $key, $default = '' ) {
	return $GLOBALS['buyer_origin_options'][ $key ] ?? $default;
}
function current_time( $type ) {
	return '2026-10-28 12:00:00';
}
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function sanitize_textarea_field( $value ) {
	return sanitize_text_field( $value );
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}
function __( $text, $domain = '' ) {
	return $text;
}

/** Only wpdb transport is substituted; all origin decisions use production code. */
final class BuyerOriginSqliteWpdb {
	public string $prefix = 'wp_';
	public string $last_error = '';
	public int $insert_id = 0;
	public array $queries = array();
	public PDO $database;

	public function __construct() {
		$this->database = new PDO( 'sqlite::memory:', null, null, array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ) );
		$this->database->exec( 'CREATE TABLE wp_kiriminaja_shipment_location (
			id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, phone TEXT, address TEXT,
			address_2 TEXT, sub_district_name TEXT, city TEXT, state TEXT, country TEXT,
			sub_district_id INTEGER, zip_code TEXT, latitude TEXT, longitude TEXT,
			is_default INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, created_at TEXT, updated_at TEXT
		)' );
		$this->database->exec( 'CREATE TABLE wp_kiriminaja_settings (id INTEGER PRIMARY KEY, `key` TEXT, value TEXT)' );
	}

	public function prepare( $sql, ...$values ) {
		if ( isset( $values[0] ) && is_array( $values[0] ) ) {
			$values = $values[0];
		}
		$index = 0;
		return preg_replace_callback( '/%[ids]/', function ( $match ) use ( &$index, $values ) {
			$value = $values[ $index++ ];
			if ( '%i' === $match[0] ) {
				return '"' . str_replace( '"', '""', (string) $value ) . '"';
			}
			return '%d' === $match[0] ? (string) (int) $value : $this->database->quote( (string) $value );
		}, $sql );
	}

	public function get_row( $sql ) {
		$this->queries[] = $sql;
		return $this->database->query( $sql )->fetch( PDO::FETCH_OBJ ) ?: null;
	}
	public function get_var( $sql ) {
		$this->queries[] = $sql;
		return $this->database->query( $sql )->fetchColumn();
	}
	public function get_results( $sql ) {
		$this->queries[] = $sql;
		return $this->database->query( $sql )->fetchAll( PDO::FETCH_OBJ );
	}
	public function query( $sql ) {
		$this->queries[] = $sql;
		return $this->database->exec( 'START TRANSACTION' === $sql ? 'BEGIN TRANSACTION' : $sql );
	}
	public function insert( $table, $data, $formats = array() ) {
		$columns = implode( ', ', array_map( static fn( $key ) => '"' . $key . '"', array_keys( $data ) ) );
		$values  = implode( ', ', array_map( fn( $value ) => $this->database->quote( (string) $value ), array_values( $data ) ) );
		$result  = $this->query( 'INSERT INTO "' . $table . '" (' . $columns . ') VALUES (' . $values . ')' );
		$this->insert_id = (int) $this->database->lastInsertId();
		return $result;
	}
	public function update( $table, $data, $where, $formats = array(), $where_formats = array() ) {
		$assignments = array();
		foreach ( $data as $key => $value ) {
			$assignments[] = '"' . $key . '" = ' . $this->database->quote( (string) $value );
		}
		return $this->query( 'UPDATE "' . $table . '" SET ' . implode( ', ', $assignments ) . ' WHERE id = ' . (int) $where['id'] );
	}
	public function delete( $table, $where, $formats = array() ) {
		return $this->query( 'DELETE FROM "' . $table . '" WHERE id = ' . (int) $where['id'] );
	}
}

function buyer_origin_database( int $locations = 100 ): BuyerOriginSqliteWpdb {
	$database = new BuyerOriginSqliteWpdb();
	for ( $id = 1; $id <= $locations; ++$id ) {
		$database->insert( 'wp_kiriminaja_shipment_location', array(
			'id' => $id, 'name' => 'Warehouse ' . $id, 'phone' => '08123456789',
			'address' => 'Street ' . $id, 'address_2' => 'Floor 2', 'city' => 'Bandung',
			'state' => 'JB', 'country' => 'ID', 'sub_district_name' => 'Coblong',
			'sub_district_id' => 3201010, 'zip_code' => '40123',
			'latitude' => '-6.91', 'longitude' => '107.61',
			'is_default' => 37 === $id ? 1 : 0, 'is_active' => 1,
			'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ),
		) );
	}
	$database->queries = array();
	return $database;
}

function buyer_origin_counts( array $queries ): array {
	$reads = 0;
	$writes = 0;
	foreach ( $queries as $sql ) {
		$reads += preg_match( '/^SELECT\b/i', $sql );
		$writes += preg_match( '/^(INSERT|UPDATE|DELETE|REPLACE)\b/i', $sql );
	}
	return array( 'total' => count( $queries ), 'reads' => $reads, 'writes' => $writes, 'transactions' => count( $queries ) - $reads - $writes );
}

function buyer_origin_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$config = json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR );
require ABSPATH . 'inc/Repositories/ShipmentLocationRepository.php';
require ABSPATH . 'inc/Repositories/SettingRepository.php';
require $config['query_source'] ?? ABSPATH . 'inc/Services/ShipmentLocationService.php';

try {
	if ( 'check' === ( $config['mode'] ?? 'check' ) ) {
		$wpdb = buyer_origin_database();
		$service = new ShipmentLocationService();
		$expected = (array) $service->getDefaultLocation();
		buyer_origin_assert( 37 === (int) $expected['id'], 'Selected default changed.' );
		buyer_origin_assert( array( 'total' => 1, 'reads' => 1, 'writes' => 0, 'transactions' => 0 ) === buyer_origin_counts( $wpdb->queries ), 'Healthy read must use one SELECT.' );
		$service->getDefaultLocation();
		buyer_origin_assert( 1 === count( $wpdb->queries ), 'Same instance missed its cache.' );
		( new ShipmentLocationService() )->getDefaultLocation();
		buyer_origin_assert( 2 === count( $wpdb->queries ), 'Independent instance did not read independently.' );
		$wpdb->queries = array();
		buyer_origin_assert( 12 === ( new ShipmentLocationService() )->originForLocation( 12 )['id'], 'Explicit origin changed.' );
		buyer_origin_assert( 1 === count( $wpdb->queries ), 'Explicit origin resolved the default unnecessarily.' );

		$wpdb->database->exec( 'UPDATE wp_kiriminaja_shipment_location SET is_default = 0' );
		$wpdb->database->exec( 'UPDATE wp_kiriminaja_shipment_location SET is_default = 1, is_active = 0 WHERE id = 1' );
		$wpdb->queries = array();
		buyer_origin_assert( 2 === (int) ( new ShipmentLocationService() )->getDefaultLocation()->id, 'Missing active default was not repaired.' );
		buyer_origin_assert( 2 === buyer_origin_counts( $wpdb->queries )['writes'], 'Repair did not promote and clear defaults.' );
		buyer_origin_assert( 0 === (int) $wpdb->database->query( 'SELECT is_active FROM wp_kiriminaja_shipment_location WHERE id = 1' )->fetchColumn(), 'Inactive row was reactivated.' );

		$wpdb->database->exec( 'UPDATE wp_kiriminaja_shipment_location SET is_default = 1 WHERE id IN (3, 4)' );
		$wpdb->queries = array();
		buyer_origin_assert( 2 === (int) ( new ShipmentLocationService() )->getDefaultLocation()->id, 'Duplicate defaults changed deterministic selection.' );
		buyer_origin_assert( array( 'total' => 1, 'reads' => 1, 'writes' => 0, 'transactions' => 0 ) === buyer_origin_counts( $wpdb->queries ), 'Buyer reads must not repair duplicate defaults.' );
		buyer_origin_assert( 3 === (int) $wpdb->database->query( 'SELECT COUNT(*) FROM wp_kiriminaja_shipment_location WHERE is_default = 1' )->fetchColumn(), 'Buyer read unexpectedly mutated default flags.' );
		buyer_origin_assert( ( new ShipmentLocationRepository() )->ensureDefaultExists(), 'Explicit duplicate maintenance failed.' );
		buyer_origin_assert( 1 === (int) $wpdb->database->query( 'SELECT COUNT(*) FROM wp_kiriminaja_shipment_location WHERE is_default = 1' )->fetchColumn(), 'Explicit maintenance left duplicate defaults.' );
		$wpdb->database->exec( 'UPDATE wp_kiriminaja_shipment_location SET is_active = 0' );
		$wpdb->queries = array();
		buyer_origin_assert( null === ( new ShipmentLocationService() )->getDefaultLocation(), 'All inactive rows must resolve null.' );
		buyer_origin_assert( 0 === buyer_origin_counts( $wpdb->queries )['writes'], 'All inactive rows caused writes.' );

		$wpdb = buyer_origin_database( 0 );
		$GLOBALS['buyer_origin_options'] = array();
		$service = new ShipmentLocationService();
		buyer_origin_assert( null === $service->getDefaultLocation(), 'Empty origin must resolve null.' );
		$queries = $wpdb->queries;
		buyer_origin_assert( null === $service->getDefaultLocation() && $queries === $wpdb->queries, 'Null result was not cached.' );
		buyer_origin_assert( 0 === buyer_origin_counts( $queries )['writes'], 'Empty origin caused writes.' );
		buyer_origin_assert( null === ( new ShipmentLocationService() )->getDefaultLocation(), 'Independent empty origin must resolve null.' );
		buyer_origin_assert( 0 === buyer_origin_counts( $wpdb->queries )['writes'], 'Independent empty origin retried writes.' );

		$wpdb->database->exec( "INSERT INTO wp_kiriminaja_settings (`key`, value) VALUES ('origin_address', 'Legacy street'), ('origin_sub_district_id', '99')" );
		$seeded = ( new ShipmentLocationService() )->getDefaultLocation();
		buyer_origin_assert( 'Legacy street' === $seeded->address && 99 === (int) $seeded->sub_district_id, 'Legacy origin was not seeded.' );
		$wpdb = buyer_origin_database( 0 );
		$GLOBALS['buyer_origin_options'] = array( 'woocommerce_store_address' => 'Store street', 'woocommerce_default_country' => 'ID:JB' );
		$seeded = ( new ShipmentLocationService() )->getDefaultLocation();
		buyer_origin_assert( 'Store street' === $seeded->address && 'ID' === $seeded->country && 'JB' === $seeded->state, 'WooCommerce origin fallback changed.' );
		echo json_encode( array( 'ok' => true, 'engine' => 'SQLite', 'checks' => array( 'healthy', 'instance_cache', 'independent_instances', 'explicit_origin', 'missing_default', 'inactive_rows', 'duplicate_maintenance', 'empty_origin', 'legacy_seed', 'woocommerce_seed' ) ), JSON_THROW_ON_ERROR ) . "\n";
		return;
	}

	$wpdb = buyer_origin_database();
	$expected = (array) $wpdb->database->query( 'SELECT * FROM wp_kiriminaja_shipment_location WHERE id = 37' )->fetch( PDO::FETCH_OBJ );
	$results = array();
	foreach ( array( 'three_independent_services', 'one_service_three_calls' ) as $case ) {
		$times = array();
		$samples = array();
		for ( $sample = -$config['warmups']; $sample < $config['samples']; ++$sample ) {
			$wpdb->queries = array();
			$start = hrtime( true );
			$service = null;
			$origins = array();
			for ( $consumer = 0; $consumer < 3; ++$consumer ) {
				if ( null === $service || 'three_independent_services' === $case ) {
					$service = new ShipmentLocationService();
				}
				$origins[] = (array) $service->getDefaultLocation();
			}
			$elapsed = ( hrtime( true ) - $start ) / 1000000;
			buyer_origin_assert( array( $expected, $expected, $expected ) === $origins, 'Origin result parity failed.' );
			if ( $sample >= 0 ) {
				$times[] = $elapsed;
				$samples[] = array( 'ms' => $elapsed, 'counts' => buyer_origin_counts( $wpdb->queries ) );
			}
		}
		sort( $times );
		$count = count( $times );
		$results[ $case ] = array(
			'median_ms' => ( $times[ (int) floor( ( $count - 1 ) / 2 ) ] + $times[ (int) floor( $count / 2 ) ] ) / 2,
			'p95_ms' => $times[ (int) ceil( $count * 0.95 ) - 1 ],
			'counts_per_sample' => buyer_origin_counts( $wpdb->queries ),
			'sql_per_sample' => $wpdb->queries,
			'samples' => $samples,
			'result_parity' => true,
		);
	}
	echo json_encode( array(
		'engine' => 'SQLite in-memory (not MySQL; no WordPress bootstrap or network)',
		'sqlite_version' => $wpdb->database->query( 'SELECT sqlite_version()' )->fetchColumn(),
		'php_version' => PHP_VERSION, 'source' => realpath( $config['query_source'] ),
		'source_sha256' => hash_file( 'sha256', $config['query_source'] ),
		'locations' => 100, 'default_id' => 37, 'warmups' => $config['warmups'], 'sample_count' => $config['samples'],
		'expected_result_sha256' => hash( 'sha256', json_encode( $expected, JSON_THROW_ON_ERROR ) ),
		'cases' => $results,
	), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR ) . "\n";
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . "\n" );
	exit( 1 );
}
