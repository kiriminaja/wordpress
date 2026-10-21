<?php
// Reuse the full live-quote checkout harness, then exercise fixed diagnostic mapping.
ob_start();
require __DIR__ . '/instant-checkout-order-runtime.php';
$result = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
function add_filter(...$args) {}
class WC_Shipping_Method { public int $instance_id = 4; }
require dirname(__DIR__, 2) . '/wc/KiriminajaInstantShippingMethod.php';
kiriof_instant_shipping_method();
$method = (new ReflectionClass(Kiriof_Instant_Shipping_Method_Controller::class))->newInstanceWithoutConstructor();
$storeStatus = new ReflectionMethod($method, 'store_status');
$storeStatus->invoke($method, WC()->session, $package, 'recipient_address_invalid', false, 0);
$result['address_status'] = array_values(WC()->session->get('kiriof_instant_checkout_status'))[0];
$reason = new ReflectionMethod($controller, 'reason');
$result['address_reason'] = $reason->invoke($controller, new InvalidArgumentException('recipient_address_invalid'), 'fallback');
echo json_encode($result);
