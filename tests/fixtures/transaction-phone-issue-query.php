<?php
/** Isolated production SQL fixture for recipient phone issue classification. */
declare(strict_types=1);

namespace Automattic\WooCommerce\Utilities {
	final class OrderUtil {
		public static bool $enabled = false;
		public static function custom_orders_table_usage_is_enabled(): bool { return self::$enabled; }
	}
}

namespace {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
	$scenario = $argv[2] ?? '';
	$GLOBALS['scenario'] = $scenario;
	foreach ( array( 'Contracts/TransactionListQueryInterface', 'Services/TransactionDeliveryType', 'Services/TransactionPhoneIssue', 'Services/ListDateRangeFilter', 'Services/CheckoutPhoneValidation', 'Services/TransactionProcessServices/RecipientDataResolver', 'Queries/WordPressTransactionListQuery', 'Queries/WordPressTransactionBadgeQuery' ) as $file ) {
		require_once ABSPATH . 'inc/' . $file . '.php';
	}
	function wc_get_order( $id ) {
		++$GLOBALS['phone_reads'];
		if ( 'order-unavailable' === $GLOBALS['scenario'] && 1 === (int) $id ) { return false; }
		return new class( $id ) {
			private $id;
			public function __construct( $id ) { $this->id = $id; }
			public function get_address( $type ) {
				$phones = array(
					1 => array( 'shipping' => 'bad', 'billing' => '08123456789' ),
					2 => array( 'billing' => '+62 (812) 3456-7890' ),
					3 => array(),
					4 => array(),
					5 => array( 'shipping' => 'bad' ),
					6 => array( 'shipping' => 'bad' ),
					7 => array( 'shipping' => 'bad' ),
					8 => array( 'shipping' => 'bad' ),
					9 => array( 'shipping' => 'bad' ),
					10 => array( 'shipping' => 'bad' ),
					11 => array( 'shipping' => 'bad' ),
				);
				$phone = $phones[ $this->id ][ $type ] ?? '';
				if ( 'duplicates' === $GLOBALS['scenario'] && 1 === $this->id ) { $phone = ''; }
				if ( 'phone-length' === $GLOBALS['scenario'] && 1 === $this->id && 'shipping' === $type ) { $phone = '085451111000000'; }
				if ( 'phone-length' === $GLOBALS['scenario'] && 2 === $this->id && 'billing' === $type ) { $phone = '+6285234534567'; }
				if ( $this->id >= 12 ) { $phone = 212 === $this->id ? 'bad' : '08123456789'; }
				return array( 'phone' => 1 === $this->id && ! empty( $GLOBALS['corrected'] ) ? '08123456789' : $phone );
			}
		};
	}
	final class PhoneIssueWpdb {
		public $prefix = 'wp_';
		public $postmeta = 'wp_postmeta';
		public $last_error = '';
		public $scans = 0;
		public $db;
		public function __construct() {
			$this->db = new PDO( 'sqlite::memory:' );
			$this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			foreach ( array(
				'CREATE TABLE wp_posts (ID INTEGER, post_date TEXT, post_status TEXT, post_type TEXT)',
				'CREATE TABLE wp_wc_orders (id INTEGER, date_created_gmt TEXT, status TEXT, type TEXT)',
				'CREATE TABLE wp_kiriminaja_transactions (id INTEGER, wp_wc_order_stat_order_id INTEGER, status TEXT, delivery_type TEXT, is_deficit INTEGER, awb TEXT, pickup_number TEXT, shipping_info TEXT)',
				'CREATE TABLE wp_kiriminaja_payments (pickup_number TEXT)',
				'CREATE TABLE wp_woocommerce_order_items (order_item_id INTEGER, order_id INTEGER, order_item_type TEXT)',
				'CREATE TABLE wp_woocommerce_order_itemmeta (order_item_id INTEGER, meta_key TEXT, meta_value TEXT)',
				'CREATE TABLE wp_postmeta (post_id INTEGER, meta_key TEXT, meta_value TEXT)',
			) as $sql ) { $this->db->exec( $sql ); }
			for ( $id = 1; $id <= 11; ++$id ) {
				$status = 9 === $id ? 'trash' : 'wc-processing';
				foreach ( array( 'wp_posts', 'wp_wc_orders' ) as $table ) {
					$this->db->exec( "INSERT INTO {$table} VALUES ({$id}, '2025-02-" . sprintf( '%02d', $id ) . "', '{$status}', 'shop_order')" );
				}
				$snapshot = 3 === $id ? '{"_billing_phone":"08123456789"}' : '{"_shipping_phone":"bad"}';
				$transaction_status = 6 === $id ? 'shipped' : 'new';
				$type = 7 === $id ? 'instant' : 'express';
				$deficit = 8 === $id ? 1 : 0;
				$awb = 10 === $id ? 'BOOKED' : '';
				$pickup = 5 === $id || 6 === $id ? 'PAID' : '';
				$stmt = $this->db->prepare( 'INSERT INTO wp_kiriminaja_transactions VALUES (?, ?, ?, ?, ?, ?, ?, ?)' );
				$stmt->execute( array( $id, $id, $transaction_status, $type, $deficit, $awb, $pickup, $snapshot ) );
				if ( 11 !== $id ) { $this->db->exec( "INSERT INTO wp_woocommerce_order_items VALUES ({$id}, {$id}, 'line_item')" ); }
			}
			$this->db->exec( "INSERT INTO wp_kiriminaja_payments VALUES ('PAID')" );
			if ( 'duplicates' === $GLOBALS['scenario'] ) {
				$this->db->exec( "UPDATE wp_kiriminaja_transactions SET awb = ' ', pickup_number = ' ' WHERE id = 1" );
				$this->db->exec( "INSERT INTO wp_kiriminaja_transactions VALUES (12, 1, 'new', 'express', 0, '', '', '{\"_billing_phone\":\"08123456789\"}')" );
			}
			if ( 'large' === $GLOBALS['scenario'] ) {
				for ( $id = 12; $id <= 212; ++$id ) {
					$this->db->exec( "INSERT INTO wp_posts VALUES ({$id}, '2025-03-01', 'wc-processing', 'shop_order')" );
					$this->db->exec( "INSERT INTO wp_wc_orders VALUES ({$id}, '2025-03-01', 'wc-processing', 'shop_order')" );
					$this->db->exec( "INSERT INTO wp_woocommerce_order_items VALUES ({$id}, {$id}, 'line_item')" );
					$this->db->exec( "INSERT INTO wp_kiriminaja_transactions VALUES ({$id}, {$id}, 'new', 'express', 0, '', '', '{}')" );
				}
			}
		}
		public function prepare( $sql, ...$args ) {
			$i = 0;
			$sql = preg_replace_callback( '/%[sd]/', function ( $match ) use ( &$i, $args ) {
				$value = $args[ $i++ ];
				return '%d' === $match[0] ? (string) (int) $value : $this->db->quote( (string) $value );
			}, $sql );
			if ( $i !== count( $args ) ) { throw new RuntimeException( 'Placeholder mismatch' ); }
			return $sql;
		}
		public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
		public function get_var( $sql ) { return $this->db->query( $sql )->fetchColumn(); }
		public function get_row( $sql ) { return $this->db->query( $sql )->fetch( PDO::FETCH_OBJ ); }
		public function get_results( $sql ) {
			if ( str_contains( $sql, 'ORDER BY t.id ASC LIMIT 200' ) ) { ++$this->scans; }
			return $this->db->query( $sql )->fetchAll( PDO::FETCH_OBJ );
		}
	}
	\Automattic\WooCommerce\Utilities\OrderUtil::$enabled = 'hpos' === ( $argv[1] ?? '' );
	$GLOBALS['phone_reads'] = 0;
	$wpdb = new PhoneIssueWpdb();
	$query = new \KiriminAjaOfficial\Queries\WordPressTransactionListQuery( $wpdb );
	$filters = array( 'key' => '', 'month' => '', 'status' => 'order-issue', 'cod' => '', 'courier' => '', 'print_status' => '' );
	$page = $query->getPage( $filters, 99, 2 );
	$counts = $query->getStatusCounts();
	$badges = $query->getDeliveryCounts();
	$filters['status'] = 'wc-processing';
	$regular = $query->getPage( $filters, 1, 100 );
	$filters['status'] = 'all';
	$regular_all = $query->getPage( $filters, 1, 100 );
	$filters['status'] = 'processed';
	$processed = $query->getPage( $filters, 1, 100 );
	$reads = $GLOBALS['phone_reads'];
	$scans = $wpdb->scans;
	$GLOBALS['corrected'] = true;
	$filters['status'] = 'order-issue';
	$corrected_query = new \KiriminAjaOfficial\Queries\WordPressTransactionListQuery( $wpdb );
	$corrected = $corrected_query->getPage( $filters, 1, 100 );
	$corrected_badges = $corrected_query->getDeliveryCounts();
	$filters['status'] = 'wc-processing';
	$corrected_regular = $corrected_query->getPage( $filters, 1, 100 );
	$runtime = array( 'ini_file' => php_ini_loaded_file(), 'pdo' => extension_loaded( 'PDO' ), 'pdo_sqlite' => extension_loaded( 'pdo_sqlite' ) );
	echo json_encode( compact( 'page', 'counts', 'badges', 'regular', 'regular_all', 'processed', 'corrected', 'corrected_badges', 'corrected_regular', 'reads', 'scans', 'runtime' ), JSON_THROW_ON_ERROR );
}
