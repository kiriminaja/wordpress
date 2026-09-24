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
        return $this->call_sdk(
            static fn() => KiriminAja::getDistrictByName( (string) $search ),
            static fn( $data, $message ) => array(
                'status' => true,
                'text'   => $message,
                'result' => $data,
            )
        );
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
