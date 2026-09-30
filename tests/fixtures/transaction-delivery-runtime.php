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
    define('ABSPATH', $temp . '/');
    define('HOUR_IN_SECONDS', 3600);
    foreach (['Services/TransactionDeliveryType', 'Contracts/TransactionListQueryInterface', 'Contracts/TransactionPrintRepositoryInterface', 'Queries/WordPressTransactionListQuery', 'Repositories/TransactionRepository', 'Migration/SetupMigration'] as $file) { require_once $root . '/inc/' . $file . '.php'; }
    function get_option($key, $default = '') { return $GLOBALS['options'][$key] ?? $default; }
    function update_option($key, $value, $autoload = false) { $GLOBALS['options'][$key] = $value; return true; }
    function esc_sql($value) { return $value; }
    function dbDelta($sql) { $GLOBALS['wpdb']->queries[] = $sql; $GLOBALS['wpdb']->columns = ['delivery_type', 'vehicle']; $GLOBALS['wpdb']->indexed = true; }
    function delete_transient($key) { $GLOBALS['deleted'][] = $key; }
    function get_transient($key) { return $GLOBALS['cache'][$key] ?? false; }
    function set_transient($key, $value, $ttl) { $GLOBALS['cache'][$key] = $value; }
    function kiriof_log($level, $message) { $GLOBALS['logs'][] = $message; }
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
        public function get_row($sql) { $this->queries[] = $sql; return $this->indexed ? (object) ['Key_name'=>'delivery_type'] : null; }
        public function get_results($sql): array { $this->queries[] = $sql; return [(object) ['service'=>'gosend']]; }
        public function update($table, $changes, $where) { $this->updates[] = $changes; return 1; }
        public function query($sql) {
            $this->queries[] = $sql;
            if ($this->fail) { return false; }
            if (str_contains($sql, 'ADD delivery_type')) { $this->columns[] = 'delivery_type'; }
            if (str_contains($sql, 'ADD vehicle')) { $this->columns[] = 'vehicle'; }
            if (str_contains($sql, 'ADD KEY delivery_type')) { $this->indexed = true; }
            return 1;
        }
    }
    $input = json_decode($argv[1], true);
    // Legacy onboarding sync state must not gate the independent schema upgrade.
    $GLOBALS['options'] = ['kiriof_sync_version'=>'v3'];
    $wpdb = new DeliveryWpdb();
    $mode = $input['mode'] ?? 'query';
    if ('migration' === $mode) {
        $wpdb->exists = $input['exists'] ?? true;
        $wpdb->columns = $input['columns'] ?? [];
        $wpdb->indexed = $input['indexed'] ?? false;
        $wpdb->fail = $input['fail'] ?? false;
        if ($input['complete'] ?? false) { $GLOBALS['options']['kiriof_transaction_partition_v1'] = '1'; }
        (new \ReflectionMethod(\KiriminAjaOfficial\Migration\SetupMigration::class, 'transactionsTable'))->invoke(new \KiriminAjaOfficial\Migration\SetupMigration());
        $first_queries = count($wpdb->queries);
        (new \ReflectionMethod(\KiriminAjaOfficial\Migration\SetupMigration::class, 'transactionsTable'))->invoke(new \KiriminAjaOfficial\Migration\SetupMigration());
    } elseif ('update' === $mode) {
        $ok = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->updateTransactionByCallbackVerified(['changes'=>$input['changes'], 'condition'=>['order_id'=>'test']]);
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
    echo json_encode(['queries'=>$wpdb->queries, 'prepared'=>$wpdb->prepared, 'page'=>$page ?? null, 'counts'=>$counts ?? null, 'updates'=>$wpdb->updates, 'options'=>$GLOBALS['options'] ?? [], 'first_queries'=>$first_queries ?? null, 'ok'=>$ok ?? null, 'deleted'=>$GLOBALS['deleted'] ?? [], 'cache_keys'=>array_keys($GLOBALS['cache'] ?? [])], JSON_THROW_ON_ERROR);
}
