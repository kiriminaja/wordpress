<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use KiriminAja\Base\Api\Api;
use KiriminAja\Models\ShippingPriceInstantData;
use KiriminAja\Responses\ServiceResponse;
use KiriminAja\Services\KiriminAja;
use KiriminAjaOfficial\Base\KiriminAjaApi;

/** Official SDK adapter for Instant delivery; never retries remote operations. */
class InstantDeliveryApiRepository extends KiriminAjaApi {
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
		// Use the configured SDK transport directly; never retry or expose errors.
		try {
			[ $transport, $body ] = ( new Api() )->post( 'api/mitra/v6.2/instant/request_pickup', $payload );
			if ( ! $transport || ! is_array( $body ) || true !== ( $body['status'] ?? null ) ) {
				return $this->failure( 'Instant booking failed.' );
			}
			return array( 'status' => true, 'data' => json_decode( wp_json_encode( $body ) ) );
		} catch ( \Throwable $throwable ) {
			return $this->failure( 'Instant booking failed.' );
		}
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
					[ $transport, $body ] = ( new Api() )->post( 'api/mitra/v6.2/pin/validate', array( 'pin' => $pin ) );
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
