<?php

namespace KiriminAjaOfficial\Services;

use KiriminAjaOfficial\Services\TransactionProcessServices\RecipientDataResolver;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts pickup-detail query results into the Svelte workspace contract.
 */
class PickupDetailPageData {
	private RecipientDataResolver $recipient_resolver;
	private ShipmentLocationService $location_service;

	public function __construct(
		?RecipientDataResolver $recipient_resolver = null,
		?ShipmentLocationService $location_service = null
	) {
		$this->recipient_resolver = $recipient_resolver ?? new RecipientDataResolver();
		$this->location_service   = $location_service ?? new ShipmentLocationService();
	}

	/**
	 * @param array<string,mixed> $payment_data Payment summary.
	 * @param array<int,object>   $transactions Transactions in this pickup.
	 * @return array<string,mixed>
	 */
	public function prepare( array $payment_data, array $transactions, string $back_url, string $print_error = '' ): array {
		$print_nonce = wp_create_nonce( 'kiriof_resi_print' );
		$print_ids   = array();
		$rows        = array();

		foreach ( $transactions as $index => $transaction ) {
			$order_id = (string) ( $transaction->order_id ?? '' );
			$awb      = (string) ( $transaction->awb ?? '' );
			if ( '' !== $awb ) {
				$print_ids[] = $order_id;
			}

			$wc_order      = function_exists( 'wc_get_order' ) ? wc_get_order( (int) ( $transaction->wp_wc_order_stat_order_id ?? 0 ) ) : false;
			$shipping_info = json_decode( (string) ( $transaction->shipping_info ?? '{}' ) );
			$recipient     = $this->recipient_resolver->resolve( $wc_order, $shipping_info, $transaction );
			$billing       = $wc_order && method_exists( $wc_order, 'get_address' ) ? (array) $wc_order->get_address( 'billing' ) : array();
			$customer_name = trim( (string) ( $billing['first_name'] ?? '' ) . ' ' . (string) ( $billing['last_name'] ?? '' ) );
			$recipient_name = trim( $recipient['first_name'] . ' ' . $recipient['last_name'] );
			if ( '' === $customer_name ) {
				$customer_name = $recipient_name;
			}
			if ( '' === $recipient_name ) {
				$recipient_name = $customer_name;
			}

			$origin_snapshot = json_decode( (string) ( $transaction->shipment_location_snapshot ?? '{}' ), true );
			$origin_snapshot = is_array( $origin_snapshot ) ? $origin_snapshot : array();
			$origin_name = trim( (string) ( $origin_snapshot['origin_name'] ?? $origin_snapshot['location_name'] ?? $origin_snapshot['name'] ?? '' ) );
			if ( '' === $origin_name && ! empty( $transaction->shipment_location_id ) ) {
				$location = $this->location_service->repository()->getById( (int) $transaction->shipment_location_id );
				$origin_name = trim( (string) ( $location->name ?? $location->location_name ?? '' ) );
			}
			if ( '' === $origin_name ) {
				$origin_name = __( 'Default origin', 'kiriminaja-official' );
			}

			$address_lines = array_values(
				array_filter(
					array(
						(string) $recipient['address_1'],
						(string) $recipient['address_2'],
						implode( ', ', array_filter( array( (string) ( $transaction->destination_sub_district ?? '' ), $recipient['city'], $recipient['state'] ) ) ),
						implode( ', ', array_filter( array( $recipient['postcode'], $recipient['country'] ) ) ),
					)
				)
			);

			$shipping_cost    = (float) ( $transaction->shipping_cost ?? 0 );
			$insurance_cost   = (float) ( $transaction->insurance_cost ?? 0 );
			$cod_fee          = (float) ( $transaction->cod_fee ?? 0 );
			$discount         = (float) ( $transaction->discount_amount ?? 0 );
			$transaction_value = (float) ( $transaction->transaction_value ?? 0 );
			$total             = max( 0, $shipping_cost + $insurance_cost + $cod_fee - $discount );
			$cod_value         = $shipping_cost + $insurance_cost + ( $cod_fee > 0 ? $cod_fee + $transaction_value : 0 );
			$raw_status        = (string) ( $transaction->raw_status ?? $transaction->status ?? '' );
			$status_label      = isset( $transaction->raw_status ) ? (string) ( $transaction->status ?? '' ) : kiriof_helper()->transactionStatusLabel( $raw_status );

			$rows[] = array(
				'number'       => $index + 1,
				'orderId'      => $order_id,
				'orderUrl'     => admin_url( 'post.php?post=' . absint( $transaction->wp_wc_order_stat_order_id ?? 0 ) . '&action=edit' ),
				'customer'     => array(
					'name'         => $customer_name,
					'phone'        => (string) $recipient['phone'],
					'paymentLabel' => $cod_fee > 0 ? __( 'COD', 'kiriminaja-official' ) : __( 'Non-COD', 'kiriminaja-official' ),
				),
				'courier'      => array(
					'code'    => strtolower( (string) ( $transaction->service ?? '' ) ),
					'service' => kiriof_helper()->formatServiceName( $transaction->service ?? '', $transaction->service_name ?? '' ),
				),
				'awb'          => $awb,
				'route'        => array(
					'origin'       => $origin_name,
					'recipient'    => $recipient_name,
					'addressLines' => $address_lines,
				),
				'package'      => array(
					'weight'        => (float) ( $transaction->weight ?? 0 ),
					'shipping'      => $shipping_cost,
					'insurance'     => $insurance_cost,
					'codFee'        => $cod_fee,
					'discount'      => $discount,
					'total'         => $total,
					'codValue'      => $cod_value,
				),
				'status'       => array(
					'label' => $status_label,
					'tone'  => $this->statusTone( $raw_status ),
				),
				'printUrl'     => '' !== $awb ? admin_url( 'admin-post.php?action=kiriof_resi_print&oids=' . rawurlencode( $order_id ) . '&_wpnonce=' . $print_nonce ) : '',
			);
		}

		$print_all_url = empty( $print_ids )
			? ''
			: admin_url( 'admin-post.php?action=kiriof_resi_print&oids=' . implode( ',', array_map( 'rawurlencode', $print_ids ) ) . '&_wpnonce=' . $print_nonce );

		return array(
			'toolbar'     => array(
				'logoUrl'   => KIRIOF_URL . 'assets/admin/img/icon-128x128.png',
				'rootUrl'   => $back_url,
				'rootLabel' => __( 'Payments', 'kiriminaja-official' ),
				'title'     => (string) ( $payment_data['pickup_number'] ?? '' ),
			),
			'summary'     => array(
				array( 'value' => (int) ( $payment_data['package_count'] ?? 0 ), 'label' => __( 'Total Packages', 'kiriminaja-official' ) ),
				array( 'value' => (int) ( $payment_data['cod_count'] ?? 0 ), 'label' => __( 'Cash on Delivery', 'kiriminaja-official' ) ),
				array( 'value' => (int) ( $payment_data['non_cod_count'] ?? 0 ), 'label' => __( 'Non-COD', 'kiriminaja-official' ) ),
			),
			'rows'        => $rows,
			'schedule'    => (string) ( $payment_data['schedule'] ?? '' ),
			'printAllUrl' => $print_all_url,
			'printError'  => $print_error,
			'i18n'        => array(
				'order'        => __( 'Order / Transaction', 'kiriminaja-official' ),
				'courier'      => __( 'Courier & Service', 'kiriminaja-official' ),
				'airwaybill'   => __( 'Airwaybill / Order ID', 'kiriminaja-official' ),
				'route'        => __( 'Shipment Route', 'kiriminaja-official' ),
				'packages'     => __( 'Packages & Fee', 'kiriminaja-official' ),
				'codValue'     => __( 'COD Value', 'kiriminaja-official' ),
				'status'       => __( 'Status', 'kiriminaja-official' ),
				'action'       => __( 'Action', 'kiriminaja-official' ),
				'pickup'       => __( 'Pickup', 'kiriminaja-official' ),
				'print'        => __( 'Print', 'kiriminaja-official' ),
				'detail'       => __( 'Detail', 'kiriminaja-official' ),
				'printAll'     => __( 'Print All', 'kiriminaja-official' ),
				'empty'        => __( 'Not Found', 'kiriminaja-official' ),
				'weight'       => __( 'Weight', 'kiriminaja-official' ),
				'shipping'     => __( 'Shipping', 'kiriminaja-official' ),
				'insurance'    => __( 'Insurance', 'kiriminaja-official' ),
				'codFee'       => __( 'COD Fee', 'kiriminaja-official' ),
				'discount'     => __( 'Discount', 'kiriminaja-official' ),
				'total'        => __( 'Total', 'kiriminaja-official' ),
			),
		);
	}

	private function statusTone( string $status ): string {
		if ( in_array( $status, array( 'finished', 'shipped' ), true ) ) {
			return 'success';
		}
		if ( in_array( $status, array( 'rejected', 'canceled', 'return', 'returned' ), true ) ) {
			return 'warning';
		}
		if ( in_array( $status, array( 'request_pickup', 'pending', 'new' ), true ) ) {
			return 'info';
		}
		return 'neutral';
	}
}
