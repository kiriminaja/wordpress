<?php
/** Executes the real Express cancellation service with observable side effects. */
namespace KiriminAjaOfficial\Repositories {
    class TransactionRepository {
        public object $row;
        public array $writes = [];
        public function getTransactionByOrderId($id) { return $this->row; }
        public function updateTransactionByCallback($payload) { $this->writes[] = $payload; return true; }
    }
    class KiriminajaApiRepository {
        public array $calls = [];
        public function cancelShipment($awb, $reason) { $this->calls[] = [$awb, $reason]; return ['status' => true]; }
    }
}
namespace {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
    function __($text, $domain) { return $text; }
    function plugin_dir_path($file) { return dirname($file) . '/'; }
    function plugin_dir_url($file) { return '/plugin/'; }
    function plugin_basename($file) { return basename($file); }
    function home_url() { return 'https://example.test/'; }
    function kiriof_helper() { return new class { public function dateConvertGMT($date) { return $date; } }; }
    function wc_get_order($id) { ++$GLOBALS['woo_reads']; return $GLOBALS['order']; }
    function remove_action(...$args) {}
    function add_action(...$args) {}
    require ABSPATH . 'vendor/autoload.php';
    $input = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
    $repo = new \KiriminAjaOfficial\Repositories\TransactionRepository();
    $repo->row = (object) array_replace([
        'order_id' => 'KA-1', 'delivery_type' => 'express', 'service' => 'jne',
        'status' => 'pending', 'awb' => 'AWB-1', 'wp_wc_order_stat_order_id' => 10,
    ], $input);
    $api = new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository();
    $GLOBALS['woo_reads'] = 0;
    $GLOBALS['order'] = new class {
        public array $writes = [];
        public function get_status() { return 'processing'; }
        public function update_status($status, $note) { $this->writes[] = [$status, $note]; }
    };
    $result = (new \KiriminAjaOfficial\Services\TransactionProcessServices\CancelTransactionService($repo, $api))
        ->orderId('KA-1')->reason('Customer changed their mind')->call();
    echo json_encode([
        'status' => $result->status(), 'message' => $result->message(),
        'calls' => $api->calls, 'writes' => $repo->writes,
        'woo_reads' => $GLOBALS['woo_reads'], 'woo_writes' => $GLOBALS['order']->writes,
    ], JSON_THROW_ON_ERROR);
}
