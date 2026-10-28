<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAja\Services\KiriminAja;
use KiriminAjaOfficial\Base\KiriminAjaApi;
use KiriminAjaOfficial\Infrastructure\AddressApiTransport;
use KiriminAjaOfficial\Services\AddressHierarchyResolver;

const DEFAULT_PICKUP_OPTION = array( 'PICKUP' );

class KiriminajaApiRepository extends KiriminAjaApi {
    private array $address_transport_metadata = array();
    private string $pickup_request_payload_json = '';

    /** Failed booking diagnostics only; authorization is not part of the payload. */
    public function pickupRequestPayloadJson(): string {
        return $this->pickup_request_payload_json;
    }

    /** The unified mapping is authoritative for names and postal codes. */
    public function sub_district_search( $search ) {
        $this->address_transport_metadata = array();
        $deadline = $this->address_lookup_clock() + 25.0;
        if ( ! is_string( $search ) || strlen( trim( $search ) ) < 3 || strlen( $search ) > 200 ) {
            $this->address_transport_metadata = array(
                'input_type' => gettype( $search ),
                'input_length' => is_string( $search ) ? min( 201, strlen( trim( $search ) ) ) : null,
                'input_error' => ! is_string( $search ) ? 'non_string' : ( strlen( trim( $search ) ) < 3 ? 'too_short' : 'too_long' ),
            );
            return $this->address_failure( 'invalid_search' );
        }
        $search = trim( $search );
        $postcode = 1 === preg_match( '/^[0-9]{5}$/D', $search ) ? $search : null;
        $response = $this->bounded_address( 'GET', 'api/mitra/v6.1/addresses', array( 'search' => $search ), $deadline );
        if ( empty( $response['status'] ) ) {
            return $response;
        }
        $addresses = $response['data']['data'] ?? null;
        // Missing lists are not zero matches. Never return a partial mapping.
        if ( ! is_array( $addresses ) || ! array_is_list( $addresses ) ) {
            return $this->address_failure( 'invalid_list' );
        }
        if ( count( $addresses ) > 500 ) {
            return $this->address_failure( 'result_limit' );
        }
        $rows = array();
        $hierarchies = array();
        foreach ( $addresses as $address ) {
            if ( $this->address_lookup_clock() >= $deadline ) {
                return $this->address_failure( 'deadline' );
            }
            if ( ! is_array( $address ) || array_is_list( $address ) ) {
                return $this->address_failure( 'invalid_row' );
            }
            foreach ( array( 'province_id', 'city_id', 'district_id', 'subdistrict_id' ) as $key ) {
                $address[ $key ] = $this->subdistrict_positive_id( $address[ $key ] ?? null );
                if ( null === $address[ $key ] ) {
                    return $this->address_failure( 'invalid_id' );
                }
            }
            // Aliases cannot override the official village or parent IDs.
            foreach ( array( 'id' => 'subdistrict_id', 'kecamatan_id' => 'district_id', 'provinsi_id' => 'province_id', 'kabupaten_id' => 'city_id' ) as $alias => $key ) {
                if ( array_key_exists( $alias, $address ) && $this->subdistrict_positive_id( $address[ $alias ] ) !== $address[ $key ] ) {
                    return $this->address_failure( 'conflicting_alias' );
                }
            }
            $full_address = $address['full_address'] ?? null;
            if ( ! is_string( $full_address ) || strlen( $full_address ) > 1000 ) {
                return $this->address_failure( 'invalid_hierarchy' );
            }
            $parts = array_map( 'trim', explode( ',', trim( $full_address ) ) );
            if ( 5 !== count( $parts ) || in_array( '', $parts, true ) || 1 !== preg_match( '/^[0-9]{5}$/D', $parts[4] ) ) {
                return $this->address_failure( 'invalid_hierarchy' );
            }
            $row = array(
                'id'               => $address['subdistrict_id'],
                'subdistrict_id'   => $address['subdistrict_id'],
                'district_id'      => $address['district_id'],
                'city_id'          => $address['city_id'],
                'province_id'      => $address['province_id'],
                'subdistrict_name' => $parts[0],
                'district_name'    => $parts[1],
                'city_name'        => $parts[2],
                'province_name'    => $parts[3],
                'zip_code'         => $parts[4],
                'text'             => implode( ', ', $parts ),
            );
            foreach ( array( 'subdistrict_name', 'kelurahan_name', 'district_name', 'city_name', 'province_name', 'zip_code' ) as $alias ) {
                $key = 'kelurahan_name' === $alias ? 'subdistrict_name' : $alias;
                if ( array_key_exists( $alias, $address ) && ( ! is_string( $address[ $alias ] ) || trim( $address[ $alias ] ) !== $row[ $key ] ) ) {
                    return $this->address_failure( 'conflicting_alias' );
                }
            }
            // Check consistency across the entire response, before postal filtering.
            foreach ( array(
                'province' => array( $row['province_id'], $row['province_name'] ),
                'city' => array( $row['city_id'], $row['province_id'], $row['city_name'] ),
                'district' => array( $row['district_id'], $row['city_id'], $row['province_id'], $row['district_name'] ),
            ) as $level => $hierarchy ) {
                $key = $level . ':' . $hierarchy[0];
                if ( isset( $hierarchies[ $key ] ) && $hierarchies[ $key ] !== $hierarchy ) {
                    return $this->address_failure( 'conflicting_hierarchy' );
                }
                $hierarchies[ $key ] = $hierarchy;
            }
            $id = $row['id'];
            if ( isset( $rows[ $id ] ) && $rows[ $id ] !== $row ) {
                return $this->address_failure( 'conflicting_village' );
            }
            $rows[ $id ] = $row;
        }
        if ( $this->address_lookup_clock() >= $deadline ) {
            return $this->address_failure( 'deadline' );
        }
        if ( null !== $postcode ) {
            $rows = array_filter( $rows, static fn( $row ) => $postcode === $row['zip_code'] );
        }
        AddressHierarchyResolver::remember( $rows );
        return array( 'status' => true, 'data' => (object) array( 'status' => true, 'result' => array_map( static fn( $row ) => (object) $row, array_values( $rows ) ) ) );
    }

    /** Monotonic clock seam for deterministic, offline deadline tests. */
    protected function address_lookup_clock(): float {
        return hrtime( true ) / 1e9;
    }

    /** SDK request construction/authentication, with address-specific limits. */
    protected function address_request( string $method, string $endpoint, array $payload ): array {
        $client = $this->address_client();
        $response = 'GET' === $method ? $client->get( $endpoint, $payload ) : $client->post( $endpoint, $payload );
        // Never retain explanatory error bodies or messages from the transport.
        $metadata = $client->diagnostics();
        $this->address_transport_metadata = array(
            'transport_code' => in_array( $metadata['code'] ?? null, array( 'transport_success', 'http_failure', 'transport_exception', 'invalid_json', 'invalid_json_shape', 'invalid_content_length', 'oversized_response', 'stalled_response', 'insecure_url', 'method_unsupported' ), true ) ? $metadata['code'] : 'unknown',
            'submitted'   => true === ( $metadata['submitted'] ?? false ),
            'network_failure' => true === ( $metadata['network_failure'] ?? false ),
            'http_status' => is_int( $metadata['http_status'] ?? null ) ? $metadata['http_status'] : null,
            'elapsed_ms'  => min( 25000, max( 0, (int) ( $metadata['elapsed_ms'] ?? 0 ) ) ),
        );
        return $response;
    }

    protected function address_client(): AddressApiTransport {
        return new AddressApiTransport();
    }

    /** One attempt, no legacy-parent lookup or child fan-out. */
    private function bounded_address( string $method, string $endpoint, array $payload, float $deadline ): array {
        if ( $this->address_lookup_clock() + AddressApiTransport::REQUEST_TIMEOUT > $deadline ) {
            return $this->address_failure( 'deadline' );
        }
        try {
            [ $ok, $body ] = $this->address_request( $method, $endpoint, $payload );
            if ( $this->address_lookup_clock() >= $deadline ) {
                return $this->address_failure( 'deadline' );
            }
            if ( true !== $ok ) {
                return $this->address_failure( 'transport_failure' );
            }
            if ( ! is_array( $body ) || ! is_bool( $body['status'] ?? null ) ) {
                return $this->address_failure( 'invalid_envelope' );
            }
            if ( ! $body['status'] ) {
                return $this->address_failure( 'api_rejection' );
            }
            return array( 'status' => true, 'data' => $body );
        } catch ( \Throwable $throwable ) {
            return $this->address_failure( 'transport_exception' );
        }
    }

    /** Fixed reason codes and numeric metadata only: no query, body or token. */
    private function address_failure( string $reason ): array {
        if ( function_exists( 'kiriof_log' ) ) {
            kiriof_log( 'warning', 'Address lookup failed.', array_merge(
                array( 'source' => 'kiriminaja_api', 'operation' => 'sub_district_search', 'reason' => $reason, 'backtrace' => false ),
                $this->address_transport_metadata
            ) );
        }
        $transient = 'transport_failure' === $reason && (
            ( 'http_failure' === ( $this->address_transport_metadata['transport_code'] ?? null ) && in_array( $this->address_transport_metadata['http_status'] ?? null, array( 502, 503, 504 ), true ) )
            || ( 'transport_exception' === ( $this->address_transport_metadata['transport_code'] ?? null ) && true === ( $this->address_transport_metadata['network_failure'] ?? false ) && true === ( $this->address_transport_metadata['submitted'] ?? false ) && null === ( $this->address_transport_metadata['http_status'] ?? null ) )
        );
        return array( 'status' => false, 'data' => 'Could not load subdistricts.', 'retryable' => $transient );
    }

    private function subdistrict_positive_id( $value ): ?int {
        if ( ! is_int( $value ) && ! is_string( $value ) ) {
            return null;
        }
        $id = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
        return false === $id ? null : $id;
    }

    public function setCallback( $callback_url ) {
        return $this->call_sdk(
            static fn() => KiriminAja::setCallback( (string) $callback_url ),
            static fn( $data, $message ) => array(
                'status' => true,
                'text'   => $message,
                'data'   => $data,
            )
        );
    }

    public function processSetupKey( $payload ) {
        return $this->post(
            '/api/service/api-request/integrate',
            array(
                'setup_key'    => $payload['setup_key'],
                'callback_url' => $payload['callback_url'],
            ),
            array(
                'source'    => 'kiriminaja_settings',
                'operation' => 'process_setup_key',
            )
        );
    }

    public function getPayment( $payload ) {
		return $this->fetch_utility( 'POST', 'api/mitra/v2/get_payment', array( 'payment_id' => (string) $payload['payment_id'] ), array( 'data' => 'object' ) );
    }

    public function getTracking( $payload ) {
		return $this->fetch_utility( 'POST', 'api/mitra/tracking', array( 'order_id' => (string) $payload['order_id'] ), array( 'status_code' => 'scalar', 'details' => 'object', 'histories' => 'list' ) );
    }

    public function getPricing( $payload ) {
        $origin = AddressHierarchyResolver::resolve( $payload['subdistrict_origin'] ?? null, $payload['origin_postcode'] ?? '', array( $this, 'sub_district_search' ) );
        if ( null === $origin ) {
            $this->log_pricing_hierarchy_failure( 'origin_hierarchy_unavailable' );
            return array( 'status' => false, 'data' => 'Could not resolve shipping address hierarchy.' );
        }
        $destination = AddressHierarchyResolver::resolve( $payload['subdistrict_destination'] ?? null, $payload['destination_postcode'] ?? '', array( $this, 'sub_district_search' ) );
        if ( null === $destination ) {
            $this->log_pricing_hierarchy_failure( 'destination_hierarchy_unavailable' );
            return array( 'status' => false, 'data' => 'Could not resolve shipping address hierarchy.' );
        }
        return $this->post(
            '/api/mitra/v6.1/shipping_price',
            array(
                'origin'                  => $origin['district_id'],
                'destination'             => $destination['district_id'],
                'subdistrict_origin'      => (int) $payload['subdistrict_origin'],
                'subdistrict_destination' => (int) $payload['subdistrict_destination'],
                'weight'                  => (int) $payload['weight'],
                'length'                  => (int) $payload['length'],
                'width'                   => (int) $payload['width'],
                'height'                  => (int) $payload['height'],
                'insurance'               => (int) $payload['insurance'],
                'item_value'              => (int) $payload['item_value'],
                'courier'                 => $payload['courier'],
                'pickup_option'            => $payload['pickup_option'] ?? DEFAULT_PICKUP_OPTION,
            ),
            array(
                'source'    => 'kiriminaja_shipping',
                'operation' => 'get_pricing',
            )
        );
    }

	/** Log failed mapping verification without retaining customer or warehouse data. */
	private function log_pricing_hierarchy_failure( string $code ): void {
		if ( function_exists( 'kiriof_log' ) ) {
			kiriof_log( 'warning', 'Express shipping address verification failed.', array( 'operation' => 'get_pricing', 'code' => $code, 'backtrace' => false ), 'kiriminaja_shipping' );
		}
	}

    public function getRequestPickupSchedule() {
		return $this->fetch_utility( 'POST', 'api/mitra/v2/schedules', array(), array( 'schedules' => 'list' ) );
    }

    public function sendPickupRequest( $payload ) {
        $this->pickup_request_payload_json = '';
        $method = is_array( $payload ) && array_key_exists( 'payment_method', $payload ) ? $payload['payment_method'] : '';
        if ( ! is_array( $payload ) || ! is_string( $method ) || ! in_array( $method, array( '', 'credit', 'qris', 'cash' ), true ) ) {
            return array( 'status' => false, 'data' => 'Invalid Express payment method.' );
        }
        if ( 'qris' === $method ) {
            $payload['payment_method'] = 'cash';
        }
        if ( 'credit' !== $method ) {
            unset( $payload['pin'] );
        }
        // Booking errors can echo PINs. Never pass them to the generic API logger.
        try {
            [ $transport, $body ] = $this->create_api_client()->post( 'api/mitra/v6.2/request_pickup', $payload );
            if ( true === $transport && is_array( $body ) && true === ( $body['status'] ?? null ) ) {
                return array( 'status' => true, 'data' => json_decode( wp_json_encode( $body ) ) );
            }
            $this->captureFailedPickupPayload( $payload );
            $code = is_array( $body ) ? ( $body['results']['error'] ?? null ) : null;
            $messages = array(
                'PIN_INVALID' => 'KA Credit PIN is invalid.',
                'PIN_MAX_ATTEMPT_REACHED' => 'KA Credit PIN attempt limit reached.',
                'BALANCE_NOT_ENOUGH' => 'KA Credit balance is insufficient.',
            );
            if ( true === $transport && false === ( $body['status'] ?? null ) && is_string( $code ) && isset( $messages[ $code ] ) ) {
                return array( 'status' => false, 'data' => (object) array( 'status' => false, 'text' => $messages[ $code ], 'results' => (object) array( 'error' => $code ) ) );
            }
        } catch ( \Throwable $error ) {
            // An uncertain submission must not retry or fall back to the legacy API.
            $this->captureFailedPickupPayload( $payload );
        }
        return array( 'status' => false, 'data' => 'Unable to request Express pickup.' );
    }

    private function captureFailedPickupPayload( array $payload ): void {
        $payload = $this->redactPickupDiagnostic( $payload );
        $json = wp_json_encode( $payload );
        $this->pickup_request_payload_json = is_string( $json ) ? $json : '';
    }

    /** Keep operational values/types, not customer identities or arbitrary descriptive text. */
    private function redactPickupDiagnostic( array $payload ): array {
        $operational = array( 'order_id', 'platform_name', 'schedule', 'payment_method', 'service', 'service_type', 'package_type_id', 'qty', 'weight', 'width', 'height', 'length', 'price', 'item_value', 'insurance_amount', 'shipping_cost', 'cod', 'drop', 'dropoff', 'is_with_insurance', 'discount_amount', 'discount_percentage', 'woocommerce_discount_amount', 'kelurahan_id', 'kecamatan_id', 'destination_kelurahan_id', 'destination_kecamatan_id' );
        foreach ( $payload as $key => $value ) {
            if ( is_array( $value ) ) {
                $payload[ $key ] = $this->redactPickupDiagnostic( $value );
            } elseif ( ! in_array( $key, $operational, true ) ) {
                $payload[ $key ] = '[redacted]';
            }
        }
        return $payload;
    }

    public function get_couriers() {
		return $this->fetch_utility( 'POST', 'api/mitra/couriers', array(), array( 'datas' => 'list' ) );
    }

    public function getProvinces() {
		return $this->fetch_utility( 'POST', 'api/mitra/province', array(), array( 'datas' => 'list' ) );
    }

    public function getCitiesByProvinceId( $province_id ) {
		return $this->fetch_utility( 'POST', 'api/mitra/city', array( 'provinsi_id' => (int) $province_id ), array( 'datas' => 'list' ) );
    }

    public function getPrintAwb( $awb ) {
        $awbs = is_array( $awb ) ? $awb : array( $awb );
        $awbs = array_values( array_filter( array_map( 'strval', $awbs ) ) );

        if ( empty( $awbs ) ) {
            return array(
                'status' => false,
                'data'   => 'AWB is empty',
            );
        }

        $payloads = array();
        $payloads[] = array( 'awb' => $awbs );
        if ( 1 === count( $awbs ) ) {
            $payloads[] = array( 'awb' => $awbs[0] );
            $payloads[] = array( 'awbs' => $awbs );
            $payloads[] = array( 'tracking_number' => $awbs[0] );
        } else {
            $payloads[] = array( 'awbs' => $awbs );
            $payloads[] = array( 'awb' => implode( ',', $awbs ) );
        }

        $attempts = array();
        foreach ( $payloads as $attempt => $payload ) {
            $response = $this->post(
                '/api/mitra/v6.1/awb/print',
                $payload,
                array(
                    'source'    => 'kiriminaja_shipping',
                    'operation' => 'get_print_awb',
                    'attempt'   => $attempt + 1,
                )
            );
            $attempts[] = array(
                'payload_keys'  => array_keys( $payload ),
                'payload_shape' => is_array( reset( $payload ) ) ? 'array' : 'scalar',
                'status'        => $response['status'] ?? null,
                'message'       => is_scalar( $response['data'] ?? null ) ? substr( (string) $response['data'], 0, 200 ) : '',
            );

            if ( ! empty( $response['status'] ) ) {
                $response['attempts'] = $attempts;
                return $response;
            }
        }

        $response['attempts'] = $attempts;
        return $response;
    }

    public function cancelShipment( $awb, $reason ) {
        return $this->call_sdk(
            static fn() => KiriminAja::cancelShipment( (string) $awb, (string) $reason ),
            static fn( $data, $message ) => array(
                'status' => true,
                'text'   => $message,
                'data'   => $data,
            )
        );
    }

    public function getProfile() {
		return $this->fetch_profile();
    }

    public function getCreditBalance() {
		return $this->fetch_credit_balance();
    }

    public function pinValidate( $pin ) {
        // PIN errors can echo secrets. Do not use the inherited remote-error logger.
        try {
            [ $transport, $body ] = ( new \KiriminAjaOfficial\Infrastructure\InstantApiTransport() )->post(
                'api/mitra/v6.2/pin/validate',
                array( 'pin' => $pin )
            );
            if ( true === $transport && is_array( $body ) && is_bool( $body['status'] ?? null ) ) {
                return array( 'status' => true, 'data' => json_decode( wp_json_encode( $body ) ) );
            }
        } catch ( \Throwable $throwable ) {
            // Never retain remote exception text.
        }
        return array( 'status' => false, 'data' => 'PIN validation failed.' );
    }

    public function sendPickupRequestV2( $payload ) {
        return $this->sendPickupRequest( $payload );
    }
}
