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
use KiriminAjaOfficial\Utils\CreditBalance;

/** Official SDK adapter for Instant delivery; never retries remote operations. */
class InstantDeliveryApiRepository extends KiriminAjaApi {
	private array $booking_diagnostics = array();

	/** Facts only, never request/response text, credentials, or customer data. */
	public function bookingDiagnostics(): array {
		return $this->booking_diagnostics;
	}

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
		$this->booking_diagnostics = array( 'code' => 'local_validation_failed' );
		if ( isset( $payload['origin'] ) || ! $this->valid_location( $payload, 'latitude', 'longitude' ) ) {
			return $this->notSubmittedFailure( 'Instant origin requires an address and coordinates.' );
		}
		foreach ( array( 'name', 'phone' ) as $field ) {
			if ( ! is_string( $payload[ $field ] ?? null ) || '' === trim( $payload[ $field ] ) ) {
				return $this->notSubmittedFailure( 'Instant origin requires a name and phone.' );
			}
		}
		if ( array_key_exists( 'payment_method', $payload ) && ! in_array( $payload['payment_method'], array( 'credit', 'qris', 'cash' ), true ) ) {
			return $this->notSubmittedFailure( 'Unsupported Instant payment method.' );
		}
		if ( 'credit' === ( $payload['payment_method'] ?? null ) && ( ! is_string( $payload['pin'] ?? null ) || ! preg_match( '/\A[0-9]{6}\z/', $payload['pin'] ) ) ) {
			return $this->notSubmittedFailure( 'A six-digit PIN is required for KA Credit.' );
		}
		$packages = $payload['packages'] ?? null;
		if ( isset( $payload['address_note'] ) && ! is_string( $payload['address_note'] ) ) {
			return $this->notSubmittedFailure( 'Instant address notes must be text.' );
		}
		$payload['address_note'] = '' !== trim( $payload['address_note'] ?? '' ) ? $payload['address_note'] : $payload['address'];
		if ( ! is_array( $packages ) || ! array_is_list( $packages ) || count( $packages ) < 1 || count( $packages ) > 10 ) {
			return $this->notSubmittedFailure( 'Instant booking requires 1 to 10 packages.' );
		}
		foreach ( $packages as $index => $package ) {
			if ( ! is_array( $package ) ) {
				return $this->notSubmittedFailure( 'Invalid Instant package.' );
			}
			if ( ! in_array( $package['service'] ?? null, array( 'gosend', 'grab_express' ), true ) ) {
				return $this->notSubmittedFailure( 'Unsupported Instant courier.' );
			}
			if ( ! $this->valid_location( $package['destination'] ?? null, 'latitude', 'longitude' ) ) {
				return $this->notSubmittedFailure( 'Instant destinations require an address and coordinates.' );
			}
			foreach ( array( 'name', 'phone' ) as $field ) {
				if ( ! is_string( $package['destination'][ $field ] ?? null ) || '' === trim( $package['destination'][ $field ] ) ) {
					return $this->notSubmittedFailure( 'Instant destinations require a name and phone.' );
				}
			}
			if ( isset( $package['destination']['address_note'] ) && ! is_string( $package['destination']['address_note'] ) ) {
				return $this->notSubmittedFailure( 'Instant address notes must be text.' );
			}
			$payload['packages'][ $index ]['destination']['address_note'] = '' !== trim( $package['destination']['address_note'] ?? '' ) ? $package['destination']['address_note'] : $package['destination']['address'];
		}

		// The inherited post() logs remote errors, which may echo the PIN.
		// Use the bounded Instant transport directly; never retry or expose errors.
		try {
			$client = new InstantApiTransport();
			[ $transport, $body ] = $client->post( 'api/mitra/v6.2/instant/request_pickup', $payload );
			$this->booking_diagnostics = $client->diagnostics();
			$http_rejection = false === $transport && in_array( $this->booking_diagnostics['http_status'] ?? null, array( 400, 422 ), true );
			if ( $http_rejection ) {
				// The tuple remains a failure. Inspect bounded JSON privately, never upstream text.
				$body = $client->errorResponse();
			}
			$this->booking_diagnostics['acknowledgement_type'] = gettype( is_array( $body ) ? ( $body['status'] ?? null ) : null );
			$this->booking_diagnostics['acknowledged'] = is_array( $body ) && true === ( $body['status'] ?? null );
			$this->booking_diagnostics['result_type'] = gettype( is_array( $body ) ? ( $body['result'] ?? $body['results'] ?? null ) : null );
			if ( false === $transport && false === ( $this->booking_diagnostics['submitted'] ?? null ) ) {
				return $this->notSubmittedFailure( 'Instant booking failed.' );
			}
			if ( ( true === $transport || $http_rejection ) && $this->booking_rejected( $body ) ) {
				// Never expose upstream text or extra fields: they can echo the PIN.
				return array( 'status' => false, 'data' => (object) array( 'status' => false, 'result' => (object) array() ), 'operation_rejected' => true );
			}
			if ( ! $transport || ! is_array( $body ) || true !== ( $body['status'] ?? null ) ) {
				return $this->failure( 'Instant booking failed.' );
			}
			return array( 'status' => true, 'data' => json_decode( wp_json_encode( $body ) ) );
		} catch ( \Throwable $throwable ) {
			$this->booking_diagnostics['code'] = 'booking_adapter_exception';
			return $this->failure( 'Instant booking failed.' );
		}
	}

	/** Accept proven empty results or the observed field-validation envelope, not status alone. */
	private function booking_rejected( $body ): bool {
		if ( ! is_array( $body ) || array_is_list( $body ) || false !== ( $body['status'] ?? null ) ) {
			return false;
		}
		if ( array_key_exists( 'code', $body ) && ! is_int( $body['code'] ) && ! is_string( $body['code'] ) ) {
			return false;
		}
		$has_result = false;
		$has_validation = $this->validationRejected( $body );
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
			if ( 'errors' === $field && $has_validation ) {
				continue;
			}
			if ( in_array( $field, array( 'status', 'result', 'results' ), true ) ) {
				continue;
			}
			if ( is_array( $value ) || is_object( $value ) || ( in_array( $field, array( 'id', 'order_id', 'payment_id', 'payment', 'packages', 'awb' ), true ) && null !== $value && '' !== $value ) ) {
				return false;
			}
		}
		return $has_result || $has_validation;
	}

	/** Merchant-observed validation response: only known field paths and textual errors. */
	private function validationRejected( array $body ): bool {
		$errors = $body['errors'] ?? null;
		if ( ! is_array( $errors ) || empty( $errors ) || array_is_list( $errors ) || count( $errors ) > 100 ) {
			return false;
		}
		foreach ( array_keys( $body ) as $field ) {
			if ( ! in_array( $field, array( 'status', 'message', 'text', 'code', 'errors', 'result', 'results' ), true ) ) {
				return false;
			}
		}
		foreach ( array( 'message', 'text' ) as $field ) {
			if ( array_key_exists( $field, $body ) && ! is_string( $body[ $field ] ) ) {
				return false;
			}
		}
		foreach ( $errors as $field => $messages ) {
			if ( ! is_string( $field ) || ! preg_match( '/\A(?:address|address_note|phone|latitude|longitude|name|zipcode|payment_method|pin|packages(?:\.[0-9]+)?(?:\.(?:order_id|shipping_cost|service|service_type|package_type_id|vehicle|destination(?:\.(?:name|phone|latitude|longitude|address|address_note))?|items(?:\.[0-9]+)?(?:\.(?:name|description|price|weight|qty|length|width|height))?))?)\z/', $field ) ) {
				return false;
			}
			$messages = is_string( $messages ) ? array( $messages ) : $messages;
			if ( ! is_array( $messages ) || ! array_is_list( $messages ) || empty( $messages ) || count( $messages ) > 20 ) {
				return false;
			}
			foreach ( $messages as $message ) {
				if ( ! is_string( $message ) || '' === trim( $message ) || strlen( $message ) > 4096 ) {
					return false;
				}
			}
		}
		return true;
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
					return new ServiceResponse( true === $transport && is_array( $body ) && true === ( $body['status'] ?? null ) && true === ( $body['data']['valid'] ?? true ), 'PIN validation failed.', is_array( $body ) ? $body : null );
				} catch ( \Throwable $throwable ) {
					return new ServiceResponse( false, 'PIN validation failed.', null );
				}
			},
			static fn( $data ) => array( 'status' => true, 'result' => $data )
		);
		if ( ! $validation['status'] ) {
			return $validation;
		}
		$balance = $this->creditBalance();
		$available = CreditBalance::parse( $balance );
		if ( true !== ( $balance['status'] ?? null ) || null === $available ) {
			return $this->failure( 'Unable to verify credit balance.' );
		}
		if ( $available < $amount ) {
			return $this->failure( 'Insufficient KA Credit balance.' );
		}
		return $balance;
	}

	/** Single SDK lookup. Never expose or log upstream error text. */
	public function creditBalance(): array {
		try {
			$response = KiriminAja::getCreditBalance();
			$balance = $response instanceof ServiceResponse && true === $response->status ? CreditBalance::parse( $response->data ) : null;
			if ( null !== $balance ) {
				return array( 'status' => true, 'data' => (object) array( 'balance' => $balance ) );
			}
		} catch ( \Throwable $throwable ) {
			// Remote messages can contain credentials; unknown balance fails closed.
		}
		if ( function_exists( 'kiriof_log' ) ) {
			kiriof_log( 'warning', 'Unable to verify credit balance.', array( 'source' => 'kiriminaja_api', 'operation' => 'credit_balance', 'reason' => 'balance_unavailable' ) );
		}
		return $this->failure( 'Unable to verify credit balance.' );
	}

	private function valid_location( $location, string $latitude = 'lat', string $longitude = 'long' ): bool {
		return is_array( $location ) && is_string( $location['address'] ?? null ) && '' !== trim( $location['address'] )
			&& is_numeric( $location[ $latitude ] ?? null ) && is_numeric( $location[ $longitude ] ?? null )
			&& is_finite( (float) $location[ $latitude ] ) && is_finite( (float) $location[ $longitude ] )
			&& abs( (float) $location[ $latitude ] ) <= 90 && abs( (float) $location[ $longitude ] ) <= 180;
	}

	/** Only local validation or explicit transport proof can permit a safe retry. */
	private function notSubmittedFailure( string $message ): array {
		$failure = $this->failure( $message );
		$failure['operation_not_submitted'] = true;
		return $failure;
	}

	private function failure( string $message ): array {
		return array( 'status' => false, 'data' => $message );
	}
}
