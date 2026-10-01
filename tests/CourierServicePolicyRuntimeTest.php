<?php

use PHPUnit\Framework\TestCase;

/** Isolated runtime: no WordPress globals leak into other test workers. */
final class CourierServicePolicyRuntimeTest extends TestCase {
    public function test_service_policy_runtime(): void {
        $script = <<<'PHP'
<?php
 define( 'ABSPATH', __DIR__ );
 error_reporting( E_ALL & ~E_DEPRECATED );
 function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
 function get_transient( $key ) { return $GLOBALS['transients'][$key] ?? $GLOBALS['courier_cache'] ?? false; }
 function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][$key] = $value; }
 function kiriof_log( ...$args ) {}
 define( 'DAY_IN_SECONDS', 86400 );
 define( 'WEEK_IN_SECONDS', 604800 );
 function current_user_can( $capability ) { return true; }
 function wp_verify_nonce( $nonce, $action ) { return true; }
 function wp_unslash( $value ) { return $value; }
 function wp_json_encode( $value ) { return json_encode( $value ); }
 function wp_send_json_success( $value ) { $GLOBALS['ajax_result'] = array( true, $value ); }
 function wp_send_json_error( $value ) { $GLOBALS['ajax_result'] = array( false, $value ); }
 define( 'KIRIOF_NONCE', 'policy' );
 require %AUTOLOAD%;
 require %CATALOG%;
 require %REPOSITORY%;
 require %CONTROLLER%;
 require %RESPONSE%;
 require %BASE%;
 require %API%;
 eval( 'namespace KiriminAjaOfficial\\Repositories; class KiriminajaApiRepository { public function get_couriers() { return $GLOBALS["api_result"]; } }' );
 use KiriminAjaOfficial\Services\CourierServiceCatalog as Catalog;
 use KiriminAjaOfficial\Repositories\SettingRepository as Repository;
 function check( $actual, $expected ) {
     if ( $actual !== $expected ) { throw new RuntimeException( var_export( array( $actual, $expected ), true ) ); }
 }
 class PolicyDb {
     public $prefix = 'policy_';
     public $last_error = '';
     public $rows = array();
     public $fail = false;
     private $snapshot = array();
     public function query( $sql ) {
         if ( 'START TRANSACTION' === $sql ) { $this->snapshot = $this->rows; }
         if ( 'ROLLBACK' === $sql ) { $this->rows = $this->snapshot; }
         return 1;
     }
     public function prepare( $sql, $key ) { return $key; }
     public function get_row( $key ) { return isset( $this->rows[$key] ) ? (object) array( 'value' => $this->rows[$key] ) : null; }
     public function insert( $table, $data, $formats ) { if ( $this->fail ) { return false; } $this->rows[$data['key']] = $data['value']; return 1; }
     public function update( $table, $data, $where, ...$formats ) { if ( $this->fail ) { return false; } $this->rows[$where['key']] = $data['value']; return 1; }
 }
 $wpdb = new PolicyDb();
 $repository = new Repository();
 check( $repository->getCourierServiceSelection(), null );
 check( $repository->isCourierServiceEnabled( 'jne', 'REG23' ), true );
 $repository->storeCourierWhitelist( array( 'origin_whitelist_expedition_id' => 'JNE' ) );
 check( $repository->isCourierServiceEnabled( 'idx', '00' ), false );
 $repository->storeCourierWhitelist( array( 'service_selection' => '{"jne":["REG"],"idx":["00"],"ninja":["Standard"],"paxel":["PAXEL BIG"]}' ) );
 check( $repository->isCourierServiceEnabled( 'JNE', 'reg23' ), true );
 check( $repository->isCourierServiceEnabled( 'jne', 'CTC' ), false );
 check( $repository->isCourierServiceEnabled( 'jne', 'REG99' ), false );
 check( $repository->isCourierServiceEnabled( 'idx', '00' ), true );
 check( $repository->isCourierServiceEnabled( 'idx', '0' ), false );
 check( $repository->isCourierServiceEnabled( 'idx', 'Standard' ), false );
 check( $repository->isCourierServiceEnabled( 'ninja', 'standard' ), true );
 check( $repository->isCourierServiceEnabled( 'paxel', 'PAXEL BIG' ), true );
 $rows = array( (object) array( 'service' => 'jne', 'service_name' => 'REG23' ), array( 'service' => 'jne', 'service_name' => 'CTC' ), array( 'service' => 'idx', 'service_name' => '00' ) );
 check( $repository->validateWhiteListExpedition( $rows ), array( $rows[0], $rows[2] ) );
 $typed_rows = array( array( 'service' => 'jne', 'service_type' => 'REG23', 'service_name' => 'Display label' ), (object) array( 'service' => 'jne', 'service_type' => 'CTC', 'service_name' => 'REG' ) );
 check( $repository->validateWhiteListExpedition( $typed_rows ), array( $typed_rows[0] ) );
 $repository->storeCourierWhitelist( array( 'origin_whitelist_expedition_id' => 'jnt' ) );
 check( $repository->isCourierServiceEnabled( 'jnt', 'EZ' ), false );
 check( $repository->isCourierServiceEnabled( 'jne', 'REG23' ), true );
 $repository->storeCourierWhitelist( array( 'service_selection' => '{"jne":["YES"]}' ) );
 check( $repository->isCourierServiceEnabled( 'jne', 'REG23' ), false );
 $repository->storeCourierWhitelist( array( 'service_selection' => '{}' ) );
 check( $repository->getCourierServiceSelection(), array() );
 check( $repository->validateWhiteListExpedition( $rows ), array() );
 $repository->storeCourierWhitelist( array( 'service_selection' => '{"jne":[]}' ) );
 check( $repository->isCourierServiceEnabled( 'jne', 'REG' ), false );
 foreach ( array( '[]', 'null', '{', '{"jne":"REG"}', '{"jne":[0]}', '{"jne":{}}' ) as $bad ) {
     try { $repository->storeCourierWhitelist( array( 'service_selection' => $bad ) ); throw new RuntimeException( 'Malformed policy accepted' ); }
     catch ( InvalidArgumentException $e ) {}
 }
 $couriers = Catalog::enrich( array( (object) array( 'code' => 'jne', 'name' => 'JNE' ), array( 'code' => 'mystery', 'name' => 'Mystery' ), array( 'code' => 'instantx', 'type' => 'instant' ), array( 'code' => 'internationalx', 'region' => 'INTERNATIONAL' ) ) );
 check( count( $couriers ), 2 );
 check( $couriers[1]['services'], array( array( 'code' => '*', 'name' => 'All services' ) ) );
 $embedded = Catalog::enrich( array( array( 'code' => 'jne', 'services' => array( array( 'code' => 'NEW', 'name' => 'New Service' ) ) ) ) );
 check( $embedded[0]['services'], array( array( 'code' => 'NEW', 'name' => 'New Service' ) ) );
 $GLOBALS['courier_cache'] = array( array( 'code' => 'mystery' ) );
 check( Catalog::canonicalService( 'mystery', '*' ), '*' );
 $repository->storeCourierWhitelist( array( 'service_selection' => '{"mystery":["*"]}' ) );
 check( $repository->isCourierServiceEnabled( 'mystery', 'anything' ), true );
 // Exercise the real controller and repository together, including legacy CSV mirrors.
 require %COMPOSITION%;
 $controller = courier_setting_controller( $repository );
 function save_selection( $json, $success ) {
     global $controller, $wpdb;
     $before = $wpdb->rows;
     $_POST = array( 'data' => array( 'nonce' => 'policy', 'service_selection' => $json, 'whitelist_names' => 'Attacker label', 'whitelist_ids' => 'attacker' ) );
     $controller->storeCourierWhitelist();
     check( $GLOBALS['ajax_result'][0], $success );
     if ( ! $success ) { check( $wpdb->rows, $before ); }
 }
 $GLOBALS['courier_cache'] = false;
 // Live, warm-cache and last-success paths share the same exclusion policy.
 $raw = array( array( 'code' => 'jne' ), array( 'code' => 'ninja_inter', 'type' => 'regular' ), array( 'code' => 'instantx', 'type' => 'instant' ), array( 'code' => 'overseas', 'region' => ' INTERNATIONAL ' ), array( 'code' => 'mystery' ) );
 $api = new KiriminAjaOfficial\Services\KiriminajaApiService();
 $GLOBALS['api_result'] = array( 'status' => true, 'data' => (object) array( 'status' => true, 'datas' => $raw ) );
 check( array_column( $api->get_couriers()->data, 'code' ), array( 'jne', 'mystery' ) );
 $GLOBALS['transients']['kiriof_couriers_list_v2'] = $raw;
 check( array_column( $api->get_couriers()->data, 'code' ), array( 'jne', 'mystery' ) );
 unset( $GLOBALS['transients']['kiriof_couriers_list_v2'] );
 $GLOBALS['transients']['kiriof_couriers_last_success_cache'] = $raw;
 $GLOBALS['api_result'] = array( 'status' => false );
 check( array_column( $api->get_couriers()->data, 'code' ), array( 'jne', 'mystery' ) );
 $repository->storeCourierWhitelist( array( 'service_selection' => '{"ninja_inter":["*"],"jne":["REG"]}', 'origin_whitelist_expedition_id' => 'ninja_inter,jne' ) );
 $_POST = array( 'data' => array( 'nonce' => 'policy' ) );
 $controller->getCourierWhitelist();
 check( (array) $GLOBALS['ajax_result'][1]['data']['service_selection'], array( 'jne' => array( 'REG' ) ) );
 check( $GLOBALS['ajax_result'][1]['data']['whitelist_ids'], array( 'jne' ) );
 save_selection( '{"ninja_inter":["*"]}', false );
 check( strpos( $GLOBALS['ajax_result'][1]['message'], 'Remove international or instant couriers' ) !== false, true );
 // Enable All from GET must be saveable, including numeric API codes and wildcard fallbacks.
 $GLOBALS['transients']['kiriof_couriers_list_v2'] = array( array( 'code' => 'idx', 'services' => array( array( 'code' => '00' ), array( 'code' => 7 ) ) ), array( 'code' => 'mystery' ), array( 'code' => 'jne', 'services' => array( array( 'code' => 'NEW' ) ) ) );
 $controller->getCourierWhitelist();
 $all = array();
 foreach ( $GLOBALS['ajax_result'][1]['data']['couriers'] as $courier ) { $all[$courier['code']] = array_column( $courier['services'], 'code' ); }
 save_selection( json_encode( (object) $all ), true );
 check( Catalog::canonicalService( 'idx', '00' ), '00' );
 check( Catalog::canonicalService( 'idx', '7' ), '7' );
 check( Catalog::canonicalService( 'jne', 'REG23', Catalog::available() ), 'REG' );
 save_selection( '{"idx":["777"]}', false );
 $GLOBALS['transients']['kiriof_couriers_last_success_cache'] = $GLOBALS['transients']['kiriof_couriers_list_v2'];
 unset( $GLOBALS['transients']['kiriof_couriers_list_v2'] );
 save_selection( json_encode( (object) $all ), true );
 $GLOBALS['transients'] = array();
 $repository->storeCourierWhitelist( array( 'service_selection' => '{"jne":["Retired"],"mystery":["Legacy","*"],"other":["Gone"]}', 'origin_whitelist_expedition_id' => 'jne,mystery', 'origin_whitelist_expedition_name' => 'JNE,Historical Courier' ) );
 save_selection( '{"jne":["retired","REG"],"mystery":["legacy","*"],"other":["gone"]}', true );
 check( $repository->getCourierServiceSelection(), array( 'jne' => array( 'Retired', 'REG' ), 'mystery' => array( 'Legacy', '*' ), 'other' => array( 'Gone' ) ) );
 check( $wpdb->rows['origin_whitelist_expedition_name'], 'JNE Express,Historical Courier,OTHER' );
 $repository->storeCourierWhitelist( array( 'service_selection' => '{"mystery":["Legacy"]}', 'origin_whitelist_expedition_id' => 'mystery', 'origin_whitelist_expedition_name' => '<b>Historical Courier</b>' ) );
 save_selection( '{"mystery":["legacy"]}', true );
 check( $wpdb->rows['origin_whitelist_expedition_name'], 'Historical Courier' );
 $repository->storeCourierWhitelist( array( 'service_selection' => '{"jne":["Retired"],"mystery":["Legacy","*"],"other":["Gone"]}' ) );
 save_selection( '{"jne":["NewUnknown"]}', false );
 save_selection( '{"mystery":["NewUnknown"]}', false );
 save_selection( '{"newcourier":["Legacy"]}', false );
 save_selection( '{"jne":["Legacy"]}', false );
 save_selection( '{"mystery":["gone"]}', false );
 // The old wildcard can be retained or expanded into newly displayed explicit services.
 $GLOBALS['courier_cache'] = array( array( 'code' => 'mystery', 'services' => array( array( 'code' => 'NEW' ), array( 'code' => 'NEXT' ) ) ) );
 save_selection( '{"mystery":["*"]}', true );
 save_selection( '{"mystery":["NEW","NEXT"]}', true );
 save_selection( '{"mystery":["*"]}', false );
 // An absent courier may be disabled, but an existing empty entry grants no choices.
 $repository->storeCourierWhitelist( array( 'service_selection' => '{"missing":[]}' ) );
 save_selection( '{"missing":[]}', true );
 save_selection( '{"missing":["*"]}', false );
 save_selection( '{}', true );
 check( $wpdb->rows['origin_whitelist_expedition_id'], '' );
 // Historical keys cannot become injected CSV columns, and corrupt policy grants no exception.
 $repository->storeCourierWhitelist( array( 'service_selection' => '{"bad,code":["OLD"]}' ) );
 save_selection( '{"bad,code":["OLD"]}', false );
 save_selection( '{}', true );
 $repository->storeCourierWhitelist( array( 'service_selection' => '{"jne":["OLD"]}' ) );
 $wpdb->rows['origin_whitelist_expedition_services'] = '{';
 $cache = new ReflectionProperty( Repository::class, 'setting_cache' );
 if ( PHP_VERSION_ID < 80100 ) { $cache->setAccessible( true ); }
 $cache->setValue( null, array() );
 save_selection( '{"jne":["OLD"]}', false );
 $wpdb->fail = true;
 try { $repository->storeCourierWhitelist( array( 'service_selection' => '{}' ) ); throw new RuntimeException( 'Failed write accepted' ); }
 catch ( RuntimeException $e ) { check( $e->getMessage(), 'Unable to save courier settings.' ); }
 echo 'ok';
PHP;
        $paths = array( '%COMPOSITION%' => 'tests/fixtures/courier-setting-controller.php', '%AUTOLOAD%' => 'vendor/autoload.php', '%CATALOG%' => 'inc/Services/CourierServiceCatalog.php', '%REPOSITORY%' => 'inc/Repositories/SettingRepository.php', '%CONTROLLER%' => 'inc/Controllers/SettingController.php', '%RESPONSE%' => 'inc/Utils/ServiceResponse.php', '%BASE%' => 'inc/Base/BaseService.php', '%API%' => 'inc/Services/KiriminajaApiService.php' );
        foreach ( $paths as $placeholder => $path ) { $script = str_replace( $placeholder, var_export( PLUGIN_DIR . '/' . $path, true ), $script ); }
        $file = tempnam( sys_get_temp_dir(), 'courier-policy-' );
        file_put_contents( $file, $script );
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $output, $status );
        unlink( $file );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        $this->assertSame( 'ok', implode( "\n", $output ) );
    }
}
