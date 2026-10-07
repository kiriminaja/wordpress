<?php
/** Execute the production read model against real in-memory tables, isolated from WP test stubs. */
declare(strict_types=1);

namespace Automattic\WooCommerce\Utilities {
    final class OrderUtil {
        public static bool $enabled = false;
        public static function custom_orders_table_usage_is_enabled(): bool { return self::$enabled; }
    }
}

namespace {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
    require_once ABSPATH . 'inc/Contracts/TransactionListQueryInterface.php';
    require_once ABSPATH . 'inc/Services/TransactionDeliveryType.php';
    require_once ABSPATH . 'inc/Services/ListDateRangeFilter.php';
    require_once ABSPATH . 'inc/Queries/WordPressTransactionBadgeQuery.php';
    require_once ABSPATH . 'inc/Queries/WordPressTransactionListQuery.php';
    require_once ABSPATH . 'inc/Contracts/TransactionPrintRepositoryInterface.php';
    require_once ABSPATH . 'inc/Repositories/TransactionRepository.php';

    function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }

    final class TransactionBadgeCountsRuntimeWpdb {
        public string $prefix = 'wp_';
        public string $postmeta = 'wp_postmeta';
        public string $last_error = '';
        public array $queries = [];
        private PDO $db;
        // Like wpdb's placeholder escape, protect percent signs in already-prepared
        // fragments from an outer prepare(). Remove only immediately before execution.
        private const PERCENT = '{transaction-database-percent}';

        public function __construct() {
            $this->db = class_exists('Pdo\\Sqlite') ? new \Pdo\Sqlite('sqlite::memory:') : new PDO('sqlite::memory:');
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // SQLite LIKE lacks MySQL's default backslash escape. Register that
            // compatibility behavior without rewriting any production SQL clauses.
            $like = static function ($pattern, $subject): int {
                if (null === $subject || null === $pattern) { return 0; }
                $regex = '';
                $pattern = (string) $pattern;
                for ($i = 0, $length = strlen($pattern); $i < $length; ++$i) {
                    $char = $pattern[$i];
                    if ('\\' === $char && $i + 1 < $length) {
                        $regex .= preg_quote($pattern[++$i], '~');
                    } elseif ('%' === $char) { $regex .= '.*';
                    } elseif ('_' === $char) { $regex .= '.';
                    } else { $regex .= preg_quote($char, '~'); }
                }
                return (int) preg_match('~\\A' . $regex . '\\z~isu', (string) $subject);
            };
            if (method_exists($this->db, 'createFunction')) {
                $this->db->createFunction('like', $like, 2);
            } else {
                $this->db->sqliteCreateFunction('like', $like, 2);
            }
            foreach ([
                'CREATE TABLE wp_posts (ID INTEGER PRIMARY KEY, post_date TEXT, post_status TEXT, post_type TEXT)',
                'CREATE TABLE wp_wc_orders (id INTEGER PRIMARY KEY, date_created_gmt TEXT, status TEXT, type TEXT)',
                'CREATE TABLE wp_kiriminaja_transactions (id INTEGER PRIMARY KEY, wp_wc_order_stat_order_id INTEGER, status TEXT, pickup_number TEXT, is_deficit INTEGER, cod_fee REAL, service TEXT, delivery_type TEXT, is_printed INTEGER, awb TEXT, order_id TEXT, created_at TEXT, instant_payment_id TEXT, instant_status_code INTEGER)',
                'CREATE TABLE wp_kiriminaja_payments (id INTEGER PRIMARY KEY, pickup_number TEXT)',
                'CREATE TABLE wp_woocommerce_order_items (order_item_id INTEGER PRIMARY KEY, order_id INTEGER, order_item_type TEXT)',
                'CREATE TABLE wp_woocommerce_order_itemmeta (order_item_id INTEGER, meta_key TEXT, meta_value TEXT)',
                'CREATE TABLE wp_postmeta (post_id INTEGER, meta_key TEXT, meta_value TEXT)',
            ] as $sql) { $this->db->exec($sql); }
            $this->seed();
        }

        public function esc_like($value): string { return addcslashes((string) $value, '_%\\'); }

        public function prepare($sql, ...$values): string {
            if (1 === count($values) && is_array($values[0])) { $values = $values[0]; }
            $index = 0;
            $prepared = preg_replace_callback('/%%|%[sd]/', function (array $match) use ($values, &$index): string {
                if ('%%' === $match[0]) { return self::PERCENT; }
                if (!array_key_exists($index, $values)) { throw new RuntimeException('Missing prepare argument'); }
                $value = $values[$index++];
                $literal = '%d' === $match[0] ? (string) (int) $value : $this->db->quote((string) $value);
                return str_replace('%', self::PERCENT, $literal);
            }, $sql);
            if ($index !== count($values)) { throw new RuntimeException('Unused prepare argument'); }
            return $prepared;
        }

        private function execute(string $sql): PDOStatement {
            $sql = str_replace(self::PERCENT, '%', $sql);
            $this->queries[] = $sql;
            $this->last_error = '';
            try { return $this->db->query($sql); }
            catch (PDOException $error) { $this->last_error = $error->getMessage(); throw $error; }
        }
        public function get_row($sql) { return $this->execute($sql)->fetch(PDO::FETCH_OBJ); }
        public function get_var($sql) { return $this->execute($sql)->fetchColumn(); }
        public function get_results($sql): array { return $this->execute($sql)->fetchAll(PDO::FETCH_OBJ); }

        private function insert(string $table, array $values): void {
            $stmt = $this->db->prepare('INSERT INTO ' . $table . ' VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')');
            $stmt->execute($values);
        }

        private function seed(): void {
            // Badges require processing + new + at least one physical line item.
            $rows = [
                1 => [], 2 => ['delivery' => 'instant'], 3 => ['deficit' => 1],
                4 => ['delivery' => null], 5 => ['delivery' => 'legacy'],
                6 => ['virtual' => 'yes'], 7 => ['variation' => 'yes'],
                8 => ['virtual' => 'yes', 'variation' => 'no'],
                9 => ['virtual' => 'yes', 'mixed' => true], 10 => ['no_items' => true],
                11 => ['shipment' => 'shipped'], 12 => ['shipment' => 'finished'],
                13 => ['shipment' => 'rejected'], 14 => ['shipment' => 'canceled'],
                15 => ['wc' => 'wc-on-hold'], 16 => ['wc' => 'wc-pending'],
                17 => ['wc' => 'wc-completed'], 18 => ['wc' => 'wc-cancelled'],
                19 => ['wc' => 'trash'], 20 => ['wc' => 'auto-draft'],
                21 => ['duplicates' => [['instant', 0, 'new']]],
                22 => ['duplicates' => [['instant', 0, 'new'], ['express', 1, 'new']]],
                23 => ['duplicates' => [['express', 0, 'new'], ['express', 0, 'new']]],
                24 => ['delivery' => 'instant', 'deficit' => 1],
                25 => ['type' => 'shop_order_refund'],
                26 => ['shipment' => 'shipped', 'deficit' => 1],
                // An old issue row must not outrank a pending instant row.
                27 => ['delivery' => 'instant', 'duplicates' => [['express', 1, 'shipped']]],
                28 => ['delivery' => null, 'deficit' => 1],
            ];
            $transaction_id = 0;
            foreach ($rows as $id => $row) {
                global $payload;
                if (isset($payload['only_ids']) && !in_array($id, $payload['only_ids'], true)) { continue; }
                $date = '2025-02-' . sprintf('%02d', $id) . ' 12:00:00';
                $wc = $row['wc'] ?? 'wc-processing';
                $type = $row['type'] ?? 'shop_order';
                $this->insert('wp_posts', [$id, $date, $wc, $type]);
                $this->insert('wp_wc_orders', [$id, $date, $wc, $type]);
                $delivery = array_key_exists('delivery', $row) ? $row['delivery'] : 'express';
                $transactions = array_merge([[$delivery, $row['deficit'] ?? 0, $row['shipment'] ?? 'new']], $row['duplicates'] ?? []);
                foreach ($transactions as [$delivery, $deficit, $shipment]) {
                    $this->insert('wp_kiriminaja_transactions', [++$transaction_id, $id, $shipment, 'PICKUP-' . $id, $deficit, 100, 'instant' === $delivery ? 'gosend' : 'jne', $delivery, 0, 'KA-' . $id, 'KA-' . $id, $date, 'instant' === $delivery ? 'PAY-' . $id : null, 'instant' === $delivery ? 100 : null]);
                }
                $this->insert('wp_kiriminaja_payments', [$id, 'PICKUP-' . $id]);
                if (!empty($row['no_items'])) { continue; }
                $item = $id * 10;
                $product = 1000 + $id;
                $this->insert('wp_woocommerce_order_items', [$item, $id, 'line_item']);
                $this->insert('wp_woocommerce_order_itemmeta', [$item, '_product_id', $product]);
                $this->insert('wp_postmeta', [$product, '_virtual', $row['virtual'] ?? 'no']);
                if (isset($row['variation'])) {
                    $this->insert('wp_woocommerce_order_itemmeta', [$item, '_variation_id', 2000 + $id]);
                    $this->insert('wp_postmeta', [2000 + $id, '_virtual', $row['variation']]);
                }
                if (!empty($row['mixed'])) {
                    $this->insert('wp_woocommerce_order_items', [$item + 1, $id, 'line_item']);
                    $this->insert('wp_woocommerce_order_itemmeta', [$item + 1, '_product_id', 3000 + $id]);
                    $this->insert('wp_postmeta', [3000 + $id, '_virtual', 'no']);
                }
            }
        }
    }

    $payload = json_decode($argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
    \Automattic\WooCommerce\Utilities\OrderUtil::$enabled = !empty($payload['hpos']);
    $wpdb = new TransactionBadgeCountsRuntimeWpdb();
    $query = new \KiriminAjaOfficial\Queries\WordPressTransactionListQuery($wpdb);
    $badge = new \KiriminAjaOfficial\Queries\WordPressTransactionBadgeQuery($wpdb);
    $repository = new \KiriminAjaOfficial\Repositories\TransactionRepository();
    $defaults = ['key' => '', 'month' => '', 'status' => 'all', 'cod' => '', 'courier' => '', 'print_status' => '', 'delivery_type' => 'express'];
    $responses = [];
    foreach ($payload['requests'] ?? [[]] as $request) {
        $wpdb->queries = [];
        $page = $query->getPage(array_replace($defaults, $request['filters'] ?? []), 1, 100);
        $responses[] = [
            'page' => $page,
            'status_counts' => $query->getStatusCounts(),
            'counts' => $badge->getCounts(),
            'tabs' => $query->getDeliveryCounts(),
            'sidebar' => $repository->getCountTransactionProcessNew(),
            'queries' => $wpdb->queries,
            'last_error' => $wpdb->last_error,
        ];
    }
    echo json_encode($responses, JSON_THROW_ON_ERROR);
}
