<?php
/** Minimal WP/storage boundary; all catalog, API service and settings logic is real. */
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'KIRIOF_NONCE', 'instant-settings' );
error_reporting( E_ALL & ~E_DEPRECATED );
$GLOBALS['transients'] = array();
$GLOBALS['api_calls'] = 0;
$GLOBALS['allowed'] = true;
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function get_transient( $key ) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][$key] = $value; $GLOBALS['ttls'][$key] = $ttl; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][$key] ); $GLOBALS['deleted'][] = $key; }
function kiriof_log( ...$args ) {}
function __( $value, $domain ) { return $value; }
function current_user_can( $capability ) { same( $capability, 'manage_woocommerce' ); return $GLOBALS['allowed']; }
function wp_verify_nonce( $nonce, $action ) { same( $action, KIRIOF_NONCE ); return 'valid' === $nonce; }
function wp_unslash( $value ) { return $value; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_send_json_success( $value ) { $GLOBALS['ajax'] = array( true, $value ); }
function wp_send_json_error( $value ) { $GLOBALS['ajax'] = array( false, $value ); }
function same( $actual, $expected ): void {
    if ( $actual !== $expected ) { throw new RuntimeException( 'Mismatch: ' . var_export( array( $actual, $expected ), true ) ); }
}
// WP terminates after authorization errors. Verify at that boundary instead of
// throwing a fake exception that the controller's Throwable handler would catch.
function wp_die() {
    same( $GLOBALS['ajax'], array( false, array( 'status' => 403, 'message' => $GLOBALS['expected_denial'] ) ) );
    same( $GLOBALS['wpdb']->rows, $GLOBALS['before_rows'] );
    same( $GLOBALS['api_calls'], 0 );
    same( $GLOBALS['transients'], array() );
    echo 'ok';
    exit;
}
class InstantSettingsDb {
    public $prefix = 'instant_test_';
    public $last_error = '';
    public $rows = array( 'origin_name' => 'Untouched origin' );
    private $snapshot = array();
    public function prepare( $sql, $key ) { return $key; }
    public function get_row( $key ) { return isset( $this->rows[$key] ) ? (object) array( 'value' => $this->rows[$key] ) : null; }
    public function query( $sql ) {
        if ( 'START TRANSACTION' === $sql ) { $this->snapshot = $this->rows; }
        if ( 'ROLLBACK' === $sql ) { $this->rows = $this->snapshot; }
        return 1;
    }
    public function insert( $table, $data, $formats ) { $this->rows[$data['key']] = $data['value']; return 1; }
    public function update( $table, $data, $where, ...$formats ) { $this->rows[$where['key']] = $data['value']; return 1; }
}
$root = dirname( __DIR__, 2 );
require $root . '/vendor/autoload.php';
require $root . '/inc/Utils/ServiceResponse.php';
require $root . '/inc/Base/BaseService.php';
require $root . '/inc/Services/CourierServiceCatalog.php';
require $root . '/inc/Repositories/SettingRepository.php';
require $root . '/inc/Services/KiriminajaApiService.php';
require $root . '/inc/Contracts/TrackingPageRepositoryInterface.php';
require $root . '/inc/Controllers/SettingController.php';
// Stub the network boundary before autoloading the real service.
eval( 'namespace KiriminAjaOfficial\\Repositories; class KiriminajaApiRepository { public function get_couriers() { ++$GLOBALS["api_calls"]; return $GLOBALS["api_result"]; } }' );
require $root . '/tests/fixtures/courier-setting-controller.php';
use KiriminAjaOfficial\Services\CourierServiceCatalog as Catalog;
use KiriminAjaOfficial\Services\KiriminajaApiService as Api;
use KiriminAjaOfficial\Repositories\SettingRepository as Repository;
$wpdb = new InstantSettingsDb();
$repository = new Repository();
$controller = courier_setting_controller( $repository );
$api = new Api();
$raw = array(
    (object) array( 'code' => 'jne', 'name' => 'JNE' ),
    array( 'code' => 'gosend', 'name' => 'GoSend', 'type' => 'instant', 'services' => array( array( 'code' => 'GO-INSTANT', 'name' => 'Instant' ), array( 'code' => 'GO-SAMEDAY', 'name' => 'Same Day' ) ) ),
    array( 'code' => 'grab_express', 'name' => 'Grab', 'services' => array( array( 'code' => 'GRAB-BIKE' ) ) ),
    array( 'code' => 'borzo', 'type' => ' INSTANT ' ),
    array( 'code' => 'instantx', 'type' => 'instant' ),
    array( 'code' => 'ninja_inter', 'type' => 'regular' ),
    array( 'code' => 'overseas', 'region' => ' INTERNATIONAL ' ),
    array( 'code' => 'foreign', 'type' => 'international' ),
);
$all_codes = array( 'jne', 'gosend', 'grab_express', 'borzo' );
$GLOBALS['api_result'] = array( 'status' => true, 'data' => (object) array( 'status' => true, 'datas' => $raw ) );
function save( $data, $success ): void {
    global $controller, $wpdb;
    $before = $wpdb->rows;
    $_POST = array( 'data' => array_merge( array( 'nonce' => 'valid' ), $data ) );
    $controller->storeCourierWhitelist();
    same( $GLOBALS['ajax'][0], $success );
    if ( ! $success ) { same( $wpdb->rows, $before ); }
}
function getSettings(): array {
    global $controller;
    $_POST = array( 'data' => array( 'nonce' => 'valid' ) );
    $controller->getCourierWhitelist();
    same( $GLOBALS['ajax'][0], true );
    return $GLOBALS['ajax'][1]['data'];
}
$scenario = $argv[1];
if ( str_contains( $scenario, 'permission' ) || str_contains( $scenario, 'nonce' ) ) {
    $GLOBALS['before_rows'] = $wpdb->rows;
    $permission = str_contains( $scenario, 'permission' );
    $GLOBALS['allowed'] = ! $permission;
    $GLOBALS['expected_denial'] = $permission ? 'Insufficient permissions' : 'Security check failed';
    $_POST = array( 'data' => array( 'service_selection' => '{"gosend":["GO-INSTANT"]}' ) );
    if ( ! str_contains( $scenario, 'missing' ) ) { $_POST['data']['nonce'] = $permission ? 'valid' : 'forged'; }
    if ( str_starts_with( $scenario, 'get-' ) ) { $controller->getCourierWhitelist(); }
    else { $controller->storeCourierWhitelist(); }
    throw new RuntimeException( 'Authorization did not terminate' );
}
switch ( $scenario ) {
    case 'live':
        same( array_column( $api->get_couriers()->data, 'code' ), array( 'jne' ) );
        $old = $GLOBALS['transients'];
        same( array_column( $api->get_couriers( true )->data, 'code' ), $all_codes );
        same( $GLOBALS['api_calls'], 2 );
        foreach ( $old as $key => $value ) { same( $GLOBALS['transients'][$key], $value ); }
        same( array_column( $GLOBALS['transients']['kiriof_couriers_all_v1'], 'code' ), $all_codes );
        same( $GLOBALS['transients']['kiriof_couriers_all_last_success_v1'], $GLOBALS['transients']['kiriof_couriers_all_v1'] );
        same( $GLOBALS['ttls']['kiriof_couriers_all_v1'], DAY_IN_SECONDS );
        same( $GLOBALS['ttls']['kiriof_couriers_all_last_success_v1'], WEEK_IN_SECONDS );
        same( array_column( $api->get_couriers()->data, 'code' ), array( 'jne' ) );
        same( $GLOBALS['api_calls'], 2 );
        break;
    case 'warm':
        $GLOBALS['transients']['kiriof_couriers_all_v1'] = $raw;
        $GLOBALS['transients']['kiriof_couriers_list_v2'] = $raw;
        $GLOBALS['api_result'] = array( 'status' => false );
        same( array_column( $api->get_couriers( true )->data, 'code' ), $all_codes );
        same( array_column( $api->get_couriers()->data, 'code' ), array( 'jne' ) );
        same( $GLOBALS['api_calls'], 0 );
        same( array_keys( Catalog::available( 'instant' ) ), array( 'gosend', 'grab_express', 'borzo' ) );
        same( isset( Catalog::available( 'express' )['gosend'] ), false );
        same( Catalog::canonicalService( 'gosend', 'go-instant' ), 'GO-INSTANT' );
        break;
    case 'fallback':
        $GLOBALS['api_result'] = array( 'status' => false );
        $GLOBALS['transients']['kiriof_couriers_last_success_cache'] = $raw;
        same( $api->get_couriers( true )->status, 400 );
        same( isset( $GLOBALS['transients']['kiriof_couriers_all_v1'] ), false );
        $GLOBALS['transients']['kiriof_couriers_all_last_success_v1'] = $raw;
        same( Catalog::canonicalService( 'gosend', 'GO-INSTANT' ), 'GO-INSTANT' );
        $result = $api->get_couriers( true );
        same( array_column( $result->data, 'code' ), $all_codes );
        same( $result->customCode, 'courier_cache_fallback' );
        same( $result->message, 'Using cached courier data.' );
        same( $GLOBALS['transients']['kiriof_couriers_all_v1'], $result->data );
        same( array_column( $api->get_couriers()->data, 'code' ), array( 'jne' ) );
        unset( $GLOBALS['transients']['kiriof_couriers_list_v2'], $GLOBALS['transients']['kiriof_couriers_last_success_cache'] );
        same( $api->get_couriers()->status, 400 );
        break;
    case 'missing':
        $missing = array( array( 'code' => 'gosend' ), array( 'code' => 'grab_express', 'services' => array() ), array( 'code' => 'borzo', 'services' => array( array( 'code' => '' ), array( 'code' => null ), array( 'code' => false ), array( 'name' => 'Label only' ) ) ), array( 'code' => 'mystery' ) );
        same( array_column( Catalog::enrich( $missing ), 'code' ), array( 'mystery' ) );
        $GLOBALS['transients']['kiriof_couriers_all_v1'] = $missing;
        $settings = getSettings();
        same( $settings['couriers'][0]['delivery_type'], 'instant' );
        same( $settings['couriers'][0]['services'], array( array( 'code' => 'instant', 'name' => 'Instant' ), array( 'code' => 'sameday', 'name' => 'Same Day' ) ) );
        same( $settings['couriers'][1]['delivery_type'], 'instant' );
        same( $settings['couriers'][1]['services'], array( array( 'code' => 'instant', 'name' => 'Instant' ), array( 'code' => 'sameday', 'name' => 'Same Day' ) ) );
        same( $settings['couriers'][2]['delivery_type'], 'instant' );
        same( $settings['couriers'][2]['services'], array() );
        same( $settings['couriers'][3]['services'], array( array( 'code' => '*', 'name' => 'All services' ) ) );
        foreach ( Catalog::instantCodes() as $code ) {
            same( Catalog::canonicalService( $code, '*' ), null );
            save( array( 'service_selection' => json_encode( array( $code => array( '*' ) ) ) ), false );
            save( array( 'service_selection' => json_encode( array( $code => array( 'INSTANT' ) ) ) ), 'borzo' !== $code );
        }
        save( array( 'service_selection' => '{"gosend":[],"grab_express":[],"borzo":[]}' ), true );
        same( $repository->hasEnabledCourierServices(), false );
        same( $repository->getWhitelistExpeditionIds(), array() );
        break;
    case 'controller':
        $settings = getSettings();
        same( array_column( $settings['couriers'], 'code' ), $all_codes );
        same( $settings['service_selection'], null );
        same( $GLOBALS['api_calls'], 1 );
        save( array( 'service_selection' => '{"GoSend":["go-instant","GO-INSTANT"],"grab_express":["grab-bike"],"borzo":[],"jne":["reg23"]}', 'whitelist_ids' => 'attacker', 'whitelist_names' => 'Attacker' ), true );
        $policy = array( 'gosend' => array( 'GO-INSTANT' ), 'grab_express' => array( 'GRAB-BIKE' ), 'borzo' => array(), 'jne' => array( 'REG' ) );
        same( $repository->getCourierServiceSelection(), $policy );
        same( json_decode( $wpdb->rows['origin_whitelist_expedition_services'], true ), $policy );
        same( $repository->getWhitelistExpeditionIds(), array( 'gosend', 'grab_express', 'jne' ) );
        same( $wpdb->rows['origin_whitelist_expedition_name'], 'GoSend,Grab,JNE' );
        $reloaded = new Repository();
        $reloaded->clearCache();
        same( $reloaded->getCourierServiceSelection(), $policy );
        same( (array) getSettings()['service_selection'], $policy );
        same( $reloaded->isCourierServiceEnabled( 'GOSEND', 'go-instant' ), true );
        same( $reloaded->isCourierServiceEnabled( 'gosend', 'GO-SAMEDAY' ), false );
        foreach ( array( '{"gosend":["*"]}', '{"gosend":["REG"]}', '{"grab_express":["GO-INSTANT"]}', '{"borzo":["INSTANT"]}', '{"ninja_inter":["*"]}', '{"instantx":["INSTANT"]}', '{"gosend":[0]}', '[]' ) as $bad ) { save( array( 'service_selection' => $bad ), false ); }
        $rates = array( array( 'service' => 'jne', 'service_type' => 'REG23' ), (object) array( 'service' => 'gosend', 'service_type' => 'GO-INSTANT' ), array( 'service' => 'grab_express', 'service_type' => 'GRAB-BIKE' ), array( 'service' => 'jne', 'type' => 'instant', 'service_type' => 'REG' ) );
        same( $repository->validateWhiteListExpedition( $rates ), array( $rates[0] ) );
        save( array( 'service_selection' => '{"gosend":[]}' ), true );
        same( $reloaded->isCourierServiceEnabled( 'gosend', 'GO-INSTANT' ), false );
        same( $reloaded->getWhitelistExpeditionIds(), array() );
        same( $wpdb->rows['origin_name'], 'Untouched origin' );
        break;
    case 'legacy':
        foreach ( array( '', 'gosend,grab_express,borzo', 'jne,gosend' ) as $ids ) {
            $repository->storeCourierWhitelist( array( 'origin_whitelist_expedition_id' => $ids ) );
            same( $repository->getCourierServiceSelection(), null );
            foreach ( Catalog::instantCodes() as $code ) { same( $repository->isCourierServiceEnabled( $code, 'ANY' ), false ); }
        }
        foreach ( Catalog::instantCodes() as $code ) { save( array( 'whitelist_ids' => $code, 'whitelist_names' => 'Instant' ), false ); }
        $repository->storeCourierWhitelist( array( 'origin_whitelist_expedition_id' => 'gosend' ) );
        save( array( 'service_selection' => '{"gosend":["*"]}' ), false );
        $repository->storeCourierWhitelist( array( 'origin_whitelist_expedition_id' => 'ninja_inter' ) );
        $settings = getSettings();
        same( $settings['whitelist_ids'], array() );
        same( $settings['legacy_restricted'], true );
        same( $settings['service_selection'], null );
        save( array( 'whitelist_ids' => 'jne', 'whitelist_names' => 'JNE' ), true );
        same( $repository->isCourierServiceEnabled( 'jne', 'REG' ), true );
        save( array( 'service_selection' => '{}' ), true );
        save( array( 'whitelist_ids' => 'jne', 'whitelist_names' => 'JNE' ), true );
        same( $repository->isCourierServiceEnabled( 'jne', 'REG' ), false );
        same( $repository->isCourierServiceEnabled( 'gosend', 'ANY' ), false );
        break;
    case 'invalidation':
        $keys = array( 'kiriof_couriers_list_v2', 'kiriof_couriers_all_v1', 'kiriof_couriers_last_success_cache', 'kiriof_couriers_all_last_success_v1' );
        foreach ( $keys as $key ) { $GLOBALS['transients'][$key] = $raw; }
        $GLOBALS['transients']['unrelated'] = 'keep';
        $api->invalidateCouriersCache( false );
        same( $GLOBALS['deleted'], array_slice( $keys, 0, 2 ) );
        same( array_keys( $GLOBALS['transients'] ), array( $keys[2], $keys[3], 'unrelated' ) );
        $GLOBALS['deleted'] = array();
        $api->invalidateCouriersCache();
        same( $GLOBALS['deleted'], $keys );
        same( $GLOBALS['transients'], array( 'unrelated' => 'keep' ) );
        $raw[1]['services'] = array( array( 'code' => 'NEW-INSTANT' ) );
        $GLOBALS['api_result']['data']->datas = $raw;
        getSettings();
        same( Catalog::canonicalService( 'gosend', 'GO-INSTANT' ), null );
        same( Catalog::canonicalService( 'gosend', 'NEW-INSTANT' ), 'NEW-INSTANT' );
        break;
    default: throw new RuntimeException( 'Unknown scenario' );
}
echo 'ok';
