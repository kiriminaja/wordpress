<?php

namespace KiriminAjaOfficial\Services;

use DateTimeZone;
use KiriminAjaOfficial\Services\TransactionProcessServices\RecipientDataResolver;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts transaction query rows into a serializable UI contract.
 */
class TransactionListViewModelFactory {
	private RecipientDataResolver $recipient_resolver;
	private ShipmentLocationService $location_service;
	private ShippingDiscountCouponService $coupon_service;
	private TransactionOriginResolver $origin_resolver;

	public function __construct(
		?RecipientDataResolver $recipient_resolver = null,
		?ShipmentLocationService $location_service = null,
		?ShippingDiscountCouponService $coupon_service = null,
		?TransactionOriginResolver $origin_resolver = null
	) {
		$this->recipient_resolver = $recipient_resolver ?? new RecipientDataResolver();
		$this->location_service   = $location_service ?? new ShipmentLocationService();
		$this->coupon_service     = $coupon_service ?? new ShippingDiscountCouponService();
		$this->origin_resolver    = $origin_resolver ?? new TransactionOriginResolver( $this->location_service );
	}

	/**
	 * @param array<int, object> $results Query rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function createRows( array $results, string $status_filter ): array {
		return array_values(
			array_map(
				fn ( $row ) => $this->createRow( $row, $status_filter ),
				$results
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function createRow( object $row, string $status_filter ): array {
		$delivery_type         = TransactionDeliveryType::resolve( $row );
		$is_express            = 'express' === $delivery_type;
		$helper                = kiriof_helper();
		$shipping_info         = json_decode( (string) ( $row->shipping_info ?? '{}' ) );
		$wc_order              = function_exists( 'wc_get_order' ) ? wc_get_order( $row->wc_order_id ) : false;
		$recipient             = $this->recipient_resolver->resolve( $wc_order, $shipping_info, $row );
		$billing_address       = $wc_order && method_exists( $wc_order, 'get_address' ) ? (array) $wc_order->get_address( 'billing' ) : array();
		$billing_name          = trim( (string) ( $billing_address['first_name'] ?? '' ) . ' ' . (string) ( $billing_address['last_name'] ?? '' ) );
		$recipient_name        = trim( $recipient['first_name'] . ' ' . $recipient['last_name'] );
		$billing_name          = '' !== $billing_name ? $billing_name : $recipient_name;
		$recipient_name        = '' !== $recipient_name ? $recipient_name : $billing_name;
		$payment_method        = $wc_order ? (string) $wc_order->get_payment_method() : (string) ( $shipping_info->_payment_method ?? '' );
		$is_cod                = 'cod' === $payment_method;
		$shipping_cost         = (float) ( $row->shipping_cost ?? 0 );
		$insurance_cost        = (float) ( $row->insurance_cost ?? 0 );
		$discount_amount       = (float) ( $row->discount_amount ?? 0 );
		$cod_fee               = (float) ( $row->cod_fee ?? 0 );
		$transaction_value     = (float) ( $row->transaction_value ?? 0 );
		$cod_value             = $cod_fee > 0 ? $shipping_cost + $insurance_cost + $cod_fee + $transaction_value : 0.0;
		$is_deficit            = ! empty( $row->is_deficit );
		$item_discount         = $wc_order ? (float) $wc_order->get_discount_total() : 0.0;
		$coupon_codes          = $wc_order ? (array) $wc_order->get_coupon_codes() : array();
		$coupon_scopes         = $this->coupon_service->splitCouponCodesByScope( $coupon_codes );
		$shipping_discount     = $wc_order ? max( 0.0, $shipping_cost - (float) $wc_order->get_shipping_total() ) : max( 0.0, $discount_amount );
		$paid_shipping         = $wc_order ? max( 0.0, (float) $wc_order->get_shipping_total() ) : max( 0.0, $shipping_cost - $shipping_discount );
		$wc_total              = $wc_order ? (float) $wc_order->get_total() : 0.0;
		$wc_subtotal           = $wc_order ? (float) $wc_order->get_subtotal() : 0.0;
		$deficit_minimum       = $shipping_cost + $insurance_cost + $cod_fee;
		$effective_payout      = $wc_total - max( 0.0, $shipping_cost - $shipping_discount ) - $insurance_cost - $cod_fee;
		$post_status           = (string) ( $row->post_status ?? $row->wc_status ?? 'wc-processing' );
		$is_processable        = 'wc-processing' === $post_status && 'new' === (string) $row->status;
		$awb                   = (string) ( $row->awb ?? '' );
		$order_id              = (string) ( $row->order_id ?? '' );
		$can_request_pickup    = $is_processable && ( ! $is_deficit || $effective_payout >= 0 );
		$print_capable_filter  = in_array( $status_filter, array( 'all', 'processed' ), true );
		$can_print             = $is_express && $print_capable_filter && '' !== $awb && 'request_pickup' === (string) $row->status;
		$terminal_statuses     = array( 'shipped', 'finished', 'returned', 'return', 'canceled' );
		$can_cancel            = $is_express ? ( '' !== $awb && ! in_array( (string) $row->status, $terminal_statuses, true ) ) : InstantShipmentState::canCancel( $row );
		$can_remote_instant    = ! $is_express && 'new' !== (string) $row->status && in_array( strtolower( trim( (string) ( $row->service ?? '' ) ) ), array( 'gosend', 'grab_express' ), true );
		$can_request_pickup    = $is_express && $can_request_pickup;
		$can_process_instant   = ! $is_express && InstantShipmentContext::canProcess( $row ) && $wc_order
			&& ! in_array( $wc_order->get_status(), array( 'cancelled', 'completed', 'refunded', 'failed', 'trash' ), true )
			&& ( $wc_order->is_paid() || 'processing' === $wc_order->get_status() );
		$can_print             = $is_express ? $can_print : InstantLabelService::canPrint( $row );
		$instant_payment_id    = (string) ( $row->instant_payment_id ?? '' );
		$can_pay_instant       = ! $is_express && 'qris' === ( $row->instant_payment_method ?? '' )
			&& in_array( $row->instant_payment_status ?? '', array( 'pending', 'unpaid' ), true )
			&& 1 === preg_match( '/\A[A-Za-z0-9][A-Za-z0-9_-]{0,99}\z/', $instant_payment_id );
		$checkbox_disabled     = ! $can_print && ! $can_request_pickup && ! $can_process_instant;
		$origin                = $this->origin_resolver->resolve( $row );
		$origin_name           = $origin['name'];
		$origin_address        = $origin['address'];
		$origin_location_id    = $origin['locationId'];
		$is_ka_order           = 'wc-processing' === $post_status;
		$status_label          = $is_express && $is_deficit
			? __( 'COD Deficit', 'kiriminaja-official' )
			: ( $is_ka_order ? $helper->transactionStatusLabel( $row->status ) : $helper->wcStatusLabel( $post_status ) );
		$status_tone           = $this->statusTone( $is_deficit, $post_status, (string) $row->status );
		$status_presentation   = $is_express
			? array( 'label' => $status_label, 'tone' => $status_tone, 'deficit' => $is_deficit, 'tooltip' => '', 'issue' => false )
			: array_merge( InstantDeliveryStatus::describe( $row ), array( 'deficit' => false ) );
		// An Instant shipment state must not conceal WooCommerce's order hold.
		// Prefer the live order over a potentially stale query status (legacy/HPOS).
		$instant_wc_status = $wc_order ? (string) $wc_order->get_status() : preg_replace( '/^wc-/', '', $post_status );
		if ( ! $is_express && 'on-hold' === $instant_wc_status ) {
			$status_presentation['key']   = 'wc-on-hold';
			$status_presentation['label'] = __( 'On Hold', 'kiriminaja-official' );
			$status_presentation['tone']  = 'warning';
		}
		if ( ! $is_express && $is_deficit ) {
			$status_presentation['issue'] = $status_presentation['issue'] ?: __( 'Instant order issue', 'kiriminaja-official' );
		}
		$address_lines         = array_values(
			array_filter(
				array(
					(string) $recipient['address_1'],
					(string) $recipient['address_2'],
					implode( ', ', array_filter( array( (string) ( $row->destination_sub_district ?? '' ), (string) $recipient['city'], (string) $recipient['state'] ) ) ),
					implode( ', ', array_filter( array( (string) $recipient['postcode'], (string) $recipient['country'] ) ) ),
				),
				static fn ( $value ) => '' !== trim( $value )
			)
		);

		return array(
			'deliveryType'    => $delivery_type,
			'vehicle'         => TransactionDeliveryType::normalizeVehicle( $row->vehicle ?? null ),
			'instantPayment'  => array(
				'method' => $is_express ? '' : (string) ( $row->instant_payment_method ?? '' ),
				'status' => $is_express ? '' : (string) ( $row->instant_payment_status ?? '' ),
				'id'     => $is_express ? '' : (string) ( $row->instant_payment_id ?? '' ),
			),
			'id'              => (int) ( $row->id ?? $row->wc_order_id ),
			'wcOrderId'       => (int) $row->wc_order_id,
			'wcOrderUrl'      => admin_url( 'post.php?post=' . (int) $row->wc_order_id . '&action=edit' ),
			'detailUrl'       => admin_url( 'admin.php?page=kiriminaja-transaction-detail&id=' . absint( $row->wc_order_id ?? $row->wp_wc_order_stat_order_id ?? 0 ) ),
			'createdAt'       => wp_date( 'M d, Y H:i', strtotime( (string) $row->wc_date_created ), new DateTimeZone( 'UTC' ) ),
			'customer'        => array( 'name' => $billing_name, 'phone' => (string) $recipient['phone'] ),
			'courier'         => array(
				'code'         => strtolower( (string) ( $row->service ?? '' ) ),
				'service'      => $helper->formatServiceName( $row->service, $row->service_name ?? '' ),
				'paymentLabel' => $is_cod ? __( 'COD', 'kiriminaja-official' ) : __( 'NON COD', 'kiriminaja-official' ),
			),
			'status'          => $status_presentation,
			'printStatus'     => ! empty( $row->is_printed ) ? 'printed' : 'unprinted',
			'awb'             => $awb,
			'kaOrderId'       => $order_id,
			'route'           => array( 'origin' => $origin_name, 'destination' => $recipient_name, 'addressLines' => $address_lines ),
			'package'         => array(
				'weight'           => (float) ( $row->weight ?? 0 ),
				'quantity'         => isset( $row->quantity ) ? (int) $row->quantity : 1,
				'actualShipping'   => $shipping_cost,
				'paidShipping'     => $paid_shipping,
				'insurance'        => $insurance_cost,
				'codFee'           => $cod_fee,
				'codValue'         => $cod_value,
				'itemDiscount'     => $item_discount,
				'shippingDiscount' => $shipping_discount,
				'itemCoupon'       => (string) ( $coupon_scopes['item'][0] ?? '' ),
				'shippingCoupon'   => (string) ( $coupon_scopes['shipping'][0] ?? '' ),
			),
			'selection'       => array(
				'disabled'  => $checkbox_disabled,
				'canPickup' => $can_request_pickup,
				'canProcess' => (bool) $can_process_instant,
				'canPrint'  => $can_print,
				'title'     => $is_express ? $this->selectionTitle( $is_deficit, $effective_payout, $print_capable_filter, $can_print, $is_processable ) : ( $checkbox_disabled ? __( 'This Instant shipment cannot be processed or printed. Review its shipment and payment state.', 'kiriminaja-official' ) : '' ),
			),
			'actions'         => array(
				'paymentUrl'   => $can_pay_instant ? add_query_arg( array( 'key' => $instant_payment_id, 'instant_payment_id' => $instant_payment_id, 'open_payment' => '1' ), admin_url( 'admin.php?page=kiriminaja-request-pickup' ) ) : '',
				'track'        => $can_remote_instant && InstantTrackingPresentation::hasRoute( $row ),
				'reconcile'    => InstantShipmentState::canRecheck( $row ),
				'liveTrackingUrl' => $is_express ? '' : InstantTrackingPresentation::trackingUrl( $row ),
				'preview'      => true,
				'process'      => (bool) $can_process_instant,
				'changeOrigin' => $is_express && $is_processable,
				'adjustDeficit'=> $is_express && $is_deficit,
				'cancelDeficit'=> $is_express && $is_deficit,
				'print'        => $is_express ? ( '' !== $awb && 'request_pickup' === (string) $row->status ) : $can_print,
				'cancel'       => ( ! $is_express || ! $is_deficit ) && $can_cancel,
				'printUrl'     => $is_express
					? admin_url( 'admin-post.php?action=kiriof_resi_print&oids=' . rawurlencode( $order_id ) . '&_wpnonce=' . wp_create_nonce( 'kiriof_resi_print' ) )
					: ( $can_print ? admin_url( 'admin-post.php?action=kiriof_instant_labels&oids=' . rawurlencode( $order_id ) . '&_wpnonce=' . wp_create_nonce( 'kiriof_instant_labels' ) ) : '' ),
			),
			'actionData'      => array(
				'nonce'                => wp_create_nonce( KIRIOF_NONCE ),
				'kaOrderId'            => $order_id,
				'currentOrigin'        => $origin_name,
				'currentOriginAddress' => $origin_address,
				'currentLocationId'    => $origin_location_id,
				'currentCod'           => $wc_total,
				'codMinimum'           => $deficit_minimum,
				'codMaximum'           => (float) KIRIOF_MAX_COD_AMOUNT,
				'shippingCost'         => $shipping_cost,
				'insuranceFee'         => $insurance_cost,
				'codFee'               => $cod_fee,
				'itemPrice'            => $wc_subtotal,
				'itemDiscount'         => $item_discount,
				'shippingDiscount'     => $shipping_discount,
				'itemCoupon'           => (string) ( $coupon_scopes['item'][0] ?? '' ),
				'shippingCoupon'       => (string) ( $coupon_scopes['shipping'][0] ?? '' ),
			),
		);
	}

	private function statusTone( bool $deficit, string $post_status, string $status ): string {
		if ( $deficit || in_array( $status, array( 'canceled', 'returned', 'return' ), true ) || 'wc-cancelled' === $post_status ) {
			return 'danger';
		}
		if ( in_array( $post_status, array( 'wc-on-hold', 'wc-pending' ), true ) ) {
			return 'warning';
		}
		if ( in_array( $status, array( 'finished' ), true ) ) {
			return 'success';
		}
		if ( 'shipped' === $status ) {
			return 'teal';
		}
		if ( 'new' === $status ) {
			return 'info';
		}
		return 'info';
	}

	private function selectionTitle( bool $deficit, float $payout, bool $print_capable_filter, bool $can_print, bool $processable ): string {
		if ( $deficit && $payout < 0 ) {
			return __( 'Resolve the COD deficit before proceeding.', 'kiriminaja-official' );
		}
		if ( $print_capable_filter && ! $processable ) {
			return $can_print ? '' : __( 'Order must have an AWB and request pickup status before it can be printed.', 'kiriminaja-official' );
		}
		return $processable ? '' : __( 'Order must be in Processing status before it can be picked up.', 'kiriminaja-official' );
	}
}
