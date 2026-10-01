<?php
namespace KiriminAjaOfficial\Repositories {
    class SettingRepository {
        public array $selection = ['gosend' => ['GO-INSTANT'], 'grab_express' => ['GRAB-BIKE']];
        public bool $credentials = true;
        public bool $throw = false;
        public function getCourierServiceSelection(): ?array { if ($this->throw) { throw new \InvalidArgumentException('secret-api-key'); } return $this->selection; }
        public function isCourierServiceEnabled(string $courier, string $service): bool { return in_array($service, $this->selection[$courier] ?? [], true); }
        public function getSettingByKey($key) { return (object) ['value' => $this->credentials ? 'secret-api-key' : '']; }
    }
    class InstantDeliveryApiRepository {
        public int $calls = 0;
        public array $payloads = [];
        public string $scenario = '';
        public function price(array $payload): array {
            ++$this->calls; $this->payloads[] = $payload;
            if ('throw' === $this->scenario) { throw new \RuntimeException('secret-api-key'); }
            $cost = ['service_type' => 'GO-INSTANT', 'price' => ['shipping_costs' => 18000]];
            if ('price_negative' === $this->scenario) { $cost['price']['shipping_costs'] = -1; }
            if ('price_fraction' === $this->scenario) { $cost['price']['shipping_costs'] = 1.2; }
            if ('price_bool' === $this->scenario) { $cost['price']['shipping_costs'] = true; }
            if ('price_missing' === $this->scenario) { unset($cost['price']); }
            if ('zero_price' === $this->scenario) { $cost['price']['shipping_costs'] = 0; }
            if ('wrong_service' === $this->scenario) { $cost['service_type'] = 'SECRET'; }
            if ('car' === $this->scenario) { $cost['vehicle'] = 'car'; }
            $row = ['name' => 'gosend', 'costs' => [$cost]];
            if ('duplicate' === $this->scenario) { $row['costs'][] = $cost; }
            if ('wrong_courier' === $this->scenario) { $row['name'] = 'borzo'; }
            if ('grab' === $this->scenario) { $row['name'] = 'grab_express'; $row['costs'][0]['service_type'] = 'GRAB-BIKE'; }
            $response = ['status' => true, 'data' => (object) ['result' => [(object) $row]]];
            if ('results_envelope' === $this->scenario) { $response['data'] = (object) ['results' => (object) ['result' => [$row]]]; }
            if ('api_failure' === $this->scenario) { $response = ['status' => false, 'data' => 'secret']; }
            if ('status_string' === $this->scenario) { $response['status'] = 'true'; }
            if ('malformed' === $this->scenario) { $response['data'] = (object) ['result' => 'secret']; }
            return $response;
        }
    }
}
namespace KiriminAjaOfficial\Services {
    class ShipmentLocationService {
        public bool $missing = false;
        public int $calls = 0;
        public function getDefaultLocation() { ++$this->calls; return $this->missing ? null : (object) ['id' => 7]; }
        public function locationToOrigin($location): array {
            return $location ? ['location_id' => 7, 'origin_name' => 'Merchant Full Name', 'origin_phone' => '081234567890', 'origin_address' => 'Jalan Merdeka number 123 Jakarta', 'origin_zip_code' => '12345', 'origin_latitude' => '-6.2', 'origin_longitude' => '106.8'] : [];
        }
    }
}
namespace {
    define('ABSPATH', __DIR__);
    function __($text, $domain = '') { return $text; }
    function sanitize_text_field($text) { return trim(strip_tags($text)); }
    function wp_timezone_string() { return $GLOBALS['timezone'] ?? 'Asia/Jakarta'; }
    function wc_get_weight($value, $unit) { return $value; }
    function wc_get_dimension($value, $unit) { return $value; }
    function wp_json_encode($value) { return json_encode($value); }
    function WC() { return $GLOBALS['wc']; }
    class QuoteSession {
        public array $data = [];
        public function get($key, $default = null) { return $this->data[$key] ?? $default; }
        public function set($key, $value) { $this->data[$key] = $value; }
    }
    class QuoteProduct {
        public bool $shipping = true;
        public $weight = 500;
        public $width = 10;
        public function needs_shipping() { return $this->shipping; }
        public function get_weight() { return $this->weight; }
        public function get_width() { return $this->width; }
        public function get_length() { return 10; }
        public function get_height() { return 10; }
        public function get_name() { return 'Physical Product'; }
        public function get_sku() { return 'SKU'; }
        public function get_id() { return 123; }
    }
    require dirname(__DIR__, 2) . '/inc/Services/BuyerDestination.php';
    require dirname(__DIR__, 2) . '/inc/Services/InstantCheckoutQuoteService.php';
    $input = json_decode($argv[1] ?? '{}', true);
    $scenario = $input['scenario'] ?? '';
    $settings = new \KiriminAjaOfficial\Repositories\SettingRepository();
    $locations = new \KiriminAjaOfficial\Services\ShipmentLocationService();
    $api = new \KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository(); $api->scenario = $scenario;
    $GLOBALS['wc'] = (object) ['session' => new QuoteSession()];
    $product = new QuoteProduct();
    $address = ['address_1' => 'Jalan Pembeli number 123 Jakarta', 'address_2' => '', 'city' => 'Jakarta', 'state' => 'JK', 'postcode' => '12345', 'country' => 'ID'];
    $package = ['package_id' => 0, 'contents' => ['cart-key' => ['data' => $product, 'quantity' => 2, 'product_id' => 123, 'variation_id' => 0, 'line_total' => 100000]], 'destination' => $address + ['first_name' => 'Buyer', 'last_name' => 'Full Name', 'phone' => '081234567890']];
    $destination = ['district_id' => '42', 'district_label' => 'Jakarta District', 'postcode' => '12345', 'country' => 'ID', 'address_type' => 'shipping', 'version' => 2, 'destination_latitude' => '-6.3', 'destination_longitude' => '106.9', 'shipping_address' => $address];
    $payment = 'bacs'; $insurance = false;
    switch ($scenario) {
        case 'cod': $payment = 'cod'; break;
        case 'insurance': $insurance = true; break;
        case 'disabled': $settings->selection = []; break;
        case 'credentials': $settings->credentials = false; break;
        case 'settings_throw': $settings->throw = true; break;
        case 'no_pin': unset($destination['destination_latitude']); break;
        case 'v1': $destination['version'] = 1; unset($destination['destination_latitude'], $destination['destination_longitude']); break;
        case 'country': $package['destination']['country'] = 'US'; break;
        case 'stale_address': $package['destination']['address_2'] = 'Apartment 1'; break;
        case 'origin_bad': $package['origin'] = []; break;
        case 'origin_missing': $locations->missing = true; break;
        case 'timezone': $GLOBALS['timezone'] = 'UTC'; break;
        case 'name': $package['destination']['first_name'] = ''; $package['destination']['last_name'] = ''; break;
        case 'phone': $package['destination']['phone'] = '123'; break;
        case 'postcode': $package['destination']['postcode'] = '123'; break;
        case 'virtual': $product->shipping = false; break;
        case 'weight_zero': $product->weight = 0; break;
        case 'dimensions_zero': $product->width = 0; break;
        case 'overweight': $product->weight = 40001; break;
        case 'quantity': $package['contents']['cart-key']['quantity'] = 1.5; break;
        case 'negative_value': $package['contents']['cart-key']['line_total'] = -1; break;
        case 'fractional_units': $product->weight = 0.1; $product->width = 0.1; break;
        case 'zero_coordinates': $destination['destination_latitude'] = '0'; $destination['destination_longitude'] = '0'; $package['origin'] = $locations->locationToOrigin((object) []); $package['origin']['origin_latitude'] = '0'; $package['origin']['origin_longitude'] = '0'; break;
    }
    $service = new \KiriminAjaOfficial\Services\InstantCheckoutQuoteService($settings, $locations, $api);
    $quote = $service->quote($package, $destination, $payment, $insurance);
    $again = $service->quote($package, $destination, $payment, $insurance);
    if ('cache_bound' === $scenario) {
        for ($i = 1; $i <= 10; ++$i) { $package['package_id'] = $i; $service->quote($package, $destination, $payment, $insurance); }
    }
    $validated = null; $validation_error = '';
    if ($quote['eligible']) {
        $rate = $quote['rates'][0];
        switch ($scenario) {
            case 'mutate_cart': $package['contents']['cart-key']['quantity'] = 3; break;
            case 'mutate_name': $package['destination']['first_name'] = 'Changed'; break;
            case 'mutate_phone': $package['destination']['phone'] = '081234567899'; break;
            case 'mutate_pin': $destination['destination_latitude'] = '-6.4'; break;
            case 'mutate_origin': $package['origin'] = $locations->locationToOrigin((object) []); $package['origin']['origin_latitude'] = '-6.4'; break;
            case 'mutate_policy': $settings->selection = []; break;
            case 'mutate_payment': $payment = 'cod'; break;
            case 'mutate_insurance': $insurance = true; break;
            case 'mutate_package_id': $package['package_id'] = 1; break;
            case 'mutate_dimensions': $product->width = 11; break;
            case 'mutate_variation': $package['contents']['cart-key']['variation_id'] = 321; break;
            case 'mutate_value': $package['contents']['cart-key']['line_total'] = 100001; break;
            case 'mutate_fractional_value': $package['contents']['cart-key']['line_total'] = 100000.1; break;
            case 'mutate_fractional_dimensions': $product->width = 9.9; break;
            case 'mutate_credentials': $settings->credentials = false; break;
            case 'clear_session': WC()->session->data = []; break;
            case 'forge': $rate['quote_token'] = str_repeat('a', 64); break;
            case 'wrong_selection': $rate['service'] = 'sameday'; break;
            case 'expire': foreach (WC()->session->data['kiriof_instant_checkout_quotes'] as &$entry) { $entry['expires'] = time() - 1; } unset($entry); break;
        }
        try { $validated = $service->validate($rate['quote_token'], $rate['courier'], $rate['service'], $package, $destination, $payment, $insurance); }
        catch (\Throwable $error) { $validation_error = $error->getMessage(); }
    }
    echo json_encode(['quote' => $quote, 'again' => $again, 'validated' => $validated, 'validation_error' => $validation_error, 'calls' => $api->calls, 'payloads' => $api->payloads, 'cache' => WC()->session->data]);
}
