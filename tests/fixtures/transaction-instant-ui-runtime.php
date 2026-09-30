<?php
/** Isolated production view-model execution with WordPress/service collaborators stubbed. */
namespace KiriminAjaOfficial\Services\TransactionProcessServices {
    class RecipientDataResolver {
        public function resolve($order, $info, $row): array {
            return array_fill_keys(['first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone'], '');
        }
    }
}
namespace KiriminAjaOfficial\Services {
    class ShipmentLocationService {
        public function repository() { return new class { public function getAll($active): array { return []; } }; }
    }
    class PluginUpdateNoticeService { public function get_toolbar_update() { return null; } }
    class ShippingDiscountCouponService {
        public function splitCouponCodesByScope($codes): array { return ['item' => [], 'shipping' => []]; }
    }
    class TransactionOriginResolver {
        public function __construct($service) {}
        public function resolve($row): array { return ['name' => 'Origin', 'address' => '', 'locationId' => 1, 'phone' => '', 'addressLines' => []]; }
    }
}
namespace {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
    define('KIRIOF_URL', '/plugin/');
    define('KIRIOF_NONCE', 'test');
    define('KIRIOF_MAX_COD_AMOUNT', 1000000);
    function __($text, $domain): string { return $text; }
    function admin_url($url): string { return $url; }
    function absint($value): int { return abs((int) $value); }
    function wp_create_nonce($action): string { return 'nonce'; }
    function wp_date($format, $timestamp, $timezone = null): string { return date($format, $timestamp); }
    function kiriof_helper() {
        return new class {
            public function transactionStatusLabel($status): string { return (string) $status; }
            public function wcStatusLabel($status): string { return (string) $status; }
            public function formatServiceName($service, $name): string { return (string) $service; }
        };
    }
    require ABSPATH . 'inc/Services/TransactionDeliveryType.php';
    require ABSPATH . 'inc/Services/InstantDeliveryStatus.php';
    require ABSPATH . 'inc/Services/TransactionListViewModelFactory.php';
    require ABSPATH . 'inc/Services/TransactionDetailPageData.php';
    $payload = json_decode($argv[1] ?? '{}', true) ?: [];
    $row = (object) array_merge([
        'id' => 1, 'wc_order_id' => 10, 'wc_date_created' => '2025-01-01',
        'status' => 'new', 'service' => 'jne', 'post_status' => 'wc-processing',
        'awb' => 'AWB-1', 'order_id' => 'KA-1',
    ], $payload);
    if (in_array($payload['mode'] ?? '', ['detail', 'fallback'], true)) {
        $data = new \KiriminAjaOfficial\Services\TransactionDetailPageData();
        echo json_encode(($payload['mode'] === 'fallback' ? $data->prepareFallback($row, 'Test fallback') : $data->prepare($row))['transaction'], JSON_THROW_ON_ERROR);
        exit;
    }
    echo json_encode((new \KiriminAjaOfficial\Services\TransactionListViewModelFactory())->createRows([$row], 'all')[0], JSON_THROW_ON_ERROR);
}
