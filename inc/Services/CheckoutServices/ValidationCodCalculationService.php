<?php 
namespace Inc\Services\CheckoutServices;

use Inc\Base\BaseService;

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
            
            if( $chosen_shipping_methods[0]  == 'kiriminaja' ){
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
            wc_add_notice( __( 'Minimum COD is '.wc_price($this->minCodValue), 'kiriminaja' ), 'error' );
        }
    }

    private function validateMaximumCodValue(){
        if( $this->payload['cart_total'] > $this->maxCodValue ){
            wc_add_notice( __( 'Maximum COD is '.wc_price($this->maxCodValue), 'kiriminaja' ), 'error' );
        }
    }
}

?>