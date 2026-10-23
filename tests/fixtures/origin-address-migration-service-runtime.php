<?php
namespace KiriminAjaOfficial\Repositories {
    class SettingRepository {
        public function getSettingByKey( $key ) { return (object) array( 'value' => $GLOBALS['credential'] ); }
    }
    class OriginAddressMigrationRepository {
        public function next( int $after ): ?object {
            if ( $GLOBALS['read_fail'] ) { throw new \RuntimeException( 'PRIVATE address token' ); }
            foreach ( $GLOBALS['locations'] as $row ) {
                if ( (int) $row->id > $after ) { return clone $row; }
            }
            return null;
        }
        public function repair( object $snapshot, array $canonical ): bool {
            $GLOBALS['attempted'][] = array( $snapshot->id, $canonical );
            if ( $GLOBALS['write_fail'] ) { return false; }
            $GLOBALS['repaired'][] = array( $snapshot->id, $canonical );
            return true;
        }
    }
}
namespace {
    define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
    require ABSPATH . 'vendor/autoload.php';
    $GLOBALS['options'] = array(); $GLOBALS['events'] = array(); $GLOBALS['actions'] = array();
    $GLOBALS['credential'] = 'PRIVATE'; $GLOBALS['lookups'] = array(); $GLOBALS['repaired'] = array(); $GLOBALS['attempted'] = array(); $GLOBALS['logs'] = array();
    $GLOBALS['write_fail'] = false; $GLOBALS['read_fail'] = false;
    function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
    function update_option( $key, $value, $autoload = null ) { $changed = ! isset( $GLOBALS['options'][ $key ] ) || $GLOBALS['options'][ $key ] !== $value; $GLOBALS['options'][ $key ] = $value; return $changed; }
    function add_option( $key, $value, $deprecated = '', $autoload = null ) { if ( array_key_exists( $key, $GLOBALS['options'] ) ) { return false; } $GLOBALS['options'][ $key ] = $value; return true; }
    function wp_next_scheduled( $hook ) { return $GLOBALS['events'][ $hook ] ?? false; }
    function wp_schedule_single_event( $when, $hook ) { $GLOBALS['events'][ $hook ] = $when; return true; }
    function wp_unschedule_event( $when, $hook ) { unset( $GLOBALS['events'][ $hook ] ); return true; }
    function wp_cache_delete( ...$args ) {}
    function wp_generate_uuid4() { static $i = 0; return 'owner-' . ++$i; }
    function maybe_serialize( $value ) { return serialize( $value ); }
    function add_action( $hook, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['actions'][ $hook ][] = array( $callback, $priority, $accepted ); }
    function flush_rewrite_rules() {}
    function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['events'][ $hook ] ); }
    function kiriof_log( ...$args ) { $GLOBALS['logs'][] = $args; }
    class WC_Cache_Helper { public static function get_transient_version( $key, $refresh = false ) { $GLOBALS['invalidations'][] = array( $key, $refresh ); } }
    $wpdb = new class {
        public string $options = 'wp_options';
        public function prepare( $query, ...$values ) { return $values; }
        public function get_var( $values ) { return $GLOBALS['options'][ $values[1] ] ?? null; }
        public function query( $values ) {
            if ( count( $values ) === 6 ) {
                list( $table, $other, $lock, $owner, $serialized, $key ) = $values;
                if ( ( $GLOBALS['options'][ $lock ] ?? null ) !== $owner ) { return 0; }
                $GLOBALS['options'][ $key ] = unserialize( $serialized ); return 1;
            }
            list( $table, $key, $owner ) = $values;
            if ( ( $GLOBALS['options'][ $key ] ?? null ) !== $owner ) { return 0; }
            unset( $GLOBALS['options'][ $key ] ); return 1;
        }
    };
    function check( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
    $saved = (object) array( 'id' => 1, 'sub_district_id' => 99, 'sub_district_name' => 'Sari Harjo, Ngaglik, Sleman, DI Yogyakarta, 55581', 'zip_code' => '55581', 'country' => 'ID', 'is_default' => 1 );
    $row = (object) array( 'id' => 46310, 'subdistrict_id' => 46310, 'district_id' => 2275, 'city_id' => 419, 'province_id' => 5, 'text' => 'Sariharjo, Ngaglik, Sleman, DI Yogyakarta, 55581', 'zip_code' => '55581' );
    $GLOBALS['locations'] = array( $saved );
    $lookup = static function ( $postcode ) use ( $row ) {
        $GLOBALS['lookups'][] = $postcode;
        check( isset( $GLOBALS['events'][ \KiriminAjaOfficial\Services\OriginAddressMigrationService::HOOK ] ), 'No crash watchdog armed before network' );
        if ( $GLOBALS['mode'] === 'superseded' ) {
            $GLOBALS['options']['kiriof_origin_address_migration_lock'] = (time() + 120) . ':successor';
            $GLOBALS['options'][ \KiriminAjaOfficial\Services\OriginAddressMigrationService::STATUS_OPTION ]['cursor'] = 99;
        }
        if ( $GLOBALS['mode'] === 'exception' ) { throw new RuntimeException( 'PRIVATE token address' ); }
        if ( $GLOBALS['mode'] === 'lookup-fail' ) { return array( 'status' => false, 'data' => 'PRIVATE token address' ); }
        $rows = $GLOBALS['mode'] === 'empty' ? array() : array( $row );
        if ( $GLOBALS['mode'] === 'ambiguous' ) { $other = clone $row; $other->id = 46311; $other->subdistrict_id = 46311; $rows[] = $other; }
        return array( 'status' => true, 'data' => (object) array( 'status' => true, 'result' => $rows ) );
    };
    $GLOBALS['mode'] = $argv[1];
    $class = \KiriminAjaOfficial\Services\OriginAddressMigrationService::class;
    $hook = $class::HOOK; $status_key = $class::STATUS_OPTION;
    $service = new $class( new \KiriminAjaOfficial\Repositories\OriginAddressMigrationRepository(), new \KiriminAjaOfficial\Repositories\SettingRepository(), $lookup );
    $tick = static function () use ( $service, $hook, $status_key ) {
        unset( $GLOBALS['events'][ $hook ] );
        if ( isset( $GLOBALS['options'][ $status_key ] ) ) { $GLOBALS['options'][ $status_key ]['next_after'] = 0; }
        $service->run();
    };
    switch ( $GLOBALS['mode'] ) {
        case 'lifecycle':
            // Already-defined optional services must not perform unrelated setup work.
            eval( 'namespace KiriminAjaOfficial\\Services; class ShippingDiscountRegionCacheService { public function scheduleRefresh($force) {} } class WooCommerceShippingMethodRegistrationService { public function register() {} }' );
            (new \KiriminAjaOfficial\Base\Activate())->activate();
            check( isset( $GLOBALS['events'][ $hook ] ) && ! $GLOBALS['lookups'], 'Activation did not schedule or ran network' );
            (new \KiriminAjaOfficial\Base\Deactivate())->deactivate();
            check( ! isset( $GLOBALS['events'][ $hook ] ), 'Deactivation retained migration jobs' );
            break;
        case 'register':
            $service->register(); $service->schedule(); $service->schedule();
            check( count( $GLOBALS['actions']['init'] ) === 1 && count( $GLOBALS['actions'][ $hook ] ) === 1, 'Hooks missing' );
            check( count( $GLOBALS['events'] ) === 1 && ! $GLOBALS['lookups'], 'Schedule performed network or duplicated jobs' );
            break;
        case 'credentials':
            $GLOBALS['credential'] = ''; $service->schedule(); $service->run();
            check( ! $GLOBALS['events'] && ! $GLOBALS['lookups'] && ! $GLOBALS['repaired'], 'Unconfigured migration ran' );
            $GLOBALS['credential'] = 'PRIVATE'; $service->schedule(); check( isset( $GLOBALS['events'][ $hook ] ), 'Setup did not resume migration' );
            break;
        case 'locked':
            $GLOBALS['options']['kiriof_origin_address_migration_lock'] = (time() + 120) . ':other';
            $service->run(); check( ! $GLOBALS['lookups'] && ! $GLOBALS['repaired'], 'Concurrent worker bypassed lease' );
            break;
        case 'superseded':
            $tick();
            check( ! $GLOBALS['repaired'] && $GLOBALS['options'][ $status_key ]['cursor'] === 99, 'Expired owner overwrote successor state' );
            check( str_ends_with( $GLOBALS['options']['kiriof_origin_address_migration_lock'], ':successor' ), 'Expired owner removed successor lease' );
            break;
        case 'crashed':
            $GLOBALS['options']['kiriof_origin_address_migration_lock'] = (time() - 1) . ':dead';
            $GLOBALS['options'][ $status_key ] = array( 'version' => 1, 'state' => 'running', 'cursor' => 0, 'attempts' => 0, 'resolved' => 0, 'unresolved' => 0, 'next_after' => 0 );
            $tick(); check( count( $GLOBALS['repaired'] ) === 1, 'Crashed worker not recovered' ); break;
        case 'resume':
            $second = clone $saved; $second->id = 3; $second->is_default = 0; $GLOBALS['locations'][] = $second;
            $tick(); check( count( $GLOBALS['lookups'] ) === 1 && count( $GLOBALS['repaired'] ) === 1 && $GLOBALS['options'][ $status_key ]['cursor'] === 1, 'Batch was not bounded' );
            $tick(); $tick();
            check( $GLOBALS['options'][ $status_key ]['state'] === 'complete' && count( $GLOBALS['repaired'] ) === 2, 'Cursor did not resume' );
            $before = $GLOBALS['lookups']; $service->schedule(); $tick(); check( $before === $GLOBALS['lookups'], 'Completed migration repeated' );
            break;
        case 'legacy-label':
            $GLOBALS['locations'][0]->sub_district_name = '';
            $GLOBALS['locations'][0]->_origin_mirror = array( 'origin_sub_district_id' => '99', 'origin_sub_district_name' => $saved->sub_district_name, 'origin_zip_code' => '55581' );
            // $saved and the row share identity; explicitly restore the source label.
            $GLOBALS['locations'][0]->_origin_mirror['origin_sub_district_name'] = 'Sari Harjo, Ngaglik, Sleman, DI Yogyakarta, 55581';
            $tick(); check( count( $GLOBALS['repaired'] ) === 1, 'Legacy default seed label not recovered' ); break;
        case 'foreign': case 'postal-conflict': case 'ambiguous': case 'empty': case 'snapshot-conflict': case 'legacy-postal-conflict':
            if ( $GLOBALS['mode'] === 'legacy-postal-conflict' ) {
                $GLOBALS['locations'][0]->zip_code = '';
                $GLOBALS['locations'][0]->_origin_mirror = array( 'origin_zip_code' => '99999' );
            }
            if ( $GLOBALS['mode'] === 'snapshot-conflict' ) { $GLOBALS['locations'][0]->_origin_error = 'duplicate_mirrors'; }
            if ( $GLOBALS['mode'] === 'foreign' ) { $GLOBALS['locations'][0]->country = 'US'; }
            if ( $GLOBALS['mode'] === 'postal-conflict' ) { $GLOBALS['locations'][0]->zip_code = '99999'; }
            $tick(); $tick();
            check( ! $GLOBALS['repaired'] && $GLOBALS['options'][ $status_key ]['state'] === 'needs_review', 'Unsafe identity repaired or not reported' ); break;
        case 'lookup-fail': case 'exception': case 'write-fail':
            $GLOBALS['write_fail'] = $GLOBALS['mode'] === 'write-fail';
            for ( $i = 0; $i < 3; ++$i ) { $tick(); }
            $tick();
            check( count( $GLOBALS['lookups'] ) === 3 && ! $GLOBALS['repaired'] && $GLOBALS['options'][ $status_key ]['state'] === 'needs_review', 'Retries not bounded or failure marked success' );
            break;
        case 'read-fail':
            $GLOBALS['read_fail'] = true; $tick();
            check( $GLOBALS['options'][ $status_key ]['state'] === 'retrying' && $GLOBALS['options'][ $status_key ]['cursor'] === 0 && ! $GLOBALS['lookups'], 'Read failure became completion' ); break;
        case 'delay':
            $tick(); $before = count( $GLOBALS['lookups'] ); $service->run();
            check( count( $GLOBALS['lookups'] ) === $before, 'Retry delay bypassed' ); break;
        default:
            $tick(); $tick();
            check( count( $GLOBALS['repaired'] ) === 1 && $GLOBALS['options'][ $status_key ]['state'] === 'complete', 'Valid migration did not finish' );
            $canonical = $GLOBALS['repaired'][0][1];
            check( $canonical['sub_district_id'] === 46310 && $canonical['sub_district_name'] === $row->text, 'Identity not canonical' );
    }
    $private = json_encode( array( $GLOBALS['options'], $GLOBALS['logs'] ) );
    foreach ( array( 'Sari', '55581', 'PRIVATE', 'Ngaglik' ) as $secret ) { check( ! str_contains( $private, $secret ), 'Private migration data persisted' ); }
    echo 'ok';
}
