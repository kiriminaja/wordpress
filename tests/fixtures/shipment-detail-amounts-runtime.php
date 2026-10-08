<?php
/** Isolated production view-model execution with WordPress/service collaborators stubbed. */
namespace KiriminAjaOfficial\Services {
    function get_option( $name, $default = '' ) { return $default; }
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
    function add_query_arg($args, $url): string { return $url . '&' . http_build_query($args); }
    function absint($value): int { return abs((int) $value); }
    function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
    function esc_url_raw($url, $protocols = null): string {
        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), $protocols ?? ['http', 'https'], true) ? $url : '';
    }
    function wp_create_nonce($action): string { return 'nonce'; }
    function wp_date($format, $timestamp, $timezone = null): string { return date($format, $timestamp); }
    function kiriof_helper() {
        return new class extends \KiriminAjaOfficial\Base\Helper {
            public function __construct() {}
            public function formatServiceName($service, $name): string { return (string) $service; }
        };
    }
    require ABSPATH . 'inc/Base/BaseInit.php';
    require ABSPATH . 'inc/Base/Helper.php';
    require ABSPATH . 'inc/Services/TransactionDeliveryType.php';
    require ABSPATH . 'inc/Services/ShipmentDetailPayment.php';
    require ABSPATH . 'inc/Services/InstantDeliveryStatus.php';
    // Load the production static lifecycle guards; no repository instance is needed.
    require ABSPATH . 'inc/Services/InstantShipmentState.php';
    require ABSPATH . 'inc/Services/InstantTrackingPresentation.php';
    require ABSPATH . 'inc/Services/GoogleMapsSettings.php';
    require ABSPATH . 'inc/Services/InstantDetailMapData.php';
    require ABSPATH . 'inc/Services/TransactionProcessServices/RecipientDataResolver.php';
    require ABSPATH . 'inc/Services/InstantShipmentContext.php';
    require ABSPATH . 'inc/Services/InstantLabelService.php';
    require ABSPATH . 'inc/Services/TransactionListViewModelFactory.php';
    require ABSPATH . 'inc/Services/TransactionDetailPageData.php';
    $payload = json_decode($argv[1] ?? '{}', true) ?: [];
    // Keep legacy display-only cases orderless. Return the same fake on every
    // lookup: the factory and real label guard independently load the WC order.
    $fixture_order = isset($payload['wc_order']) && is_array($payload['wc_order'])
        ? new class($payload['wc_order']) {
            public function __construct(private array $data) {}
            public function get_status(): string { return $this->data['status'] ?? 'processing'; }
            public function is_paid(): bool { return $this->data['paid'] ?? false; }
            public function get_address($type): array { return $this->data['address'] ?? []; }
            public function get_meta($key, $single = true) { return $this->data['meta'][$key] ?? ''; }
            public function get_payment_method(): string { return 'bacs'; }
            public function get_discount_total(): float { return 0; }
            public function get_coupon_codes(): array { return []; }
            public function get_shipping_total(): float { return 12000; }
            public function get_total(): float { return 62000; }
            public function get_subtotal(): float { return 50000; }
            public function get_items($type = 'line_item'): array {
                if ('fee' === $type) {
                    if (!empty($this->data['fees_unavailable'])) { throw new \RuntimeException('Unavailable'); }
                    return array_map(static fn($data) => new class($data) {
                        public function __construct(private array $data) {}
                        public function get_meta($key, $single = true) { return $this->data['type'] ?? ''; }
                        public function get_name(): string { return $this->data['name'] ?? 'Admin Fee'; }
                        public function get_total() { return $this->data['total'] ?? 0; }
                    }, $this->data['fees'] ?? []);
                }
                return [new class {
                    public function get_product() { return false; } // Deleted product.
                    public function get_name(): string { return 'Current WC item'; }
                    public function get_quantity(): int { return 1; }
                    public function get_total(): float { return 50000; }
                }];
            }
        } : false;
    function wc_get_order($id) {
        global $fixture_order;
        return (int) $id === 10 ? $fixture_order : false;
    }
    unset($payload['wc_order']);
    $row = (object) array_merge([
        'id' => 1, 'wc_order_id' => 10, 'wp_wc_order_stat_order_id' => 10, 'wc_date_created' => '2025-01-01',
        'status' => 'new', 'service' => 'jne', 'post_status' => 'wc-processing',
        'awb' => 'AWB-1', 'order_id' => 'KA-1',
    ], $payload);
    if (in_array($payload['mode'] ?? '', ['detail', 'fallback'], true)) {
        $data = new \KiriminAjaOfficial\Services\TransactionDetailPageData();
        echo json_encode($payload['mode'] === 'fallback' ? $data->prepareFallback($row, 'Test fallback') : $data->prepare($row), JSON_THROW_ON_ERROR);
        exit;
    }
    echo json_encode((new \KiriminAjaOfficial\Services\TransactionListViewModelFactory())->createRows([$row], 'all')[0], JSON_THROW_ON_ERROR);
}
