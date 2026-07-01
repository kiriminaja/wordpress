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
            $shipping_method = $this->getChosenShippingMethod();
            $chosen_shipping_methods = '' !== $shipping_method ? explode('_', $shipping_method) : array();
            
            if( $chosen_shipping_methods[0]  == 'kiriminaja-official' ){
                if( isset($this->payload['payment_method']) && $this->payload['payment_method'] == 'cod' ){
                    $this->validateSelectedCourierSupportsCod($shipping_method);
                    $this->validateMinimumCodValue();
                    $this->validateMaximumCodValue();
                }
            }
        } catch (\Throwable $th) {
            return $this->error([],$th->getMessage());
        }
    }
    private function getChosenShippingMethod(): string {
        if ( empty( $this->payload['shipping_method'] ) || ! is_array( $this->payload['shipping_method'] ) ) {
            return '';
        }

        return sanitize_text_field( (string) ( $this->payload['shipping_method'][0] ?? '' ) );
    }
    private function validateSelectedCourierSupportsCod( string $shipping_method ): void {
        $selected_rate = $this->getSelectedShippingRate( $shipping_method );
        if ( ! $selected_rate instanceof \WC_Shipping_Rate ) {
            return;
        }

        $cod_available = $selected_rate->get_meta_data()['kiriof_rate_cod_available'] ?? '';
        if ( 'no' === $cod_available ) {
            wc_add_notice(
                esc_html__( 'Selected courier service does not support Cash on Delivery. Please choose a COD-supported courier service.', 'kiriminaja-official' ),
                'error'
            );
        }
    }
    private function getSelectedShippingRate( string $shipping_method ) {
        $packages = isset( $this->payload['shipping_packages'] ) && is_array( $this->payload['shipping_packages'] )
            ? $this->payload['shipping_packages']
            : array();

        foreach ( $packages as $package ) {
            $rates = isset( $package['rates'] ) && is_array( $package['rates'] ) ? $package['rates'] : array();
            foreach ( $rates as $rate_id => $rate ) {
                if ( (string) $rate_id === $shipping_method || ( $rate instanceof \WC_Shipping_Rate && $rate->get_id() === $shipping_method ) ) {
                    return $rate;
                }
            }
        }

        return null;
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
