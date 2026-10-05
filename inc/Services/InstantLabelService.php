<?php
namespace KiriminAjaOfficial\Services;

use InvalidArgumentException;
use KiriminAjaOfficial\Repositories\TransactionRepository;
use KiriminAjaOfficial\Services\TransactionProcessServices\RecipientDataResolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Read-only, local labels for already booked supported Instant shipments. */
class InstantLabelService {
	private TransactionRepository $repo;
	private RecipientDataResolver $recipient_resolver;
	private TransactionOriginResolver $origin_resolver;

	public function __construct( TransactionRepository $repo, ?RecipientDataResolver $recipient_resolver = null, ?TransactionOriginResolver $origin_resolver = null ) {
		$this->repo               = $repo;
		$this->recipient_resolver = $recipient_resolver ?? new RecipientDataResolver();
		// Origin display must never seed a warehouse or overlay a changed location.
		$this->origin_resolver = $origin_resolver ?? new TransactionOriginResolver(
			new class() extends ShipmentLocationService {
				public function __construct() {}
				public function getLocationOrDefault( $location_id ) { return null; }
			}
		);
	}

	/** Fail-closed row guard using the same booked snapshots as prepare(). */
	public static function canPrint( $row ): bool {
		$row = is_array( $row ) ? (object) $row : $row;
		if ( ! is_object( $row ) ) {
			return false;
		}
		// Never treat malformed or control-character identities as printable AWBs.
		if ( ! is_string( $row->awb ?? null ) || ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]{0,99}$/D', $row->awb ) ) {
			return false;
		}
		$awb = $row->awb;
		$snapshot = json_decode( (string) ( $row->shipping_info ?? '' ) );
		$origin = json_decode( (string) ( $row->shipment_location_snapshot ?? '' ), true );
		if ( ! is_object( $snapshot ) || empty( self::booked_items( $snapshot ) ) || ! is_array( $origin )
			|| '' === trim( (string) ( $origin['origin_name'] ?? $origin['location_name'] ?? $origin['name'] ?? '' ) )
			|| '' === trim( (string) ( $origin['origin_address'] ?? $origin['address'] ?? $origin['address_1'] ?? '' ) ) ) {
			return false;
		}
		$recipient = ( new RecipientDataResolver() )->resolve( false, $snapshot, $row );
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) ( $row->wp_wc_order_stat_order_id ?? 0 ) ) : false;
		return $order && in_array( $order->get_status(), array( 'processing', 'completed' ), true )
			&& '' !== trim( $recipient['first_name'] . ' ' . $recipient['last_name'] ) && '' !== $recipient['address_1']
			&& preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]{0,99}$/D', (string) ( $row->order_id ?? '' ) )
			&& 'instant' === TransactionDeliveryType::resolve( $row )
			&& in_array( strtolower( trim( (string) ( $row->service ?? '' ) ) ), array( 'gosend', 'grab_express' ), true )
			&& in_array( $row->status ?? '', array( 'request_pickup', 'shipped', 'finished' ), true )
			&& ! in_array( (int) ( $row->instant_status_code ?? 0 ), array( 300, 302, 350 ), true )
			&& '' !== $awb && '-' !== $awb;
	}

	/**
	 * Validate the whole batch before returning any printable data.
	 *
	 * @param array $ids KiriminAja order IDs, not WooCommerce IDs.
	 * @return array<int,array<string,mixed>> Prepared local labels in request order.
	 * @throws InvalidArgumentException When any requested shipment is ineligible.
	 */
	public function prepare( array $ids ): array {
		if ( count( $ids ) < 1 || count( $ids ) > 50 ) {
			throw new InvalidArgumentException( esc_html__( 'Select between 1 and 50 Instant shipments.', 'kiriminaja-official' ) );
		}
		$normalized = array();
		foreach ( $ids as $id ) {
			if ( ! is_string( $id ) && ! is_int( $id ) ) {
				throw new InvalidArgumentException( esc_html__( 'Invalid Instant shipment ID.', 'kiriminaja-official' ) );
			}
			$id = trim( (string) $id );
			if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]{0,99}$/D', $id ) || sanitize_text_field( $id ) !== $id ) {
				throw new InvalidArgumentException( esc_html__( 'Invalid Instant shipment ID.', 'kiriminaja-official' ) );
			}
			$normalized[] = $id;
		}
		$normalized = array_values( array_unique( $normalized ) );
		$rows       = $this->repo->getTransactionByOrderIds( $normalized );
		if ( ! is_array( $rows ) || count( $rows ) !== count( $normalized ) ) {
			throw new InvalidArgumentException( esc_html__( 'Every requested Instant shipment must exist.', 'kiriminaja-official' ) );
		}
		$indexed = array();
		foreach ( $rows as $row ) {
			$id = is_object( $row ) ? (string) ( $row->order_id ?? '' ) : '';
			if ( ! in_array( $id, $normalized, true ) || isset( $indexed[ $id ] ) ) {
				throw new InvalidArgumentException( esc_html__( 'Every requested Instant shipment must exist exactly once.', 'kiriminaja-official' ) );
			}
			$indexed[ $id ] = $row;
		}
		$labels = array();
		$awbs   = array();
		foreach ( $normalized as $id ) {
			$label = $this->prepare_label( $indexed[ $id ] );
			if ( isset( $awbs[ $label['awb'] ] ) ) {
				throw new InvalidArgumentException( esc_html__( 'Each requested Instant shipment must have a distinct AWB.', 'kiriminaja-official' ) );
			}
			$awbs[ $label['awb'] ] = true;
			$labels[]            = $label;
		}
		return $labels;
	}

	/** Validate the nonempty JSON list persisted by InstantDispatchService. */
	private static function booked_items( $snapshot ): array {
		$items = is_object( $snapshot ) ? ( $snapshot->instant_items ?? null ) : null;
		if ( ! is_array( $items ) || empty( $items ) || ! array_is_list( $items ) ) {
			return array();
		}
		$result = array();
		foreach ( $items as $item ) {
			// At least one gram per unit and a 40000g dispatch limit bound quantity.
			if ( ! is_object( $item ) || ! is_string( $item->name ?? null ) || '' === trim( $item->name )
				|| ! is_int( $item->qty ?? null ) || $item->qty < 1 || $item->qty > 40000 ) {
				return array();
			}
			$result[] = array( 'name' => $item->name, 'quantity' => $item->qty );
		}
		return $result;
	}

	/** @return array<string,mixed> */
	private function prepare_label( object $row ): array {
		if ( ! self::canPrint( $row ) ) {
			throw new InvalidArgumentException( esc_html__( 'Local labels require a booked, non-cancelled GoSend or Grab Instant shipment with an AWB.', 'kiriminaja-official' ) );
		}
		$awb = $row->awb;
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) ( $row->wp_wc_order_stat_order_id ?? 0 ) ) : false;
		if ( ! $order || ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			throw new InvalidArgumentException( esc_html__( 'The WooCommerce order is not eligible for shipping.', 'kiriminaja-official' ) );
		}
		$snapshot = json_decode( (string) ( $row->shipping_info ?? '' ) );
		// Dispatch persists physical items; never consult mutable WooCommerce products.
		$items = self::booked_items( $snapshot );
		$origin   = json_decode( (string) ( $row->shipment_location_snapshot ?? '' ), true );
		if ( ! is_object( $snapshot ) || ! is_array( $origin ) || empty( $origin ) ) {
			throw new InvalidArgumentException( esc_html__( 'Booked shipment address snapshots are required for a local label.', 'kiriminaja-official' ) );
		}
		// Never overlay the booked destination with a post-dispatch WooCommerce edit.
		$recipient = $this->recipient_resolver->resolve( false, $snapshot, $row );
		if ( '' === trim( $recipient['first_name'] . ' ' . $recipient['last_name'] ) || '' === $recipient['address_1'] ) {
			throw new InvalidArgumentException( esc_html__( 'The booked destination snapshot is incomplete.', 'kiriminaja-official' ) );
		}
		$origin_name    = trim( (string) ( $origin['origin_name'] ?? $origin['location_name'] ?? $origin['name'] ?? '' ) );
		$origin_address = trim( (string) ( $origin['origin_address'] ?? $origin['address'] ?? $origin['address_1'] ?? '' ) );
		if ( '' === $origin_name || '' === $origin_address ) {
			throw new InvalidArgumentException( esc_html__( 'The booked origin snapshot is incomplete.', 'kiriminaja-official' ) );
		}
		$sender = $this->origin_resolver->resolve( $row );
		return array(
			'order_id'       => (string) $row->order_id,
			'wc_reference'   => (string) $order->get_order_number(),
			'awb'            => $awb,
			'courier'        => 'gosend' === strtolower( trim( $row->service ) ) ? 'GoSend' : 'Grab Express',
			'service'        => (string) ( $row->service_name ?? '' ),
			'vehicle'        => TransactionDeliveryType::normalizeVehicle( $row->vehicle ?? null ) ?? '',
			'sender'         => array( 'name' => $sender['name'], 'phone' => $sender['phone'], 'address' => $sender['addressLines'] ),
			'recipient'      => array(
				'name'    => trim( $recipient['first_name'] . ' ' . $recipient['last_name'] ),
				'phone'   => $recipient['phone'],
				'address' => array_values( array_filter( array( $recipient['address_1'], $recipient['address_2'], (string) ( $row->destination_sub_district ?? '' ), $recipient['city'], $recipient['state'], $recipient['postcode'], $recipient['country'] ), static fn( $value ) => '' !== $value ) ),
			),
			'weight'         => (int) ( $row->weight ?? 0 ),
			'items'          => $items,
			'payment_method' => (string) ( $row->instant_payment_method ?? '' ),
			'payment_status' => (string) ( $row->instant_payment_status ?? '' ),
			'fee'            => (float) ( $row->shipping_cost ?? 0 ),
		);
	}
}
