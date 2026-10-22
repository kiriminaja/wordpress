<?php
/** Isolated managed-warehouse controller/bootstrap harness. */
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'KIRIOF_DIR', ABSPATH );
define( 'KIRIOF_NONCE', 'warehouse-test' );
$input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
class WarehouseSettingsDatabase {
	public $prefix = 'wp_';
	public $last_error = '';
	public array $settings = array();
	public function prepare( $sql, ...$args ) { return $args[0]; }
	public function get_row( $key ) {
		$this->last_error = '';
		if ( warehouse_failure( 'db_read' ) ) { $this->last_error = 'Private SQL details'; return null; }
		return array_key_exists( $key, $this->settings ) ? (object) array( 'key' => $key, 'value' => $this->settings[$key] ) : null;
	}
	public function insert( $table, $data, ...$formats ) {
		$this->last_error = '';
		if ( warehouse_failure( 'db_insert' ) ) { $this->last_error = 'Private SQL details'; return false; }
		$this->settings[$data['key']] = $data['value']; return 1;
	}
	public function update( $table, $data, $where, ...$formats ) {
		$this->last_error = '';
		if ( warehouse_failure( 'db_update' ) ) { $this->last_error = 'Private SQL details'; return false; }
		if ( ! array_key_exists( $where['key'], $this->settings ) ) { return 0; }
		$changed = $this->settings[$where['key']] !== $data['value'];
		$this->settings[$where['key']] = $data['value']; return $changed ? 1 : 0;
	}
	public function delete( $table, $where, ...$formats ) {
		$this->last_error = '';
		if ( warehouse_failure( 'db_delete' ) ) { return false; }
		if ( isset( $where['id'] ) ) { unset( $GLOBALS['warehouse_repo']->rows[$where['id']] ); }
		else { unset( $this->settings[$where['key']] ); } return 1;
	}
}
$GLOBALS['wpdb'] = new WarehouseSettingsDatabase();
$GLOBALS['wpdb']->settings = $input['settings'] ?? array();
$GLOBALS['allowed'] = $input['allowed'] ?? true;
$GLOBALS['options'] = $input['options'] ?? array();
$GLOBALS['hooks'] = array();
$GLOBALS['failures'] = $input['failures'] ?? array();
$GLOBALS['boundary_calls'] = array();
function warehouse_failure( $method ) {
	$count = ( $GLOBALS['boundary_calls'][$method] ?? 0 ) + 1;
	$GLOBALS['boundary_calls'][$method] = $count;
	$failure = $GLOBALS['failures'][$method] ?? null;
	if ( is_array( $failure ) ) { $failure = $failure[$count] ?? null; }
	if ( 'throw' === $failure ) { throw new RuntimeException( 'Private repository connection details' ); }
	return 'false' === $failure;
}
class WarehouseCountries {
	public function get_allowed_countries() {
		$GLOBALS['country_calls'][] = 'get_allowed_countries';
		return $GLOBALS['warehouse_input']['countries'] ?? array( 'ID' => 'Indonesia', 'SG' => 'Singapore' );
	}
	public function get_states( $country ) {
		$GLOBALS['country_calls'][] = 'get_states:' . $country;
		$states = $GLOBALS['warehouse_input']['states'] ?? array( 'ID' => array( 'JK' => 'DKI Jakarta', 'JB' => 'Jawa Barat' ), 'SG' => false );
		return $states[$country] ?? false;
	}
}
$GLOBALS['warehouse_input'] = $input;
$GLOBALS['country_calls'] = array();
function WC() { static $wc; return $wc ?? ( $wc = (object) array( 'countries' => new WarehouseCountries() ) ); }
function __( $text, $domain = '' ) { return $text; }
function current_user_can( $cap ) { return $GLOBALS['allowed']; }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
function wp_verify_nonce( $value, $action ) { return 'valid' === $value && KIRIOF_NONCE === $action; }
function wp_create_nonce( $action ) { return 'valid'; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function get_transient( $key ) { return false; }
function get_locale() { return 'en_US'; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function update_option( $key, $value ) {
	if ( warehouse_failure( 'option:' . $key ) ) { return false; }
	$changed = ! array_key_exists( $key, $GLOBALS['options'] ) || $GLOBALS['options'][$key] !== $value;
	$GLOBALS['options'][$key] = $value; return $changed;
}
function delete_option( $key ) { if ( warehouse_failure( 'delete_option:' . $key ) ) { return false; } unset( $GLOBALS['options'][$key] ); return true; }
function apply_filters( $name, $value ) { return $value; }
function add_action( $hook, $callback, ...$rest ) { $GLOBALS['hooks'][] = $hook; }
function add_filter( $hook, $callback, ...$rest ) { $GLOBALS['hooks'][] = $hook; }
function add_query_arg( $key, $value, $url ) { return $url . '&' . $key . '=' . rawurlencode( $value ); }
function wp_safe_redirect( $url ) { echo json_encode( array( 'redirect' => $url ), JSON_THROW_ON_ERROR ); }
class WarehouseResponse extends Exception {
	public array $response;
	public function __construct( array $response ) { $this->response = $response; }
}
function wp_send_json_error( $data, $status = null ) { throw new WarehouseResponse( array( 'success' => false, 'data' => $data, 'http' => $status ) ); }
function wp_send_json_success( $data ) { throw new WarehouseResponse( array( 'success' => true, 'data' => $data ) ); }
spl_autoload_register( static function ( $class ) {
	$prefix = 'KiriminAjaOfficial\\';
	if ( str_starts_with( $class, $prefix ) ) {
		$file = ABSPATH . 'inc/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_file( $file ) ) { require_once $file; }
	}
} );
class WarehouseRepository extends \KiriminAjaOfficial\Repositories\ShipmentLocationRepository {
	public array $rows = array();
	public array $writes = array();
	public function count() { warehouse_failure( 'count' ); return count( $this->rows ); }
	public function getAll( $activeOnly = false ) { return array_values( $this->rows ); }
	public function getById( $id ) { if ( warehouse_failure( 'getById' ) ) { return null; } return $this->rows[$id] ?? null; }
	public function countCustomLocations() { warehouse_failure( 'countCustomLocations' ); return count( array_filter( $this->rows, static fn( $r ) => ! $r->is_default ) ); }
	public function insert( array $data ) {
		if ( warehouse_failure( 'insert' ) ) { return false; }
		if ( empty( $data['is_default'] ) && ! $this->canCreateCustomLocation() ) { return false; }
		$id = count( $this->rows ) + 1;
		$this->rows[$id] = (object) array_merge( $data, array( 'id' => $id ) );
		$this->writes[] = array( 'insert' => $data );
		return $id;
	}
	public function update( $id, array $data ) {
		if ( warehouse_failure( 'update' ) ) { return false; }
		$this->writes[] = array( 'id' => $id, 'data' => $data );
		if ( ! $this->getById( $id ) ) { return false; }
		$this->rows[$id] = (object) array_merge( get_object_vars( $this->rows[$id] ), $data );
		return true;
	}
	public function delete( $id ) { if ( warehouse_failure( 'delete' ) ) { return false; } $this->writes[] = array( 'delete' => $id ); unset( $this->rows[$id] ); return true; }
}
class WarehouseSettings extends \KiriminAjaOfficial\Repositories\SettingRepository {
	public array $mirrors = array();
	public function getSettingByArray( $keys ) { return array(); }
	public function storeOriginMirrorData( array $payload ) { if ( warehouse_failure( 'mirror' ) ) { return false; } $this->mirrors[] = $payload; return parent::storeOriginMirrorData( $payload ); }
}
$repo = new WarehouseRepository();
$GLOBALS['warehouse_repo'] = $repo;
$default = array( 'id' => 1, 'name' => 'Default warehouse', 'phone' => '0812', 'address' => 'Main road', 'address_2' => 'Floor 2', 'city' => 'Jakarta', 'country' => 'ID', 'state' => 'JK', 'sub_district_id' => 123, 'sub_district_name' => 'District label', 'zip_code' => '12345', 'latitude' => '-6', 'longitude' => '106', 'is_default' => 1, 'is_active' => 1 );
foreach ( $input['rows'] ?? array( $default, array_merge( $default, array( 'id' => 2, 'name' => 'Custom warehouse', 'is_default' => 0 ) ) ) as $row ) { $repo->rows[$row['id']] = (object) $row; }
$settings = new WarehouseSettings();
$service = new \KiriminAjaOfficial\Services\ShipmentLocationService( $repo, $settings );
$controller = new \KiriminAjaOfficial\Controllers\WarehouseSettingsController( $service, $settings );
$_POST = array( 'data' => $input['data'] ?? array() );
$_GET = $input['query'] ?? array();
$_SERVER['REQUEST_METHOD'] = $input['method'] ?? 'GET';
try {
	switch ( $input['operation'] ) {
		case 'mirror': $result = $settings->storeOriginMirrorData( $input['mirror_payload'] ); break;
		case 'bootstrap': $result = ( new \KiriminAjaOfficial\Services\WarehousesPageData( $service ) )->prepare(); break;
		case 'register': $controller->register(); $result = $GLOBALS['hooks']; break;
		case 'legacy': $controller->redirectLegacyRoute(); $result = array( 'notRedirected' => true ); break;
		default: $controller->{$input['operation']}(); $result = array();
	}
} catch ( WarehouseResponse $response ) { $result = $response->response; }
echo json_encode( array( 'result' => $result, 'writes' => $repo->writes, 'rows' => array_values( $repo->rows ), 'options' => $GLOBALS['options'], 'mirrors' => $settings->mirrors, 'settings' => $GLOBALS['wpdb']->settings, 'country_calls' => $GLOBALS['country_calls'] ), JSON_THROW_ON_ERROR );
