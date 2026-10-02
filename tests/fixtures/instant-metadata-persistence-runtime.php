<?php
/** Isolated production persistence harness with WordPress boundary stubs. */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/kiriof-metadata-' . getmypid();
mkdir($temp . '/wp-admin/includes', 0777, true);
file_put_contents($temp . '/wp-admin/includes/upgrade.php', '<?php');
define('ABSPATH', $temp . '/');
foreach (['Contracts/TransactionPrintRepositoryInterface', 'Services/TransactionDeliveryType', 'Repositories/TransactionRepository', 'Migration/SetupMigration'] as $file) {
    require_once $root . '/inc/' . $file . '.php';
}
function get_option($key, $default = '') { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = false) { $GLOBALS['options'][$key] = $value; return true; }
function esc_sql($value) { return $value; }
function delete_transient($key) { $GLOBALS['deleted'][] = $key; }
function wp_parse_url($url, $component = -1) {
    $GLOBALS['wordpress_helpers'][] = 'wp_parse_url';
    return parse_url($url, $component);
}
function esc_url_raw($url, $protocols = null) {
    $GLOBALS['wordpress_helpers'][] = 'esc_url_raw';
    return $url;
}
function sanitize_text_field($value) {
    $GLOBALS['wordpress_helpers'][] = 'sanitize_text_field';
    $value = preg_replace('@<(script|style)[^>]*?>.*?</\1>@si', '', $value);
    return trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', strip_tags($value)));
}
function dbDelta($sql) {
    $wpdb = $GLOBALS['wpdb'];
    $wpdb->queries[] = $sql;
    $wpdb->exists = true;
    preg_match_all('/^\s*`([^`]+)`\s/m', $sql, $matches);
    $wpdb->columns = $matches[1];
}

final class InstantMetadataWpdb {
    public string $prefix = 'wp_';
    public string $last_error = '';
    public array $queries = [];
    public array $prepared = [];
    public array $updates = [];
    public array $columns = [];
    public array $row = [];
    public bool $exists = true;
    public string $table = 'wp_kiriminaja_transactions';
    public ?string $fail_field = null;
    public ?string $ghost_field = null;
    public bool $fail_describe = false;
    public bool $fail_write = false;

    public function prepare($sql, ...$args): string {
        if (preg_match_all('/%[sdf]/', $sql) !== count($args)) {
            throw new RuntimeException('Placeholder mismatch');
        }
        $this->prepared[] = [$sql, $args];
        foreach ($args as $arg) {
            $sql = preg_replace_callback('/%[sdf]/', static function ($match) use ($arg) {
                return '%s' === $match[0] ? "'" . str_replace("'", "''", (string) $arg) . "'" : (string) (float) $arg;
            }, $sql, 1);
        }
        return $sql;
    }
    public function get_var($sql) {
        $this->queries[] = $sql;
        $this->last_error = '';
        return $this->exists ? $this->table : null;
    }
    public function get_col($sql, $index) {
        $this->queries[] = $sql;
        $this->last_error = $this->fail_describe ? 'DESCRIBE failed' : '';
        return $this->fail_describe ? null : $this->columns;
    }
    public function get_row($sql) {
        $this->queries[] = $sql;
        $this->last_error = '';
        return str_starts_with($sql, 'SHOW INDEX') ? (object) ['Key_name' => 'delivery_type'] : (object) $this->row;
    }
    public function query($sql) {
        $this->queries[] = $sql;
        $this->last_error = '';
        if (preg_match('/ALTER TABLE .* ADD `([a-z_]+)`/', $sql, $matches)) {
            $field = $matches[1];
            if ($field === $this->fail_field) {
                $this->fail_field = null;
                $this->last_error = 'DDL failed';
                return false;
            }
            if ($field !== $this->ghost_field) { $this->columns[] = $field; }
        }
        if (str_starts_with($sql, 'INSERT')) {
            if ($this->fail_write) { return false; }
            [$template, $args] = end($this->prepared);
            preg_match('/INSERT INTO .*?\((.*?)\)\s*VALUES/s', $template, $match);
            preg_match_all('/`([^`]+)`/', $match[1], $columns);
            if (count($columns[1]) !== count($args)) { throw new RuntimeException('Column/value mismatch'); }
            $this->row = array_combine($columns[1], $args);
            foreach (array_merge(['vehicle'], metadataFields()) as $field) {
                // Emulate the exact NULLIF(%s, '') semantics after prepare casts to a string.
                $value = (string) $this->row[$field];
                $this->row[$field] = '' === $value ? null : $value;
            }
        }
        return 1;
    }
    public function update($table, $changes, $where) {
        $this->last_error = '';
        if ($this->fail_write) { return false; }
        $this->updates[] = $changes;
        $same = $this->row === array_replace($this->row, $changes);
        $this->row = array_replace($this->row, $changes);
        return $same ? 0 : 1;
    }
}
function metadataFields(): array {
    return ['instant_status_code', 'instant_payment_status', 'instant_payment_method', 'instant_payment_id', 'destination_latitude', 'destination_longitude', 'live_tracking_url'];
}
$input = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$GLOBALS['options'] = ['kiriof_sync_version' => 'v3'];
$GLOBALS['deleted'] = [];
$wpdb = new InstantMetadataWpdb();
$wpdb->row = array_replace(['service' => 'gosend', 'delivery_type' => 'instant', 'vehicle' => 'motor', 'status' => 'shipped', 'awb' => 'existing-awb', 'instant_status_code' => 101, 'instant_payment_status' => 'paid', 'instant_payment_id' => 'old-payment'], $input['prior'] ?? []);
if ('migration' === ($input['mode'] ?? '')) {
    $migration = new \KiriminAjaOfficial\Migration\SetupMigration();
    $migration->suffix = $input['suffix'] ?? '';
    $suffix = preg_replace('/[^a-zA-Z0-9_]/', '', $migration->suffix);
    $wpdb->table .= $suffix;
    $wpdb->exists = $input['exists'] ?? true;
    $wpdb->columns = $input['columns'] ?? [];
    $wpdb->fail_field = $input['fail_field'] ?? null;
    $wpdb->ghost_field = $input['ghost_field'] ?? null;
    $wpdb->fail_describe = $input['fail_describe'] ?? false;
    $GLOBALS['options']['kiriof_transaction_partition_v1' . $suffix] = '1';
    if ($input['complete'] ?? false) { $GLOBALS['options']['kiriof_instant_metadata_v1' . $suffix] = '1'; }
    if ($input['fresh'] ?? false) {
        unset($GLOBALS['options']['kiriof_transaction_partition_v1' . $suffix]);
        $wpdb->exists = false;
        (new ReflectionMethod($migration, 'transactionsTable'))->invoke($migration);
    }
    $method = new ReflectionMethod($migration, 'instantMetadataTable');
    $method->invoke($migration);
    $first_queries = $wpdb->queries;
    $first_options = $GLOBALS['options'];
    $method->invoke($migration);
    $second_count = count($wpdb->queries);
    $method->invoke($migration);
    $ok = null;
} else {
    $repository = new \KiriminAjaOfficial\Repositories\TransactionRepository();
    $wpdb->fail_write = $input['fail_write'] ?? false;
    if ('insert' === ($input['mode'] ?? '')) {
        $payload = array_fill_keys(['order_id', 'shipping_info', 'destination_sub_district_id', 'destination_sub_district', 'status', 'service', 'service_name', 'weight', 'width', 'height', 'length', 'shipping_cost', 'insurance_cost', 'cod_fee', 'transaction_value', 'created_at', 'wp_wc_order_stat_order_id'], '');
        $ok = $repository->createTransaction(array_replace($payload, ['service' => 'jne', 'status' => 'new'], $input['changes'] ?? []));
    } elseif ('full' === ($input['writer'] ?? '')) {
        $payload = array_replace(array_fill_keys(['destination_sub_district_id', 'destination_sub_district', 'service_name', 'shipping_cost', 'insurance_cost', 'cod_fee'], ''), ['service' => 'gosend', 'wp_wc_order_stat_order_id' => 42], $input['changes']);
        $ok = $repository->updateTransaction($payload);
    } else {
        $method = 'verified' === ($input['writer'] ?? '') ? 'updateTransactionByCallbackVerified' : 'updateTransactionByCallback';
        $ok = $repository->$method(['changes' => $input['changes'] ?? [], 'condition' => ['order_id' => 'test']]);
    }
}
unlink($temp . '/wp-admin/includes/upgrade.php');
rmdir($temp . '/wp-admin/includes'); rmdir($temp . '/wp-admin'); rmdir($temp);
echo json_encode(['ok' => $ok, 'row' => $wpdb->row, 'queries' => $wpdb->queries, 'prepared' => $wpdb->prepared, 'updates' => $wpdb->updates, 'deleted' => $GLOBALS['deleted'], 'columns' => $wpdb->columns, 'options' => $GLOBALS['options'], 'first_options' => $first_options ?? [], 'first_queries' => $first_queries ?? [], 'second_count' => $second_count ?? 0, 'wordpress_helpers' => $GLOBALS['wordpress_helpers'] ?? []], JSON_THROW_ON_ERROR);
