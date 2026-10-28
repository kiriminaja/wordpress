<?php

require dirname( __DIR__ ) . '/bootstrap.php';

use KiriminAjaOfficial\Queries\WordPressTransactionListQuery;
use KiriminAjaOfficial\Repositories\TransactionRepository;
use KiriminAjaOfficial\Services\TransactionPhoneIssueCache;

$GLOBALS['clock'] = 1000;
$GLOBALS['cache'] = array();
$GLOBALS['hooks'] = array();
$GLOBALS['reads'] = 0;
$GLOBALS['phones'] = array( 1 => 'bad', 205 => '' );
function get_transient( $key ) {
	$entry = $GLOBALS['cache'][ $key ] ?? null;
	return $entry && $entry[1] > $GLOBALS['clock'] ? $entry[0] : false;
}
function set_transient( $key, $value, $ttl ) { $GLOBALS['cache'][ $key ] = array( $value, $GLOBALS['clock'] + $ttl ); return true; }
function delete_transient( $key ) { unset( $GLOBALS['cache'][ $key ] ); }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = array( $callback, $args ); }
function do_action( $hook, ...$args ) {
	foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $entry ) { call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) ); }
}
function wc_get_order( $id ) {
	++$GLOBALS['reads'];
	return new class( $id ) {
		private $id;
		public function __construct( $id ) { $this->id = $id; }
		public function get_address( $type ) { return array( 'phone' => $GLOBALS['phones'][ $this->id ] ?? '08123456789' ); }
	};
}
final class PhoneCacheWpdb {
	public $prefix = 'wp_';
	public $postmeta = 'wp_postmeta';
	public $last_error = '';
	public $rows = array();
	public $scans = 0;
	public $queries = array();
	public $race = false;
	public $fail = false;
	public function __construct() {
		for ( $id = 1; $id <= 205; ++$id ) {
			$this->rows[ $id ] = (object) array( 'id' => $id, 'wp_wc_order_stat_order_id' => $id, 'shipping_info' => '{"_billing_phone":"bad"}', 'status' => 'new', 'awb' => '' );
		}
	}
	public function prepare( $sql, ...$args ) {
		foreach ( $args as $arg ) { $sql = preg_replace( '/%[sdf]/', is_numeric( $arg ) ? (string) $arg : "'" . addslashes( (string) $arg ) . "'", $sql, 1 ); }
		return $sql;
	}
	public function get_results( $sql ) {
		++$this->scans;
		$this->queries[] = $sql;
		if ( $this->race ) { $this->race = false; TransactionPhoneIssueCache::invalidate(); }
		if ( $this->fail ) { $this->last_error = 'Read failed'; return null; }
		preg_match( '/t.id > (\d+)/', $sql, $match );
		return array_slice( array_values( array_filter( $this->rows, static function ( $row ) use ( $match ) { return $row->id > (int) $match[1] && 'new' === $row->status && '' === $row->awb; } ) ), 0, 200 );
	}
	public function update( $table, $changes, $condition ) {
		$id = (int) $condition['wp_wc_order_stat_order_id'];
		foreach ( $changes as $key => $value ) { $this->rows[ $id ]->$key = $value; }
		return 1;
	}
	public function query( $sql ) {
		$this->rows[206] = (object) array( 'id' => 206, 'wp_wc_order_stat_order_id' => 206, 'shipping_info' => '{}', 'status' => 'new', 'awb' => '' );
		$GLOBALS['phones'][206] = 'bad';
		return 1;
	}
}
$wpdb = new PhoneCacheWpdb();
$cache_service = new TransactionPhoneIssueCache();
$cache_service->register();
$query = new WordPressTransactionListQuery( $wpdb );
$cold = $query->getPhoneIssueIds();
$cold_scans = $wpdb->scans;
$cold_reads = $GLOBALS['reads'];
(new WordPressTransactionListQuery( $wpdb ))->getPhoneIssueIds();
$query->getPhoneIssueIds();
$warm_scans = $wpdb->scans;
$warm_reads = $GLOBALS['reads'];
$GLOBALS['phones'][1] = '08123456789';
do_action( 'woocommerce_after_order_object_save', 1 );
$corrected = $query->getPhoneIssueIds();
$repository = new TransactionRepository();
$repository->updateTransactionByCallback( array( 'changes' => array( 'shipping_info' => '{"_billing_phone":"08123456789"}' ), 'condition' => array( 'wp_wc_order_stat_order_id' => 205 ) ) );
$snapshot_corrected = $query->getPhoneIssueIds();
$GLOBALS['phones'][1] = 'bad';
do_action( 'updated_post_meta', 1, 1, '_shipping_phone' );
$repository->updateTransactionByCallbackVerified( array( 'changes' => array( 'awb' => 'BOOKED' ), 'condition' => array( 'wp_wc_order_stat_order_id' => 1 ) ) );
$booked = $query->getPhoneIssueIds();
$payload = array_fill_keys( array( 'order_id', 'shipping_info', 'destination_sub_district_id', 'destination_sub_district', 'status', 'service', 'service_name', 'weight', 'width', 'height', 'length', 'shipping_cost', 'insurance_cost', 'cod_fee', 'transaction_value', 'created_at', 'wp_wc_order_stat_order_id' ), '' );
$repository->createTransaction( $payload );
$created = $query->getPhoneIssueIds();
$GLOBALS['clock'] += 61;
$previous_scans = $wpdb->scans;
$refreshed = $query->getPhoneIssueIds();
if ( $wpdb->scans <= $previous_scans ) { throw new RuntimeException( 'Expired cache did not rescan.' ); }
$hooks_invalidated = true;
foreach ( array_keys( $GLOBALS['hooks'] ) as $hook ) {
	$revision = TransactionPhoneIssueCache::revision();
	do_action( $hook, 1, 1, '_virtual' );
	$hooks_invalidated = $hooks_invalidated && $revision !== TransactionPhoneIssueCache::revision();
}
$wpdb->race = true;
$query->getPhoneIssueIds();
$race_discarded = null === TransactionPhoneIssueCache::get( TransactionPhoneIssueCache::revision() );
$wpdb->fail = true;
$query->getPhoneIssueIds();
$error_not_cached = null === TransactionPhoneIssueCache::get( TransactionPhoneIssueCache::revision() );
$wpdb->fail = false;
$wpdb->last_error = '';
$query->getPhoneIssueIds();
$retained_keys = count( $GLOBALS['cache'] );
$minimal_fields = ! str_contains( implode( '\n', $wpdb->queries ), 'SELECT t.*' ) && str_contains( $wpdb->queries[0], 'SELECT t.id, t.wp_wc_order_stat_order_id, t.shipping_info' );
echo json_encode( compact( 'cold', 'cold_scans', 'cold_reads', 'warm_scans', 'warm_reads', 'corrected', 'snapshot_corrected', 'booked', 'created', 'refreshed', 'retained_keys', 'hooks_invalidated', 'race_discarded', 'error_not_cached', 'minimal_fields' ), JSON_THROW_ON_ERROR );
