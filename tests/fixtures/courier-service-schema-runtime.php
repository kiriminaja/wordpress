<?php
/** Isolated WordPress boundary; migration, controller, catalog and repository are real. */
error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );
$root = dirname( __DIR__, 2 );
$upgrade_root = sys_get_temp_dir() . '/courier-schema-' . uniqid( '', true );
mkdir( $upgrade_root . '/wp-admin/includes', 0777, true );
file_put_contents( $upgrade_root . '/wp-admin/includes/upgrade.php', '<?php function dbDelta( $sql ) { $GLOBALS["wpdb"]->create_table( $sql ); }' );
register_shutdown_function( static function () use ( $upgrade_root ) {
	unlink( $upgrade_root . '/wp-admin/includes/upgrade.php' );
	rmdir( $upgrade_root . '/wp-admin/includes' );
	rmdir( $upgrade_root . '/wp-admin' );
	rmdir( $upgrade_root );
} );
define( 'ABSPATH', $upgrade_root . '/' );
define( 'KIRIOF_NONCE', 'schema-runtime' );
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return strtolower( $value ); }
function esc_sql( $value ) { return addslashes( $value ); }
function wp_unslash( $value ) { return $value; }
function wp_json_encode( $value ) { return json_encode( $value, JSON_THROW_ON_ERROR ); }
function current_user_can( $capability ) { return true; }
function wp_verify_nonce( $nonce, $action ) { return true; }
function wp_send_json_success( $value ) { $GLOBALS['ajax_result'] = array( true, $value ); }
function wp_send_json_error( $value ) { $GLOBALS['ajax_result'] = array( false, $value ); }
function get_transient( $key ) { return $GLOBALS['courier_cache'] ?? false; }
function get_option( $key, $default = false ) { return $default; }
function __( $text, $domain = '' ) { return $text; }
function kiriof_get_tracking_page_id() { return 0; }

final class CourierSchemaDb {
	public $prefix = 'schema_';
	public $posts = 'schema_posts';
	public $postmeta = 'schema_postmeta';
	public $last_error = '';
	public $rows = array();
	public $column_type = 'varchar(255)';
	public $engine = 'InnoDB';
	public $exists = true;
	public $schema = '';
	public $queries = array();
	public $value_alters = 0;
	private $snapshot;
	public function prepare( $sql, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) ) { $args = $args[0]; }
		return preg_replace_callback( '/%[sd]/', static function ( $match ) use ( &$args ) {
			$value = array_shift( $args );
			return '%d' === $match[0] ? (string) (int) $value : "'" . addslashes( (string) $value ) . "'";
		}, $sql );
	}
	private function key( $sql ) {
		if ( ! preg_match( '/`key`\s*=\s*\'([^\']*)\'/i', $sql, $matches ) ) { throw new RuntimeException( 'Unexpected setting query: ' . $sql ); }
		return stripslashes( $matches[1] );
	}
	public function get_row( $sql, ...$args ) {
		$this->last_error = '';
		if ( false !== stripos( $sql, 'SHOW COLUMNS' ) ) { return (object) array( 'Field' => 'value', 'Type' => $this->column_type, 'Null' => 'YES' ); }
		if ( false !== stripos( $sql, 'SHOW TABLE STATUS' ) ) { return (object) array( 'Engine' => $this->engine ); }
		$key = $this->key( $sql );
		return array_key_exists( $key, $this->rows ) ? (object) array( 'key' => $key, 'value' => $this->rows[ $key ] ) : null;
	}
	public function get_var( $sql, $column = 0 ) {
		$this->last_error = '';
		if ( false !== stripos( $sql, 'SHOW TABLES' ) ) { return $this->exists ? $this->prefix . 'kiriminaja_settings' : null; }
		if ( false !== stripos( $sql, 'SHOW COLUMNS' ) ) { return $this->column_type; }
		if ( false !== stripos( $sql, 'SHOW TABLE STATUS' ) || false !== stripos( $sql, 'information_schema' ) ) { return $this->engine; }
		if ( false !== strpos( $sql, 'COUNT(DISTINCT p.ID)' ) ) { return 0; }
		if ( false !== stripos( $sql, 'COUNT(*)' ) ) { return array_key_exists( $this->key( $sql ), $this->rows ) ? 1 : 0; }
		throw new RuntimeException( 'Unexpected scalar query: ' . $sql );
	}
	public function get_results( $sql ) {
		$this->last_error = '';
		preg_match_all( "/'([^']*)'/", $sql, $matches );
		$rows = array();
		foreach ( $matches[1] as $key ) {
			if ( array_key_exists( $key, $this->rows ) ) { $rows[] = (object) array( 'key' => $key, 'value' => $this->rows[ $key ] ); }
		}
		return $rows;
	}
	public function create_table( $sql ) {
		$this->schema = $sql;
		$this->exists = true;
		if ( ! preg_match( '/`value`\s+(longtext|varchar\(255\))/i', $sql, $matches ) ) { throw new RuntimeException( 'Missing value schema' ); }
		$this->column_type = strtolower( $matches[1] );
	}
	public function query( $sql ) {
		$this->last_error = '';
		$this->queries[] = $sql;
		if ( 'START TRANSACTION' === $sql ) { $this->snapshot = $this->rows; }
		elseif ( 'ROLLBACK' === $sql ) { $this->rows = $this->snapshot; $this->snapshot = null; }
		elseif ( 'COMMIT' === $sql ) { $this->snapshot = null; }
		elseif ( preg_match( '/^ALTER TABLE .*\b(?:MODIFY|CHANGE)\b.*`?value`?.*longtext/i', $sql ) ) { $this->column_type = 'longtext'; ++$this->value_alters; }
		elseif ( preg_match( '/^ALTER TABLE .*ENGINE\s*=\s*InnoDB/i', $sql ) ) { $this->engine = 'InnoDB'; }
		elseif ( preg_match( '/^INSERT INTO /i', $sql ) ) {
			preg_match_all( "/\('([^']*)',\s*'([^']*)'\)/", $sql, $matches, PREG_SET_ORDER );
			foreach ( $matches as $row ) { $this->write( $row[1], $row[2] ); }
		} else { throw new RuntimeException( 'Unexpected write query: ' . $sql ); }
		return 1;
	}
	private function write( $key, $value ) {
		$this->last_error = '';
		if ( 'varchar(255)' === $this->column_type && strlen( (string) $value ) > 255 ) {
			$this->last_error = "Data too long for column 'value'";
			return false;
		}
		$this->rows[ $key ] = $value;
		return 1;
	}
	public function insert( $table, $data, ...$formats ) { return $this->write( $data['key'], $data['value'] ); }
	public function update( $table, $data, $where, ...$formats ) { return $this->write( $where['key'], $data['value'] ); }
}

foreach ( array( 'Services/CourierServiceCatalog', 'Repositories/SettingRepository', 'Controllers/SettingController', 'Services/OnboardingSetupStateService', 'Migration/SetupMigration' ) as $file ) {
	require $root . '/inc/' . $file . '.php';
}
use KiriminAjaOfficial\Services\CourierServiceCatalog;
use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Controllers\SettingController;
use KiriminAjaOfficial\Services\OnboardingSetupStateService;
use KiriminAjaOfficial\Migration\SetupMigration;

function migrate_settings() {
	$method = new ReflectionMethod( SetupMigration::class, 'settingsTable' );
	$method->setAccessible( true );
	$method->invoke( new SetupMigration() );
}
function reset_repository_cache() {
	foreach ( array( 'setting_cache', 'whitelist_expedition_ids_cache' ) as $name ) {
		$property = new ReflectionProperty( SettingRepository::class, $name );
		$property->setAccessible( true );
		$property->setValue( null, array() );
	}
}
function save_all( $json ) {
	$_POST = array( 'data' => array( 'nonce' => KIRIOF_NONCE, 'service_selection' => $json ) );
	( new SettingController() )->storeCourierWhitelist();
	return $GLOBALS['ajax_result'];
}
function transaction_queries( $queries ) {
	return array_values( array_intersect( $queries, array( 'START TRANSACTION', 'COMMIT', 'ROLLBACK' ) ) );
}

$wpdb = new CourierSchemaDb();
$wpdb->rows = array(
	'origin_whitelist_expedition_id' => '',
	'origin_whitelist_expedition_name' => '',
	'origin_whitelist_expedition_services' => '{}',
	'api_key' => 'existing-api-token',
	'is_top' => 'yes',
	'unrelated' => 'unrelated merchant setting',
);
$scenario = $argv[1] ?? '';
if ( 'longtext' === $scenario ) {
	$wpdb->column_type = 'longtext';
	$before = $wpdb->rows;
	migrate_settings();
	migrate_settings();
	echo json_encode( array( 'column_type' => $wpdb->column_type, 'value_alters' => $wpdb->value_alters, 'rows_preserved' => $before === $wpdb->rows ), JSON_THROW_ON_ERROR );
	exit;
}

// The actual known domestic catalog, including numeric and space-containing codes.
// API-provided descriptive names also exercise the legacy CSV mirror >255 boundary.
$catalog = CourierServiceCatalog::known();
$selection = array();
foreach ( $catalog as &$courier ) {
	$courier['name'] .= ' Domestic Express Delivery';
	$selection[ $courier['code'] ] = array_column( $courier['services'], 'code' );
}
unset( $courier );
$GLOBALS['courier_cache'] = array_values( $catalog );
$json = wp_json_encode( (object) $selection );
$names = implode( ',', array_column( $catalog, 'name' ) );
$result = array( 'policy_length' => strlen( $json ), 'names_length' => strlen( $names ) );
if ( 'existing' === $scenario ) {
	$before = $wpdb->rows;
	$response = save_all( $json );
	$result['before_success'] = $response[0];
	$result['before_message'] = $response[1]['message'];
	$result['before_transactions'] = transaction_queries( $wpdb->queries );
	$result['rollback_preserved_rows'] = $before === $wpdb->rows;
	migrate_settings();
	$result['migration_preserved_rows'] = $before === $wpdb->rows;
	migrate_settings();
} elseif ( 'fresh' === $scenario ) {
	$wpdb->exists = false;
	$wpdb->rows = array();
	migrate_settings();
	$result['schema'] = $wpdb->schema;
	$result['is_top'] = $wpdb->rows['is_top'];
} else { throw new RuntimeException( 'Unknown scenario' ); }
$result['column_type'] = $wpdb->column_type;
$result['value_alters'] = $wpdb->value_alters;
$wpdb->queries = array();
$response = save_all( $json );
$result['after_success'] = $response[0];
$result['after_transactions'] = transaction_queries( $wpdb->queries );
// Clear all static state so a successful save cannot be disguised by warm caches.
reset_repository_cache();
$repository = new SettingRepository();
$result['reload_matches'] = $selection === $repository->getCourierServiceSelection();
$result['mirrors_match'] = implode( ',', array_keys( $selection ) ) === ( $wpdb->rows['origin_whitelist_expedition_id'] ?? null ) && $names === ( $wpdb->rows['origin_whitelist_expedition_name'] ?? null );
$result['every_service_enabled'] = true;
foreach ( $selection as $code => $services ) {
	foreach ( $services as $service ) {
		$result['every_service_enabled'] = $result['every_service_enabled'] && $repository->isCourierServiceEnabled( $code, $service );
	}
}
$result['couriers_done'] = ( new OnboardingSetupStateService() )->get_steps()['couriers']['done'];
$result['unrelated'] = $wpdb->rows['unrelated'] ?? null;
echo json_encode( $result, JSON_THROW_ON_ERROR );
