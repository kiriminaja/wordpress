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

    /** The unified mapping is authoritative for names and postal codes. */
    public function sub_district_search( $search ) {
        $this->address_transport_metadata = array();
        $deadline = $this->address_lookup_clock() + 25.0;
        if ( ! is_string( $search ) || strlen( trim( $search ) ) < 3 || strlen( $search ) > 200 ) {
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
        $client = new AddressApiTransport();
        $response = 'GET' === $method ? $client->get( $endpoint, $payload ) : $client->post( $endpoint, $payload );
        // Never retain explanatory error bodies or messages from the transport.
        $metadata = $client->diagnostics();
        $this->address_transport_metadata = array(
            'http_status' => is_int( $metadata['http_status'] ?? null ) ? $metadata['http_status'] : null,
            'elapsed_ms'  => min( 25000, max( 0, (int) ( $metadata['elapsed_ms'] ?? 0 ) ) ),
        );
        return $response;
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
        return array( 'status' => false, 'data' => 'Could not load subdistricts.' );
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
        return $this->call_sdk(
            static fn() => KiriminAja::getPayment( (string) $payload['payment_id'] ),
            static fn( $data, $message ) => array(
                'status' => true,
                'text'   => $message,
                'data'   => $data,
            )
        );
    }

    public function getTracking( $payload ) {
        return $this->call_sdk(
            static fn() => KiriminAja::getTracking( (string) $payload['order_id'] ),
            static fn( $data, $message ) => array_merge(
                array(
                    'status' => true,
                    'text'   => $message,
                ),
                is_array( $data ) ? $data : array()
            )
        );
    }

    public function getPricing( $payload ) {
        $origin = AddressHierarchyResolver::resolve( $payload['subdistrict_origin'] ?? null, $payload['origin_postcode'] ?? '', array( $this, 'sub_district_search' ) );
        if ( null === $origin ) {
            return array( 'status' => false, 'data' => 'Could not resolve shipping address hierarchy.' );
        }
        $destination = AddressHierarchyResolver::resolve( $payload['subdistrict_destination'] ?? null, $payload['destination_postcode'] ?? '', array( $this, 'sub_district_search' ) );
        if ( null === $destination ) {
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

    public function getRequestPickupSchedule() {
        return $this->call_sdk(
            static fn() => KiriminAja::getSchedules(),
            static fn( $data, $message ) => array(
                'status'    => true,
                'text'      => $message,
                'schedules' => $data,
            )
        );
    }

    public function sendPickupRequest( $payload ) {
        return $this->post(
            '/api/mitra/v6.1/request_pickup',
            $payload,
            array(
                'source'    => 'kiriminaja_shipping',
                'operation' => 'send_pickup_request',
            )
        );
    }

    public function get_couriers() {
        return $this->call_sdk(
            static fn() => KiriminAja::getCouriers(),
            static fn( $data, $message ) => array(
                'status' => true,
                'text'   => $message,
                'datas'  => $data,
            )
        );
    }

    public function getProvinces() {
        return $this->call_sdk(
            static fn() => KiriminAja::getProvince(),
            static fn( $data, $message ) => array(
                'status' => true,
                'text'   => $message,
                'datas'  => $data,
            )
        );
    }

    public function getCitiesByProvinceId( $province_id ) {
        return $this->call_sdk(
            static fn() => KiriminAja::getCity( (int) $province_id ),
            static function ( $data, $message ) {
                if ( is_array( $data ) && array_key_exists( 'status', $data ) ) {
                    return $data;
                }

                return array(
                    'status' => true,
                    'text'   => $message,
                    'datas'  => $data,
                );
            }
        );
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
        return $this->call_sdk(
            static fn() => KiriminAja::getProfile(),
            static fn( $data, $message ) => array(
                'status'  => true,
                'text'    => $message,
                'results' => $data,
            )
        );
    }

    public function getCreditBalance() {
        return $this->call_sdk(
            static fn() => KiriminAja::getCreditBalance(),
            static fn( $data, $message ) => array(
                'status'  => true,
                'text'    => $message,
                'results' => $data,
            )
        );
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
        return $this->post(
            '/api/mitra/v6.2/request_pickup',
            $payload,
            array(
                'source'    => 'kiriminaja_shipping',
                'operation' => 'send_pickup_request_v2',
            )
        );
    }
}
