<?php
/** Read-only payment grouping against actual SQL, isolated from WordPress stubs. */
declare(strict_types=1);

namespace KiriminAjaOfficial\Services {
    final class PluginUpdateNoticeService { public function get_toolbar_update() { return null; } }
    final class RevampAnnouncementService { public static function attach_announcement($toolbar) { return $toolbar; } }
}
namespace {
    $input = json_decode(str_starts_with($argv[1], '@') ? file_get_contents(substr($argv[1], 1)) : $argv[1], true, 512, JSON_THROW_ON_ERROR);
    define('ABSPATH', dirname(__DIR__, 2) . '/');
    define('KIRIOF_URL', '/plugin/');
    define('KIRIOF_NONCE', 'test');
    function __($text, $domain = '') { return $text; }
    function wp_date($format, $time) { return gmdate($format, $time); }
    function kiriof_money_format($value) { return number_format((float) $value); }
    function admin_url($path) { return '/wp-admin/' . $path; }
    function wp_create_nonce($action) { return 'test'; }
    function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
    function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value); }
    function add_query_arg($args, $url) { return $url . '&' . http_build_query($args); }
    require_once ABSPATH . 'inc/Contracts/PaymentListQueryInterface.php';
    require_once ABSPATH . 'inc/Services/ListDateRangeFilter.php';
    require_once $input['query_source'] ?? ABSPATH . 'inc/Queries/WordPressPaymentListQuery.php';
    require_once ABSPATH . 'inc/Services/PaymentListRenderService.php';

    final class PaymentListDatabaseWpdb {
        public string $prefix = 'wp_';
        public string $last_error = '';
        public array $queries = [];
        public PDO $db;
        private const PERCENT = '{payment-list-percent}';
        public function __construct(string $collation = 'BINARY') {
            if (!in_array($collation, ['BINARY', 'NOCASE'], true)) {
                throw new InvalidArgumentException('Unsupported payment identity collation');
            }
            $this->db = class_exists('Pdo\\Sqlite') ? new \Pdo\Sqlite('sqlite::memory:') : new PDO('sqlite::memory:');
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if (method_exists($this->db, 'createFunction')) {
                $this->db->createFunction('CONCAT', static fn($a, $b) => $a . $b, 2);
            } else { $this->db->sqliteCreateFunction('CONCAT', static fn($a, $b) => $a . $b, 2); }
            $this->db->exec('CREATE TABLE wp_kiriminaja_payments (pickup_number TEXT, created_at TEXT, pickup_schedule TEXT, order_amt INT, method TEXT, status TEXT)');
            $this->db->exec('CREATE TABLE wp_kiriminaja_transactions (order_id TEXT, pickup_number TEXT, created_at TEXT, cod_fee REAL, shipping_cost REAL, discount_amount REAL, insurance_cost REAL, delivery_type TEXT, instant_payment_id TEXT COLLATE ' . $collation . ', request_pickup_at TEXT, instant_payment_method TEXT, instant_payment_status TEXT)');
        }
        public function esc_like($value) { return addcslashes((string) $value, '_%\\'); }
        public function prepare($sql, ...$args) {
            if (1 === count($args) && is_array($args[0])) { $args = $args[0]; }
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
    $db = new PaymentListDatabaseWpdb($input['instant_payment_id_collation'] ?? 'BINARY');
    if (isset($input['benchmark'])) {
        $options = $input['benchmark'];
        $db->db->beginTransaction();
        for ($group = 0; $group < $options['groups']; ++$group) {
            $instant = 1 === $group % 2;
            $identity = sprintf('payment-%06d', $group);
            $created = gmdate('Y-m-d H:i:s', 1740787200 + $group);
            $status = ['unpaid', 'paid', 'pending', 'refunded'][intdiv($group, 2) % 4];
            if (!$instant) {
                $db->insert('payments', ['pickup_number'=>$identity, 'created_at'=>$created, 'order_amt'=>3, 'method'=>'qris', 'status'=>$status]);
            }
            for ($member = 0; $member < 3; ++$member) {
                $db->insert('transactions', ['order_id'=>sprintf('%08d', $group * 3 + $member), 'pickup_number'=>$instant ? '' : $identity, 'created_at'=>$created, 'delivery_type'=>$instant ? 'instant' : 'express', 'instant_payment_id'=>$instant ? $identity : null, 'instant_payment_status'=>$status, 'instant_payment_method'=>'qris', 'shipping_cost'=>10000, 'discount_amount'=>100, 'insurance_cost'=>50, 'cod_fee'=>0]);
            }
        }
        $db->db->commit();
        $query = new \KiriminAjaOfficial\Queries\WordPressPaymentListQuery($db);
        $samples = [];
        $budgets = [];
        for ($iteration = -$options['warmups']; $iteration < $options['iterations']; ++$iteration) {
            $db->queries = [];
            $start = hrtime(true);
            $page = $query->getPage(['key'=>'', 'month'=>'', 'status'=>''], 1, 20);
            $page_end = hrtime(true);
            $page_queries = count($db->queries);
            $counts = $query->getStatusCounts();
            $counts_end = hrtime(true);
            $counts_queries = count($db->queries) - $page_queries;
            $oldest = $query->getOldestCreatedAt();
            $end = hrtime(true);
            if ($iteration >= 0) {
                $samples['page'][] = ($page_end - $start) / 1e6;
                $samples['status'][] = ($counts_end - $page_end) / 1e6;
                $samples['oldest'][] = ($end - $counts_end) / 1e6;
                $samples['combined'][] = ($end - $start) / 1e6;
                $budgets[] = ['page'=>$page_queries, 'status'=>$counts_queries, 'oldest'=>count($db->queries) - $page_queries - $counts_queries, 'combined'=>count($db->queries)];
            }
        }
        $summary = [];
        foreach ($samples as $operation=>$values) {
            sort($values);
            $n = count($values);
            $summary[$operation] = ['median_ms'=>($values[(int) floor(($n - 1) / 2)] + $values[(int) ceil(($n - 1) / 2)]) / 2, 'p95_ms'=>$values[(int) ceil($n * 0.95) - 1]];
        }
        echo json_encode(['environment'=>['php'=>PHP_VERSION, 'sqlite'=>$db->db->query('SELECT sqlite_version()')->fetchColumn(), 'os'=>php_uname(), 'opcache_cli'=>ini_get('opcache.enable_cli')], 'dataset'=>['groups'=>$options['groups'], 'express_groups'=>(int) ceil($options['groups'] / 2), 'instant_groups'=>(int) floor($options['groups'] / 2), 'members_per_group'=>3, 'page_size'=>20, 'indexes'=>'none', 'storage'=>'SQLite in-memory', 'warmups'=>$options['warmups'], 'iterations'=>$options['iterations']], 'query_source'=>realpath($input['query_source'] ?? ABSPATH . 'inc/Queries/WordPressPaymentListQuery.php'), 'summary'=>$summary, 'query_counts'=>$budgets, 'samples_ms'=>$samples, 'result'=>['total'=>$page['total'], 'rows'=>count($page['results']), 'counts'=>$counts, 'oldest'=>$oldest], 'caveat'=>'SQLite fixture timings are not MySQL production timings; no network, WordPress bootstrap, rendering or seed time is measured.'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        exit;
    }
    foreach ($input['payments'] ?? [] as $row) { $db->insert('payments', $row); }
    foreach ($input['transactions'] ?? [] as $row) { $db->insert('transactions', $row); }
    $query = new \KiriminAjaOfficial\Queries\WordPressPaymentListQuery($db);
    $pages = [];
    foreach ($input['requests'] ?? [[]] as $request) {
        $pages[] = $query->getPage(array_replace(['key'=>'', 'month'=>'', 'status'=>''], $request['filters'] ?? []), $request['page'] ?? 1, $request['per_page'] ?? 20);
    }
    $counts = $query->getStatusCounts();
    $renderer = new \KiriminAjaOfficial\Services\PaymentListRenderService($query);
    $_GET = $input['get'] ?? [];
    $filters = (new ReflectionMethod($renderer, 'getFilters'))->invoke($renderer);
    $method = new ReflectionMethod($renderer, 'prepareSvelteBootstrap');
    $bootstrap = $method->invoke($renderer, $pages[0]['results'], $filters, $pages[0]['page'], $pages[0]['total_pages'], $pages[0]['total'], $pages[0]['items_per_page'], [], $counts);
    echo json_encode(['pages'=>$pages, 'counts'=>$counts, 'oldest'=>$query->getOldestCreatedAt(), 'bootstrap'=>$bootstrap, 'queries'=>$db->queries], JSON_THROW_ON_ERROR);
}
