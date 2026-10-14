<?php

error_reporting( E_ALL );
$root         = dirname( __DIR__, 2 );
$upgrade_root = sys_get_temp_dir() . '/transaction-migration-' . uniqid( '', true );
mkdir( $upgrade_root . '/wp-admin/includes', 0777, true );
file_put_contents( $upgrade_root . '/wp-admin/includes/upgrade.php', '<?php function dbDelta( $sql ) { $GLOBALS["wpdb"]->create_table( $sql ); }' );
register_shutdown_function(
	static function () use ( $upgrade_root ) {
		unlink( $upgrade_root . '/wp-admin/includes/upgrade.php' );
		rmdir( $upgrade_root . '/wp-admin/includes' );
		rmdir( $upgrade_root . '/wp-admin' );
		rmdir( $upgrade_root );
	}
);
define( 'ABSPATH', $upgrade_root . '/' );

function esc_sql( $value ) {
	return addslashes( $value );
}

final class TransactionMigrationDb {
	public $prefix           = 'traffic_';
	public $table_name       = 'traffic_kiriminaja_transactions';
	public $exists           = true;
	public $queries          = array();
	public $metadata_queries = array();
	public $schemas          = array();
	public $read_failures    = 0;
	public $alter_failures   = 0;
	public $status;
	public $columns = array(
		'status',
		'canceled_at',
		'discount_amount',
		'discount_percentage',
		'woocommerce_discount_amount',
		'woocommerce_discount_description',
		'is_deficit',
		'cod_minimum',
		'is_printed',
		'printed_at',
		'shipment_location_id',
		'shipment_location_snapshot',
	);

	public function __construct() {
		$this->status = (object) array(
			'Field'   => 'status',
			'Type'    => "enum('new','request_pickup','pending','finished','shipped','return','returned','rejected','canceled')",
			'Null'    => 'NO',
			'Default' => 'new',
		);
	}

	public function prepare( $sql, ...$args ) {
		return preg_replace_callback(
			'/%s/',
			static function () use ( &$args ) {
				return "'" . addslashes( (string) array_shift( $args ) ) . "'";
			},
			$sql
		);
	}

	public function get_var( $sql ) {
		if ( "SHOW TABLES LIKE '" . $this->table_name . "'" !== $sql ) {
			throw new RuntimeException( 'Unexpected table query: ' . $sql );
		}
		return $this->exists ? $this->table_name : null;
	}

	public function get_col( $sql, $column = 0 ) {
		if ( 'DESCRIBE `' . $this->table_name . '`' !== $sql || 0 !== $column ) {
			throw new RuntimeException( 'Unexpected column query: ' . $sql );
		}
		return $this->columns;
	}

	public function get_row( $sql ) {
		$this->metadata_queries[] = $sql;
		if ( "SHOW COLUMNS FROM `" . $this->table_name . "` LIKE 'status'" !== $sql ) {
			throw new RuntimeException( 'Unexpected metadata query: ' . $sql );
		}
		if ( 0 < $this->read_failures ) {
			--$this->read_failures;
			return null;
		}
		return clone $this->status;
	}

	public function query( $sql ) {
		$this->queries[] = $sql;
		if ( preg_match( '/^ALTER TABLE `' . preg_quote( $this->table_name, '/' ) . '` MODIFY COLUMN `?status`? (enum\(.*\)) NOT NULL DEFAULT \'new\'$/', $sql, $matches ) ) {
			if ( 0 < $this->alter_failures ) {
				--$this->alter_failures;
				return false;
			}
			$this->status->Type    = $matches[1];
			$this->status->Null    = 'NO';
			$this->status->Default = 'new';
			return 0;
		}
		if ( 'ALTER TABLE `' . $this->table_name . '` ADD canceled_at timestamp NULL DEFAULT NULL' === $sql ) {
			$this->columns[] = 'canceled_at';
			return 0;
		}
		throw new RuntimeException( 'Unexpected write query: ' . $sql );
	}

	public function create_table( $sql ) {
		$this->schemas[] = $sql;
		if ( ! preg_match( '/`status` (enum\(.*\)) NOT NULL DEFAULT \'new\'/', $sql, $matches ) ) {
			throw new RuntimeException( 'Missing status schema: ' . $sql );
		}
		$this->exists       = true;
		$this->status->Type = $matches[1];
	}
}

require $root . '/inc/Migration/SetupMigration.php';

$scenario  = $argv[1] ?? 'current';
$wpdb      = new TransactionMigrationDb();
$migration = new KiriminAjaOfficial\Migration\SetupMigration();

switch ( $scenario ) {
	case 'legacy':
	case 'read_failure':
	case 'alter_failure':
	case 'suffix':
		$wpdb->status->Type = "enum('new','request_pickup','pending','finished','shipped','return','returned','rejected')";
		if ( 'read_failure' === $scenario ) {
			$wpdb->read_failures = 1;
		} elseif ( 'alter_failure' === $scenario ) {
			$wpdb->alter_failures = 1;
		} elseif ( 'suffix' === $scenario ) {
			$migration->suffix = '_archive`!';
			$wpdb->table_name .= '_archive';
		}
		break;
	case 'nullable':
		$wpdb->status->Null = 'YES';
		break;
	case 'default':
		$wpdb->status->Default = 'pending';
		break;
	case 'missing_status':
		$wpdb->columns = array_values( array_diff( $wpdb->columns, array( 'status' ) ) );
		break;
	case 'missing_canceled_at':
		$wpdb->columns = array_values( array_diff( $wpdb->columns, array( 'canceled_at' ) ) );
		break;
	case 'fresh':
		$wpdb->exists = false;
		break;
	case 'current':
		break;
	default:
		throw new RuntimeException( 'Unknown scenario: ' . $scenario );
}

$method = new ReflectionMethod( $migration, 'transactionsTable' );
$method->setAccessible( true );
$alter_counts = array();
$request_count = 'current' === $scenario ? 100 : 3;
for ( $request = 0; $request < $request_count; ++$request ) {
	$method->invoke( $migration );
	$alter_counts[] = count(
		array_filter(
			$wpdb->queries,
			static function ( $sql ) {
				return false !== strpos( $sql, 'MODIFY COLUMN' );
			}
		)
	);
}

echo json_encode(
	array(
		'alter_counts'     => $alter_counts,
		'queries'          => $wpdb->queries,
		'metadata_queries' => $wpdb->metadata_queries,
		'status'           => $wpdb->status,
		'columns'          => $wpdb->columns,
		'schemas'          => $wpdb->schemas,
	),
	JSON_THROW_ON_ERROR
);
