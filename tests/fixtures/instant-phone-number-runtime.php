<?php
// Synthetic contacts only; exercise production services with isolated WC/API boundaries.
$input = json_decode($argv[1] ?? '{}', true);
if (($input['mode'] ?? '') === 'admin_fallback') {
    require __DIR__ . '/instant-phone-number-admin-runtime.php';
    exit;
}
if (($input['mode'] ?? '') === 'admin') {
    ob_start();
    register_shutdown_function(static function () {
        $result = json_decode(ob_get_clean(), true);
        $result['source'] = ['origin' => $GLOBALS['row']->shipment_location_snapshot, 'shipping_info' => $GLOBALS['row']->shipping_info, 'address' => $GLOBALS['order']->get_address('shipping')];
        echo json_encode($result);
    });
    require __DIR__ . '/instant-shipment-context-runtime.php';
    exit;
}
ob_start();
require __DIR__ . '/instant-checkout-quote-runtime.php';
ob_end_clean();
$input = json_decode($argv[1] ?? '{}', true);
WC()->session->data = [];
$api->calls = 0;
$package['origin'] = $locations->locationToOrigin((object) []);
$package['origin']['origin_phone'] = array_key_exists('origin_phone', $input) ? $input['origin_phone'] : '+62 (812) 0000-1111';
$package['destination']['phone'] = array_key_exists('phone', $input) ? $input['phone'] : '+62 (812) 0000-2222';
if (isset($input['address'])) {
    $package['destination'] = array_replace($package['destination'], $input['address']);
    $destination['shipping_address'] = array_replace($destination['shipping_address'], $input['address']);
}
if (array_key_exists('origin', $input)) { $package['origin'] = $input['origin']; }
$before = $package;
$quote = $service->quote($package, $destination, 'bacs', false);
$validation = null; $error = '';
$token = $quote['rates'][0]['quote_token'] ?? str_repeat('a', 64);
if (isset($input['validate_phone'])) { $package['destination']['phone'] = $input['validate_phone']; }
if (isset($input['validate_origin_phone'])) { $package['origin']['origin_phone'] = $input['validate_origin_phone']; }
try { $validation = $service->validate($token, 'gosend', 'GO-INSTANT', $package, $destination, 'bacs', false); }
catch (Throwable $e) { $error = $e->getMessage(); }
$again = $service->quote($package, $destination, 'bacs', false);
// Actual buyer status lookup from the quote's machine reason, never upstream prose.
require dirname(__DIR__, 2) . '/inc/Services/InstantCheckoutStatusService.php';
$session = WC()->session;
$GLOBALS['wc'] = new class($session, $package) {
    public function __construct(public $session, private array $package) {}
    public function shipping() { return $this; }
    public function get_packages() { return [$this->package]; }
};
$key = '0:' . hash('sha256', wp_json_encode(array_keys($package['contents'])));
$session->set('kiriof_instant_checkout_status', [$key => ['code' => $quote['code'], 'message' => 'untrusted upstream', 'updated' => time()]]);
$status = (new \KiriminAjaOfficial\Services\InstantCheckoutStatusService())->data();
echo json_encode(['status' => $status, 'quote' => $quote, 'validated' => $validation, 'error' => $error, 'again' => $again, 'calls' => $api->calls, 'before' => $before, 'after' => $package]);
