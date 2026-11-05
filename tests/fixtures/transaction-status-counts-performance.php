<?php
/** SQLite execution fixture for the production transaction status counts. */
declare( strict_types=1 );

namespace Automattic\WooCommerce\Utilities {
    if ( 'cli' !== PHP_SAPI ) {
        exit;
    }
    final class OrderUtil {
        public static bool $enabled = false;
        public static function custom_orders_table_usage_is_enabled(): bool { return self::$enabled; }
    }
}

namespace {
    if ( ! defined( 'ABSPATH' ) ) {
        define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
    }
    require_once ABSPATH . 'inc/Contracts/TransactionListQueryInterface.php';
    require_once ABSPATH . 'inc/Services/TransactionDeliveryType.php';

    final class TransactionStatusCountsDatabase {
        public string $prefix = 'wp_';
        public string $postmeta = 'wp_postmeta';
        public string $last_error = '';
        public array $queries = array();
        private PDO $db;

        public function __construct( int $orders, bool $duplicates ) {
            $this->db = class_exists( 'Pdo\\Sqlite' ) ? new \Pdo\Sqlite( 'sqlite::memory:' ) : new PDO( 'sqlite::memory:' );
            $this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
            foreach ( array(
                'CREATE TABLE wp_posts (ID INTEGER, post_status TEXT, post_type TEXT)',
                'CREATE TABLE wp_wc_orders (id INTEGER, status TEXT, type TEXT)',
                'CREATE TABLE wp_kiriminaja_transactions (id INTEGER PRIMARY KEY, wp_wc_order_stat_order_id INTEGER, status TEXT, pickup_number TEXT, is_deficit INTEGER, delivery_type TEXT, awb TEXT, instant_payment_id TEXT, instant_status_code INTEGER)',
                'CREATE TABLE wp_kiriminaja_payments (pickup_number TEXT)',
                'CREATE TABLE wp_woocommerce_order_items (order_item_id INTEGER, order_id INTEGER, order_item_type TEXT)',
                'CREATE TABLE wp_woocommerce_order_itemmeta (order_item_id INTEGER, meta_key TEXT, meta_value TEXT)',
                'CREATE TABLE wp_postmeta (post_id INTEGER, meta_key TEXT, meta_value TEXT)',
                'CREATE INDEX items_order ON wp_woocommerce_order_items (order_id)',
                'CREATE INDEX itemmeta_item ON wp_woocommerce_order_itemmeta (order_item_id, meta_key)',
                'CREATE INDEX product_meta ON wp_postmeta (post_id, meta_key)',
                'CREATE INDEX payment_pickup ON wp_kiriminaja_payments (pickup_number)',
                'CREATE INDEX transactions_order ON wp_kiriminaja_transactions (wp_wc_order_stat_order_id)',
            ) as $sql ) {
                $this->db->exec( $sql );
            }
            $this->db->beginTransaction();
            for ( $id = 1; $id <= $orders; ++$id ) {
                $case = ( $id - 1 ) % 40;
                $status = array( 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-cancelled', 'wc-completed' )[ $case % 5 ];
                if ( 5 === $case || 25 === $case ) { $status = 'trash'; }
                if ( 6 === $case || 26 === $case ) { $status = 'auto-draft'; }
                $type = 7 === $case % 8 ? 'shop_order_refund' : 'shop_order';
                foreach ( array( 'wp_posts', 'wp_wc_orders' ) as $table ) {
                    $this->insert( $table, array( $id, $status, $type ) );
                    if ( $duplicates ) { $this->insert( $table, array( $id, $status, $type ) ); }
                }
                $delivery = $case < 20 ? 'express' : 'instant';
                $lifecycle = array( 'new', 'request_pickup', 'shipped', 'finished', 'return', 'returned', 'rejected', 'canceled', null, 'new' )[ $case % 10 ];
                if ( 1 === $case || 2 === $case || 21 === $case || 22 === $case ) { $lifecycle = 'new'; }
                $pickup = 0 === $case % 10 || 9 === $case % 10 ? ( 0 === $case % 2 ? null : '  ' ) : 'PICKUP-' . $id;
                $payment_id = array( null, '', 'PAY-' . $id, '0' )[ $case % 4 ];
                $code = 0 === $case % 3 ? null : ( 0 === $case % 2 ? 0 : 100 );
                $transaction = array( $id, $id, $lifecycle, $pickup, 0 === $case % 7 ? 1 : 0, $delivery, null, $payment_id, $code );
                $this->insert( 'wp_kiriminaja_transactions', $transaction );
                if ( $duplicates ) {
                    $transaction[0] += $orders;
                    $this->insert( 'wp_kiriminaja_transactions', $transaction );
                }
                // Include empty and NULL payment keys as well as duplicate matches.
                if ( 0 !== $case % 3 ) {
                    $this->insert( 'wp_kiriminaja_payments', array( $pickup ) );
                    if ( $duplicates ) { $this->insert( 'wp_kiriminaja_payments', array( $pickup ) ); }
                }
                if ( 15 === $case ) { continue; }
                $this->insert( 'wp_woocommerce_order_items', array( $id, $id, 16 === $case ? 'shipping' : 'line_item' ) );
                $this->insert( 'wp_woocommerce_order_itemmeta', array( $id, '_product_id', $id + 10000 ) );
                $this->insert( 'wp_postmeta', array( $id + 10000, '_virtual', 0 === $case % 6 ? 'yes' : 'no' ) );
                if ( 0 === $case % 5 ) {
                    $this->insert( 'wp_woocommerce_order_itemmeta', array( $id, '_variation_id', $id + 20000 ) );
                    $this->insert( 'wp_postmeta', array( $id + 20000, '_virtual', 0 === $case % 10 ? 'no' : 'yes' ) );
                }
            }
            $this->db->commit();
        }

        private function insert( string $table, array $values ): void {
            $statement = $this->db->prepare( 'INSERT INTO ' . $table . ' VALUES (' . implode( ',', array_fill( 0, count( $values ), '?' ) ) . ')' );
            $statement->execute( $values );
        }

        public function prepare( $sql, ...$values ): string {
            if ( 1 === count( $values ) && is_array( $values[0] ) ) { $values = $values[0]; }
            $index = 0;
            $prepared = preg_replace_callback( '/%[sd]/', function ( array $match ) use ( $values, &$index ): string {
                if ( ! array_key_exists( $index, $values ) ) { throw new RuntimeException( 'Missing prepare argument' ); }
                $value = $values[ $index++ ];
                return '%d' === $match[0] ? (string) (int) $value : $this->db->quote( (string) $value );
            }, $sql );
            if ( $index !== count( $values ) ) { throw new RuntimeException( 'Unused prepare argument' ); }
            return $prepared;
        }

        private function execute( string $sql ): PDOStatement {
            $this->queries[] = $sql;
            return $this->db->query( $sql );
        }
        public function get_var( $sql ) { return $this->execute( $sql )->fetchColumn(); }
        public function get_row( $sql ) { return $this->execute( $sql )->fetchObject(); }
    }

    function transaction_status_counts_run( array $options ): array {
        $source = $options['source'] ?? ABSPATH . 'inc/Queries/WordPressTransactionListQuery.php';
        require_once $source;
        $orders = (int) ( $options['orders'] ?? 2000 );
        $wpdb = new TransactionStatusCountsDatabase( $orders, $options['duplicates'] ?? true );
        $results = array();
        foreach ( array( false, true ) as $hpos ) {
            \Automattic\WooCommerce\Utilities\OrderUtil::$enabled = $hpos;
            foreach ( array( 'express', 'instant' ) as $delivery ) {
                $query = new \KiriminAjaOfficial\Queries\WordPressTransactionListQuery( $wpdb );
                $scope = new ReflectionProperty( $query, 'delivery_type' );
                $scope->setValue( $query, $delivery );
                $phone_ids = array();
                if ( ! ( $options['empty_phone_cache'] ?? false ) ) {
                    for ( $id = 1; $id <= $orders; ++$id ) {
                        if ( 0 === ( $id - 1 ) % 10 || 9 === ( $id - 1 ) % 10 ) { $phone_ids[] = $id; }
                    }
                }
                // Warm the production request cache; no order hydration is measured.
                $cache = new ReflectionProperty( $query, 'phone_issue_ids' );
                $cache->setValue( $query, $phone_ids );
                $warmups = (int) ( $options['warmups'] ?? 3 );
                $samples = (int) ( $options['samples'] ?? 15 );
                $times = array();
                $budgets = array();
                $counts = array();
                for ( $i = 0; $i < $warmups + $samples; ++$i ) {
                    $wpdb->queries = array();
                    $start = hrtime( true );
                    $current = $query->getStatusCounts();
                    $elapsed = ( hrtime( true ) - $start ) / 1000000;
                    if ( $counts && $counts !== $current ) { throw new RuntimeException( 'Unstable counts' ); }
                    $counts = $current;
                    if ( $i >= $warmups ) { $times[] = $elapsed; $budgets[] = count( $wpdb->queries ); }
                }
                $sorted = $times;
                sort( $sorted );
                $results[ ( $hpos ? 'hpos' : 'posts' ) . '_' . $delivery ] = array(
                    'counts' => $counts,
                    'counts_sha256' => hash( 'sha256', json_encode( $counts, JSON_THROW_ON_ERROR ) ),
                    'query_counts' => $budgets,
                    'samples_ms' => $times,
                    'median_ms' => $sorted[ intdiv( count( $sorted ), 2 ) ],
                    'p95_ms' => $sorted[ (int) ceil( count( $sorted ) * 0.95 ) - 1 ],
                    'queries' => $wpdb->queries,
                );
            }
        }
        return array( 'engine' => 'SQLite in-memory (not MySQL)', 'php' => PHP_VERSION, 'sqlite' => $wpdb->get_var( 'SELECT sqlite_version()' ), 'source_sha256' => hash_file( 'sha256', $source ), 'orders' => $orders, 'warmups' => $warmups, 'samples' => $samples, 'results' => $results );
    }

    if ( realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) === __FILE__ ) {
        echo json_encode( transaction_status_counts_run( json_decode( $argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR ) ), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR ) . PHP_EOL;
    }
}
