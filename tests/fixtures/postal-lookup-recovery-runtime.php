<?php
// Production factory/service/repository, with only the outbound request replaced.
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'KIRIOF_NONCE', 'postal-recovery' );
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
$GLOBALS['postal_cache'] = $GLOBALS['postal_reads'] = $GLOBALS['postal_writes'] = $GLOBALS['postal_logs'] = array();
function get_transient( $key ) { if ( str_starts_with( $key, 'kiriof_district_search_' ) ) { $GLOBALS['postal_reads'][] = $key; } return $GLOBALS['postal_cache'][$key] ?? false; }
function set_transient( $key, $value, $ttl ) { if ( str_starts_with( $key, 'kiriof_district_search_' ) ) { $GLOBALS['postal_writes'][] = array( $key, $value, $ttl ); } $GLOBALS['postal_cache'][$key] = $value; return true; }
function kiriof_log( ...$args ) { $GLOBALS['postal_logs'][] = $args; }
function WC() { throw new RuntimeException( 'Lookup must not access checkout state.' ); }
function update_option( ...$args ) { throw new RuntimeException( 'Lookup must not write options.' ); }
function update_post_meta( ...$args ) { throw new RuntimeException( 'Lookup must not write orders.' ); }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function map_deep( $value, $callback ) { return is_array( $value ) ? array_map( static fn( $item ) => map_deep( $item, $callback ), $value ) : $callback( $value ); }
function wp_verify_nonce( $nonce, $action ) { return 'valid' === $nonce && KIRIOF_NONCE === $action; }
function __( $text, $domain ) { return $text; }
function postal_result( $response ) {
    echo json_encode( array( 'response' => $response, 'calls' => $GLOBALS['postal_repository']->calls, 'reads' => $GLOBALS['postal_reads'], 'writes' => $GLOBALS['postal_writes'], 'cache' => $GLOBALS['postal_cache'], 'logs' => $GLOBALS['postal_logs'] ), JSON_THROW_ON_ERROR );
    exit;
}
function wp_send_json_success( $data ) { postal_result( array( 'success' => true, 'data' => $data ) ); }
function wp_send_json_error( $data, $status = null ) { postal_result( array( 'success' => false, 'data' => $data, 'http_status' => $status ) ); }
$repository = new class() extends \KiriminAjaOfficial\Repositories\KiriminajaApiRepository {
    public array $calls = array();
    public array $queue = array();
    public function __construct() {}
    protected function address_request( string $method, string $endpoint, array $payload ): array {
        $this->calls[] = array( $method, $endpoint, $payload );
        if ( empty( $this->queue ) ) { throw new RuntimeException( 'Unexpected network call.' ); }
        return array_shift( $this->queue );
    }
};
$GLOBALS['postal_repository'] = $repository;
$empty = static fn( $class ) => ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor();
$factory = new \KiriminAjaOfficial\Services\CheckoutServiceFactory(
    $empty( \KiriminAjaOfficial\Repositories\SettingRepository::class ),
    $empty( \KiriminAjaOfficial\Repositories\TransactionRepository::class ),
    $empty( \KiriminAjaOfficial\Repositories\WpPostMetaRepository::class ), $repository,
    $empty( \KiriminAjaOfficial\Repositories\CodFeeApiRepository::class ),
    $empty( \KiriminAjaOfficial\Services\ShipmentLocationService::class )
);
$row = array( 'province_id' => 11, 'city_id' => 164, 'district_id' => 2275, 'subdistrict_id' => 46310, 'full_address' => 'Sariharjo, Ngaglik, Sleman, DI Yogyakarta, 55581' );
$tuple = static fn( $rows ) => array( true, array( 'status' => true, 'data' => $rows ) );
$key = 'kiriof_district_search_v4_' . md5( '55581' );
$scenario = $argv[1] ?? 'empty';
if ( 'empty' === $scenario || 'wrong_postcode' === $scenario ) {
    $GLOBALS['postal_cache']['kiriof_district_search_v3_' . md5( '55581' )] = array();
    $first_rows = 'empty' === $scenario ? array() : array( array_replace( $row, array( 'full_address' => 'Sariharjo, Ngaglik, Sleman, DI Yogyakarta, 55582' ) ) );
    $repository->queue = array( $tuple( $first_rows ), $tuple( array( $row ) ) );
    $first = $factory->districtSearch( '55581' );
    $second = $factory->districtSearch( '55581' );
    $third = $factory->districtSearch( '55581' );
    postal_result( array( $first, $second, $third ) );
}
if ( 'corrupt' === $scenario ) {
    $GLOBALS['postal_cache'][$key] = array( (object) array( 'id' => 0, 'text' => 'Invalid' ) );
    $repository->queue = array( $tuple( array( $row ) ) );
    postal_result( $factory->districtSearch( '55581' ) );
}
$repository->queue = array( $tuple( array( $row ) ) );
$factory->districtSearch( '55581' );
$repository->queue = array( $tuple( array( $row, array_replace( $row, array( 'subdistrict_id' => 46311, 'full_address' => 'Other village, Ngaglik, Sleman, DI Yogyakarta, 55581' ) ) ) ) );
$_POST = array( 'nonce' => 'valid', 'data' => array( 'search' => '55581' ) );
switch ( $scenario ) {
    case 'retry': $_POST['retry'] = '1'; break;
    case 'nested_retry': $_POST['data']['retry'] = '1'; break;
    case 'failed_retry': $_POST['retry'] = '1'; $repository->queue = array( array( false, 'Unavailable' ) ); break;
    case 'invalid_nonce': $_POST['nonce'] = 'invalid'; $_POST['retry'] = '1'; break;
    case 'array_nonce': $_POST['nonce'] = array( 'valid' ); $_POST['retry'] = '1'; break;
    case 'array_retry': $_POST['retry'] = array( '1' ); $_POST['data']['retry'] = array( '1' ); break;
    case 'nested_integer_retry': $_POST['data']['retry'] = 1; break;
    case 'nested_boolean_retry': $_POST['data']['retry'] = true; break;
    case 'float_retry': $_POST['retry'] = 1.0; break;
    case 'padded_retry': $_POST['retry'] = ' 1 '; break;
    case 'zero_retry': $_POST['retry'] = '01'; break;
    case 'integer_retry': $_POST['retry'] = 1; break;
    case 'boolean_retry': $_POST['retry'] = true; break;
    case 'sanitized_retry': $_POST['retry'] = '<b>1</b>'; break;
}
( new \KiriminAjaOfficial\Controllers\GeneralAjaxController( $factory ) )->kiriminajaSubdistrictSearch();
