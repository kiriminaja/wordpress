<?php
namespace KiriminAjaOfficial\Repositories {
    class TransactionRepository {
        public array $rows = [];
        public bool $fail = false;
        public function getTransactionByWCOrderId($id) { return $this->rows[0] ?? null; }
        public function createTransaction($payload) { if ($this->fail) { return false; } $this->rows[] = (object) $payload; return 1; }
    }
}
namespace KiriminAjaOfficial\Services\KiriminAja {
    class GenerateOrderId { public function call() { return 'INSTANT-123'; } }
}
namespace {
    // Reuse the isolated live quote doubles, with output buffered, not production APIs.
    $argv[1] = '{}';
    ob_start();
    require __DIR__ . '/instant-checkout-quote-runtime.php';
    ob_end_clean();
    $scenario = $argv[2] ?? '';
    $GLOBALS['hooks'] = []; $GLOBALS['locks'] = []; $GLOBALS['logs'] = [];
    function add_action($hook, $callback, $priority, $args) { $GLOBALS['hooks'][$hook] = [$priority, $args]; }
    function add_option($key, $value, $deprecated = '', $autoload = false) { if (isset($GLOBALS['locks'][$key])) { return false; } $GLOBALS['locks'][$key] = $value; return true; }
    function delete_option($key) { unset($GLOBALS['locks'][$key]); }
    class OrderShipping {
        public string $method = 'kiriminaja-instant'; public int $instance = 4; public $total = 18000; public array $meta;
        public function __construct($rate) { foreach (['courier', 'service', 'vehicle', 'quote_token'] as $key) { $this->meta['kiriof_instant_' . $key] = $rate[$key]; } $this->meta['kiriof_instant_quote_expires'] = $rate['expires']; }
        public function get_method_id() { return $this->method; }
        public function get_instance_id() { return $this->instance; }
        public function get_total() { return $this->total; }
        public function get_meta($key) { return $this->meta[$key] ?? ''; }
    }
    class OrderFixture {
        public array $meta = []; public array $address; public array $lines; public string $payment = 'bacs';
        public function __construct($address, $line) { $this->address = $address; $this->lines = [$line]; }
        public function get_items($type) { return $type === 'shipping' ? $this->lines : []; }
        public function get_id() { return 123; }
        public function get_meta($key) { return $this->meta[$key] ?? ''; }
        public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
        public function get_payment_method() { return $this->payment; }
        public function set_payment_method($value) { $this->payment = $value; }
        public function get_discount_total() { return 100; }
        public function __call($name, $args) { if (str_starts_with($name, 'get_shipping_')) { return $this->address[substr($name, 13)] ?? ''; } if (str_starts_with($name, 'set_shipping_')) { $this->address[substr($name, 13)] = $args[0]; } }
    }
    class OrderRequest { public array $params; public function get_param($key) { return $this->params[$key] ?? null; } }
    class OrderWC {
        public $session; public $customer; public array $packages;
        public function shipping() { return $this; }
        public function get_packages() { return $this->packages; }
    }
    class WC_Shipping_Zones {
        public static bool $enabled = true;
        public static function get_zone_matching_package($package) { return new self(); }
        public function get_shipping_methods($enabled) { return self::$enabled ? [4 => (object) ['id' => 'kiriminaja-instant', 'enabled' => 'yes']] : []; }
    }
    require dirname(__DIR__, 2) . '/inc/Controllers/InstantCheckoutController.php';
    $savedSession = WC()->session;
    $wc = new OrderWC(); $wc->session = $savedSession; $wc->packages = [$package]; $GLOBALS['wc'] = $wc;
    $line = new OrderShipping($quote['rates'][0]); $order = new OrderFixture($package['destination'], $line);
    $request = new OrderRequest(); $request->params = ['shipping_address' => $order->address, 'payment_method' => 'bacs', 'extensions' => ['kiriminaja-official' => ['destination' => $destination]]];
    $transactions = new \KiriminAjaOfficial\Repositories\TransactionRepository();
    $controller = new \KiriminAjaOfficial\Controllers\InstantCheckoutController($settings, $transactions, $service, new \KiriminAjaOfficial\Services\KiriminAja\GenerateOrderId());
    $controller->register(); $error = ''; $processedError = '';
    switch ($scenario) {
        case 'price': $line->total = 17999; break;
        case 'fraction': $line->total = 18000.1; break;
        case 'pin': $request->params['extensions']['kiriminaja-official']['destination']['destination_latitude'] = '-6.4'; break;
        case 'address': $request->params['shipping_address']['address_2'] = 'Apartment 1'; break;
        case 'phone': $request->params['shipping_address']['phone'] = '081234567899'; break;
        case 'service': $line->meta['kiriof_instant_service'] = 'FORGED'; break;
        case 'expiry': foreach ($wc->session->data['kiriof_instant_checkout_quotes'] as &$entry) { $entry['expires'] = time() - 1; } unset($entry); break;
        case 'cart': $wc->packages[0]['contents']['cart-key']['quantity'] = 3; break;
        case 'origin': $wc->packages[0]['origin'] = []; break;
        case 'disabled': $settings->selection = []; break;
        case 'zone': WC_Shipping_Zones::$enabled = false; break;
        case 'instance': $line->instance = 0; break;
        case 'cod': $request->params['payment_method'] = 'cod'; break;
        case 'mixed': $order->lines[] = new OrderShipping($quote['rates'][0]); $order->lines[1]->method = 'flat_rate'; break;
        case 'packages': $wc->packages[] = $package; break;
        case 'express': $line->method = 'kiriminaja-official'; break;
        case 'insert': $transactions->fail = true; break;
        case 'classic': $wc->session->set('kiriof_buyer_destination', $destination); break;
    }
    try { if ($scenario === 'classic') { $controller->afterCheckoutBeforeCreated($order, []); } else { $controller->afterStoreApiCheckoutUpdateOrderFromRequest($order, $request); } } catch (\Throwable $e) { $error = $e->getMessage(); }
    if ($error === '') {
        try {
            $controller->afterStoreApiCheckoutOrderProcessed($order);
            if ($scenario === 'insert') { $transactions->fail = false; $wc->session->data = []; $controller->afterStoreApiCheckoutOrderProcessed($order); }
            $controller->afterCheckoutAfterCreated(123, [], $order);
        } catch (\Throwable $e) {
            $processedError = $e->getMessage();
            if ($scenario === 'insert') { $transactions->fail = false; $wc->session->data = []; $controller->afterStoreApiCheckoutOrderProcessed($order); }
        }
    }
    echo json_encode(['error' => $error, 'processed_error' => $processedError, 'rows' => $transactions->rows, 'meta' => $order->meta, 'calls' => $api->calls, 'hooks' => $GLOBALS['hooks'], 'locks' => $GLOBALS['locks'], 'logs' => $GLOBALS['logs']]);
}
