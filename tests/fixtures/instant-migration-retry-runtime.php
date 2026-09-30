<?php
declare(strict_types=1);

$input = json_decode($argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
$root = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/kiriof-migration-retry-' . getmypid();
mkdir($temp . '/wp-admin/includes', 0777, true);
file_put_contents($temp . '/wp-admin/includes/upgrade.php', '<?php');
define('ABSPATH', $temp . '/');
require_once $root . '/inc/Services/TransactionDeliveryType.php';
require_once $root . '/inc/Migration/SetupMigration.php';
function get_option($key, $default = '') { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = false) { $GLOBALS['options'][$key] = $value; return true; }
function esc_sql($value) { return $value; }
final class RetryWpdb {
    public string $prefix = 'wp_';
    public string $last_error = '';
    public array $queries = [];
    public array $columns;
    public bool $indexed = true;
    public bool $failed_snapshot = false;
    public bool $failed_status = false;
    public function prepare($sql, ...$args): string {
        foreach ($args as $arg) { $sql = preg_replace('/%s/', "'" . (string) $arg . "'", $sql, 1); }
        return $sql;
    }
    public function get_var($sql) { $this->queries[] = $sql; return 'wp_kiriminaja_transactions'; }
    public function get_col($sql, $index) { $this->queries[] = $sql; return $this->columns; }
    public function get_row($sql) { $this->queries[] = $sql; return $this->indexed ? (object) ['Key_name' => 'delivery_type'] : null; }
    public function query($sql) {
        $this->queries[] = $sql;
        $this->last_error = '';
        if (str_contains($sql, 'ADD shipment_location_snapshot') && ! $this->failed_snapshot) {
            $this->failed_snapshot = true; $this->last_error = 'simulated snapshot failure'; return false;
        }
        if (str_contains($sql, 'MODIFY COLUMN status') && ! $this->failed_status) {
            $this->failed_status = true; $this->last_error = 'simulated status failure'; return false;
        }
        if (preg_match('/ADD ([a-z_]+)/', $sql, $match)) { $this->columns[] = $match[1]; }
        return 1;
    }
}
$GLOBALS['options'] = ['kiriof_sync_version' => 'v3'];
$GLOBALS['wpdb'] = new RetryWpdb();
$GLOBALS['wpdb']->columns = ['status','canceled_at','discount_amount','discount_percentage','woocommerce_discount_amount','woocommerce_discount_description','is_deficit','cod_minimum','is_printed','printed_at','shipment_location_id','delivery_type','vehicle'];
if ('status' === ($input['failure'] ?? 'snapshot')) {
    $GLOBALS['wpdb']->columns[] = 'shipment_location_snapshot';
} else {
    $GLOBALS['wpdb']->failed_status = true;
}
$migration = new \KiriminAjaOfficial\Migration\SetupMigration();
$method = new \ReflectionMethod($migration, 'transactionsTable');
$method->invoke($migration);
$first = $GLOBALS['wpdb']->queries;
$first_option = $GLOBALS['options']['kiriof_transaction_partition_v1'] ?? null;
$first_error = $GLOBALS['wpdb']->last_error;
$method->invoke($migration);
$all = $GLOBALS['wpdb']->queries;
$method->invoke($migration);
echo json_encode(['first_queries' => $first, 'all_queries' => $all, 'third_query_count' => count($GLOBALS['wpdb']->queries), 'first_error' => $first_error, 'first_option' => $first_option, 'option' => $GLOBALS['options']['kiriof_transaction_partition_v1'] ?? null, 'columns' => $GLOBALS['wpdb']->columns], JSON_THROW_ON_ERROR);
unlink($temp . '/wp-admin/includes/upgrade.php');
rmdir($temp . '/wp-admin/includes'); rmdir($temp . '/wp-admin'); rmdir($temp);
