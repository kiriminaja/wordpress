<?php
/** Render the real controller, service and template with isolated WC/carrier collaborators. */
namespace KiriminAjaOfficial\Repositories {
    class TransactionRepository { public function getTransactionByWCOrderId($id) { return $GLOBALS['row']; } }
    class SettingRepository {}
    class KiriminajaApiRepository {}
    class PaymentRepository {
        public function getPaymentByPaymentId($id) {
            $GLOBALS['payment_lookup'][] = $id;
            return isset($GLOBALS['payload']['carrier_payment']) ? (object) $GLOBALS['payload']['carrier_payment'] : false;
        }
    }
}
namespace KiriminAjaOfficial\Services {
    class ShippingDiscountCouponService {
        public function splitCouponCodesByScope($codes): array { return ['item' => array_values(array_diff($codes, ['SHIP'])), 'shipping' => in_array('SHIP', $codes, true) ? ['SHIP'] : []]; }
    }
}
namespace {
    error_reporting(E_ALL & ~E_DEPRECATED);
    define('ABSPATH', dirname(__DIR__, 2) . '/');
    define('KIRIOF_DIR', ABSPATH);
    define('KIRIOF_NONCE', 'fixture');
    function __($text, $domain = '') { return $text; }
    function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
    function esc_attr($text) { return esc_html($text); }
    function esc_html__($text, $domain = '') { return esc_html($text); }
    function esc_html_e($text, $domain = '') { echo esc_html($text); }
    function esc_attr_e($text, $domain = '') { echo esc_attr($text); }
    function esc_url($text) { return esc_attr($text); }
    function wp_kses_post($text) { return $text; }
    function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
    function wc_price($amount) { return 'Rp' . number_format((float) $amount, 0, ',', '.'); }
    function wp_create_nonce($action) { return 'nonce'; }
    function admin_url($url) { return $url; }
    function absint($value) { return abs((int) $value); }
    function add_query_arg($key, $value, $url) { return $url . '&' . $key . '=' . $value; }
    function kiriof_money_format($amount) { return number_format((float) $amount); }
    function number_format_i18n($amount, $decimals = 0) { return number_format((float) $amount, $decimals); }
    function kiriof_helper() { return new class {
        public function transactionStatusLabel($status) { return $status; }
        public function transactionStatusClass($status) { return ''; }
        public function formatServiceName($service, $name) { return $service . ' ' . $name; }
    }; }
    class WC_Order {
        public function __construct(private array $data) {}
        public function get_id() { return 10; }
        public function get_subtotal() { return $this->data['subtotal'] ?? 20000; }
        public function get_total() { return $this->data['total'] ?? 32000; }
        public function get_discount_total() { return $this->data['discount'] ?? 0; }
        public function get_coupon_codes() { return $this->data['coupons'] ?? []; }
        public function needs_payment() { return $this->data['needs_payment'] ?? false; }
        public function get_shipping_total() { return $this->data['shipping'] ?? 11000; }
        public function get_meta($key, $single = true) { return $this->data['meta'][$key] ?? ''; }
        public function get_items($type = 'line_item') { return array_map(static fn($fee) => new class($fee) {
            public function __construct(private array $fee) {}
            public function get_meta($key, $single = true) { return $this->fee['type'] ?? ''; }
            public function get_total() { return $this->fee['total'] ?? 0; }
            public function get_name() { return $this->fee['name'] ?? 'Admin Fee'; }
        }, $this->data['fees'] ?? []); }
    }
    function wc_get_order($id) { return $GLOBALS['order']; }
    $payload = json_decode($argv[1] ?? '{}', true) ?: [];
    $row = (object) array_merge(['id' => 1, 'wp_wc_order_stat_order_id' => 10, 'service' => 'gosend', 'service_name' => 'Instant', 'delivery_type' => 'instant', 'vehicle' => 'motor', 'order_id' => 'KA-1', 'status' => 'new', 'shipping_cost' => 11000, 'discount_amount' => 0], $payload['row'] ?? []);
    $order = new WC_Order($payload['wc_order'] ?? ['fees' => [['type' => 'instant_admin_fee', 'total' => 1000]]]);
    $payment_lookup = [];
    require ABSPATH . 'inc/Utils/ServiceResponse.php';
    require ABSPATH . 'inc/Base/BaseService.php';
    require ABSPATH . 'inc/Services/ShipmentDetailAmounts.php';
    require ABSPATH . 'inc/Services/TransactionDeliveryType.php';
    require ABSPATH . 'inc/Services/ShipmentDetailPayment.php';
    require ABSPATH . 'inc/Services/OrderEditPageServices/ShippingInfoServices.php';
    require ABSPATH . 'inc/Controllers/EditOrderController.php';
    $controller = new \KiriminAjaOfficial\Controllers\EditOrderController(new \KiriminAjaOfficial\Repositories\TransactionRepository(), new \KiriminAjaOfficial\Repositories\SettingRepository(), new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository());
    ob_start();
    $controller->renderShippingMetaBox(empty($payload['legacy']) ? $order : (object) ['ID' => 10]);
    $html = ob_get_clean();
    preg_match('/<script type="application\/json" data-kiriof-order-metabox-payload>(.*?)<\/script>/s', $html, $match);
    echo json_encode(['html' => json_decode($match[1], true)['html'], 'payment_lookup' => $payment_lookup], JSON_THROW_ON_ERROR);
}
