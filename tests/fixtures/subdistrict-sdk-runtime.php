<?php
// Offline unified-address fixtures; never contacts the API or reads credentials.
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
function kiriof_log( $level, $message, $context ) { $GLOBALS['address_logs'][] = array( $level, $message, $context ); }
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
$repository = new class() extends \KiriminAjaOfficial\Repositories\KiriminajaApiRepository {
    public array $tuple = array();
    public array $calls = array();
    public float $clock = 0.0;
    public float $duration = 0.0;
    public bool $throws = false;
    public function __construct() {}
    protected function address_lookup_clock(): float { return $this->clock; }
    protected function address_request( string $method, string $endpoint, array $payload ): array {
        $this->calls[] = array( $method, $endpoint, $payload );
        $this->clock += $this->duration;
        if ( $this->throws ) { throw new \RuntimeException( 'Bearer PRIVATE query sari harjo' ); }
        return $this->tuple;
    }
};
$service = new \KiriminAjaOfficial\Services\KiriminajaApiService( $repository );
$address = array( 'province_id' => 11, 'city_id' => 164, 'district_id' => 2275, 'subdistrict_id' => 46310, 'full_address' => 'Sidokerto, Mojowarno, Jombang, Jawa Timur, 61475' );
$run = static function ( $body, $search = 'sari harjo', $duration = 0.0, $throws = false, $ok = true ) use ( $repository, $service ) {
    $repository->tuple = array( $ok, $body );
    $repository->clock = 0.0;
    $repository->duration = $duration;
    $repository->throws = $throws;
    $repository->calls = array();
    $GLOBALS['address_logs'] = array();
    return array( 'response' => $service->sub_district_search( $search ), 'calls' => $repository->calls, 'logs' => $GLOBALS['address_logs'] );
};
$body = static fn( $rows ) => array( 'status' => true, 'method' => 'getAddresses', 'text' => 'Success', 'data' => $rows );
$results = array();
$results['official'] = $run( $body( array( $address ) ), 'Ngemplak' );
$sari = array_replace( $address, array( 'full_address' => 'Sariharjo, Ngaglik, Sleman, DI Yogyakarta, 55581' ) );
$results['sari'] = $run( $body( array( $sari ) ) );
$results['empty'] = $run( $body( array() ) );
$results['duplicate'] = $run( $body( array( $address, $address ) ) );
$alias = $address + array( 'id' => '46310', 'kecamatan_id' => '2275', 'kelurahan_name' => 'Sidokerto' );
$results['aliases'] = $run( $body( array( $alias ) ) );
$wrong_zip = array_replace( $address, array( 'subdistrict_id' => 46311, 'full_address' => 'Other, Mojowarno, Jombang, Jawa Timur, 61476' ) );
$results['postcode'] = $run( $body( array( $address, $wrong_zip ) ), '61475' );
$results['postcode_empty'] = $run( $body( array( $wrong_zip ) ), '61475' );
foreach ( array(
    'missing_id' => array_diff_key( $address, array( 'subdistrict_id' => true ) ),
    'invalid_id' => array_replace( $address, array( 'subdistrict_id' => 0 ) ),
    'float_id' => array_replace( $address, array( 'subdistrict_id' => 46310.0 ) ),
    'parent_alias' => $address + array( 'id' => 2275 ),
    'parent_mismatch' => $address + array( 'kecamatan_id' => 999 ),
    'name_mismatch' => $address + array( 'kelurahan_name' => 'Different' ),
    'hierarchy' => array_replace( $address, array( 'full_address' => 'District, City, Province, 61475' ) ),
    'no_postcode' => array_replace( $address, array( 'full_address' => 'Sidokerto, Mojowarno, Jombang, Jawa Timur' ) ),
) as $name => $row ) { $results[$name] = $run( $body( array( $row ) ) ); }
$results['conflict'] = $run( $body( array( $address, $wrong_zip + array( 'id' => 46310 ) ) ) );
$results['village_conflict'] = $run( $body( array( $address, array_replace( $wrong_zip, array( 'subdistrict_id' => 46310 ) ) ) ) );
$results['parent_conflict'] = $run( $body( array( $address, array_replace( $wrong_zip, array( 'city_id' => 999 ) ) ) ) );
$results['missing_list'] = $run( array( 'status' => true ) );
$results['wrong_list'] = $run( array( 'status' => true, 'data' => array( 'result' => $address ) ) );
$results['bad_status'] = $run( array( 'status' => 'true', 'data' => array() ) );
$results['rejection'] = $run( array( 'status' => false, 'text' => 'PRIVATE credentials' ) );
$results['transport'] = $run( 'PRIVATE token', 'sari harjo', 0.0, false, false );
$results['exception'] = $run( null, 'sari harjo', 0.0, true );
$results['late'] = $run( $body( array( $address ) ), 'sari harjo', 26.0 );
$results['short'] = $run( $body( array() ), 'ab' );
$results['boundary'] = $run( $body( array_fill( 0, 500, $address ) ) );
$results['limit'] = $run( $body( array_fill( 0, 501, $address ) ) );
echo json_encode( $results, JSON_THROW_ON_ERROR );
