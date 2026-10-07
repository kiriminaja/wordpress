<?php
// Offline official address rows: village identity is not its Kecamatan parent.
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
function get_transient( $key ) { return $GLOBALS['identity_transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['identity_transients'][ $key ] = $value; return true; }
require ABSPATH . 'vendor/autoload.php';
$repository = new class() extends \KiriminAjaOfficial\Repositories\KiriminajaApiRepository {
    public array $searches = array();
    public array $posts = array();
    public function __construct() {}
    protected function address_request( string $method, string $endpoint, array $payload ): array {
        $this->searches[] = array( $method, $endpoint, $payload );
        return array( true, array( 'status' => true, 'data' => array(
            array( 'province_id' => 11, 'city_id' => 164, 'district_id' => 2275, 'subdistrict_id' => 46310, 'full_address' => 'Sidokerto, Mojowarno, Jombang, Jawa Timur, 61475' ),
            array( 'province_id' => 5, 'city_id' => 39, 'district_id' => 548, 'subdistrict_id' => 31483, 'full_address' => 'Village, District, City, Province, 55791' ),
        ) ) );
    }
    public function post( $endpoint, $body = array(), $log_context = array(), $request_args = array() ) {
        $this->posts[] = array( $endpoint, $body );
        return array( 'status' => true, 'data' => (object) array( 'status' => true, 'results' => array() ) );
    }
};
$payload = array( 'subdistrict_origin' => 46310, 'subdistrict_destination' => 31483, 'origin_postcode' => '61475', 'destination_postcode' => '55791', 'weight' => 1000, 'length' => 1, 'width' => 2, 'height' => 3, 'insurance' => 1, 'item_value' => 100000, 'courier' => array( 'jne' ) );
$result['first'] = $repository->getPricing( $payload );
$result['repeat'] = $repository->getPricing( $payload );
$result['wrong_postcode'] = $repository->getPricing( array_replace( $payload, array( 'destination_postcode' => '61475' ) ) );
$result['missing_origin'] = $repository->getPricing( array_replace( $payload, array( 'subdistrict_origin' => 0 ) ) );
$result['unknown_without_postcode'] = $repository->getPricing( array_replace( $payload, array( 'subdistrict_origin' => 99999, 'origin_postcode' => '' ) ) );
$result['cached_without_postcode'] = $repository->getPricing( array_replace( $payload, array( 'origin_postcode' => '', 'destination_postcode' => '' ) ) );
$result['wrong_village'] = $repository->getPricing( array_replace( $payload, array( 'subdistrict_destination' => 99998 ) ) );
$result['cached_identities'] = $GLOBALS['identity_transients']['kiriof_address_identity_v1'];
// Real UI lookup warms mapping; pricing then needs no additional address request.
$repository->sub_district_search( 'Sidokerto' );
$warm_searches = count( $repository->searches );
$result['warm'] = $repository->getPricing( $payload );
$result['warm_additional_searches'] = count( $repository->searches ) - $warm_searches;
$result['searches'] = $repository->searches;
$result['posts'] = $repository->posts;
echo json_encode( $result, JSON_THROW_ON_ERROR );
