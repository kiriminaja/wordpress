<?php
namespace KiriminAjaOfficial\Repositories {
    class SettingRepository {
        public static int $constructed = 0;
        public array $selection = ['gosend' => ['GO-INSTANT'], 'grab_express' => ['GRAB-BIKE']];
        public string $credential = 'private-api-key';
        public string $insurance = 'no';
        public bool $fail = false;
        public function __construct() { ++self::$constructed; }
        public function getCourierServiceSelection(): ?array { if ($this->fail) { throw new \RuntimeException('private-api-key private street'); } return $this->selection; }
        public function getSettingByKey($key) { return (object) ['value' => 'api_key' === $key ? $this->credential : $this->insurance]; }
    }
}
namespace KiriminAjaOfficial\Services {
    class ShipmentLocationService {
        public static int $constructed = 0;
        public array $origin = ['origin_name' => 'Private Merchant Name', 'origin_phone' => '081234567890', 'origin_address' => 'Private street number 123 Jakarta', 'origin_zip_code' => '12345', 'origin_latitude' => '-6.2', 'origin_longitude' => '106.8'];
        public function __construct($repository = null, $settings = null) { ++self::$constructed; }
        public function getDefaultLocation() { return (object) ['id' => 1]; }
        public function repository() { return new class { public function getDefault() { return (object) ['id' => 1]; } }; }
        public function locationToOrigin($location): array { return $this->origin; }
    }
}
namespace {
    define('ABSPATH', __DIR__);
    $input = json_decode($argv[1] ?? '{}', true);
    $scenario = $input['scenario'] ?? '';
    if (in_array($scenario, ['ready', 'insurance', 'method_missing', 'zone_enabled', 'zone_disabled', 'zone_missing', 'zone_conflict', 'zone_unregistered'], true)) { eval('namespace KiriminAjaOfficial\\Controllers; class InstantCheckoutController {}'); }
    if ('method_missing' !== $scenario) { class Kiriof_Instant_Shipping_Method_Controller {} }
    function kiriof_register_instant_shipping_method($methods) { return $methods; }
    function sanitize_text_field($text) { return trim(strip_tags($text)); }
    function wp_timezone_string() { return $GLOBALS['timezone'] ?? 'Asia/Jakarta'; }
    function WC() { return $GLOBALS['wc']; }
    function is_checkout() { return !in_array($GLOBALS['scenario'], ['cart', 'unrelated', 'rest', 'other_rest'], true); }
    function is_cart() { return 'cart' === $GLOBALS['scenario']; }
    function is_admin() { return 'admin' === $GLOBALS['scenario']; }
    function is_order_received_page() { return 'received' === $GLOBALS['scenario']; }
    function wp_doing_ajax() { return false; }
    function has_filter($hook, $callback) { return in_array($GLOBALS['scenario'], ['filter_missing', 'zone_unregistered'], true) ? false : 10; }
    function add_action($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['hooks'][$hook] = [$priority, $args]; }
    function kiriof_log($level, $message, $context, $source) { $GLOBALS['logs'][] = compact('level', 'message', 'context', 'source'); }
    function wp_remote_post() { ++$GLOBALS['network']; throw new RuntimeException('Network forbidden'); }
    class DiagnosticsSession {
        public array $data = [];
        public function get($key, $default = null) { return $this->data[$key] ?? $default; }
    }
    class DiagnosticsCustomer {
        public array $address = ['address_1' => 'Private buyer street number 456', 'address_2' => '', 'city' => 'Jakarta', 'state' => 'JK', 'postcode' => '12345', 'country' => 'ID'];
        public function __call($method, $args) { return $this->address[substr($method, 13)]; }
    }
    class DiagnosticsCart { public bool $shipping = true; public function needs_shipping() { return $this->shipping; } }
    $GLOBALS['logs'] = []; $GLOBALS['hooks'] = []; $GLOBALS['network'] = 0;
    if ( in_array($scenario, ['rest', 'other_rest'], true) ) { define('REST_REQUEST', true); $GLOBALS['wp'] = (object) ['query_vars' => ['rest_route' => 'rest' === $scenario ? '/wc/store/v1/cart' : '/wp/v2/posts']]; }
    if (in_array($scenario, ['zone_enabled', 'zone_disabled', 'zone_missing', 'zone_conflict', 'zone_unregistered'], true)) {
        // Define the boundary before loading/snapshotting diagnostics; never load real
        // shipping classes (their buyer-specific is_enabled() gates need an address).
        class DiagnosticsZoneMethod {
            public string $id = 'kiriminaja-instant';
            public int $instance_id = 73;
            public string $enabled;
            public array $instance_settings;
            public function __construct() {
                $this->enabled = in_array($GLOBALS['scenario'], ['zone_enabled', 'zone_unregistered'], true) ? 'yes' : 'no';
                $this->instance_settings = ['enabled' => 'zone_conflict' === $GLOBALS['scenario'] ? 'yes' : $this->enabled, 'title' => 'Private merchant title'];
            }
            public function is_enabled() { throw new RuntimeException('Buyer-specific gates must not run'); }
            public function get_instance_id() { return $this->instance_id; }
        }
        class WC_Shipping_Zones {
            public static function get_zone_matching_package($package) {
                return new class {
                    public function get_id() { return (int) ($GLOBALS['input']['zone_id'] ?? 1); }
                    public function get_shipping_methods($enabled) {
                        if (false !== $enabled) { throw new RuntimeException('Disabled rows must be included'); }
                        return 'zone_missing' === $GLOBALS['scenario'] ? [(object) ['id' => 'kiriminaja-official', 'instance_id' => 72, 'enabled' => 'yes']] : [new DiagnosticsZoneMethod()];
                    }
                };
            }
        }
    }
    require dirname(__DIR__, 2) . '/inc/Services/BuyerDestination.php';
    require dirname(__DIR__, 2) . '/inc/Services/InstantCheckoutQuoteService.php';
    require dirname(__DIR__, 2) . '/inc/Services/InstantCheckoutDiagnosticsService.php';
    $lazy = new \KiriminAjaOfficial\Services\InstantCheckoutDiagnosticsService();
    $lazy->register();
    $lazy_counts = [\KiriminAjaOfficial\Repositories\SettingRepository::$constructed, \KiriminAjaOfficial\Services\ShipmentLocationService::$constructed];
    $settings = new \KiriminAjaOfficial\Repositories\SettingRepository();
    $locations = new \KiriminAjaOfficial\Services\ShipmentLocationService();
    $GLOBALS['wc'] = (object) ['session' => new DiagnosticsSession(), 'customer' => new DiagnosticsCustomer(), 'cart' => new DiagnosticsCart()];
    WC()->session->data['kiriof_buyer_destination'] = ['district_id' => '12', 'district_label' => 'Jakarta district', 'postcode' => '12345', 'country' => 'ID', 'address_type' => 'shipping', 'version' => 2, 'destination_latitude' => '-6.3', 'destination_longitude' => '106.9', 'shipping_address' => WC()->customer->address];
    switch ($scenario) {
        case 'no_pin': unset(WC()->session->data['kiriof_buyer_destination']); break;
        case 'v1': WC()->session->data['kiriof_buyer_destination']['version'] = 1; unset(WC()->session->data['kiriof_buyer_destination']['destination_latitude'], WC()->session->data['kiriof_buyer_destination']['destination_longitude']); break;
        case 'stale': WC()->customer->address[$input['field'] ?? 'address_1'] .= 'changed'; break;
        case 'zero': $locations->origin['origin_latitude'] = '0'; $locations->origin['origin_longitude'] = 0; WC()->session->data['kiriof_buyer_destination']['destination_latitude'] = 0; WC()->session->data['kiriof_buyer_destination']['destination_longitude'] = '0'; break;
        case 'null': $locations->origin['origin_latitude'] = 'null'; break;
        case 'origin_missing': $locations->origin = []; break;
        case 'street': $locations->origin['origin_address'] = 'Private full street'; $locations->origin['origin_city'] = 'Jakarta'; $locations->origin['origin_state'] = 'JK'; break;
        case 'name': $locations->origin['origin_name'] = 'Short'; break;
        case 'phone': $locations->origin['origin_phone'] = 'not a phone'; break;
        case 'disabled': $settings->selection = ['gosend' => ['*', ''], 'grab_express' => [], 'jne' => ['REG']]; break;
        case 'cod': WC()->session->data['chosen_payment_method'] = 'cod'; break;
        case 'insurance': $settings->insurance = 'yes'; break;
        case 'credentials': $settings->credential = ''; break;
        case 'throw': $settings->fail = true; break;
        case 'timezone': $GLOBALS['timezone'] = 'UTC'; break;
        case 'timezone_invalid': $locations->origin['origin_timezone'] = 'UTC'; break;
        case 'timezone_lower': $locations->origin['origin_timezone'] = ' wita '; break;
        case 'makassar': $GLOBALS['timezone'] = 'Asia/Makassar'; break;
        case 'jayapura': $GLOBALS['timezone'] = 'Asia/Jayapura'; break;
        case 'virtual': WC()->cart->shipping = false; break;
    }
    $service = new \KiriminAjaOfficial\Services\InstantCheckoutDiagnosticsService($settings, $locations);
    $snapshot = null;
    try { $snapshot = $service->snapshot(); } catch (Throwable $error) {}
    $service->report(); $service->report(); $service->reportCart(WC()->cart);
    if ('change' === $scenario) { WC()->customer->address['address_1'] .= 'changed'; $service->report(); }
    if ('bound' === $scenario) { for ($i = 0; $i < 20; ++$i) { $settings->selection['gosend'] = ['CODE' . $i]; $service->report(); } }
    echo json_encode(['snapshot' => $snapshot, 'logs' => $GLOBALS['logs'], 'hooks' => $GLOBALS['hooks'], 'lazy_counts' => $lazy_counts, 'network' => $GLOBALS['network']]);
}
