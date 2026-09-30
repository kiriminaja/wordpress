<?php
/** Subprocess runtime harness: captures generated SQL without executing a database. */

declare(strict_types=1);

namespace Automattic\WooCommerce\Utilities {
    final class OrderUtil {
        public static bool $enabled = false;
        public static function custom_orders_table_usage_is_enabled(): bool { return self::$enabled; }
    }
}

namespace {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
    require_once dirname(__DIR__, 2) . '/inc/Services/TransactionDeliveryType.php';
    require_once dirname(__DIR__, 2) . '/inc/Contracts/TransactionListQueryInterface.php';
    require_once dirname(__DIR__, 2) . '/inc/Queries/WordPressTransactionListQuery.php';

    function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
    function wp_unslash($value) { return $value; }

    final class TransactionMultiFilterRuntimeWpdb {
        public string $prefix = 'wp_';
        public string $postmeta = 'wp_postmeta';
        public string $last_error = '';
        public array $queries = [];
        public function esc_like($value): string { return addcslashes((string) $value, '_%\\'); }
        public function prepare($sql, ...$values): string {
            foreach ($values as $value) {
                $type = is_int($value) ? '%d' : '%s';
                $pos = strpos($sql, $type);
                if (false === $pos) { $this->last_error = 'unresolved prepare argument'; continue; }
                $replacement = '%d' === $type ? (string) $value : "'" . str_replace("'", "''", (string) $value) . "'";
                $sql = substr_replace($sql, $replacement, $pos, 2);
            }
            if (preg_match('/%[sd]/', $sql)) { $this->last_error = 'unresolved placeholder'; }
            return $sql;
        }
        public function get_var($sql) { $this->queries[] = $sql; return '55'; }
        public function get_results($sql): array { $this->queries[] = $sql; return [(object) ['wc_order_id' => 10]]; }
    }

    $payload = json_decode($argv[1] ?? '{}', true) ?: [];
    if (($payload['mode'] ?? 'query') === 'renderer') {
        require_once dirname(__DIR__, 2) . '/inc/Services/TransactionListRenderService.php';
        $_GET = $payload['get'] ?? [];
        $ref = new \ReflectionClass(\KiriminAjaOfficial\Services\TransactionListRenderService::class);
        $service = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('getFilters');
        echo json_encode($method->invoke($service), JSON_THROW_ON_ERROR);
        exit;
    }

    \Automattic\WooCommerce\Utilities\OrderUtil::$enabled = !empty($payload['hpos']);
    $wpdb = new TransactionMultiFilterRuntimeWpdb();
    $query = new \KiriminAjaOfficial\Queries\WordPressTransactionListQuery($wpdb);
    $filters = $payload['filters'] ?? ['key'=>'', 'month'=>'', 'status'=>'all', 'cod'=>'', 'courier'=>'', 'print_status'=>''];
    $page = $query->getPage($filters, (int) ($payload['page'] ?? 1), (int) ($payload['per_page'] ?? 25));
    echo json_encode(['page' => $page, 'queries' => $wpdb->queries, 'last_error' => $wpdb->last_error], JSON_THROW_ON_ERROR);
}
