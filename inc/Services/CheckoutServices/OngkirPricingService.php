<?php

namespace Inc\Services\CheckoutServices;

use Inc\Base\BaseService;

class OngkirPricingService extends BaseService{
    
    public $payload;
    public function __construct($payload)
    {
        $this->payload = $payload;
        return $this;
    }

    public function call(){

//        (new \Inc\Base\BaseInit())->logThis('payload',[$this->payload]);
//        $cart = WC()->cart->cart_contents;
//        (new \Inc\Base\BaseInit())->logThis('$cart',[$cart]);
        
        $kjPricing = (new \Inc\Repositories\KiriminajaApiRepository())->getPricing([
            'subdistrict_origin'     => 31552,
            'subdistrict_destination'     => 31552,
            'weight'     => 1100,
            "length"        => 20,
            "width"     => 180,
            "height"    => 20,
            'insurance'     => 0,
            'item_value'     => 100000,
            'courier'     => null
        ]);
        if(!$kjPricing['data']->status){
            return self::error([],@$kjPricing['data'] ?? 'Terjadi Kesalahan!');
        }

        (new \Inc\Base\BaseInit())->logThis('$kjPricing',[$kjPricing]);
        
        return self::success([
            'options' => self::filterOptions($kjPricing['data'])
        ]);
    }
    
    private function filterOptions($pricingData){
        $options = @$pricingData->results ?? [];
        $filteredOptions = [];
        foreach ($options as $option){
            $filteredOptions[] = [
               'key'=>$option->service.'_'.$option->service_type,
               'value'=>$option->service_name.' (Rp'.(localMoneyFormat($option->cost-$option->discount_amount)).')'
            ];
        }
        return $filteredOptions;
    }
    
}