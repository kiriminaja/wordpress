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
    class GenerateOrderId { public int $calls = 0; public function call() { ++$this->calls; return 'INSTANT-' . $this->calls; } }
}
namespace Automattic\WooCommerce\StoreApi\Exceptions {
    class RouteException extends \RuntimeException { public int $status; public function __construct($code, $message, $status) { parent::__construct($message); $this->status = $status; } }
}
namespace {
    // Reuse the isolated live quote doubles, with output buffered, not production APIs.
    $argv[1] = json_encode(['scenario' => in_array($argv[2] ?? '', ['example_total', 'zero_admin', 'zero_price'], true) ? $argv[2] : '']);
    ob_start();
    require __DIR__ . '/instant-checkout-quote-runtime.php';
    ob_end_clean();
    $scenario = $argv[2] ?? '';
    class OrderSettings extends \KiriminAjaOfficial\Repositories\SettingRepository {
        public bool $insurance = false;
        public function getSettingByKey($key) { return $key === 'enable_insurance' ? (object) ['value' => $this->insurance ? 'yes' : 'no'] : parent::getSettingByKey($key); }
    }
    $settings = new OrderSettings();
    $service = new \KiriminAjaOfficial\Services\InstantCheckoutQuoteService($settings, $locations, $api);
    $GLOBALS['hooks'] = []; $GLOBALS['locks'] = []; $GLOBALS['logs'] = [];
    function add_action($hook, $callback, $priority, $args) { $GLOBALS['hooks'][$hook] = [$priority, $args]; }
    function add_option($key, $value, $deprecated = '', $autoload = false) { if (isset($GLOBALS['locks'][$key])) { return false; } $GLOBALS['locks'][$key] = $value; return true; }
    function delete_option($key) { unset($GLOBALS['locks'][$key]); }
    function get_option($key, $default = '') { return $GLOBALS['locks'][$key] ?? $default; }
    function wp_cache_delete($key, $group) {}
    class LockDatabase {
        public string $options = 'wp_options';
        public function prepare($sql, ...$args) { return [$sql, $args]; }
        public function query($query) {
            [$sql, $args] = $query;
            if (str_starts_with($sql, 'UPDATE')) { [$owner, $key, $previous] = $args; if (($GLOBALS['locks'][$key] ?? null) !== $previous) { return 0; } $GLOBALS['locks'][$key] = $owner; return 1; }
            [$key, $owner] = $args; if (($GLOBALS['locks'][$key] ?? null) !== $owner) { return 0; } unset($GLOBALS['locks'][$key]); return 1;
        }
    }
    $GLOBALS['wpdb'] = new LockDatabase();
    class OrderShipping {
        public string $method = 'kiriminaja-instant'; public int $instance = 4; public $total = 19000; public array $meta;
        public function __construct($rate) { $this->total = $rate['shipping_costs']; foreach (['courier', 'service', 'vehicle', 'quote_token'] as $key) { $this->meta['kiriof_instant_' . $key] = $rate[$key]; } $this->meta['kiriof_instant_quote_expires'] = $rate['expires']; }
        public function get_id() { return $this->method . ':' . $this->instance . ':' . $this->meta['kiriof_instant_courier'] . ':' . $this->meta['kiriof_instant_service']; }
        public function get_method_id() { return $this->method; }
        public function get_instance_id() { return $this->instance; }
        public function get_total() { return $this->total; }
        public function get_cost() { return $this->total; }
        public function get_meta_data() { return $this->meta; }
        public function get_meta($key) { return $this->meta[$key] ?? ''; }
    }
    class OrderFee {
        public $total = 1000; public $tax = 0; public $name = 'Admin Fee'; public array $meta = [];
        public function get_name() { return $this->name; }
        public function get_total() { return $this->total; }
        public function get_total_tax() { return $this->tax; }
        public function get_meta($key) { return $this->meta[$key] ?? ''; }
        public function add_meta_data($key, $value, $unique = false) { $this->meta[$key] = $value; }
    }
    class OrderFixture {
        public array $fees = []; public array $meta = []; public array $address; public array $lines; public string $payment = 'bacs';
        public function __construct($address, $line) { $this->address = $address; $this->lines = [$line]; }
        public function get_items($type) { return $type === 'shipping' ? $this->lines : ($type === 'fee' ? $this->fees : []); }
        public function get_id() { return 123; }
        public function get_meta($key) { return $this->meta[$key] ?? ''; }
        public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
        public function get_payment_method() { return $this->payment; }
        public function set_payment_method($value) { $this->payment = $value; }
        public function get_discount_total() { return 100; }
        public function save_meta_data() {}
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
    $controller = new \KiriminAjaOfficial\Controllers\InstantCheckoutController($settings, $transactions, $service, $generator = new \KiriminAjaOfficial\Services\KiriminAja\GenerateOrderId());
    $controller->register(); $error = ''; $processedError = ''; $errorStatus = null;
    $wc->packages[0]['rates'] = [clone $line];
    $wc->session->set('chosen_shipping_methods', [$line->get_id()]);
    $fee = new OrderFee(); $controller->tagAdminFee($fee, $controller::CART_FEE_ID, $order, null); $order->fees = [$fee];
    switch ($scenario) {
        case 'opaque_price_meta': $line->meta['kiriof_instant_admin_fee'] = -999; $line->meta['kiriof_instant_shipping_cost'] = -999; break;
        case 'insurance': $settings->insurance = true; break;
        case 'session_insurance': $wc->session->set('kiriof_insurance', 1); break;
        case 'private_error': $settings->throw = true; break;
        case 'zero_admin': case 'zero_price': $order->fees = []; break;
        case 'missing_fee': $order->fees = []; break;
        case 'duplicate_fee': $order->fees[] = clone $fee; break;
        case 'renamed_duplicate_fee': $duplicate = clone $fee; $duplicate->name = 'Other Fee'; $order->fees[] = $duplicate; break;
        case 'tampered_fee': $fee->total++; break;
        case 'taxed_fee': $fee->tax = 100; break;
        case 'untagged_fee': $fee->meta = []; break;
        case 'wrong_selection': $wc->session->set('chosen_shipping_methods', ['kiriminaja-instant:4:gosend:GO-INSTANT-extra']); break;
        case 'missing_selection': $wc->session->set('chosen_shipping_methods', []); break;
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
        case 'express': $line->method = 'kiriminaja-official'; $wc->session->set('chosen_shipping_methods', ['kiriminaja-official:4']); break;
        case 'insert': $transactions->fail = true; break;
        case 'missing_rates': $wc->packages[0]['rates'] = []; break;
        case 'rate_vehicle': $wc->packages[0]['rates'][0]->meta['kiriof_instant_vehicle'] = 'car'; break;
        case 'stale_lock': $GLOBALS['locks']['_kiriof_instant_checkout_lock_123'] = (time() - 10) . ':' . str_repeat('a', 32); break;
        case 'busy_lock': $GLOBALS['locks']['_kiriof_instant_checkout_lock_123'] = (time() + 300) . ':' . str_repeat('a', 32); break;
        case 'classic': $wc->session->set('kiriof_buyer_destination', $destination); break;
    }
    class CartFees {
        public array $fees = [];
        public function fees_api() { return $this; }
        public function add_fee($fee) { if (isset($this->fees[$fee['id']])) { return false; } $this->fees[$fee['id']] = $fee; return (object) $fee; }
    }
    $cartFees = new CartFees();
    $wc->session->set('kiriof_buyer_destination', $destination);
    $wc->session->set('chosen_payment_method', $request->params['payment_method']);
    $savedRates = $wc->packages[0]['rates'];
    $wc->packages[0]['rates'] = [];
    foreach ($savedRates as $r) { $wc->packages[0]['rates'][$r->get_id()] = $r; }
    $controller->addAdminFee($cartFees);
    $controller->addAdminFee($cartFees);
    $wc->packages[0]['rates'] = $savedRates;
    try { if ($scenario === 'classic') { $controller->afterCheckoutBeforeCreated($order, []); } else { $controller->afterStoreApiCheckoutUpdateOrderFromRequest($order, $request); } } catch (\Throwable $e) { $error = $e->getMessage(); $errorStatus = $e->status ?? null; }
    if ($error === '') {
        if ($scenario === 'processed_fee_edit') { $fee->total++; }
        if ($scenario === 'processed_fee_missing') { $order->fees = []; }
        if ($scenario === 'snapshot_edit') { $order->meta[\KiriminAjaOfficial\Controllers\InstantCheckoutController::SNAPSHOT_META_KEY]['context']['destination']['destination_latitude'] = '-6.4'; }
        if ($scenario === 'snapshot_fee_edit') { $order->meta[\KiriminAjaOfficial\Controllers\InstantCheckoutController::SNAPSHOT_META_KEY]['rate']['admin_fee']++; }
        if ($scenario === 'receipt_fee_edit') { $order->meta['_kiriof_instant_admin_fee']++; }
        if ($scenario === 'receipt_total_edit') { $order->meta['_kiriof_instant_customer_shipping_total']++; }
        if ($scenario === 'processed_expiry') { $wc->session->data = []; }
        if ($scenario === 'conflict') { $transactions->rows[] = (object) ['delivery_type' => 'instant', 'service' => 'grab_express']; }
        try {
            $controller->afterStoreApiCheckoutOrderProcessed($order);
            if ($scenario === 'insert') { $transactions->fail = false; $wc->session->data = []; $controller->afterStoreApiCheckoutOrderProcessed($order); }
            $controller->afterCheckoutAfterCreated(123, [], $order);
        } catch (\Throwable $e) {
            $processedError = $e->getMessage();
            if ($scenario === 'insert') { $transactions->fail = false; $wc->session->data = []; $controller->afterStoreApiCheckoutOrderProcessed($order); }
        }
    }
    echo json_encode(['error' => $error, 'error_status' => $errorStatus, 'production_quote_service' => get_class($service), 'invoice_calls' => $generator->calls, 'processed_error' => $processedError, 'rows' => $transactions->rows, 'meta' => $order->meta, 'shipping_total' => $line->total, 'cart_fees' => $cartFees->fees, 'fee_lines' => $order->get_items('fee'), 'calls' => $api->calls, 'hooks' => $GLOBALS['hooks'], 'locks' => $GLOBALS['locks'], 'logs' => $GLOBALS['logs']]);
}
