<?php
namespace KiriminAjaOfficial\Services\CheckoutServices;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;
class ValidationCodCalculationService extends BaseService{
    public  $payload;
    private $minCodValue = 10000; //10.000
    private $maxCodValue = 3000000; //3.000.000
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
    private function validateMinimumCodValue(){
        if( $this->payload['cart_total'] < $this->minCodValue ){
            // Translators: %s is the minimum COD amount formatted as a price.
            wc_add_notice( sprintf( esc_html__( 'Minimum COD is %s', 'kiriminaja-official' ), wc_price( $this->minCodValue ) ), 'error' );
        }
    }
    private function validateMaximumCodValue(){
        if( $this->payload['cart_total'] > $this->maxCodValue ){
            // Translators: %s is the maximum COD amount formatted as a price.
            wc_add_notice( sprintf( esc_html__( 'Maximum COD is %s', 'kiriminaja-official' ), wc_price($this->maxCodValue) ), 'error' );
        }
    }
}