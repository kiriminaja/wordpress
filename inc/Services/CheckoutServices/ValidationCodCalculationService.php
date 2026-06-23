<?php
namespace KiriminAjaOfficial\Services\CheckoutServices;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;
use KiriminAjaOfficial\Repositories\SettingRepository;

class ValidationCodCalculationService extends BaseService{
    public  $payload;
    public function __construct($payload){
        $this->payload = $payload;
    }
    public function call(){
        try {
            $chosen_shipping_methods = isset($this->payload['shipping_method']) ? explode('_',$this->payload['shipping_method'][0]) : [];
            
            if( $chosen_shipping_methods[0]  == 'kiriminaja-official' ){
                if( isset($this->payload['payment_method']) && $this->payload['payment_method'] == 'cod' ){
                    $this->validateMinimumCodValue();
                    $this->validateMaximumCodValue();
                }
            }
        } catch (\Throwable $th) {
            return $this->error([],$th->getMessage());
        }
    }
    private function getMinCodValue(): float {
        $setting = SettingRepository::getValue( 'min_cod_threshold' );
        return $setting !== null && $setting > 0 ? (float) $setting : 10000.0;
    }
    private function getMaxCodValue(): float {
        $setting = SettingRepository::getValue( 'max_cod_threshold' );
        $default = defined( 'KIRIOF_MAX_COD_AMOUNT' ) ? (float) KIRIOF_MAX_COD_AMOUNT : 3000000.0;
        return $setting !== null && $setting > 0 ? (float) $setting : $default;
    }
    private function validateMinimumCodValue(){
        $minCodValue = $this->getMinCodValue();
        if( $this->payload['cart_total'] < $minCodValue ){
            // Translators: %s is the minimum COD amount formatted as a price.
            wc_add_notice( sprintf( esc_html__( 'Minimum COD is %s', 'kiriminaja-official' ), wc_price( $minCodValue ) ), 'error' );
        }
    }
    private function validateMaximumCodValue(){
        $maxCodValue = $this->getMaxCodValue();
        if( $this->payload['cart_total'] > $maxCodValue ){
            // Translators: %s is the maximum COD amount formatted as a price.
            wc_add_notice( sprintf( esc_html__( 'Maximum COD is %s', 'kiriminaja-official' ), wc_price($maxCodValue) ), 'error' );
        }
    }
}