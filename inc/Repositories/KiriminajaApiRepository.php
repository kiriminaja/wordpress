<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\KiriminAjaApi;

const DEFAULT_PICKUP_OPTION = ['PICKUP'];

class KiriminajaApiRepository extends KiriminAjaApi{
    public function sub_district_search($search)
    {
        return $this->get('/api/mitra/kelurahan_by_name?search='.$search, array(), array(
            'source'    => 'kiriminaja_shipping',
            'operation' => 'sub_district_search',
        ));
    }
    public function setCallback($callbackUrl)
    {
        return $this->post('/api/mitra/set_callback',[
            'url'    => $callbackUrl,
            'status' => '1'
        ], array(
            'source'    => 'kiriminaja_settings',
            'operation' => 'set_callback',
        ));
    }
    public function processSetupKey($payload){
        return $this->post('/api/service/api-request/integrate',[
            'setup_key'     => $payload['setup_key'],
            'callback_url'  => $payload['callback_url']
        ], array(
            'source'    => 'kiriminaja_settings',
            'operation' => 'process_setup_key',
        ));
    }
    public function getPayment($payload){
        return $this->post('/api/mitra/v2/get_payment',[
            'payment_id'     => $payload['payment_id']
        ], array(
            'source'    => 'kiriminaja_payment',
            'operation' => 'get_payment',
        ));
    }
    public function getTracking($payload){
        return $this->post('/api/mitra/tracking',[
            'order_id'     => $payload['order_id']
        ], array(
            'source'    => 'kiriminaja_shipping',
            'operation' => 'get_tracking',
        ));
    }
    
    public function getPricing($payload){
        return $this->post('/api/mitra/v6.1/shipping_price',[
            'subdistrict_origin'            => $payload['subdistrict_origin'],
            'subdistrict_destination'       => $payload['subdistrict_destination'],
            'weight'                        => $payload['weight'],
            'length'                        => $payload['length'],
            'width'                         => $payload['width'],
            'height'                        => $payload['height'],
            'insurance'                     => $payload['insurance'],
            'item_value'                    => $payload['item_value'],
            'courier'                       => $payload['courier'],
            'pickup_option'                 => isset($payload['pickup_option']) ? $payload['pickup_option'] : DEFAULT_PICKUP_OPTION
        ], array(
            'source'    => 'kiriminaja_shipping',
            'operation' => 'get_pricing',
        ));
    }
    
    public function getRequestPickupSchedule(){
        return $this->post('/api/mitra/v2/schedules', array(), array(
            'source'    => 'kiriminaja_shipping',
            'operation' => 'get_request_pickup_schedule',
        ));
    }
    public function sendPickupRequest($payload){
        return $this->post('/api/mitra/v6.1/request_pickup',$payload, array(
            'source'    => 'kiriminaja_shipping',
            'operation' => 'send_pickup_request',
        ));
    }
    public function get_couriers(){
        return $this->post('/api/mitra/couriers', array(), array(
            'source'    => 'kiriminaja_shipping',
            'operation' => 'get_couriers',
        ));
    }

    public function getProvinces(){
        $responses = array(
            $this->post('/api/mitra/province'),
            $this->get('/api/mitra/province'),
        );

        return $this->pickBestListResponse( $responses );
    }

    public function getCitiesByProvinceId($provinceId){
        $responses = array(
            $this->post('/api/mitra/city', array(
                'provinsi_id' => (int) $provinceId,
            )),
            $this->post('/api/mitra/city', array(
                'province_id' => $provinceId,
            )),
            $this->get('/api/mitra/city?provinsi_id=' . rawurlencode((string) $provinceId)),
            $this->get('/api/mitra/city?province_id=' . rawurlencode((string) $provinceId)),
        );

        return $this->pickBestListResponse( $responses );
    }

    private function pickBestListResponse( array $responses ) {
        foreach ( $responses as $response ) {
            if ( $this->responseHasListData( $response ) ) {
                return $response;
            }
        }

        foreach ( $responses as $response ) {
            if ( ! empty( $response['status'] ) ) {
                return $response;
            }
        }

        return end( $responses ) ?: array(
            'status' => false,
            'data' => 'No valid API response',
        );
    }

    private function responseHasListData( $response ): bool {
        if ( empty( $response['status'] ) || empty( $response['data'] ) || ! is_object( $response['data'] ) ) {
            return false;
        }

        $data = $response['data'];
        $candidates = array(
            $data->datas ?? null,
            $data->result ?? null,
            $data->results ?? null,
            $data->data ?? null,
        );

        foreach ( $candidates as $candidate ) {
            if ( is_array( $candidate ) && ! empty( $candidate ) ) {
                return true;
            }

            if ( $candidate instanceof \Traversable ) {
                foreach ( $candidate as $unused ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getPrintAwb($awb){
        return $this->post('/api/mitra/v6.1/awb/print',[
            'awb' => $awb,
        ], array(
            'source'    => 'kiriminaja_shipping',
            'operation' => 'get_print_awb',
        ));
    }
    public function cancelShipment($awb, $reason){
        return $this->post('/api/mitra/v3/cancel_shipment',[
            'awb'    => $awb,
            'reason' => $reason,
        ], array(
            'source'    => 'kiriminaja_shipping',
            'operation' => 'cancel_shipment',
        ));
    }
    public function getProfile(){
        return $this->get('/api/mitra/v6.2/profile', array(), array(
            'source'    => 'kiriminaja_api',
            'operation' => 'get_profile',
        ));
    }

    public function getCreditBalance(){
        return $this->get('/api/mitra/v6.2/credit/balance', array(), array(
            'source'    => 'kiriminaja_api',
            'operation' => 'get_credit_balance',
        ));
    }

    public function pinValidate($pin){
        return $this->post('/api/mitra/v6.2/pin/validate', array(
            'pin' => $pin,
        ), array(
            'source'    => 'kiriminaja_api',
            'operation' => 'pin_validate',
        ));
    }

    public function sendPickupRequestV2($payload){
        return $this->post('/api/mitra/v6.2/request_pickup', $payload, array(
            'source'    => 'kiriminaja_shipping',
            'operation' => 'send_pickup_request_v2',
        ));
    }
}
