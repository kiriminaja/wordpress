<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use KiriminAjaOfficial\Infrastructure\InstantApiTransport;
use KiriminAja\Models\ShippingPriceInstantData;
use KiriminAja\Responses\ServiceResponse;
use KiriminAja\Services\KiriminAja;
use KiriminAjaOfficial\Base\KiriminAjaApi;

/** Official SDK adapter for Instant delivery; never retries remote operations. */
class InstantDeliveryApiRepository extends KiriminAjaApi {
	public function tracking( string $order_id ): array {
		if ( ! $this->valid_order_id( $order_id ) ) {
			return $this->failure( 'Invalid Instant order ID.' );
		}
		// The SDK tracking wrapper discards the documented code-2/null result.
		// Bypass the inherited error logger: upstream errors can contain PII.
		try {
			[ $transport, $body ] = ( new InstantApiTransport() )->get( 'api/mitra/v4/instant/tracking/' . rawurlencode( $order_id ) );
			$body = $this->lifecycle_body( $body );
			if ( true !== $transport || null === $body ) {
				return $this->failure( 'Instant tracking failed.' );
			}
			if ( is_bool( $body->status ?? null ) && in_array( $body->code ?? null, array( 2, '2' ), true ) && property_exists( $body, 'result' ) && null === $body->result ) {
				return array( 'status' => false, 'data' => 'Instant tracking data was not found.', 'not_found' => true );
			}
			if ( true !== ( $body->status ?? null ) || ! in_array( $body->code ?? null, array( 0, '0' ), true ) || ! is_object( $body->result ?? null ) || $order_id !== ( $body->result->order_id ?? null ) ) {
				return $this->failure( 'Instant tracking failed.' );
			}
			return array( 'status' => true, 'data' => $body );
		} catch ( \Throwable $throwable ) {
			return $this->failure( 'Instant tracking failed.' );
		}
	}

	public function cancel( string $order_id ): array {
		if ( ! $this->valid_order_id( $order_id ) ) {
			return $this->failure( 'Invalid Instant order ID.' );
		}
		try {
			[ $transport, $body ] = ( new InstantApiTransport() )->delete( 'api/mitra/v4/instant/pickup/void/' . rawurlencode( $order_id ) );
			$body = $this->lifecycle_body( $body );
			if ( true !== $transport || null === $body || true !== ( $body->status ?? null ) || ! in_array( $body->code ?? null, array( 0, '0' ), true ) || ! is_object( $body->result ?? null ) ) {
				return $this->failure( 'Instant cancellation failed.' );
			}
			$packages = $body->result->packages ?? null;
			if ( ! is_array( $packages ) || ! array_is_list( $packages ) || 1 !== count( $packages ) || ! is_object( $packages[0] ) || $order_id !== ( $packages[0]->order_id ?? null ) || ! in_array( $packages[0]->service ?? null, array( 'gosend', 'grab_express' ), true ) ) {
				return $this->failure( 'Instant cancellation failed.' );
			}
			// A successful void operation can still report status 105. Do not
			// rewrite its remote status or assert that tracking says "cancelled".
			// The caller must also verify this package's service against its row.
			return array( 'status' => true, 'data' => $body, 'operation_accepted' => true );
		} catch ( \Throwable $throwable ) {
			return $this->failure( 'Instant cancellation failed.' );
		}
	}

	private function valid_order_id( string $order_id ): bool {
		return 1 === preg_match( '/\A[A-Za-z0-9][A-Za-z0-9_-]{0,99}\z/', $order_id );
	}

	/** Normalize SDK associative bodies without accepting scalar/list bodies. */
	private function lifecycle_body( $body ): ?object {
		if ( ! is_object( $body ) && ( ! is_array( $body ) || array_is_list( $body ) ) ) {
			return null;
		}
		$code = is_array( $body ) ? ( $body['code'] ?? null ) : ( $body->code ?? null );
		// JSON normalization must not turn floating-point zero into code 0.
		if ( ! is_int( $code ) && ! is_string( $code ) ) {
			return null;
		}
		$normalized = json_decode( wp_json_encode( $body ) );
		return is_object( $normalized ) ? $normalized : null;
	}

	public function price( array $payload ): array {
		if ( empty( $payload['service'] ) || ! is_array( $payload['service'] ) || array_diff( $payload['service'], array( 'gosend', 'grab_express' ) ) ) {
			return $this->failure( 'Unsupported Instant courier.' );
		}
		foreach ( array( 'origin', 'destination' ) as $location ) {
			if ( ! $this->valid_location( $payload[ $location ] ?? null ) ) {
				return $this->failure( 'Instant locations require an address and coordinates.' );
			}
		}
		if ( ! isset( $payload['item_price'], $payload['weight'] ) || ! is_numeric( $payload['item_price'] ) || ! is_numeric( $payload['weight'] ) || $payload['item_price'] < 0 || $payload['weight'] <= 0 ) {
			return $this->failure( 'Invalid Instant item price or weight.' );
		}

		// ModelBase has no constructor mapping: populate the declared SDK properties.
		return $this->call_sdk(
			static function () use ( $payload ) {
				$data              = new ShippingPriceInstantData();
				$data->service     = array_values( $payload['service'] );
				$data->origin      = $payload['origin'];
				$data->destination = $payload['destination'];
				$data->item_price  = (int) $payload['item_price'];
				$data->weight      = (int) $payload['weight'];
				$data->vehicle     = (string) ( $payload['vehicle'] ?? $data->vehicle );
				$data->timezone    = (string) ( $payload['timezone'] ?? $data->timezone );
				return KiriminAja::getPriceInstant( $data );
			},
			static fn( $data, $message ) => array( 'status' => true, 'text' => $message, 'result' => $data )
		);
	}

	public function book( array $payload ): array {
		if ( isset( $payload['origin'] ) || ! $this->valid_location( $payload, 'latitude', 'longitude' ) ) {
			return $this->failure( 'Instant origin requires an address and coordinates.' );
		}
		foreach ( array( 'name', 'phone' ) as $field ) {
			if ( ! is_string( $payload[ $field ] ?? null ) || '' === trim( $payload[ $field ] ) ) {
				return $this->failure( 'Instant origin requires a name and phone.' );
			}
		}
		if ( array_key_exists( 'payment_method', $payload ) && ! in_array( $payload['payment_method'], array( 'credit', 'cash' ), true ) ) {
			return $this->failure( 'Unsupported Instant payment method.' );
		}
		if ( 'credit' === ( $payload['payment_method'] ?? null ) && ( ! is_string( $payload['pin'] ?? null ) || ! preg_match( '/\A[0-9]{6}\z/', $payload['pin'] ) ) ) {
			return $this->failure( 'A six-digit PIN is required for KA Credit.' );
		}
		$packages = $payload['packages'] ?? null;
		if ( ! is_array( $packages ) || ! array_is_list( $packages ) || count( $packages ) < 1 || count( $packages ) > 10 ) {
			return $this->failure( 'Instant booking requires 1 to 10 packages.' );
		}
		foreach ( $packages as $package ) {
			if ( ! is_array( $package ) ) {
				return $this->failure( 'Invalid Instant package.' );
			}
			if ( ! in_array( $package['service'] ?? null, array( 'gosend', 'grab_express' ), true ) ) {
				return $this->failure( 'Unsupported Instant courier.' );
			}
			if ( ! $this->valid_location( $package['destination'] ?? null, 'latitude', 'longitude' ) ) {
				return $this->failure( 'Instant destinations require an address and coordinates.' );
			}
			foreach ( array( 'name', 'phone' ) as $field ) {
				if ( ! is_string( $package['destination'][ $field ] ?? null ) || '' === trim( $package['destination'][ $field ] ) ) {
					return $this->failure( 'Instant destinations require a name and phone.' );
				}
			}
		}

		// The inherited post() logs remote errors, which may echo the PIN.
		// Use the bounded Instant transport directly; never retry or expose errors.
		try {
			[ $transport, $body ] = ( new InstantApiTransport() )->post( 'api/mitra/v6.2/instant/request_pickup', $payload );
			if ( true === $transport && $this->booking_rejected( $body ) ) {
				// Never expose upstream text or extra fields: they can echo the PIN.
				return array( 'status' => false, 'data' => (object) array( 'status' => false, 'result' => (object) array() ), 'operation_rejected' => true );
			}
			if ( ! $transport || ! is_array( $body ) || true !== ( $body['status'] ?? null ) ) {
				return $this->failure( 'Instant booking failed.' );
			}
			return array( 'status' => true, 'data' => json_decode( wp_json_encode( $body ) ) );
		} catch ( \Throwable $throwable ) {
			return $this->failure( 'Instant booking failed.' );
		}
	}

	/** A negative status alone (including a null result) is not proof of rejection. */
	private function booking_rejected( $body ): bool {
		if ( ! is_array( $body ) || array_is_list( $body ) || false !== ( $body['status'] ?? null ) ) {
			return false;
		}
		if ( array_key_exists( 'code', $body ) && ! is_int( $body['code'] ) && ! is_string( $body['code'] ) ) {
			return false;
		}
		$has_result = false;
		foreach ( array( 'result', 'results' ) as $field ) {
			if ( ! array_key_exists( $field, $body ) ) {
				continue;
			}
			$result = $body[ $field ];
			if ( ( ! is_array( $result ) && ! is_object( $result ) ) || array() !== (array) $result ) {
				return false;
			}
			$has_result = true;
		}
		// Contradictory identities or unknown nested data must remain ambiguous.
		foreach ( $body as $field => $value ) {
			if ( in_array( $field, array( 'status', 'result', 'results' ), true ) ) {
				continue;
			}
			if ( is_array( $value ) || is_object( $value ) || ( in_array( $field, array( 'id', 'order_id', 'payment_id', 'payment', 'packages', 'awb' ), true ) && null !== $value && '' !== $value ) ) {
				return false;
			}
		}
		return $has_result;
	}

	public function payment( string $id ): array {
		if ( '' === trim( $id ) ) {
			return $this->failure( 'Instant payment ID is required.' );
		}
		// SDK true explicitly selects GetPaymentInstantService, whose payload is result.
		return $this->call_sdk(
			static fn() => KiriminAja::getPayment( $id, true ),
			static fn( $data, $message ) => array( 'status' => true, 'text' => $message, 'result' => $data )
		);
	}

	public function profile(): array {
		return $this->call_sdk(
			static fn() => KiriminAja::getProfile(),
			static fn( $data, $message ) => array( 'status' => true, 'text' => $message, 'results' => $data )
		);
	}

	public function validateCredit( string $pin, int|float $amount ): array {
		if ( ! preg_match( '/\A[0-9]{6}\z/', $pin ) || ! is_finite( (float) $amount ) || $amount < 0 ) {
			return $this->failure( 'A six-digit PIN and non-negative amount are required.' );
		}
		$profile = $this->profile();
		if ( ! $profile['status'] ) {
			return $this->failure( 'Unable to verify credit eligibility.' );
		}
		$metadata = $profile['data']->results->metadata ?? null;
		if ( 'TOP' === strtoupper( (string) ( $metadata->payment_method ?? '' ) ) || ! empty( $profile['data']->results->is_top ) ) {
			return $this->failure( 'KA Credit is unavailable to TOP accounts.' );
		}

		// Official OpenAPI: POST /api/mitra/v6.2/pin/validate.
		// Installed SDK has no validatePin facade. Use its transport without the
		// inherited request logger: a remote error may echo the confidential PIN.
		// The remote attempt/max_attempt/lock_until state is authoritative.
		$validation = $this->call_sdk(
			static function () use ( $pin ) {
				try {
					[ $transport, $body ] = ( new InstantApiTransport() )->post( 'api/mitra/v6.2/pin/validate', array( 'pin' => $pin ) );
					return new ServiceResponse( $transport && is_array( $body ) && true === ( $body['status'] ?? null ), 'PIN validation failed.', is_array( $body ) ? $body : null );
				} catch ( \Throwable $throwable ) {
					return new ServiceResponse( false, 'PIN validation failed.', null );
				}
			},
			static fn( $data ) => array( 'status' => true, 'result' => $data )
		);
		if ( ! $validation['status'] ) {
			return $validation;
		}
		$balance = $this->call_sdk(
			static fn() => KiriminAja::getCreditBalance(),
			static fn( $data ) => array( 'status' => true, 'results' => $data )
		);
		if ( ! $balance['status'] ) {
			return $this->failure( 'Unable to verify credit balance.' );
		}
		$available = $balance['data']->results->balance ?? null;
		if ( ! is_numeric( $available ) || ! is_finite( (float) $available ) || (float) $available < $amount ) {
			return $this->failure( 'Insufficient KA Credit balance.' );
		}
		return $balance;
	}

	private function valid_location( $location, string $latitude = 'lat', string $longitude = 'long' ): bool {
		return is_array( $location ) && is_string( $location['address'] ?? null ) && '' !== trim( $location['address'] )
			&& is_numeric( $location[ $latitude ] ?? null ) && is_numeric( $location[ $longitude ] ?? null )
			&& is_finite( (float) $location[ $latitude ] ) && is_finite( (float) $location[ $longitude ] )
			&& abs( (float) $location[ $latitude ] ) <= 90 && abs( (float) $location[ $longitude ] ) <= 180;
	}

	private function failure( string $message ): array {
		return array( 'status' => false, 'data' => $message );
	}
}
