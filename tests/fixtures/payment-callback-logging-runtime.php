<?php
namespace KiriminAjaOfficial\Repositories {
    class KiriminajaApiRepository {
        public function getPayment($payload) { return ['status' => true, 'data' => (object) ['data' => (object) ['pay_time' => '2026-01-01 10:00:00', 'payment_status' => $GLOBALS['remote_status'], 'qr_content' => 'private-qr']]]; }
        public function sendPickupRequest($payload) { $GLOBALS['payload'] = $payload; return ['status' => !$GLOBALS['fail'], 'data' => (object) ['status' => !$GLOBALS['fail'], 'pickup_number' => 'PICKUP-1', 'text' => 'private-api-response', 'results' => (object) ['error' => 'PIN_INVALID']]]; }
    }
    class PaymentRepository {
        public $payment;
        public function __construct() { $this->payment = (object) ['status' => 'unpaid', 'method' => 'qris']; }
        public function getPaymentByPaymentId($id) { return $this->payment; }
        public function updatePaymentByCallback($payload) { $this->payment->status = $payload['changes']['status']; $GLOBALS['writes'][] = $payload; }
        public function createPayment($payload) { $GLOBALS['created'] = $payload; }
    }
    class TransactionRepository {
        public function getTransactionByPickupNumber($id) { return [$GLOBALS['transaction']]; }
        public function getTransactionByOrderIds($ids) { return [$GLOBALS['transaction']]; }
        public function updateTransactionByCallback($payload) { $GLOBALS['writes'][] = $payload; }
    }
    class SettingRepository {}
}
namespace KiriminAjaOfficial\Services {
    class SettingService { public function isTopPaymentMethod() { return false; } }
    class KiriminajaApiService { public function getProfile() { return (object) []; } }
    class ShipmentLocationService {}
}
namespace KiriminAjaOfficial\Services\TransactionProcessServices {
    class RecipientDataResolver { public function resolve($order, $info, $transaction) { return ['first_name' => 'Private', 'last_name' => 'Buyer', 'address_1' => 'private-address', 'address_2' => '', 'phone' => '08123456789', 'postcode' => '12345', 'city' => 'City', 'state' => '', 'country' => 'ID']; } }
}
namespace KiriminAjaOfficial\Base {
    class BaseInit { public function logThis(...$args) { $GLOBALS['debug'][] = $args; } }
}
namespace {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
    define('KIRIOF_ENABLE_KA_CREDIT', false);
    function wp_timezone_string() { return 'UTC'; }
    function wp_timezone() { return new DateTimeZone('UTC'); }
    function __($text, $domain = '') { return $text; }
    function get_home_url() { return 'https://example.test'; }
    function kiriof_log(...$args) { $GLOBALS['logs'][] = $args; }
    function kiriof_helper() { return new class { public function minAmount($value) { return max(1, $value); } }; }
    function wc_get_order($id) { return new class { public function get_items() { return []; } public function get_payment_method() { return 'bacs'; } }; }
    require ABSPATH . 'inc/Utils/ServiceResponse.php';
    require ABSPATH . 'inc/Base/BaseService.php';
    require ABSPATH . 'inc/Services/TransactionDeliveryType.php';
    require ABSPATH . 'inc/Services/PackageTypeService.php';
    require ABSPATH . 'inc/Services/ShippingProcessServices/GetShippingProcessPayment.php';
    require ABSPATH . 'inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php';
    $logs = $debug = $writes = [];
    $remote_status = 'unpaid';
    $fail = ($argv[1] ?? '') === 'failure';
    $transaction = (object) ['order_id' => 'ORDER-1', 'wp_wc_order_stat_order_id' => 1, 'awb' => '', 'cod_fee' => 0, 'shipping_cost' => 10000, 'discount_amount' => 0, 'insurance_cost' => 0, 'weight' => 100, 'width' => 1, 'height' => 1, 'length' => 1, 'service' => 'jne', 'service_name' => 'REG', 'shipment_location_snapshot' => json_encode(['location_id' => 1, 'origin_sub_district_id' => 1, 'origin_address' => 'private-origin'])];
    $api = new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository();
    $payments = new \KiriminAjaOfficial\Repositories\PaymentRepository();
    $transactions = new \KiriminAjaOfficial\Repositories\TransactionRepository();
    if (($argv[1] ?? '') === 'poll') {
        $service = new \KiriminAjaOfficial\Services\ShippingProcessServices\GetShippingProcessPayment($api, $payments, $transactions);
        $responses = [];
        foreach (['unpaid', 'paid', 'paid', 'unpaid'] as $remote_status) { $response = $service->payment_id('PICKUP-1')->call(); $responses[] = [$response->status, $response->data['payment_in_wc_data']->status]; }
        echo json_encode(compact('logs', 'debug', 'writes', 'responses'));
    } else {
        $service = new \KiriminAjaOfficial\Services\TransactionProcessServices\SendRequestPickupTransactionService($transactions, $payments, new \KiriminAjaOfficial\Repositories\SettingRepository(), $api, new \KiriminAjaOfficial\Services\ShipmentLocationService(), new \KiriminAjaOfficial\Services\SettingService(), new \KiriminAjaOfficial\Services\KiriminajaApiService(), new \KiriminAjaOfficial\Services\TransactionProcessServices\RecipientDataResolver());
        $response = $service->orderIds(['ORDER-1'])->schedule(gmdate('Y-m-d', strtotime('+2 days')) . ' 11:00:00')->paymentMethod('qris')->call();
        echo json_encode(['logs' => $logs, 'debug' => $debug, 'writes' => $writes, 'created' => $GLOBALS['created'] ?? null, 'status' => $response->status, 'data' => $response->data]);
    }
}
