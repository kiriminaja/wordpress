<?php
namespace {
	define( 'ABSPATH', __DIR__ );
	function __( $text, $domain = '' ) { return $text; }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_html__( $text, $domain = '' ) { return esc_html( __( $text, $domain ) ); }
	function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function number_format_i18n( $number, $decimals = 0 ) { return number_format( $number, $decimals ); }
	function wc_get_order( $id ) { return empty( $GLOBALS['input']['missing_order'] ) ? new LabelOrder() : false; }
	function wp_remote_get( ...$args ) { ++$GLOBALS['calls']; throw new \RuntimeException( 'Remote call forbidden' ); }
	function update_option( ...$args ) { ++$GLOBALS['writes']; throw new \RuntimeException( 'Write forbidden' ); }
	class LabelOrder {
		function get_status() { return $GLOBALS['input']['wc_status'] ?? 'processing'; }
		function get_order_number() { return $GLOBALS['input']['wc_reference'] ?? 'WC-42'; }
		function get_address( $type ) { throw new \RuntimeException( 'Changed destination must not be read' ); }
		function get_items( $type ) {
			if ( ! empty( $GLOBALS['input']['forbid_item_reads'] ) ) { throw new \RuntimeException( 'Current items must not be read' ); }
			return array( new LabelItem( false ), new LabelItem( true ) ); }
	}
	class LabelItem {
		private bool $virtual;
		function __construct( bool $virtual ) { $this->virtual = $virtual; }
		function get_product() { if ( ! empty( $GLOBALS['input']['product_removed'] ) ) { return false; } return new LabelProduct( $this->virtual ); }
		function get_name() { return $this->virtual ? 'Virtual excluded' : ( $GLOBALS['input']['item_name'] ?? 'Physical item' ); }
		function get_quantity() { return $GLOBALS['input']['item_qty'] ?? 2; }
	}
	class LabelProduct {
		private bool $virtual;
		function __construct( bool $virtual ) { $this->virtual = $virtual; }
		function needs_shipping() { return ! $this->virtual && empty( $GLOBALS['input']['no_physical'] ); }
	}
}
namespace KiriminAjaOfficial\Repositories {
	class TransactionRepository {
		public array $ids = array();
		function getTransactionByOrderIds( $ids ) { $this->ids = $ids; return $GLOBALS['rows']; }
		function markPrintedByOrderIds( ...$args ) { ++$GLOBALS['writes']; throw new \RuntimeException( 'Print write forbidden' ); }
	}
}
namespace {
	$input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
	$GLOBALS['input'] = $input;
	$GLOBALS['writes'] = 0;
	$GLOBALS['calls'] = 0;
	$root = dirname( __DIR__, 2 );
	foreach ( array( 'Services/TransactionDeliveryType', 'Services/ShipmentLocationService', 'Services/TransactionOriginResolver', 'Services/TransactionProcessServices/RecipientDataResolver', 'Services/InstantLabelService' ) as $file ) { require $root . '/inc/' . $file . '.php'; }
	$defaults = array( 'order_id' => 'KA-1', 'wp_wc_order_stat_order_id' => 42, 'service' => 'gosend', 'service_name' => 'Instant', 'vehicle' => 'motor', 'status' => 'request_pickup', 'instant_status_code' => 100, 'awb' => 'AWB-123', 'weight' => 1000, 'shipping_cost' => 12000, 'instant_payment_method' => 'credit', 'instant_payment_status' => 'paid', 'shipping_info' => json_encode( array( 'instant_items' => array( array( 'name' => 'Physical item', 'qty' => 2, 'price' => 10000, 'weight' => 500 ) ), '_shipping_first_name' => 'Booked recipient', '_shipping_phone' => '0812345678', '_shipping_address_1' => 'Booked street', '_shipping_city' => 'Booked city' ) ), 'shipment_location_snapshot' => json_encode( array( 'origin_name' => 'Booked sender', 'origin_phone' => '0823456789', 'origin_address' => 'Booked origin', 'origin_city' => 'Origin city' ) ) );
	$GLOBALS['rows'] = array_map( static fn( $row ) => (object) array_merge( $defaults, $row ), $input['rows'] ?? array( array() ) );
	foreach ( $GLOBALS['rows'] as $index => $row ) {
		if ( ! array_key_exists( 'awb', $input['rows'][ $index ] ?? array() ) ) {
			$row->awb = 'AWB-' . $row->order_id;
		}
	}
	if ( array_key_exists( 'snapshot_items', $input ) || ! empty( $input['missing_snapshot_items'] ) ) {
		foreach ( $GLOBALS['rows'] as $row ) {
			$snapshot = json_decode( $row->shipping_info, true );
			if ( ! empty( $input['missing_snapshot_items'] ) ) { unset( $snapshot['instant_items'] ); } else { $snapshot['instant_items'] = $input['snapshot_items']; }
			$row->shipping_info = json_encode( $snapshot );
		}
	}
	$repo = new \KiriminAjaOfficial\Repositories\TransactionRepository();
	$result = array( 'labels' => array(), 'html' => '', 'error' => '' );
	try {
		$service = new \KiriminAjaOfficial\Services\InstantLabelService( $repo, null, null, static function( $awbs ) use ( $input ) {
			++$GLOBALS['calls'];
			$GLOBALS['print_awbs'] = $awbs;
			if ( ! empty( $input['print_exception'] ) ) { throw new \RuntimeException( 'secret remote token' ); }
			$response = $input['print_response'] ?? array( 'status' => false );
			if ( ! empty( $input['object_data'] ) && is_array( $response['data'] ?? null ) ) { $response['data'] = json_decode( json_encode( $response['data'] ) ); }
			return $response;
		} );
		if ( ! empty( $input['preview'] ) ) { $result['preview'] = $service->preview( $input['ids'] ?? array( 'KA-1' ) ); }
		$labels = $service->prepare( $input['ids'] ?? array( 'KA-1' ) );
		$result['labels'] = $labels;
		if ( ! empty( $input['poison'] ) ) {
			$poison = '<img src=x onerror=alert(1)>';
			foreach ( $labels as &$label ) {
				foreach ( array( 'order_id', 'wc_reference', 'awb', 'courier', 'service', 'vehicle', 'weight', 'payment_method', 'payment_status' ) as $field ) { $label[ $field ] = $poison; }
				foreach ( array( 'sender', 'recipient' ) as $field ) { $label[ $field ] = array( 'name' => $poison, 'phone' => $poison, 'address' => array( $poison ) ); }
				$label['items'] = array( array( 'name' => $poison, 'quantity' => $poison ) );
			}
			unset( $label );
		}
		ob_start(); require $root . '/templates/instant/labels.php'; $result['html'] = ob_get_clean();
	} catch ( \InvalidArgumentException $error ) { $result['error'] = $error->getMessage(); }
	$result['can_print'] = array_map( static fn( $row ) => \KiriminAjaOfficial\Services\InstantLabelService::canPrint( $row ), $GLOBALS['rows'] );
	$result['ids'] = $repo->ids;
	$result['writes'] = $GLOBALS['writes'];
	$result['calls'] = $GLOBALS['calls'];
	$result['print_awbs'] = $GLOBALS['print_awbs'] ?? array();
	echo json_encode( $result, JSON_THROW_ON_ERROR );
}
