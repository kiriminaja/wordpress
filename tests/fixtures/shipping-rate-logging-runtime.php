<?php
namespace KiriminAjaOfficial\Repositories {
    class SettingRepository { public function hasEnabledCourierServices() { return 'disabled' !== $GLOBALS['scenario']; } }
}
namespace KiriminAjaOfficial\Services\CheckoutServices {
    class PricingCacheService { public static function get($payload) { return null; } public static function put($payload, $data) {} }
}
namespace {
    // WooCommerce's stub permits legacy dynamic method properties.
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    define('ABSPATH', dirname(__DIR__, 2) . '/');
    $GLOBALS['scenario'] = $argv[1] ?? 'success';
    $GLOBALS['logs'] = [];
    function kiriof_log(...$args) { $GLOBALS['logs'][] = $args; }
    function WC() { return $GLOBALS['wc']; }
    function add_action(...$args) {}
    function add_filter(...$args) {}
    function __($text, $domain = '') { return $text; }
    function absint($value) { return abs((int) $value); }
    function wp_strip_all_tags($value) { return strip_tags($value); }
    function wc_price($value) { return (string) $value; }
    function wc_add_notice(...$args) {}
    function kiriof_setting_repository() { return new class {
        public function getSettingByKey($key) { return (object) ['value' => 123]; }
        public function getWhitelistExpeditionIds() { return ['jne']; }
    }; }
    function kiriof_checkout_service_factory() { return new class {
        public function cartAttributes($args) { return $this; }
        public function call() { return (object) ['data' => ['weight' => 1000, 'length' => 10, 'width' => 10, 'height' => 10]]; }
    }; }
    function kiriof_api_repository() { return new class {
        public function getPricing($payload) { return ['status' => true, 'data' => (object) ['results' => []]]; }
    }; }
    class WC_Shipping_Method {
        public $rates = [];
        public function add_rate($rate) { $this->rates[$rate['id']] = $rate; }
    }
    require ABSPATH . 'wc/KiriminajaShippingMethod.php';
    kiriof_shipping_method();
    class LoggingRateMethod extends Kiriof_Shipping_Method_Controller {
        public function __construct() { $this->id = 'kiriminaja-official'; }
        public function filterOptions($pricing, $quantity, $insurance = null) {
            return [['key' => 'jne_REG', 'value' => 'JNE REG', 'cost' => 10000]];
        }
    }
    $scenario = $GLOBALS['scenario'];
    $GLOBALS['wc'] = (object) ['session' => new class {
        private $data = [];
        public function get($key, $default = null) { return $this->data[$key] ?? $default; }
        public function set($key, $value) { $this->data[$key] = $value; }
    }];
    if ('missing_customer' !== $scenario) {
        WC()->customer = new class {
            public function get_meta($key) {
                if ('fallback_throw' === $GLOBALS['scenario']) { throw new \RuntimeException('Private address secret-api-token'); }
                return 'scan' === $GLOBALS['scenario'] || 'missing_district' === $GLOBALS['scenario'] ? '' : '456';
            }
            public function get_meta_data() { return 'scan' === $GLOBALS['scenario'] ? [(object) ['key' => 'private_kiriof_destination_area', 'value' => '456']] : []; }
        };
    }
    $package = ['destination' => ['country' => 'foreign' === $scenario ? 'US' : 'ID', 'address_1' => 'short' === $scenario ? 'tiny' : 'Private buyer full street address'], 'contents' => []];
    $method = new LoggingRateMethod();
    $method->calculate_shipping($package);
    echo json_encode(['logs' => $GLOBALS['logs'], 'rates' => $method->rates]);
}
