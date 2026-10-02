<?php
// Offline facade double: execute callbacks through the real call_sdk safety layer.
namespace KiriminAja\Services {
    class KiriminAja {
        public static array $parents = array();
        public static array $children = array();
        public static array $calls = array();
        public static function getDistrictByName( string $search ) {
            self::$calls[] = array( 'parent', $search );
            return new \KiriminAja\Responses\ServiceResponse( true, 'loaded', self::$parents );
        }
        public static function getSubDistrict( int $id ) {
            self::$calls[] = array( 'child', $id );
            $data = self::$children[ $id ] ?? array();
            if ( $data instanceof \Throwable ) { throw $data; }
            return new \KiriminAja\Responses\ServiceResponse( true, 'loaded', $data );
        }
    }
}
namespace {
    define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
    define( 'DAY_IN_SECONDS', 86400 );
    function wp_json_encode( $value ) { return json_encode( $value ); }
    require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
    $repository = new class() extends \KiriminAjaOfficial\Repositories\KiriminajaApiRepository {
        public array $addresses = array();
        public function __construct() {} // No settings, credentials, or networking.
        public function get( $endpoint, $body = array(), $log_context = array() ) {
            \KiriminAja\Services\KiriminAja::$calls[] = array( 'get', $endpoint, $body );
            return array( 'status' => true, 'data' => json_decode( json_encode( array( 'status' => true, 'data' => $this->addresses ) ) ) );
        }
    };
    $service = new \KiriminAjaOfficial\Services\KiriminajaApiService( $repository );
    $parent = array( 'id' => 548, 'text' => 'Pleret, Kabupaten Bantul, DI Yogyakarta' );
    $children = array(
        array( 'id' => 31483, 'subdistrict_name' => 'Bawuran', 'zip_code' => '55791' ),
        array( 'id' => '31484', 'subdistrict_name' => 'Wonokromo', 'zip_code' => '55792' ),
        array( 'id' => 31485, 'kelurahan_name' => 'Segoroyoso', 'kecamatan_id' => 548 ),
    );
    $run = static function ( $parents, $child_rows, $search ) use ( $service ) {
        \KiriminAja\Services\KiriminAja::$parents = $parents;
        \KiriminAja\Services\KiriminAja::$children = $child_rows;
        \KiriminAja\Services\KiriminAja::$calls = array();
        return array( 'response' => $service->sub_district_search( $search ), 'calls' => \KiriminAja\Services\KiriminAja::$calls );
    };
    $results = array();
    $results['text'] = $run( array( $parent, $parent ), array( 548 => array_merge( $children, array( $children[0] ) ) ), 'Pleret' );
    $results['empty'] = $run( array( $parent ), array(), 'Pleret' );
    $results['failure'] = $run( array( $parent, array( 'id' => 549, 'text' => 'Other, City, Province' ) ), array( 548 => $children, 549 => new \RuntimeException( 'Bearer secret-token' ) ), 'Pleret' );
    $results['invalid_parent'] = $run( array( array( 'id' => '55791x' ) ), array(), 'Pleret' );
    $results['malformed_child'] = $run( array( $parent ), array( 548 => array( array( 'id' => 0, 'subdistrict_name' => 'Invalid' ) ) ), 'Pleret' );
    $results['wrong_parent'] = $run( array( $parent ), array( 548 => array( array( 'id' => 31483, 'kelurahan_name' => 'Bawuran', 'kecamatan_id' => 549 ) ) ), 'Pleret' );
    $results['conflicting_duplicate'] = $run( array( $parent ), array( 548 => array( $children[0], array( 'id' => 31483, 'subdistrict_name' => 'Different' ) ) ), 'Pleret' );
    $parents = array();
    for ( $i = 1; $i <= 51; $i++ ) { $parents[] = array( 'id' => $i, 'text' => 'District, City, Province' ); }
    $results['bounds'] = $run( $parents, array(), 'District' );
    $results['boundary'] = $run( array_slice( $parents, 0, 50 ), array(), 'District' );
    $address = array( 'province_id' => 5, 'city_id' => 39, 'district_id' => 548, 'subdistrict_id' => 31483, 'full_address' => 'Bawuran, Pleret, Kabupaten Bantul, DI Yogyakarta, 55791' );
    $second = array_replace( $address, array( 'subdistrict_id' => 31485, 'full_address' => 'Segoroyoso, Pleret, Kabupaten Bantul, DI Yogyakarta, 55791' ) );
    $wrong = array_replace( $address, array( 'subdistrict_id' => 31484, 'full_address' => 'Wonokromo, Pleret, Kabupaten Bantul, DI Yogyakarta, 55792' ) );
    $postal_children = array_map( static function ( $child ) { unset( $child['zip_code'] ); return $child; }, $children );
    $repository->addresses = array( $address, $second, $wrong, $address );
    $results['postcode'] = $run( array(), array( 548 => array_merge( $postal_children, array( $postal_children[0] ) ) ), '55791' );
    $results['postcode_missing_child'] = $run( array(), array( 548 => array( $postal_children[0] ) ), '55791' );
    $repository->addresses = array( $address, array_replace( $second, array( 'district_id' => 'invalid' ) ) );
    $results['postcode_invalid_parent'] = $run( array(), array( 548 => $postal_children ), '55791' );
    $repository->addresses = array( array_replace( $address, array( 'subdistrict_id' => 0 ) ) );
    $results['postcode_invalid_child'] = $run( array(), array( 548 => $postal_children ), '55791' );
    $repository->addresses = array( $wrong );
    $results['postcode_wrong_only'] = $run( array(), array( 548 => $postal_children ), '55791' );
    $repository->addresses = array( $address );
    $results['postcode_wrong_parent'] = $run( array(), array( 548 => array( array_replace( $postal_children[0], array( 'kecamatan_id' => 549 ) ) ) ), '55791' );
    $results['postcode_failure'] = $run( array(), array( 548 => new \RuntimeException( 'Bearer secret-token' ) ), '55791' );
    $addresses = array();
    for ( $i = 1; $i <= 51; $i++ ) { $addresses[] = array_replace( $address, array( 'district_id' => $i, 'subdistrict_id' => $i ) ); }
    $repository->addresses = $addresses;
    $results['postcode_bounds'] = $run( array(), array(), '55791' );
    $repository->addresses = array_slice( $addresses, 0, 50 );
    $child_rows = array();
    for ( $i = 1; $i <= 50; $i++ ) { $child_rows[$i] = array( array( 'id' => $i, 'subdistrict_name' => 'Bawuran' ) ); }
    $results['postcode_boundary'] = $run( array(), $child_rows, '55791' );
    echo json_encode( $results, JSON_THROW_ON_ERROR );
}
