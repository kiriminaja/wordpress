<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAja\Services\KiriminAja;
use KiriminAjaOfficial\Base\KiriminAjaApi;

const DEFAULT_PICKUP_OPTION = array( 'PICKUP' );

class KiriminajaApiRepository extends KiriminAjaApi {
    public function sub_district_search( $search ) {
        if ( 1 === preg_match( '/^[0-9]{5}$/D', (string) $search ) ) {
            return $this->subdistrict_postcode_search( (string) $search );
        }
        // The search endpoint returns kecamatan, not kelurahan. Never expose a
        // parent ID as a selectable village, even when it has no children.
        $failure = array( 'status' => false, 'data' => 'Could not load subdistricts.' );
        $parents = $this->call_sdk(
            static fn() => KiriminAja::getDistrictByName( (string) $search ),
            static fn( $data, $message ) => array( 'result' => $data )
        );
        if ( empty( $parents['status'] ) || ! is_array( $parents['data']->result ?? null ) ) {
            return $failure;
        }

        $districts = array();
        foreach ( $parents['data']->result as $parent ) {
            $parent = (array) $parent;
            $id = $this->subdistrict_positive_id( $parent['district_id'] ?? $parent['id'] ?? null );
            if ( null === $id ) {
                return $failure;
            }
            // The documented district search shape is {id, text}; retain its
            // canonical hierarchy rather than using the submitted search term.
            if ( is_string( $parent['text'] ?? null ) ) {
                $parts = array_map( 'trim', explode( ',', $parent['text'] ) );
                if ( 3 === count( $parts ) ) {
                    $parent += array( 'district_name' => $parts[0], 'city_name' => $parts[1], 'province_name' => $parts[2] );
                }
            }
            if ( isset( $districts[ $id ] ) && $districts[ $id ] !== $parent ) {
                return $failure;
            }
            $districts[ $id ] = $parent;
        }
        // Fail closed rather than silently return only part of a broad lookup.
        if ( count( $districts ) > 50 ) {
            return $failure;
        }

        $rows = array();
        foreach ( $districts as $district_id => $parent ) {
            $children = $this->call_sdk(
                static fn() => KiriminAja::getSubDistrict( $district_id ),
                static fn( $data, $message ) => array( 'result' => $data )
            );
            if ( empty( $children['status'] ) || ! is_array( $children['data']->result ?? null ) ) {
                return $failure;
            }
            foreach ( $children['data']->result as $child ) {
                $child = (array) $child;
                $id = $this->subdistrict_positive_id( $child['subdistrict_id'] ?? $child['id'] ?? null );
                $name = $child['subdistrict_name'] ?? $child['kelurahan_name'] ?? null;
                if ( null === $id || ! is_string( $name ) || '' === trim( $name ) ) {
                    return $failure;
                }
                $child_parent = $child['kecamatan_id'] ?? $child['district_id'] ?? null;
                if ( null !== $child_parent && $district_id !== $this->subdistrict_positive_id( $child_parent ) ) {
                    return $failure;
                }
                $zip = is_scalar( $child['zip_code'] ?? null ) ? trim( (string) $child['zip_code'] ) : '';
                $row = array(
                    'id'               => $id,
                    'subdistrict_id'   => $id,
                    'district_id'      => $district_id,
                    'subdistrict_name' => trim( $name ),
                    'district_name'    => $parent['district_name'] ?? '',
                    'city_name'        => $parent['city_name'] ?? '',
                    'province_name'    => $parent['province_name'] ?? '',
                    'zip_code'         => $zip,
                );
                foreach ( array( 'district_name', 'city_name', 'province_name' ) as $key ) {
                    if ( ! is_string( $row[ $key ] ) || '' === trim( $row[ $key ] ) ) {
                        return $failure;
                    }
                    $row[ $key ] = trim( $row[ $key ] );
                }
                $row['text'] = implode( ', ', array( $row['subdistrict_name'], $row['district_name'], $row['city_name'], $row['province_name'] ) );
                if ( '' !== $zip ) {
                    $row['text'] .= ' ' . $zip;
                }
                if ( isset( $rows[ $id ] ) && $rows[ $id ] !== $row ) {
                    return $failure;
                }
                $rows[ $id ] = $row;
            }
        }

        return array( 'status' => true, 'data' => (object) array( 'status' => true, 'result' => array_map( static fn( $row ) => (object) $row, array_values( $rows ) ) ) );
    }

    /** Postal membership comes from addresses; the SDK confirms actual child IDs. */
    private function subdistrict_postcode_search( string $postcode ): array {
        $failure = array( 'status' => false, 'data' => 'Could not load subdistricts.' );
        try {
            $response = $this->get( '/api/mitra/v6.1/addresses', array( 'search' => $postcode ), array( 'operation' => 'subdistrict_postcode_search' ) );
        } catch ( \Throwable $throwable ) {
            return $failure;
        }
        if ( empty( $response['status'] ) || ! is_array( $response['data']->data ?? null ) ) {
            return $failure;
        }
        $districts = array();
        $candidates = array();
        foreach ( $response['data']->data as $address ) {
            $address = (array) $address;
            $full_address = $address['full_address'] ?? null;
            // The documented comma-separated address ends with its postcode.
            if ( ! is_string( $full_address ) || ! preg_match( '/(?:,\s*|\s)([0-9]{5})\s*$/D', $full_address, $match ) || $postcode !== $match[1] ) {
                continue;
            }
            foreach ( array( 'province_id', 'city_id', 'district_id', 'subdistrict_id' ) as $key ) {
                $address[ $key ] = $this->subdistrict_positive_id( $address[ $key ] ?? null );
                if ( null === $address[ $key ] ) {
                    return $failure;
                }
            }
            $hierarchy = preg_replace( '/(?:,\s*|\s)[0-9]{5}\s*$/D', '', trim( $full_address ) );
            $parts = array_map( 'trim', explode( ',', $hierarchy ) );
            if ( 4 !== count( $parts ) || in_array( '', $parts, true ) ) {
                return $failure;
            }
            $parent_id = $address['district_id'];
            $id = $address['subdistrict_id'];
            $parent = array( 'province_id' => $address['province_id'], 'city_id' => $address['city_id'], 'district_name' => $parts[1], 'city_name' => $parts[2], 'province_name' => $parts[3] );
            $candidate = array( 'district_id' => $parent_id, 'text' => trim( $full_address ) );
            if ( ( isset( $districts[ $parent_id ] ) && $districts[ $parent_id ] !== $parent ) || ( isset( $candidates[ $id ] ) && $candidates[ $id ] !== $candidate ) ) {
                return $failure;
            }
            $districts[ $parent_id ] = $parent;
            $candidates[ $id ] = $candidate;
        }
        // One address search and at most fifty SDK child requests, never partial.
        if ( count( $districts ) > 50 ) {
            return $failure;
        }
        $rows = array();
        foreach ( $districts as $parent_id => $parent ) {
            $children = $this->call_sdk(
                static fn() => KiriminAja::getSubDistrict( $parent_id ),
                static fn( $data, $message ) => array( 'result' => $data )
            );
            if ( empty( $children['status'] ) || ! is_array( $children['data']->result ?? null ) ) {
                return $failure;
            }
            foreach ( $children['data']->result as $child ) {
                $child = (array) $child;
                $id = $this->subdistrict_positive_id( $child['subdistrict_id'] ?? $child['id'] ?? null );
                if ( null === $id ) {
                    return $failure;
                }
                if ( ! isset( $candidates[ $id ] ) ) {
                    continue;
                }
                $name = $child['subdistrict_name'] ?? $child['kelurahan_name'] ?? null;
                $child_parent = $child['kecamatan_id'] ?? $child['district_id'] ?? $parent_id;
                if ( $candidates[ $id ]['district_id'] !== $parent_id || $parent_id !== $this->subdistrict_positive_id( $child_parent ) || ! is_string( $name ) || '' === trim( $name ) ) {
                    return $failure;
                }
                $row = array_merge( $parent, array( 'id' => $id, 'subdistrict_id' => $id, 'district_id' => $parent_id, 'subdistrict_name' => trim( $name ), 'zip_code' => $postcode, 'text' => $candidates[ $id ]['text'] ) );
                if ( isset( $rows[ $id ] ) && $rows[ $id ] !== $row ) {
                    return $failure;
                }
                $rows[ $id ] = $row;
            }
        }
        if ( count( $rows ) !== count( $candidates ) ) {
            return $failure;
        }
        return array( 'status' => true, 'data' => (object) array( 'status' => true, 'result' => array_map( static fn( $row ) => (object) $row, array_values( $rows ) ) ) );
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
        return $this->post(
            '/api/mitra/v6.1/shipping_price',
            array(
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
        return $this->post(
            '/api/mitra/v6.2/pin/validate',
            array( 'pin' => $pin ),
            array(
                'source'    => 'kiriminaja_api',
                'operation' => 'pin_validate',
            )
        );
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
