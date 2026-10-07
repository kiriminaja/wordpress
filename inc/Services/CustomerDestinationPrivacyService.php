<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Account destination privacy callbacks, invoked by WordPress's verified request workflow.
 *
 * WooCommerce owns order lookup, pagination, authorization and native address erasure.
 * Durable booking snapshots and custom transaction rows require an administrator's
 * fulfillment/financial retention review; these callbacks never cancel shipments.
 */
final class CustomerDestinationPrivacyService {
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
		add_filter( 'woocommerce_privacy_export_order_personal_data', array( $this, 'exportOrder' ), 10, 2 );
		add_action( 'woocommerce_privacy_remove_order_personal_data', array( $this, 'eraseOrder' ), 10, 1 );
	}

	public function exporters( array $exporters ): array {
		$exporters['kiriof-customer-destination'] = array(
			'exporter_friendly_name' => __( 'KiriminAja shipping destination', 'kiriminaja-official' ),
			'callback'               => array( $this, 'exportUser' ),
		);
		return $exporters;
	}

	public function erasers( array $erasers ): array {
		$erasers['kiriof-customer-destination'] = array(
			'eraser_friendly_name' => __( 'KiriminAja shipping destination', 'kiriminaja-official' ),
			'callback'             => array( $this, 'eraseUser' ),
		);
		return $erasers;
	}

	/** The requester may be an administrator, not the account owner. WP verifies requests. */
	public function exportUser( $email_address, $page = 1 ): array {
		$result = array( 'data' => array(), 'done' => true );
		$user   = 1 === (int) $page ? get_user_by( 'email', $email_address ) : false;
		if ( ! $user || (int) $user->ID < 1 ) {
			return $result;
		}
		$data = array();
		foreach ( $this->keys() as $key => $label ) {
			$this->addValue( $data, $label, get_user_meta( $user->ID, $key, true ) );
		}
		if ( $data ) {
			$result['data'][] = array(
				'group_id'    => 'kiriof-customer-destination',
				'group_label' => __( 'KiriminAja shipping destination', 'kiriminaja-official' ),
				'item_id'     => 'kiriof-customer-' . (int) $user->ID,
				'data'        => $data,
			);
		}
		return $result;
	}

	public function eraseUser( $email_address, $page = 1 ): array {
		$result = array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		$user   = 1 === (int) $page ? get_user_by( 'email', $email_address ) : false;
		if ( ! $user || (int) $user->ID < 1 ) {
			return $result;
		}
		foreach ( $this->keys() as $key => $label ) {
			if ( delete_user_meta( $user->ID, $key ) ) {
				$result['items_removed'] = true;
			}
		}
		// No unbounded order/transaction query, and no inference from another user's email.
		// Conservatively report the unprocessed business-data scope, even on repeat requests.
		$result['items_retained'] = true;
		$result['messages'][] = $this->retentionMessage();
		return $result;
	}

	/** Adds only buyer-owned destination/contact fields, never quote tokens or credentials. */
	public function exportOrder( array $personal_data, $order ): array {
		if ( ! is_object( $order ) || ! is_callable( array( $order, 'get_meta' ) ) ) {
			return $personal_data;
		}
		foreach ( $this->keys() as $key => $label ) {
			$this->addValue( $personal_data, $label, $order->get_meta( $key, true ) );
		}
		$snapshot = $order->get_meta( '_kiriof_instant_checkout_snapshot', true );
		if ( is_array( $snapshot ) && isset( $snapshot['context'] ) && is_array( $snapshot['context'] ) ) {
			$this->addValue( $personal_data, __( 'KiriminAja retained booking destination', 'kiriminaja-official' ), $snapshot['context']['destination'] ?? array() );
			$this->addValue( $personal_data, __( 'KiriminAja retained booking recipient', 'kiriminaja-official' ), $snapshot['context']['recipient'] ?? array() );
		}
		return $personal_data;
	}

	/** WooCommerce calls this only when its order erasure policy permits erasure. */
	public function eraseOrder( $order ): void {
		if ( ! is_object( $order ) || ! is_callable( array( $order, 'delete_meta_data' ) ) || ! is_callable( array( $order, 'save_meta_data' ) ) ) {
			return;
		}
		foreach ( $this->keys() as $key => $label ) {
			$order->delete_meta_data( $key );
		}
		$order->save_meta_data();
		// Do not erase booking snapshots, hashes, invoices or financial/transaction data.
	}

	private function retentionMessage(): string {
		return __( 'KiriminAja account destination metadata has been processed. Order erasure is managed separately by WooCommerce. Durable booking snapshots and shipment transaction records are not erased by this account callback and may contain recipient addresses, phone numbers and coordinates. An administrator must review fulfillment and applicable financial/legal retention requirements before removing those records. No shipment has been cancelled.', 'kiriminaja-official' );
	}

	/** Explicit plugin-only allowlist: native shipping and all billing fields are untouched. */
	private function keys(): array {
		$keys = array(
			BuyerDestination::META_KEY => __( 'KiriminAja shipping destination', 'kiriminaja-official' ),
			BuyerDestination::COORDINATE_META_KEY => __( 'KiriminAja destination coordinates', 'kiriminaja-official' ),
			CustomerShippingDestinationService::ADDRESS_META_KEY => __( 'KiriminAja destination address binding', 'kiriminaja-official' ),
		);
		foreach ( array( 'shipping_', 'shipping_kiriminaja-official/', '_wc_shipping/kiriminaja-official/' ) as $prefix ) {
			$keys[ $prefix . 'kiriof_destination_area' ] = __( 'KiriminAja shipping district ID', 'kiriminaja-official' ) . ' (' . $prefix . ')';
			$keys[ $prefix . 'kiriof_destination_area_name' ] = __( 'KiriminAja shipping district', 'kiriminaja-official' ) . ' (' . $prefix . ')';
		}
		return $keys;
	}

	private function addValue( array &$data, string $label, $value ): void {
		$value = $this->plainValue( $value );
		if ( '' === $value || null === $value || array() === $value ) {
			return;
		}
		$data[] = array(
			'name'  => $label,
			'value' => is_array( $value ) ? wp_json_encode( $value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) : (string) $value,
		);
	}

	/** Malformed metadata must not export embedded credentials or executable markup. */
	private function plainValue( $value ) {
		if ( is_scalar( $value ) ) {
			return sanitize_text_field( (string) $value );
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		$result = array();
		$fields = array_merge( BuyerDestination::ADDRESS_FIELDS, array( 'district_id', 'district_label', 'address_type', 'version', 'destination_latitude', 'destination_longitude', 'latitude', 'longitude', 'first_name', 'last_name', 'phone', 'name', 'address', 'zipcode' ) );
		foreach ( $fields as $field ) {
			if ( isset( $value[ $field ] ) && is_scalar( $value[ $field ] ) ) {
				$result[ $field ] = sanitize_text_field( (string) $value[ $field ] );
			}
		}
		if ( isset( $value['shipping_address'] ) && is_array( $value['shipping_address'] ) ) {
			$result['shipping_address'] = $this->plainValue( array_intersect_key( $value['shipping_address'], array_flip( BuyerDestination::ADDRESS_FIELDS ) ) );
		}
		return $result;
	}
}
