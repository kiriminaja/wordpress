<?php
/** Real provisioning, policy repository and save controller; stub only WP/Woo storage boundaries. */
define('ABSPATH', __DIR__);
define('KIRIOF_NONCE', 'zone-settings');
define('DAY_IN_SECONDS', 86400);
define('WEEK_IN_SECONDS', 604800);
error_reporting(E_ALL & ~E_DEPRECATED);
$GLOBALS['allowed'] = true;
$GLOBALS['hooks'] = $GLOBALS['options'] = $GLOBALS['logs'] = $GLOBALS['adds'] = $GLOBALS['cache_deletes'] = $GLOBALS['events'] = [];
$GLOBALS['network'] = $GLOBALS['invalidations'] = $GLOBALS['option_writes'] = 0;
$GLOBALS['registered'] = true;
function current_user_can($capability) { check($capability, 'manage_woocommerce'); return $GLOBALS['allowed']; }
function add_action($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['hooks'][$hook][] = [$callback, $priority, $args]; }
function do_action($hook, ...$args) {
    $GLOBALS['events'][] = $hook;
    foreach ($GLOBALS['hooks'][$hook] ?? [] as [$callback, $priority, $accepted]) { $callback(...array_slice($args, 0, $accepted)); }
}
function apply_filters($hook, $value) { check($hook, 'woocommerce_shipping_methods'); return $GLOBALS['registered'] ? ['kiriminaja-instant' => 'ZoneMethod'] : []; }
function add_option($key, $value, $deprecated = '', $autoload = true) {
    check($autoload, false);
    if (isset($GLOBALS['options'][$key])) { return false; }
    ++$GLOBALS['option_writes']; $GLOBALS['options'][$key] = $value;
    if (isset($GLOBALS['on_lock'])) { $callback = $GLOBALS['on_lock']; unset($GLOBALS['on_lock']); $callback($key); }
    return true;
}
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function wp_generate_uuid4() { static $id = 0; return 'test-owner-' . ++$id; }
function wp_cache_delete($key, $group) { $GLOBALS['cache_deletes'][] = [$key, $group]; }
function kiriof_log($level, $message, $context, $source) { $GLOBALS['logs'][] = compact('level', 'message', 'context', 'source'); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_verify_nonce($nonce, $action) { return 'valid' === $nonce && KIRIOF_NONCE === $action; }
function wp_unslash($value) { return $value; }
function wp_json_encode($value) { return json_encode($value); }
function __($value, $domain) { return $value; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
function wp_remote_post() { ++$GLOBALS['network']; throw new RuntimeException('Network forbidden'); }
function wp_send_json_success($value) { $GLOBALS['ajax'] = [true, $value]; $GLOBALS['events'][] = 'json_success'; }
function wp_send_json_error($value) { $GLOBALS['ajax'] = [false, $value]; }
function wp_die() {
    check($GLOBALS['ajax'][0], false);
    check($GLOBALS['wpdb']->rows, $GLOBALS['before_rows']);
    check($GLOBALS['adds'], []); check($GLOBALS['option_writes'], 0); check($GLOBALS['network'], 0);
    check(in_array('kiriof_courier_settings_saved', $GLOBALS['events'], true), false);
    echo 'ok'; exit;
}
function check($actual, $expected): void {
    if ($actual !== $expected) { throw new RuntimeException('Mismatch: ' . var_export([$actual, $expected], true)); }
}
class ZoneDb {
    public $prefix = 'zone_test_';
    public $options = 'zone_test_options';
    public $last_error = '';
    public array $rows = [];
    public array $deletes = [];
    private array $snapshot = [];
    public function prepare($sql, ...$args) { return [$sql, $args]; }
    public function get_row($prepared) { $key = $prepared[1][0]; return isset($this->rows[$key]) ? (object) ['value' => $this->rows[$key]] : null; }
    public function insert($table, $data, ...$formats) { $this->rows[$data['key']] = $data['value']; return 1; }
    public function update($table, $data, $where, ...$formats) { $this->rows[$where['key']] = $data['value']; return 1; }
    public function query($query) {
        if (is_string($query)) {
            if ('START TRANSACTION' === $query) { $this->snapshot = $this->rows; }
            if ('ROLLBACK' === $query) { $this->rows = $this->snapshot; }
            $GLOBALS['events'][] = $query; return 1;
        }
        [$sql, $args] = $query;
        check($sql, "DELETE FROM {$this->options} WHERE option_name = %s AND option_value = %s");
        [$key, $owner] = $args; $this->deletes[] = [$key, $owner];
        // Simulate a concurrent owner changing after get_option(), before the atomic DELETE.
        if (isset($GLOBALS['before_delete'])) { $callback = $GLOBALS['before_delete']; unset($GLOBALS['before_delete']); $callback($key); }
        if (($GLOBALS['options'][$key] ?? null) !== $owner) { return 0; }
        unset($GLOBALS['options'][$key]); return 1;
    }
}
class ZoneMethod {
    public function __construct(public string $id, public string $enabled, public int $instance_id, public int $method_order = 0, public array $instance_settings = []) {}
    public function is_enabled() { throw new RuntimeException('Buyer-address gate must never be consulted during provisioning'); }
}
class WC_Shipping_Zones {
    public static function get_zones() { return array_map(static fn($id) => ['zone_id' => $id], array_values(array_filter(array_keys($GLOBALS['zones']), static fn($id) => 0 !== $id))); }
}
class WC_Shipping_Zone {
    public function __construct(private int $id) {}
    public function get_shipping_methods($enabled = false) { check($enabled, false); return $GLOBALS['zones'][$this->id] ?? []; }
    public function add_shipping_method($method_id) {
        check($method_id, 'kiriminaja-instant');
        $GLOBALS['adds'][] = $this->id;
        $behavior = $GLOBALS['behavior'] ?? 'success';
        if ('false' === $behavior) { return false; }
        if ('throw' === $behavior) { throw new RuntimeException('Private address 081234567890 private-api-key'); }
        $instance = 1000 + count($GLOBALS['adds']);
        $GLOBALS['zones'][$this->id][] = new ZoneMethod($method_id, 'disabled_default' === $behavior ? 'no' : 'yes', $instance, 99);
        if ('successor' === $behavior) { $GLOBALS['options']['kiriof_instant_zone_lock_' . $this->id] = (time() + 120) . ':successor'; }
        // Woo fires this recursively when adding the companion; only Express should trigger work.
        do_action('woocommerce_shipping_zone_method_added', $instance, $method_id, $this->id);
        return $instance;
    }
}
class WC_Cache_Helper { public static function get_transient_version($group, $refresh) { check([$group, $refresh], ['shipping', true]); ++$GLOBALS['invalidations']; } }
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/tests/fixtures/courier-setting-controller.php';
// Stub the remote courier transport, not catalog or persisted policy logic.
eval('namespace KiriminAjaOfficial\\Repositories; class KiriminajaApiRepository { public function get_couriers() { ++$GLOBALS["network"]; throw new \\RuntimeException("Network forbidden"); } }');
$wpdb = new ZoneDb();
$repository = new \KiriminAjaOfficial\Repositories\SettingRepository();
$service = new \KiriminAjaOfficial\Services\InstantShippingZoneProvisioningService($repository);
$service->register();
function policy($selection): void { global $wpdb, $repository; if (null === $selection) { unset($wpdb->rows['origin_whitelist_expedition_services']); } else { $wpdb->rows['origin_whitelist_expedition_services'] = json_encode((object) $selection); } $repository->clearCache(); }
function express(int $instance = 10, string $enabled = 'yes'): ZoneMethod { return new ZoneMethod('kiriminaja-official', $enabled, $instance, 7, ['title' => 'Merchant title']); }
policy(['gosend' => ['instant']]);
$GLOBALS['zones'] = [1 => [express()], 0 => []];
$scenario = $argv[1] ?? 'sync';
switch ($scenario) {
    case 'hooks':
        foreach (['kiriof_courier_settings_saved' => ['sync', 0], 'woocommerce_shipping_zone_method_added' => ['methodAdded', 3], 'woocommerce_shipping_zone_method_status_toggled' => ['methodActivated', 4]] as $hook => [$method, $args]) {
            check(count($GLOBALS['hooks'][$hook]), 1);
            check($GLOBALS['hooks'][$hook][0], [[$service, $method], 10, $args]);
        }
        do_action('woocommerce_shipping_zone_method_status_toggled', 10, 'kiriminaja-official', 1, 0);
        do_action('woocommerce_shipping_zone_method_status_toggled', 10, 'flat_rate', 1, 1);
        do_action('woocommerce_shipping_zone_method_added', 10, 'flat_rate', 1);
        check($GLOBALS['adds'], []);
        // Woo's real order is instance_id, method_id, zone_id, integer is_enabled.
        do_action('woocommerce_shipping_zone_method_status_toggled', 10, 'kiriminaja-official', 1, 1);
        check($GLOBALS['adds'], [1]);
        do_action('woocommerce_shipping_zone_method_added', 10, 'kiriminaja-official', 1);
        check($GLOBALS['adds'], [1]);
        break;
    case 'sync':
        $GLOBALS['zones'] = [1 => [express()], 2 => [express(20, 'no')], 3 => [new ZoneMethod('flat_rate', 'yes', 30)], 4 => [express(40), new ZoneMethod('kiriminaja-instant', 'no', 41, 6, ['title' => 'Disabled by merchant'])], 5 => [express(50), new ZoneMethod('kiriminaja-instant', 'yes', 51)], 0 => [express(60)]];
        $before = serialize($GLOBALS['zones']); $originals = $GLOBALS['zones'];
        $report = $service->sync(); check(array_keys($report['added']), [1, 0]);
        check($report['skipped'], [2 => 'express_disabled_or_missing', 3 => 'express_disabled_or_missing', 4 => 'instant_disabled', 5 => 'instant_exists']);
        foreach ($originals as $id => $methods) { check(array_slice($GLOBALS['zones'][$id], 0, count($methods)), $methods); }
        check($GLOBALS['zones'][4][1]->enabled, 'no');
        $after = serialize($GLOBALS['zones']); $service->sync(); do_action('kiriof_courier_settings_saved');
        check(serialize($GLOBALS['zones']), $after); check($GLOBALS['adds'], [1, 0]); check($GLOBALS['invalidations'], 1); check($GLOBALS['options'], []);
        break;
    case 'policy':
        foreach ([null, [], ['gosend' => [], 'grab_express' => []], ['gosend' => ['*']], ['jne' => ['REG']], ['borzo' => ['instant']]] as $selection) { policy($selection); check($service->sync(), ['added' => [], 'skipped' => []]); }
        check($GLOBALS['adds'], []); check($GLOBALS['option_writes'], 0);
        policy(['grab_express' => ['instant']]); check(array_keys($service->sync()['added']), [1]);
        break;
    case 'unauthorized':
        $GLOBALS['allowed'] = false; check($service->sync(), ['added' => [], 'skipped' => []]);
        do_action('kiriof_courier_settings_saved'); do_action('woocommerce_shipping_zone_method_added', 10, 'kiriminaja-official', 1);
        check($GLOBALS['adds'], []); check($GLOBALS['option_writes'], 0); check($wpdb->deletes, []);
        break;
    case 'unregistered':
        $GLOBALS['registered'] = false; check($service->sync(), ['added' => [], 'skipped' => []]); check($GLOBALS['option_writes'], 0); break;
    case 'false': case 'throw':
        $GLOBALS['behavior'] = $scenario; $report = $service->sync();
        check($report['skipped'][1], 'false' === $scenario ? 'add_failed' : 'zone_failed'); check($GLOBALS['options'], []);
        check($GLOBALS['logs'][0]['context'], ['zone_id' => 1, 'instance_id' => 0, 'code' => 'false' === $scenario ? 'add_failed' : 'zone_failed']);
        check($GLOBALS['logs'][0]['source'], 'kiriminaja_instant');
        check(str_contains(json_encode($GLOBALS['logs']), 'Private'), false); check(str_contains(json_encode($GLOBALS['logs']), 'private-api-key'), false);
        $GLOBALS['behavior'] = 'success'; check(array_keys($service->sync()['added']), [1]); check($GLOBALS['options'], []); check($GLOBALS['adds'], [1, 1]); break;
    case 'disabled_default':
        $GLOBALS['behavior'] = 'disabled_default'; $report = $service->sync(); check($report['skipped'][1], 'added_not_enabled'); check($GLOBALS['zones'][1][1]->enabled, 'no'); $service->sync(); check($GLOBALS['adds'], [1]); break;
    case 'busy': case 'expired': case 'stale_race': case 'successor':
        $key = 'kiriof_instant_zone_lock_1';
        if ('successor' === $scenario) { $GLOBALS['behavior'] = 'successor'; }
        else { $GLOBALS['options'][$key] = (time() + ('busy' === $scenario ? 120 : -120)) . ':old-owner'; }
        if ('stale_race' === $scenario) { $GLOBALS['before_delete'] = static function($key) { $GLOBALS['options'][$key] = (time() + 120) . ':successor'; }; }
        $report = $service->sync();
        if (in_array($scenario, ['busy', 'stale_race'], true)) { check($report['skipped'][1], 'busy'); check($GLOBALS['adds'], []); check(isset($GLOBALS['options'][$key]), true); check($GLOBALS['cache_deletes'], []); }
        else { check(array_keys($report['added']), [1]); if ('expired' === $scenario) { check($GLOBALS['options'], []); check(count($wpdb->deletes), 2); } else { check(str_ends_with($GLOBALS['options'][$key], ':successor'), true); check($GLOBALS['cache_deletes'], []); } }
        break;
    case 'recheck':
        $GLOBALS['on_lock'] = static function($key) { $GLOBALS['zones'][1][] = new ZoneMethod('kiriminaja-instant', 'no', 11); };
        check($service->sync()['skipped'][1], 'instant_disabled'); check($GLOBALS['adds'], []); check($GLOBALS['options'], []); break;
    case 'controller': case 'controller-denied': case 'controller-invalid':
        policy([]); // Warm the same repository cache before the save; sync must see the committed new policy.
        check($repository->getCourierServiceSelection(), []);
        $GLOBALS['transients']['kiriof_couriers_all_v1'] = [['code' => 'gosend', 'name' => 'GoSend', 'services' => [['code' => 'instant', 'name' => 'Instant']]]];
        $GLOBALS['before_rows'] = $wpdb->rows;
        $_POST = ['data' => ['nonce' => 'valid', 'service_selection' => '{"gosend":["instant"]}']];
        if ('controller-denied' === $scenario) { $GLOBALS['allowed'] = false; }
        if ('controller-invalid' === $scenario) { $_POST['data']['service_selection'] = '{"gosend":["*"]}'; }
        courier_setting_controller($repository)->storeCourierWhitelist();
        if ('controller-invalid' === $scenario) { check($GLOBALS['ajax'][0], false); check($wpdb->rows, $GLOBALS['before_rows']); check($GLOBALS['adds'], []); check(in_array('kiriof_courier_settings_saved', $GLOBALS['events'], true), false); }
        else { check($GLOBALS['ajax'][0], true); check($repository->getCourierServiceSelection(), ['gosend' => ['instant']]); check($GLOBALS['adds'], [1]); $commit = array_search('COMMIT', $GLOBALS['events'], true); $hook = array_search('kiriof_courier_settings_saved', $GLOBALS['events'], true); $response = array_search('json_success', $GLOBALS['events'], true); check(is_int($hook) && $commit < $hook && $hook < $response, true); }
        break;
    default: throw new RuntimeException('Unknown scenario');
}
check($GLOBALS['network'], 0);
echo 'ok';
