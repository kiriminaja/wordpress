<?php
// Reuse the real SQL adapter after its own rollback/concurrency scenarios pass.
ob_start();
require __DIR__ . '/origin-address-migration-runtime.php';
ob_end_clean();
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function kiriof_log( ...$args ) { $GLOBALS['pricing_logs'][] = $args; }
$wpdb = new OriginMigrationSqliteWpdb();
$old = 'Sari Harjo, Ngaglik, Sleman, DI Yogyakarta, 55581';
$statement = $wpdb->db->prepare( 'UPDATE wp_kiriminaja_shipment_location SET sub_district_name = ?, zip_code = ? WHERE id = 1' );
$statement->execute( array( $old, '55581' ) );
$statement = $wpdb->db->prepare( 'UPDATE wp_kiriminaja_settings SET value = ? WHERE `key` = ?' );
$statement->execute( array( $old, 'origin_sub_district_name' ) );
$statement->execute( array( '55581', 'origin_zip_code' ) );
$GLOBALS['transients'] = array();
$GLOBALS['pricing_logs'] = array();
$api = new class() extends \KiriminAjaOfficial\Repositories\KiriminajaApiRepository {
    public array $posts = array();
    public function __construct() {}
    protected function address_request( string $method, string $endpoint, array $payload ): array {
        return array( true, array( 'status' => true, 'data' => array(
            array( 'province_id' => 5, 'city_id' => 419, 'district_id' => 2275, 'subdistrict_id' => 46310, 'full_address' => 'Sariharjo, Ngaglik, Sleman, DI Yogyakarta, 55581' ),
            array( 'province_id' => 5, 'city_id' => 39, 'district_id' => 548, 'subdistrict_id' => 31483, 'full_address' => 'Village, District, City, DI Yogyakarta, 55791' ),
        ) ) );
    }
    public function post( $endpoint, $body = array(), $context = array(), $args = array() ) {
        $this->posts[] = array( $endpoint, $body );
        return array( 'status' => true, 'data' => (object) array( 'status' => true, 'results' => array( (object) array( 'service' => 'jne', 'cost' => 15000 ) ) ) );
    }
};
$payload = array( 'subdistrict_origin' => 99, 'subdistrict_destination' => 31483, 'origin_postcode' => '55581', 'destination_postcode' => '55791', 'weight' => 1000, 'length' => 1, 'width' => 2, 'height' => 3, 'insurance' => 0, 'item_value' => 100000, 'courier' => array( 'jne' ) );
check( ! $api->getPricing( $payload )['status'] && ! $api->posts, 'Stale warehouse unexpectedly priced' );
$repository = new \KiriminAjaOfficial\Repositories\OriginAddressMigrationRepository();
$snapshot = $repository->next( 0 );
$lookup = $api->sub_district_search( '55581' );
$canonical = \KiriminAjaOfficial\Services\OriginAddressMatcher::match( $snapshot->sub_district_id, $snapshot->sub_district_name, $snapshot->zip_code, $lookup['data']->result );
check( null !== $canonical && $repository->repair( $snapshot, $canonical ), 'Saved hierarchy was not repaired' );
$origin = $repository->next( 0 );
check( 46310 === $origin->sub_district_id && $origin->latitude === $snapshot->latitude && $origin->address === $snapshot->address, 'Identity or untouched fields wrong' );
// Simulate a subsequent request after the temporary mapping expires.
$GLOBALS['transients']['kiriof_address_identity_v1'] = array();
$payload['subdistrict_origin'] = $origin->sub_district_id;
check( $api->getPricing( $payload )['status'] && count( $api->posts ) === 1, 'Persisted origin failed cold pricing' );
check( $api->posts[0][1]['origin'] === 2275 && $api->posts[0][1]['subdistrict_origin'] === 46310, 'Wrong parent/village sent to pricing' );
$settings = array_column( $wpdb->db->query( 'SELECT * FROM wp_kiriminaja_settings' )->fetchAll( PDO::FETCH_ASSOC ), 'value', 'key' );
check( $settings['origin_sub_district_id'] === '46310' && $settings['origin_sub_district_name'] === $canonical['sub_district_name'], 'Default mirrors were not persistent' );
echo 'ok';
