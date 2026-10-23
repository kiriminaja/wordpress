<?php
/** SQLite-backed execution of the production SQL; no WordPress/network boot. */
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require ABSPATH . 'vendor/autoload.php';
function current_time( $type ) { return '2026-05-06 07:08:09'; }
function wp_cache_delete( $key, $group ) { $GLOBALS['invalidated'][] = array( $key, $group ); return true; }

final class OriginMigrationSqliteWpdb {
    public string $prefix = 'wp_';
    public string $options = 'wp_options';
    public string $last_error = '';
    public string $fail = '';
    public array $queries = array();
    public array $engines = array(
        'wp_kiriminaja_shipment_location' => 'InnoDB',
        'wp_kiriminaja_settings' => 'InnoDB',
        'wp_options' => 'InnoDB',
    );
    public string $status_failure = '';
    public string $status_wrong_name = '';
    public PDO $db;
    public function __construct() {
        $this->db = new PDO( 'sqlite::memory:' );
        $this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        $this->db->exec( 'CREATE TABLE wp_kiriminaja_shipment_location (id INTEGER PRIMARY KEY, name TEXT, phone TEXT, address TEXT, address_2 TEXT, city TEXT, state TEXT, country TEXT, latitude TEXT, longitude TEXT, is_default INTEGER, is_active INTEGER, sub_district_id INTEGER, sub_district_name TEXT, zip_code TEXT, created_at TEXT, updated_at TEXT)' );
        $this->db->exec( 'CREATE TABLE wp_kiriminaja_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, `key` TEXT, value TEXT)' );
        $this->db->exec( 'CREATE TABLE wp_options (option_id INTEGER PRIMARY KEY AUTOINCREMENT, option_name TEXT UNIQUE, option_value TEXT, autoload TEXT)' );
        $insert = $this->db->prepare( 'INSERT INTO wp_kiriminaja_shipment_location VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)' );
        $insert->execute( array( 1, 'Merchant', '08123', "Main road\nNo. 9", 'Unit A', 'City', 'State', 'ID', '-6.123450', '107.987650', 1, 1, 99, 'Old, District, City, Province, 40123', '40123', '2020-01-01', '2020-01-02' ) );
        $insert->execute( array( 3, 'Warehouse', '08999', 'Other road', 'Unit B', 'Other city', 'Other state', 'ID', '-7.1', '108.1', 0, 0, 99, 'Old, District, City, Province, 40123', '40123', '2020-01-01', '2020-01-02' ) );
        foreach ( array( 'origin_sub_district_id' => '99', 'origin_sub_district_name' => 'Old, District, City, Province, 40123', 'origin_zip_code' => '40123', 'origin_address' => 'Do not touch', 'origin_name' => 'Saved name' ) as $key => $value ) {
            $statement = $this->db->prepare( 'INSERT INTO wp_kiriminaja_settings (`key`, value) VALUES (?, ?)' );
            $statement->execute( array( $key, $value ) );
        }
        $this->db->exec( "INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('kiriof_wc_origin_area', '99', 'yes'), ('woocommerce_store_postcode', '99999', 'yes')" );
    }
    public function prepare( $query, ...$values ) {
        $index = 0;
        $result = preg_replace_callback( '/%[ids]/', function ( $match ) use ( $values, &$index ) {
            if ( ! array_key_exists( $index, $values ) ) { throw new RuntimeException( 'Missing placeholder value' ); }
            $value = $values[ $index++ ];
            if ( '%i' === $match[0] ) { return '`' . str_replace( '`', '``', $value ) . '`'; }
            if ( '%d' === $match[0] ) { return (string) (int) $value; }
            return $this->db->quote( (string) $value );
        }, $query );
        if ( count( $values ) !== $index ) { throw new RuntimeException( 'Extra placeholder value' ); }
        return $result;
    }
    private function execute( string $query ) {
        $this->queries[] = $query;
        $this->last_error = '';
        if ( '' !== $this->fail && str_contains( $query, $this->fail ) ) { $this->last_error = 'Injected failure'; return false; }
        $query = str_replace( array( 'START TRANSACTION', ' FOR UPDATE' ), array( 'BEGIN TRANSACTION', '' ), $query );
        try { return $this->db->query( $query ); } catch ( Throwable $error ) { $this->last_error = $error->getMessage(); return false; }
    }
    public function query( $query ) { $statement = $this->execute( $query ); return false === $statement ? false : $statement->rowCount(); }
    public function get_results( $query ) { $statement = $this->execute( $query ); return false === $statement ? null : $statement->fetchAll( PDO::FETCH_OBJ ); }
    public function get_row( $query ) {
        if ( preg_match( "/^SHOW TABLE STATUS WHERE Name = '([^']+)'$/D", $query, $matches ) ) {
            $this->queries[] = $query;
            $this->last_error = '';
            $table = $matches[1];
            if ( $table === $this->status_failure ) { $this->last_error = 'Injected status failure'; }
            if ( ! array_key_exists( $table, $this->engines ) ) { return null; }
            $status = (object) array( 'Name' => $table === $this->status_wrong_name ? $table . '_other' : $table );
            if ( null !== $this->engines[ $table ] ) { $status->Engine = $this->engines[ $table ]; }
            return $status;
        }
        $rows = $this->get_results( $query ); return $rows[0] ?? null;
    }
    public function dump(): array {
        $result = array();
        foreach ( array( 'wp_kiriminaja_shipment_location', 'wp_kiriminaja_settings', 'wp_options' ) as $table ) { $result[ $table ] = $this->db->query( 'SELECT * FROM ' . $table . ' ORDER BY 1' )->fetchAll( PDO::FETCH_ASSOC ); }
        return $result;
    }
}

function check( $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$canonical = array( 'sub_district_id' => 123, 'sub_district_name' => "New 'Village', District, City, Province, 40124", 'zip_code' => '40124' );
$repository = new \KiriminAjaOfficial\Repositories\OriginAddressMigrationRepository();
$results = array();
foreach ( array( 'paging', 'custom', 'default', 'noop', 'missing', 'duplicates', 'merchant-settings', 'merchant-option', 'default-switch', 'write-failure', 'commit-failure', 'lock-failure', 'blank-label', 'blank-label-empty-zip', 'divergent-zip', 'divergent-label', 'mirror-delete', 'missing-insert', 'option-delete', 'option-insert', 'metadata-column', 'blank-label-edit', 'divergent-id' ) as $scenario ) {
    $wpdb = new OriginMigrationSqliteWpdb();
    $GLOBALS['invalidated'] = array();
    if ( in_array( $scenario, array( 'blank-label', 'blank-label-empty-zip', 'blank-label-edit', 'divergent-id' ), true ) ) { $wpdb->db->exec( "UPDATE wp_kiriminaja_shipment_location SET sub_district_name = '' WHERE id = 1" ); }
    if ( 'blank-label-empty-zip' === $scenario ) { $wpdb->db->exec( "UPDATE wp_kiriminaja_shipment_location SET zip_code = '' WHERE id = 1" ); }
    if ( 'divergent-id' === $scenario ) { $wpdb->db->exec( "UPDATE wp_kiriminaja_settings SET value = '77' WHERE `key` = 'origin_sub_district_id'" ); }
    if ( 'divergent-zip' === $scenario ) { $wpdb->db->exec( "UPDATE wp_kiriminaja_settings SET value = '99999' WHERE `key` = 'origin_zip_code'" ); }
    if ( 'divergent-label' === $scenario ) { $wpdb->db->exec( "UPDATE wp_kiriminaja_settings SET value = 'Other label' WHERE `key` = 'origin_sub_district_name'" ); }
    if ( in_array( $scenario, array( 'missing', 'missing-insert' ), true ) ) { $wpdb->db->exec( "DELETE FROM wp_kiriminaja_settings WHERE `key` LIKE 'origin_sub_%' OR `key` = 'origin_zip_code'; DELETE FROM wp_options WHERE option_name = 'kiriof_wc_origin_area'" ); }
    if ( 'option-insert' === $scenario ) { $wpdb->db->exec( "DELETE FROM wp_options WHERE option_name = 'kiriof_wc_origin_area'" ); }
    $snapshot = $repository->next( 0 );
    check( array_key_exists( 'origin_sub_district_name', $snapshot->_origin_mirror ) && property_exists( $snapshot, '_origin_option' ), 'Missing snapshot metadata' );
    if ( 'blank-label' === $scenario ) { check( '' === $snapshot->sub_district_name && 'Old, District, City, Province, 40123' === $snapshot->_origin_mirror['origin_sub_district_name'], 'Seeded legacy label lost' ); }
    if ( 'paging' === $scenario ) {
        check( 1 === $snapshot->id && 3 === $repository->next( 1 )->id && 0 === $repository->next( 1 )->is_active && null === $repository->next( 3 ), 'Cursor excludes inactive rows or is not ordered' );
        $results[] = $scenario; continue;
    }
    if ( 'custom' === $scenario ) { $snapshot = $repository->next( 1 ); }
    if ( 'mirror-delete' === $scenario ) { $wpdb->db->exec( "DELETE FROM wp_kiriminaja_settings WHERE `key` = 'origin_sub_district_name'" ); }
    if ( 'missing-insert' === $scenario ) { $wpdb->db->exec( "INSERT INTO wp_kiriminaja_settings (`key`, value) VALUES ('origin_sub_district_id', '99')" ); }
    if ( 'option-delete' === $scenario ) { $wpdb->db->exec( "DELETE FROM wp_options WHERE option_name = 'kiriof_wc_origin_area'" ); }
    if ( 'option-insert' === $scenario ) { $wpdb->db->exec( "INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('kiriof_wc_origin_area', '99', 'yes')" ); }
    if ( 'metadata-column' === $scenario ) { $snapshot->_unknown_metadata = 'must not ignore arbitrary properties'; }
    if ( 'duplicates' === $scenario ) { $wpdb->db->exec( "INSERT INTO wp_kiriminaja_settings (`key`, value) VALUES ('origin_zip_code', '40123')" ); }
    if ( 'blank-label-edit' === $scenario ) { $wpdb->db->exec( "UPDATE wp_kiriminaja_settings SET value = 'Edited, District, City, Province, 40123' WHERE `key` = 'origin_sub_district_name'" ); }
    if ( 'merchant-settings' === $scenario ) { $wpdb->db->exec( "UPDATE wp_kiriminaja_settings SET value = 'changed' WHERE `key` = 'origin_zip_code'" ); }
    if ( 'merchant-option' === $scenario ) { $wpdb->db->exec( "UPDATE wp_options SET option_value = '345' WHERE option_name = 'kiriof_wc_origin_area'" ); }
    if ( 'default-switch' === $scenario ) { $wpdb->db->exec( 'UPDATE wp_kiriminaja_shipment_location SET is_default = CASE WHEN id = 3 THEN 1 ELSE 0 END' ); }
    if ( 'write-failure' === $scenario ) { $wpdb->fail = 'UPDATE `wp_kiriminaja_shipment_location`'; }
    if ( 'commit-failure' === $scenario ) { $wpdb->fail = 'COMMIT'; }
    if ( 'lock-failure' === $scenario ) { $wpdb->fail = 'FOR UPDATE'; }
    $before = $wpdb->dump();
    $success = in_array( $scenario, array( 'custom', 'default', 'noop', 'missing', 'blank-label', 'blank-label-empty-zip' ), true );
    check( $success === $repository->repair( $snapshot, $canonical ), $scenario . ': wrong result' );
    $after = $wpdb->dump();
    if ( ! $success ) {
        check( $before === $after && array() === $GLOBALS['invalidated'], $scenario . ': partial mutation or early cache invalidation' );
    } else {
        $index = 'custom' === $scenario ? 1 : 0;
        $expected = $before['wp_kiriminaja_shipment_location'];
        $expected[ $index ] = array_merge( $expected[ $index ], $canonical, array( 'updated_at' => current_time( 'mysql' ) ) );
        check( $expected === $after['wp_kiriminaja_shipment_location'], $scenario . ': changed nonidentity fields' );
        check( $before['wp_options'][ 'missing' === $scenario ? 0 : 1 ] === $after['wp_options'][ 'missing' === $scenario ? 0 : 1 ], 'Native Woo option changed' );
        if ( 'custom' === $scenario ) {
            check( $before['wp_options'] === $after['wp_options'] && $before['wp_kiriminaja_settings'] === $after['wp_kiriminaja_settings'] && array() === $GLOBALS['invalidated'], 'Custom mirrored into default' );
        } else {
            $settings = array_column( $after['wp_kiriminaja_settings'], 'value', 'key' );
            check( '123' === $settings['origin_sub_district_id'] && $canonical['sub_district_name'] === $settings['origin_sub_district_name'] && '40124' === $settings['origin_zip_code'] && 'Do not touch' === $settings['origin_address'], 'Default mirrors incomplete' );
            $options = array_column( $after['wp_options'], 'option_value', 'option_name' );
            check( '123' === $options['kiriof_wc_origin_area'] && 3 === count( $GLOBALS['invalidated'] ), 'Option/cache not mirrored' );
        }
        if ( 'noop' === $scenario ) {
            $fresh = $repository->next( 0 );
            check( $repository->repair( $fresh, $canonical ) && $after === $wpdb->dump(), 'Already canonical repair mutated row' );
            check( ! $repository->repair( $snapshot, $canonical ) && $after === $wpdb->dump(), 'Stale snapshot replay accepted' );
        }
    }
    check( ! $wpdb->db->inTransaction(), 'Transaction leaked' );
    $results[] = $scenario;
}
// Every full-row field is an optimistic concurrency guard, including inactive,
// address order, coordinates and timestamps; identity comparison is exact.
foreach ( array( 'name', 'phone', 'address', 'address_2', 'city', 'state', 'country', 'latitude', 'longitude', 'is_default', 'is_active', 'sub_district_id', 'sub_district_name', 'zip_code', 'created_at', 'updated_at' ) as $field ) {
    $wpdb = new OriginMigrationSqliteWpdb();
    $snapshot = $repository->next( 0 );
    $wpdb->db->exec( 'UPDATE wp_kiriminaja_shipment_location SET `' . $field . "` = 'changed' WHERE id = 1" );
    $before = $wpdb->dump();
    check( ! $repository->repair( $snapshot, $canonical ) && $before === $wpdb->dump(), 'Concurrent edit overwritten: ' . $field );
    $results[] = 'concurrent-' . $field;
}
// A failed cursor read is not an exhausted cursor; mirror/option failures also
// must stop migration before it checkpoints a misleading successful snapshot.
foreach ( array( 'kiriminaja_shipment_location', 'kiriminaja_settings', 'wp_options' ) as $table ) {
    $wpdb = new OriginMigrationSqliteWpdb();
    $wpdb->fail = $table;
    $threw = false;
    try { $repository->next( 0 ); } catch ( RuntimeException $error ) { $threw = true; }
    check( $threw, 'Read failure treated as cursor exhaustion: ' . $table );
    $results[] = 'read-failure-' . $table;
}
// Engine metadata is required only at repair time, and must fail closed before
// any transaction, SQL mutation, or cache invalidation (even on query errors
// that leave an apparently valid status row).
foreach ( array( 'wp_kiriminaja_shipment_location', 'wp_kiriminaja_settings', 'wp_options' ) as $table ) {
    foreach ( array( 'myisam', 'missing-engine', 'missing-table', 'status-failure', 'wrong-name' ) as $failure ) {
        $wpdb = new OriginMigrationSqliteWpdb();
        $GLOBALS['invalidated'] = array();
        if ( 'myisam' === $failure ) { $wpdb->engines[ $table ] = 'MyISAM'; }
        if ( 'missing-engine' === $failure ) { $wpdb->engines[ $table ] = null; }
        if ( 'missing-table' === $failure ) { unset( $wpdb->engines[ $table ] ); }
        if ( 'status-failure' === $failure ) { $wpdb->status_failure = $table; }
        if ( 'wrong-name' === $failure ) { $wpdb->status_wrong_name = $table; }
        $snapshot = $repository->next( 0 );
        check( null !== $snapshot, 'Engine metadata blocked snapshot reads' );
        $before = $wpdb->dump();
        $wpdb->queries = array();
        check( ! $repository->repair( $snapshot, $canonical ), 'Unsafe engine accepted: ' . $table . '/' . $failure );
        check( $before === $wpdb->dump() && array() === $GLOBALS['invalidated'], 'Engine refusal mutated state' );
        check( ! $wpdb->db->inTransaction(), 'Engine refusal leaked transaction' );
        foreach ( $wpdb->queries as $query ) {
            check( str_starts_with( $query, 'SHOW TABLE STATUS WHERE Name = ' ), 'Engine refusal ran nonmetadata SQL: ' . $query );
        }
        $results[] = 'engine-' . $table . '-' . $failure;
    }
}
// Custom repairs require only the location table. Unrelated nontransactional
// mirrors must neither be inspected nor block their successful repair.
$wpdb = new OriginMigrationSqliteWpdb();
$GLOBALS['invalidated'] = array();
$wpdb->engines['wp_kiriminaja_shipment_location'] = 'iNnOdB';
$wpdb->engines['wp_kiriminaja_settings'] = 'MyISAM';
$wpdb->engines['wp_options'] = 'MyISAM';
$snapshot = $repository->next( 1 );
$before = $wpdb->dump();
$wpdb->queries = array();
check( $repository->repair( $snapshot, $canonical ), 'Custom repair blocked by unrelated engines' );
$after = $wpdb->dump();
check( 123 === $after['wp_kiriminaja_shipment_location'][1]['sub_district_id'], 'Custom repair did not write identity' );
check( $before['wp_kiriminaja_settings'] === $after['wp_kiriminaja_settings'] && $before['wp_options'] === $after['wp_options'] && array() === $GLOBALS['invalidated'], 'Custom engine check mutated mirrors' );
$statuses = array_values( array_filter( $wpdb->queries, static fn( $query ) => str_starts_with( $query, 'SHOW TABLE STATUS' ) ) );
check( array( "SHOW TABLE STATUS WHERE Name = 'wp_kiriminaja_shipment_location'" ) === $statuses, 'Custom repair inspected unrelated engines' );
check( ! $wpdb->db->inTransaction(), 'Custom repair leaked transaction' );
$results[] = 'engine-custom-unrelated-myisam';
$wpdb = new OriginMigrationSqliteWpdb();
$snapshot = $repository->next( 0 );
$before = $wpdb->dump();
$too_long = array_replace( $canonical, array( 'sub_district_name' => str_repeat( 'x', 192 ) ) );
check( ! $repository->repair( $snapshot, $too_long ) && $before === $wpdb->dump(), 'Oversized canonical label was truncated or mutated mirrors' );
$results[] = 'oversized-canonical';
$wpdb->db->exec( "INSERT INTO wp_kiriminaja_settings (`key`, value) VALUES ('origin_sub_district_name', 'duplicate')" );
$snapshot = $repository->next( 0 );
check( 'duplicate_mirrors' === $snapshot->_origin_error && ! $repository->repair( $snapshot, $canonical ), 'Conflicting snapshot was not marked unsafe' );
check( 3 === $repository->next( 1 )->id, 'Conflicting default blocked following warehouse' );
$results[] = 'snapshot-conflict';
echo json_encode( $results, JSON_THROW_ON_ERROR );
