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
    require_once ABSPATH . 'inc/Queries/WordPressTransactionListQuery.php';

    function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }

    final class TransactionMultiFilterDatabaseWpdb {
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
                'CREATE TABLE wp_kiriminaja_transactions (id INTEGER PRIMARY KEY, wp_wc_order_stat_order_id INTEGER, status TEXT, pickup_number TEXT, is_deficit INTEGER, cod_fee REAL, service TEXT, is_printed INTEGER, awb TEXT, order_id TEXT, created_at TEXT)',
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
        public function get_var($sql) { return $this->execute($sql)->fetchColumn(); }
        public function get_results($sql): array { return $this->execute($sql)->fetchAll(PDO::FETCH_OBJ); }

        private function insert(string $table, array $values): void {
            $stmt = $this->db->prepare('INSERT INTO ' . $table . ' VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')');
            $stmt->execute($values);
        }

        private function seed(): void {
            // Each row's date is unique so pagination order is deterministic.
            $rows = [
                1 => ['wc-processing', 'new'],
                2 => ['wc-on-hold', 'new'],
                3 => ['wc-pending', 'new'],
                4 => ['wc-completed', 'shipped', 'paid' => true],
                5 => ['wc-cancelled', 'canceled'], // Cancelled needs no payment.
                6 => ['wc-cancelled', 'shipped', 'paid' => true], // Overlap of both scopes.
                7 => ['wc-processing', 'canceled', 'paid' => true],
                8 => ['wc-processing', 'new', 'paid' => true, 'deficit' => 1],
                9 => ['wc-completed', 'shipped', 'paid' => true, 'virtual' => 'yes'],
                10 => ['trash', 'shipped', 'paid' => true],
                11 => ['auto-draft', 'shipped', 'paid' => true],
                12 => ['wc-processing', 'shipped'], // Unpaid, not a new order.
                13 => ['wc-completed', 'shipped', 'paid' => true, 'variation' => 'yes'],
                14 => ['wc-completed', 'shipped', 'paid' => true, 'virtual' => 'yes', 'variation' => 'no'],
                15 => ['wc-completed', 'shipped', 'paid' => true, 'no_items' => true],
                16 => ['wc-completed', 'shipped', 'paid' => true, 'courier' => 'sicepat'],
                17 => ['wc-completed', 'shipped', 'paid' => true, 'month' => '2025-03'],
                18 => ['wc-completed', 'shipped', 'paid' => true, 'printed' => 1],
                19 => ['wc-completed', 'shipped', 'paid' => true, 'cod' => 0],
                20 => ['wc-completed', 'shipped', 'paid' => true, 'awb' => 'OTHER', 'order_id' => 'OTHER'],
                21 => ['wc-completed', 'shipped', 'paid' => true, 'awb' => "QUOTE'100%_literal", 'order_id' => 'OTHER'],
            ];
            foreach ($rows as $id => $row) {
                $date = ($row['month'] ?? '2025-02') . '-' . sprintf('%02d', $id) . ' 12:00:00';
                $pickup = 'PICKUP-' . $id;
                $this->insert('wp_posts', [$id, $date, $row[0], 'shop_order']);
                $this->insert('wp_wc_orders', [$id, $date, $row[0], 'shop_order']);
                $this->insert('wp_kiriminaja_transactions', [$id, $id, $row[1], $pickup, $row['deficit'] ?? 0, $row['cod'] ?? 100, $row['courier'] ?? ($id % 2 ? 'jne' : 'pos'), $row['printed'] ?? 0, $row['awb'] ?? 'KA-10-' . $id, $row['order_id'] ?? 'KA-10-' . $id, $date]);
                if (!empty($row['paid'])) {
                    $this->insert('wp_kiriminaja_payments', [$id * 10, $pickup]);
                    if (4 === $id || 6 === $id) { // Real duplicate joined rows.
                        $this->insert('wp_kiriminaja_payments', [$id * 10 + 1, $pickup]);
                    }
                }
                if (!empty($row['no_items'])) { continue; }
                $product = 1000 + $id;
                $this->insert('wp_woocommerce_order_items', [$id, $id, 'line_item']);
                $this->insert('wp_woocommerce_order_itemmeta', [$id, '_product_id', $product]);
                $this->insert('wp_postmeta', [$product, '_virtual', $row['virtual'] ?? 'no']);
                if (isset($row['variation'])) {
                    $this->insert('wp_woocommerce_order_itemmeta', [$id, '_variation_id', 2000 + $id]);
                    $this->insert('wp_postmeta', [2000 + $id, '_virtual', $row['variation']]);
                }
            }
        }
    }

    $payload = json_decode($argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
    \Automattic\WooCommerce\Utilities\OrderUtil::$enabled = !empty($payload['hpos']);
    $wpdb = new TransactionMultiFilterDatabaseWpdb();
    $query = new \KiriminAjaOfficial\Queries\WordPressTransactionListQuery($wpdb);
    $defaults = ['key' => '', 'month' => '', 'status' => 'all', 'cod' => '', 'courier' => '', 'print_status' => ''];
    $responses = [];
    foreach ($payload['requests'] ?? [$payload] as $request) {
        $wpdb->queries = [];
        $page = $query->getPage(array_replace($defaults, $request['filters'] ?? []), (int) ($request['page'] ?? 1), (int) ($request['per_page'] ?? 100));
        $responses[] = ['page' => $page, 'queries' => $wpdb->queries, 'last_error' => $wpdb->last_error];
    }
    echo json_encode($responses, JSON_THROW_ON_ERROR);
}
