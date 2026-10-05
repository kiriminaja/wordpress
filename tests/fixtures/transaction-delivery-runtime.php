<?php
/** Isolated runtime harness: production methods, captured SQL (not a database engine). */
declare(strict_types=1);
namespace Automattic\WooCommerce\Utilities {
    final class OrderUtil {
        public static bool $enabled = false;
        public static function custom_orders_table_usage_is_enabled(): bool { return self::$enabled; }
    }
}
namespace {
    $root = dirname(__DIR__, 2);
    $temp = sys_get_temp_dir() . '/kiriof-delivery-' . getmypid();
    mkdir($temp . '/wp-admin/includes', 0777, true);
    file_put_contents($temp . '/wp-admin/includes/upgrade.php', '<?php');
    function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
    define('ABSPATH', $temp . '/');
    define('HOUR_IN_SECONDS', 3600);
    foreach (['Base/BaseInit', 'Services/TransactionDeliveryType', 'Services/ListDateRangeFilter', 'Contracts/TransactionListQueryInterface', 'Contracts/TransactionPrintRepositoryInterface', 'Queries/WordPressTransactionListQuery', 'Repositories/TransactionRepository', 'Migration/SetupMigration'] as $file) { require_once $root . '/inc/' . $file . '.php'; }
    function get_option($key, $default = '') { return $GLOBALS['options'][$key] ?? $default; }
    function update_option($key, $value, $autoload = false) { $GLOBALS['options'][$key] = $value; return true; }
    function esc_sql($value) { return $value; }
    function dbDelta($sql) {
        $GLOBALS['wpdb']->queries[] = $sql;
        preg_match_all('/^\s*`([^`]+)`\s/m', $sql, $matches);
        $GLOBALS['wpdb']->columns = $matches[1];
        $GLOBALS['wpdb']->indexed = true;
    }
    function delete_transient($key) { $GLOBALS['deleted'][] = $key; }
    function get_transient($key) { return $GLOBALS['cache'][$key] ?? false; }
    function set_transient($key, $value, $ttl) { $GLOBALS['cache'][$key] = $value; }
    function kiriof_log($level, $message) { $GLOBALS['logs'][] = $message; }
    function plugin_dir_path($file) { return dirname($file) . '/'; }
    function plugin_dir_url($file) { return 'https://example.test/'; }
    function plugin_basename($file) { return basename($file); }
    function home_url() { return 'https://example.test/'; }
    final class DeliveryWpdb {
        public string $prefix = 'wp_';
        public string $postmeta = 'wp_postmeta';
        public string $last_error = '';
        public array $queries = [];
        public array $prepared = [];
        public array $updates = [];
        public bool $fail = false;
        public array $columns = [];
        public bool $exists = true;
        public bool $indexed = false;
        public ?object $prior = null;
        public function prepare($sql, ...$args): string {
            if (preg_match_all('/%[sdf]/', $sql) !== count($args)) { throw new \RuntimeException('Placeholder mismatch'); }
            $this->prepared[] = [$sql, $args];
            foreach ($args as $arg) {
                $sql = preg_replace_callback('/%[sdf]/', static function ($m) use ($arg) { return '%s' === $m[0] ? "'" . str_replace("'", "''", (string) $arg) . "'" : (string) (float) $arg; }, $sql, 1);
            }
            return $sql;
        }
        public function esc_like($value): string { return addcslashes($value, '_%\\'); }
        public function get_var($sql) { $this->queries[] = $sql; return str_starts_with($sql, 'SHOW TABLES') ? ($this->exists ? 'wp_kiriminaja_transactions' : null) : 55; }
        public function get_col($sql, $index) { $this->queries[] = $sql; return $this->columns; }
        public function get_row($sql) {
            $this->queries[] = $sql;
            if (str_contains($sql, 'WHERE `order_id`') || str_contains($sql, 'WHERE wp_wc_order_stat_order_id')) {
                if ($this->fail) { $this->last_error = 'lookup failed'; return null; }
                return $this->prior;
            }
            return $this->indexed ? (object) ['Key_name'=>'delivery_type'] : null;
        }
        public function get_results($sql): array { $this->queries[] = $sql; return [(object) ['service'=>'gosend']]; }
        public function update($table, $changes, $where) {
            $this->updates[] = $changes;
            if (null !== $this->prior) { $this->prior = (object) array_replace(get_object_vars($this->prior), $changes); }
            return 1;
        }
        public function query($sql) {
            $this->queries[] = $sql;
            if ($this->fail) { return false; }
            if (preg_match('/ADD (?!KEY)([a-z_]+)/', $sql, $matches)) { $this->columns[] = $matches[1]; }
            if (str_contains($sql, 'ADD KEY delivery_type')) { $this->indexed = true; }
            return 1;
        }
    }
    $input = json_decode($argv[1], true);
    // Legacy onboarding sync state must not gate the independent schema upgrade.
    $GLOBALS['options'] = ['kiriof_sync_version'=>'v3'];
    $wpdb = new DeliveryWpdb();
    if (isset($input['prior'])) { $wpdb->prior = (object) $input['prior']; }
    $mode = $input['mode'] ?? 'query';
    if ('migration' === $mode) {
        $wpdb->exists = $input['exists'] ?? true;
        $wpdb->columns = array_unique(array_merge(['status'], $input['columns'] ?? []));
        $wpdb->indexed = $input['indexed'] ?? false;
        $wpdb->fail = $input['fail'] ?? false;
        if ($input['complete'] ?? false) { $GLOBALS['options']['kiriof_transaction_partition_v1'] = '1'; }
        (new \ReflectionMethod(\KiriminAjaOfficial\Migration\SetupMigration::class, 'transactionsTable'))->invoke(new \KiriminAjaOfficial\Migration\SetupMigration());
        $first_queries = count($wpdb->queries);
        (new \ReflectionMethod(\KiriminAjaOfficial\Migration\SetupMigration::class, 'transactionsTable'))->invoke(new \KiriminAjaOfficial\Migration\SetupMigration());
    } elseif ('update' === $mode) {
        $wpdb->fail = $input['fail_lookup'] ?? false;
        $condition = $input['condition'] ?? ['order_id'=>'test'];
        $repository = new \KiriminAjaOfficial\Repositories\TransactionRepository();
        if ('full' === ($input['writer'] ?? '')) {
            $payload = array_replace(array_fill_keys(['destination_sub_district_id', 'destination_sub_district', 'service_name', 'shipping_cost', 'insurance_cost', 'cod_fee'], ''), $input['changes'], $condition);
            $ok = $repository->updateTransaction($payload);
        } elseif ('callback' === ($input['writer'] ?? '')) {
            $ok = $repository->updateTransactionByCallback(['changes'=>$input['changes'], 'condition'=>$condition]);
        } else {
            $ok = $repository->updateTransactionByCallbackVerified(['changes'=>$input['changes'], 'condition'=>$condition]);
        }
    } elseif ('repository' === $mode) {
        $payload = array_fill_keys(['order_id','shipping_info','destination_sub_district_id','destination_sub_district','status','service','service_name','weight','width','height','length','shipping_cost','insurance_cost','cod_fee','transaction_value','created_at','wp_wc_order_stat_order_id'], '');
        $ok = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->createTransaction(array_replace($payload, $input['payload'] ?? []));
    } else {
        \Automattic\WooCommerce\Utilities\OrderUtil::$enabled = $input['hpos'] ?? false;
        $query = new \KiriminAjaOfficial\Queries\WordPressTransactionListQuery($wpdb);
        $filters = array_replace(['key'=>'','month'=>'','status'=>'all','cod'=>'','courier'=>'','print_status'=>''], $input['filters'] ?? []);
        $page = $query->getPage($filters, 9, 25);
        $counts = $query->getStatusCounts();
        $query->getCouriers();
        if ($input['reset'] ?? false) { unset($filters['delivery_type']); $query->getPage($filters, 1, 25); $query->getCouriers(); }
    }
    unlink($temp . '/wp-admin/includes/upgrade.php');
    rmdir($temp . '/wp-admin/includes'); rmdir($temp . '/wp-admin'); rmdir($temp);
    echo json_encode(['row'=>$wpdb->prior, 'queries'=>$wpdb->queries, 'prepared'=>$wpdb->prepared, 'page'=>$page ?? null, 'counts'=>$counts ?? null, 'updates'=>$wpdb->updates, 'options'=>$GLOBALS['options'] ?? [], 'first_queries'=>$first_queries ?? null, 'ok'=>$ok ?? null, 'deleted'=>$GLOBALS['deleted'] ?? [], 'cache_keys'=>array_keys($GLOBALS['cache'] ?? [])], JSON_THROW_ON_ERROR);
}
