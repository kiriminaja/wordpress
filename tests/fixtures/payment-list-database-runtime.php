<?php
/** Read-only payment grouping against actual SQL, isolated from WordPress stubs. */
declare(strict_types=1);

namespace KiriminAjaOfficial\Services {
    final class PluginUpdateNoticeService { public function get_toolbar_update() { return null; } }
    final class RevampAnnouncementService { public static function attach_announcement($toolbar) { return $toolbar; } }
}
namespace {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
    define('KIRIOF_URL', '/plugin/');
    define('KIRIOF_NONCE', 'test');
    function __($text, $domain = '') { return $text; }
    function wp_date($format, $time) { return gmdate($format, $time); }
    function kiriof_money_format($value) { return number_format((float) $value); }
    function admin_url($path) { return '/wp-admin/' . $path; }
    function wp_create_nonce($action) { return 'test'; }
    function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
    function add_query_arg($args, $url) { return $url . '&' . http_build_query($args); }
    require_once ABSPATH . 'inc/Contracts/PaymentListQueryInterface.php';
    require_once ABSPATH . 'inc/Queries/WordPressPaymentListQuery.php';
    require_once ABSPATH . 'inc/Services/PaymentListRenderService.php';

    final class PaymentListDatabaseWpdb {
        public string $prefix = 'wp_';
        public string $last_error = '';
        public array $queries = [];
        public PDO $db;
        private const PERCENT = '{payment-list-percent}';
        public function __construct() {
            $this->db = class_exists('Pdo\\Sqlite') ? new \Pdo\Sqlite('sqlite::memory:') : new PDO('sqlite::memory:');
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if (method_exists($this->db, 'createFunction')) {
                $this->db->createFunction('CONCAT', static fn($a, $b) => $a . $b, 2);
            } else { $this->db->sqliteCreateFunction('CONCAT', static fn($a, $b) => $a . $b, 2); }
            $this->db->exec('CREATE TABLE wp_kiriminaja_payments (pickup_number TEXT, created_at TEXT, pickup_schedule TEXT, order_amt INT, method TEXT, status TEXT)');
            $this->db->exec('CREATE TABLE wp_kiriminaja_transactions (pickup_number TEXT, created_at TEXT, cod_fee REAL, shipping_cost REAL, discount_amount REAL, insurance_cost REAL, delivery_type TEXT, instant_payment_id TEXT, request_pickup_at TEXT, instant_payment_method TEXT, instant_payment_status TEXT)');
        }
        public function esc_like($value) { return addcslashes((string) $value, '_%\\'); }
        public function prepare($sql, ...$args) {
            $index = 0;
            $sql = preg_replace_callback('/%[ids]/', function ($match) use ($args, &$index) {
                if (!array_key_exists($index, $args)) { throw new RuntimeException('Missing argument'); }
                $arg = $args[$index++];
                $literal = '%i' === $match[0] ? $arg : ('%d' === $match[0] ? (string) (int) $arg : $this->db->quote((string) $arg));
                return str_replace('%', self::PERCENT, $literal);
            }, $sql);
            if ($index !== count($args)) { throw new RuntimeException('Unused argument'); }
            return $sql;
        }
        private function execute($sql) {
            $sql = str_replace(self::PERCENT, '%', $sql);
            $this->queries[] = $sql;
            return $this->db->query($sql);
        }
        public function get_results($sql) { return $this->execute($sql)->fetchAll(PDO::FETCH_OBJ); }
        public function get_var($sql) { $value = $this->execute($sql)->fetchColumn(); return false === $value ? null : $value; }
        public function insert($table, $row) {
            $stmt = $this->db->prepare('INSERT INTO wp_kiriminaja_' . $table . ' (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')');
            $stmt->execute(array_values($row));
        }
    }
    $input = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
    $db = new PaymentListDatabaseWpdb();
    foreach ($input['payments'] ?? [] as $row) { $db->insert('payments', $row); }
    foreach ($input['transactions'] ?? [] as $row) { $db->insert('transactions', $row); }
    $query = new \KiriminAjaOfficial\Queries\WordPressPaymentListQuery($db);
    $pages = [];
    foreach ($input['requests'] ?? [[]] as $request) {
        $pages[] = $query->getPage(array_replace(['key'=>'', 'month'=>'', 'status'=>''], $request['filters'] ?? []), $request['page'] ?? 1, $request['per_page'] ?? 20);
    }
    $counts = $query->getStatusCounts();
    $renderer = new \KiriminAjaOfficial\Services\PaymentListRenderService($query);
    $method = new ReflectionMethod($renderer, 'prepareSvelteBootstrap');
    $bootstrap = $method->invoke($renderer, $pages[0]['results'], ['key'=>'', 'month'=>'', 'status'=>''], $pages[0]['page'], $pages[0]['total_pages'], $pages[0]['total'], $pages[0]['items_per_page'], [], $counts);
    echo json_encode(['pages'=>$pages, 'counts'=>$counts, 'oldest'=>$query->getOldestCreatedAt(), 'bootstrap'=>$bootstrap, 'queries'=>$db->queries], JSON_THROW_ON_ERROR);
}
