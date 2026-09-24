<?php
namespace KiriminAjaOfficial\Services;

use KiriminAjaOfficial\Services\TransactionProcessServices\RecipientDataResolver;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts one regular-delivery transaction into the Svelte detail workspace contract.
 */
class TransactionDetailPageData {
	private RecipientDataResolver $recipient_resolver;
	private ShipmentLocationService $location_service;
	private ShippingDiscountCouponService $coupon_service;

	public function __construct(
		?RecipientDataResolver $recipient_resolver = null,
		?ShipmentLocationService $location_service = null,
		?ShippingDiscountCouponService $coupon_service = null
	) {
		$this->recipient_resolver = $recipient_resolver ?? new RecipientDataResolver();
		$this->location_service   = $location_service ?? new ShipmentLocationService();
		$this->coupon_service     = $coupon_service ?? new ShippingDiscountCouponService();
	}

	/**
	 * @param object $transaction Transaction database row.
	 * @return array<string,mixed>
	 */
	public function prepare( object $transaction ): array {
		$wc_order      = function_exists( 'wc_get_order' ) ? wc_get_order( (int) ( $transaction->wp_wc_order_stat_order_id ?? 0 ) ) : false;
		$shipping_info = json_decode( (string) ( $transaction->shipping_info ?? '{}' ) );
		$recipient     = $this->recipient_resolver->resolve( $wc_order, $shipping_info, $transaction );
		$origin        = $this->origin( $transaction );
		$shipping      = (float) ( $transaction->shipping_cost ?? 0 );
		$insurance     = (float) ( $transaction->insurance_cost ?? 0 );
		$cod_fee       = (float) ( $transaction->cod_fee ?? 0 );
		$discount      = max( 0, (float) ( $transaction->discount_amount ?? 0 ) );
		$cod_value     = $cod_fee > 0 ? $shipping + $insurance + $cod_fee + (float) ( $transaction->transaction_value ?? 0 ) : 0.0;
		$payment_label = $cod_fee > 0 ? __( 'COD', 'kiriminaja-official' ) : __( 'Non-COD', 'kiriminaja-official' );
		$status        = (string) ( $transaction->status ?? 'new' );
		$order_url     = $wc_order && method_exists( $wc_order, 'get_edit_order_url' ) ? (string) $wc_order->get_edit_order_url() : '';
		$items         = $this->items( $wc_order );
		$notes         = $this->notes( $wc_order );
		$action_data   = $this->action_data( $transaction, $wc_order, $shipping, $insurance, $cod_fee );
		$print_url     = '' !== (string) ( $transaction->awb ?? '' )
			? admin_url( 'admin-post.php?action=kiriof_resi_print&oids=' . rawurlencode( (string) ( $transaction->order_id ?? '' ) ) . '&_wpnonce=' . wp_create_nonce( 'kiriof_resi_print' ) )
			: '';

		$toolbar = array(
			'logoUrl'   => KIRIOF_URL . 'assets/admin/img/icon-128x128.png',
			'rootUrl'   => admin_url( 'admin.php?page=kiriminaja-transaction' ),
			'rootLabel' => __( 'Transactions', 'kiriminaja-official' ),
			'title'     => '#' . (string) ( $transaction->wp_wc_order_stat_order_id ?? $transaction->id ),
			'menu'      => $this->toolbar_menu(),
		);
		$toolbar_update = ( new PluginUpdateNoticeService() )->get_toolbar_update();
		if ( $toolbar_update ) {
			$toolbar['update'] = $toolbar_update;
		}
		if ( class_exists( RevampAnnouncementService::class ) ) {
			$toolbar = RevampAnnouncementService::attach_announcement( $toolbar );
		}

		return array(
			'toolbar' => $toolbar,
			'transaction' => array(
				'id'           => (int) ( $transaction->id ?? 0 ),
				'orderId'      => (string) ( $transaction->order_id ?? '' ),
				'orderNumber'  => '#' . (string) ( $transaction->wp_wc_order_stat_order_id ?? '' ),
				'orderUrl'     => $order_url,
				'createdAt'    => $this->date( $transaction->created_at ?? '' ),
				'paymentLabel' => $payment_label,
				'isCod'        => $cod_fee > 0,
				'supportsLiveTracking' => false,
				'pickupNumber' => (string) ( $transaction->pickup_number ?? '' ),
				'status'       => array( 'label' => $this->status_label( $status ), 'tone' => $this->status_tone( $status ) ),
				'steps'        => $this->steps( $transaction, $status ),
				'sender'       => $origin,
				'recipient'    => array(
					'name'    => trim( (string) $recipient['first_name'] . ' ' . (string) $recipient['last_name'] ),
					'phone'   => (string) $recipient['phone'],
					'address' => array_values( array_filter( array( (string) $recipient['address_1'], (string) $recipient['address_2'], implode( ', ', array_filter( array( (string) ( $transaction->destination_sub_district ?? '' ), (string) $recipient['city'], (string) $recipient['state'] ) ) ), implode( ', ', array_filter( array( (string) $recipient['postcode'], (string) $recipient['country'] ) ) ) ) ) ),
				),
				'package'       => array(
					'weight' => (int) ( $transaction->weight ?? 0 ),
					'length' => (float) ( $transaction->length ?? 0 ),
					'width'  => (float) ( $transaction->width ?? 0 ),
					'height' => (float) ( $transaction->height ?? 0 ),
				),
				'items'         => $items,
				'notes'         => $notes,
				'shipment'      => array(
					'courier'       => array( 'code' => strtolower( (string) ( $transaction->service ?? '' ) ), 'service' => (string) ( $transaction->service_name ?? $transaction->service ?? '' ) ),
					'awb'           => (string) ( $transaction->awb ?? '' ),
					'costs'         => array( 'shipping' => $shipping, 'insurance' => $insurance, 'codFee' => $cod_fee, 'discount' => $discount, 'total' => max( 0, $shipping + $insurance + $cod_fee - $discount ) ),
					'codValue'      => $cod_value,
					'printUrl'      => $print_url,
					'trackingOrder' => (string) ( $transaction->order_id ?? '' ),
				),
				'actions'       => array(
					'changeOrigin'  => 'new' === $status,
					'adjustDeficit' => ! empty( $transaction->is_deficit ),
					'cancelDeficit' => ! empty( $transaction->is_deficit ),
					'cancel'        => in_array( $status, array( 'new', 'request_pickup', 'pending' ), true ),
					'data'          => $action_data,
				),
			),
			'ajax' => array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( KIRIOF_NONCE ) ),
			'i18n' => $this->i18n(),
		);
	}

	/** @return array<string,mixed> */
	private function origin( object $transaction ): array {
		$snapshot = json_decode( (string) ( $transaction->shipment_location_snapshot ?? '{}' ), true );
		$snapshot = is_array( $snapshot ) ? $snapshot : array();
		$location = ! empty( $transaction->shipment_location_id ) ? $this->location_service->repository()->getById( (int) $transaction->shipment_location_id ) : null;
		$name = trim( (string) ( $snapshot['origin_name'] ?? $snapshot['location_name'] ?? $snapshot['name'] ?? $location->name ?? $location->location_name ?? '' ) );
		$address = array_values(
			array_filter(
				array(
					(string) ( $snapshot['address'] ?? $snapshot['address_1'] ?? $location->address ?? $location->address_1 ?? '' ),
					implode( ', ', array_filter( array( (string) ( $snapshot['subdistrict_name'] ?? $snapshot['district'] ?? $location->subdistrict_name ?? '' ), (string) ( $snapshot['city_name'] ?? $snapshot['city'] ?? $location->city ?? '' ), (string) ( $snapshot['province_name'] ?? $snapshot['province'] ?? $location->province ?? '' ) ) ) ),
				)
			)
		);
		return array( 'name' => '' !== $name ? $name : __( 'Default origin', 'kiriminaja-official' ), 'phone' => (string) ( $snapshot['phone'] ?? $location->phone ?? '' ), 'address' => $address );
	}

	/** @return array<int,array<string,mixed>> */
	private function items( $order ): array {
		if ( ! $order || ! method_exists( $order, 'get_items' ) ) {
			return array();
		}
		$items = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$items[] = array( 'name' => (string) $item->get_name(), 'quantity' => (int) $item->get_quantity(), 'total' => (float) $item->get_total(), 'sku' => (string) ( $item->get_product() ? $item->get_product()->get_sku() : '' ) );
		}
		return $items;
	}

	/** @return array<int,array<string,string>> */
	private function notes( $order ): array {
		if ( ! $order ) {
			return array();
		}
		$notes = array();
		$customer_note = method_exists( $order, 'get_customer_note' ) ? trim( (string) $order->get_customer_note() ) : '';
		if ( '' !== $customer_note ) {
			$notes[] = array( 'label' => __( 'Customer note', 'kiriminaja-official' ), 'content' => $customer_note );
		}
		if ( function_exists( 'wc_get_order_notes' ) && method_exists( $order, 'get_id' ) ) {
			foreach ( wc_get_order_notes( array( 'order_id' => $order->get_id(), 'type' => 'customer' ) ) as $note ) {
				$content = trim( wp_strip_all_tags( (string) ( $note->content ?? '' ) ) );
				if ( '' !== $content && $content !== $customer_note ) {
					$notes[] = array( 'label' => __( 'Order note', 'kiriminaja-official' ), 'content' => $content );
				}
			}
		}
		return $notes;
	}

	/** @return array<string,mixed> */
	private function action_data( object $transaction, $order, float $shipping, float $insurance, float $cod_fee ): array {
		$shipping_discount = $order ? max( 0, $shipping - (float) $order->get_shipping_total() ) : max( 0, (float) ( $transaction->discount_amount ?? 0 ) );
		$coupon_scopes = $order ? $this->coupon_service->splitCouponCodesByScope( (array) $order->get_coupon_codes() ) : array( 'item' => array(), 'shipping' => array() );
		return array(
			'nonce' => wp_create_nonce( KIRIOF_NONCE ), 'kaOrderId' => (string) ( $transaction->order_id ?? '' ), 'currentOrigin' => $this->origin( $transaction )['name'], 'currentOriginAddress' => implode( ', ', $this->origin( $transaction )['address'], ), 'currentLocationId' => (int) ( $transaction->shipment_location_id ?? 0 ), 'currentCod' => $order ? (float) $order->get_total() : 0, 'codMinimum' => (float) ( $transaction->cod_minimum ?? 0 ), 'codMaximum' => 0, 'shippingCost' => $shipping, 'insuranceFee' => $insurance, 'codFee' => $cod_fee, 'itemPrice' => $order ? (float) $order->get_subtotal() : 0, 'itemDiscount' => $order ? (float) $order->get_discount_total() : 0, 'shippingDiscount' => $shipping_discount, 'itemCoupon' => (string) ( $coupon_scopes['item'][0] ?? '' ), 'shippingCoupon' => (string) ( $coupon_scopes['shipping'][0] ?? '' ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	private function steps( object $transaction, string $status ): array {
		$stages = array( array( 'key' => 'created', 'label' => __( 'Created', 'kiriminaja-official' ), 'date' => $transaction->created_at ?? '' ), array( 'key' => 'pickup', 'label' => __( 'Shipment Request', 'kiriminaja-official' ), 'date' => $transaction->request_pickup_at ?? '' ), array( 'key' => 'shipping', 'label' => __( 'Shipping', 'kiriminaja-official' ), 'date' => $transaction->shipped_at ?? '' ), array( 'key' => 'delivered', 'label' => __( 'Delivered', 'kiriminaja-official' ), 'date' => $transaction->finished_at ?? '' ) );
		$completed = array( 'created' => true, 'pickup' => in_array( $status, array( 'request_pickup', 'pending', 'shipped', 'finished' ), true ), 'shipping' => in_array( $status, array( 'shipped', 'finished' ), true ), 'delivered' => 'finished' === $status );
		foreach ( $stages as &$stage ) { $stage['completed'] = $completed[ $stage['key'] ]; $stage['date'] = $this->date( $stage['date'] ); unset( $stage['key'] ); }
		return $stages;
	}

	private function date( $value ): string { return '' === (string) $value ? '' : wp_date( 'd M Y, H:i', strtotime( (string) $value ) ); }
	private function status_label( string $status ): string { $labels = array( 'new' => __( 'New', 'kiriminaja-official' ), 'request_pickup' => __( 'Request Pickup', 'kiriminaja-official' ), 'pending' => __( 'Pending', 'kiriminaja-official' ), 'shipped' => __( 'Shipped', 'kiriminaja-official' ), 'finished' => __( 'Delivered', 'kiriminaja-official' ), 'canceled' => __( 'Cancelled', 'kiriminaja-official' ), 'rejected' => __( 'Rejected', 'kiriminaja-official' ), 'return' => __( 'Return', 'kiriminaja-official' ), 'returned' => __( 'Returned', 'kiriminaja-official' ) ); return $labels[ $status ] ?? ucfirst( $status ); }
	private function status_tone( string $status ): string { if ( 'finished' === $status ) return 'success'; if ( 'shipped' === $status ) return 'teal'; if ( in_array( $status, array( 'canceled', 'rejected', 'return', 'returned' ), true ) ) return 'danger'; if ( in_array( $status, array( 'request_pickup', 'pending' ), true ) ) return 'info'; return 'primary'; }
	/** @return array<string,mixed> */
	private function toolbar_menu(): array { return array( 'label' => __( 'More actions', 'kiriminaja-official' ), 'items' => array( array( 'label' => __( 'Get Help', 'kiriminaja-official' ), 'href' => 'https://help.kiriminaja.com/category/plugin' ), array( 'label' => __( 'Go to Dashboard', 'kiriminaja-official' ), 'href' => 'https://app.kiriminaja.com' ) ) ); }
	/** @return array<string,string> */
	private function i18n(): array { return array( 'printLabel' => __( 'Print Label', 'kiriminaja-official' ), 'liveTracking' => __( 'Live Tracking', 'kiriminaja-official' ), 'sender' => __( 'Sender', 'kiriminaja-official' ), 'recipient' => __( 'Recipient', 'kiriminaja-official' ), 'package' => __( 'Package', 'kiriminaja-official' ), 'products' => __( 'Products', 'kiriminaja-official' ), 'orderNotes' => __( 'Order notes', 'kiriminaja-official' ), 'shipment' => __( 'Shipment', 'kiriminaja-official' ), 'airwaybill' => __( 'Airwaybill', 'kiriminaja-official' ), 'pickup' => __( 'Pickup', 'kiriminaja-official' ), 'dropoff' => __( 'Drop-off', 'kiriminaja-official' ), 'weight' => __( 'Weight', 'kiriminaja-official' ), 'dimensions' => __( 'Dimensions', 'kiriminaja-official' ), 'shipping' => __( 'Shipping', 'kiriminaja-official' ), 'insurance' => __( 'Insurance', 'kiriminaja-official' ), 'codFee' => __( 'COD Fee', 'kiriminaja-official' ), 'discount' => __( 'Discount', 'kiriminaja-official' ), 'total' => __( 'Total', 'kiriminaja-official' ), 'codValue' => __( 'COD value', 'kiriminaja-official' ), 'tracking' => __( 'Tracking history', 'kiriminaja-official' ), 'trackingEmpty' => __( 'No tracking history is available yet.', 'kiriminaja-official' ), 'trackingError' => __( 'Unable to load tracking history.', 'kiriminaja-official' ), 'changeOrigin' => __( 'Change Origin', 'kiriminaja-official' ), 'adjustDeficit' => __( 'Adjust Deficit', 'kiriminaja-official' ), 'cancel' => __( 'Cancel', 'kiriminaja-official' ) ); }
}
