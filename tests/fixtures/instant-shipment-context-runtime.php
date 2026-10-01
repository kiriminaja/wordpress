<?php
// Isolated WordPress/WooCommerce runtime for the Instant context contract.
define('ABSPATH', __DIR__);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
function __($text, $domain = '') { return $text; }
function esc_html__( $text, $domain = '' ) { return htmlspecialchars( __( $text, $domain ), ENT_QUOTES, 'UTF-8' ); }
function wp_json_encode($value) { return json_encode($value); }
function wp_timezone_string() { return $GLOBALS['input']['zone'] ?? 'Asia/Jakarta'; }
function apply_filters($name, $value, ...$args) { return $GLOBALS['input']['package_type'] ?? $value; }
function wc_get_weight($value, $unit) { return (float) $value * 1000; }
function wc_get_dimension($value, $unit) { return (float) $value / 10; }
function wc_get_order($id) { return empty($GLOBALS['input']['missing_order']) ? $GLOBALS['order'] : false; }
class ContextLocations extends \KiriminAjaOfficial\Services\ShipmentLocationService {
    public function __construct() {}
    public function originForLocation($id) { ++$GLOBALS['lookups']; return $GLOBALS['live_origin']; }
}
class ContextSettings extends \KiriminAjaOfficial\Repositories\SettingRepository {
    public function __construct() {}
    public function isCourierServiceEnabled(string $courier, string $service): bool { return empty($GLOBALS['input']['disabled']); }
    public function getOriginData() { return []; }
}
class ContextProduct {
    public function is_virtual() { return !empty($GLOBALS['input']['virtual']); }
    public function get_weight() { return $GLOBALS['input']['weight'] ?? 1.5; }
    public function get_width() { return $GLOBALS['input']['dimension'] ?? 200; }
    public function get_height() { return 100; }
    public function get_length() { return 300; }
    public function get_sku() { return 'SKU-1'; }
}
class ContextItem {
    public function get_product() { return empty($GLOBALS['input']['missing_product']) ? new ContextProduct() : false; }
    public function get_quantity() { return $GLOBALS['input']['qty'] ?? 2; }
    public function get_total() { return $GLOBALS['input']['total'] ?? 12345; }
    public function get_name() { return $GLOBALS['input']['item_name'] ?? 'Discounted goods'; }
}
class ContextOrder {
    public function get_status() { return $GLOBALS['input']['status'] ?? 'processing'; }
    public function is_paid() { return $GLOBALS['input']['paid'] ?? true; }
    public function get_payment_method() { return $GLOBALS['input']['payment'] ?? 'bacs'; }
    public function get_items() { return empty($GLOBALS['input']['no_items']) ? [new ContextItem()] : []; }
    public function get_address($type) { return array_replace(['first_name'=>'Buyer', 'last_name'=>'Name', 'phone'=>'081234567890', 'address_1'=>'Jalan Sudirman Number 123', 'address_2'=>'Tower A', 'city'=>'Jakarta', 'state'=>'DKI', 'postcode'=>'12345', 'country'=>'ID'], $GLOBALS['input']['address'] ?? []); }
    public function get_meta($key, $single) { return ''; }
}
$input = json_decode($argv[1] ?? '{}', true);
$GLOBALS['input'] = $input;
$GLOBALS['order'] = new ContextOrder();
$GLOBALS['lookups'] = 0;
$origin = array_replace(['origin_name'=>'Original Store', 'origin_phone'=>'081234567890', 'origin_address'=>'Jalan Original Pickup Number 123', 'origin_zip_code'=>'12345', 'origin_latitude'=>-6.2, 'origin_longitude'=>106.8], $input['origin'] ?? []);
$GLOBALS['live_origin'] = array_replace($origin, ['origin_address'=>'Jalan NEW Pickup Number 999', 'origin_latitude'=>-7]);
$snapshot = array_replace(['_shipping_address_1'=>'Jalan Sudirman Number 123', '_shipping_address_2'=>'Tower A', '_shipping_postcode'=>'12345', '_shipping_city'=>'Jakarta', '_shipping_state'=>'DKI', '_shipping_country'=>'ID'], $input['snapshot'] ?? []);
$row = (object) array_replace(['delivery_type'=>'instant', 'service'=>'gosend', 'service_name'=>'instant', 'status'=>'new', 'order_id'=>'TEST-000123', 'wp_wc_order_stat_order_id'=>123, 'vehicle'=>'motor', 'shipping_cost'=>15000, 'shipment_location_id'=>1, 'shipment_location_snapshot'=>json_encode($origin), 'destination_latitude'=>0, 'destination_longitude'=>0, 'shipping_info'=>json_encode($snapshot)], $input['row'] ?? []);
$service = new \KiriminAjaOfficial\Services\InstantShipmentContext(new ContextLocations(), null, new ContextSettings());
try {
    $context = $service->build($row);
    echo json_encode(['ok'=>true, 'context'=>$context, 'can_process'=>$service::canProcess($row), 'lookups'=>$GLOBALS['lookups']]);
} catch (\InvalidArgumentException $error) {
    echo json_encode(['ok'=>false, 'error'=>$error->getMessage(), 'can_process'=>$service::canProcess($row), 'lookups'=>$GLOBALS['lookups']]);
}
