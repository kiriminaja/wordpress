<?php
// Reuse isolated service boundaries, but keep shipping, billing and getters distinct.
$phone_input = json_decode($argv[1] ?? '{}', true);
$argv[1] = '{}';
ob_start();
require __DIR__ . '/instant-shipment-context-runtime.php';
ob_end_clean();
$GLOBALS['phone_input'] = $phone_input;
class PhoneContextOrder extends ContextOrder {
    public function get_address($type) {
        return array_replace(parent::get_address($type), $GLOBALS['phone_input'][$type] ?? []);
    }
    public function get_shipping_phone() { return $GLOBALS['phone_input']['shipping_getter'] ?? null; }
    public function get_billing_phone() { return $GLOBALS['phone_input']['billing_getter'] ?? null; }
}
$GLOBALS['order'] = new PhoneContextOrder();
$row->shipping_info = json_encode(array_replace(json_decode($row->shipping_info, true), ['_shipping_phone' => '+62 (812) 9999-9999']));
$source = static function () use ($row) {
    return [
        'row' => (array) $row,
        'shipping' => $GLOBALS['order']->get_address('shipping'),
        'billing' => $GLOBALS['order']->get_address('billing'),
        'shipping_getter' => $GLOBALS['order']->get_shipping_phone(),
        'billing_getter' => $GLOBALS['order']->get_billing_phone(),
    ];
};
$before = $source();
try {
    $context = $service->build($row);
    $result = ['ok' => true, 'context' => $context];
} catch (InvalidArgumentException $error) {
    $result = ['ok' => false, 'error' => $error->getMessage()];
}
echo json_encode($result + ['before' => $before, 'after' => $source()]);
