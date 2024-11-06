<?php

namespace Inc\Services\CheckoutServices;

use Inc\Base\BaseService;

class OngkirPricingService extends BaseService{
    
    private bool $is_cod = false;
    private int $destination_area_id = 0;
    private array $wc_cart_contents = [];
    public function __construct($payload)
    {
        $this->is_cod               = @$payload['is_cod'];
        $this->destination_area_id  = @$payload['destination_area_id'];
        $this->wc_cart_contents     = @$payload['wc_cart_contents'];
        return $this;
    }

    public function call(){
        
        $settingRepo = (new \Inc\Repositories\SettingRepository())->getSettingByKey('origin_sub_district_id');
        if(!$settingRepo||$settingRepo->value === null){
            return self::error([],'Terjadi Kesalahan!');
        }  
        
        $cartAttributes = (new \Inc\Services\UtilServices\GetWCCartAttributeService([
            'wc_cart_contents' => $this->wc_cart_contents
        ]))->call();

        if ($cartAttributes->status !== 200){
            return self::error([],'Terjadi Kesalahan!');
        }
        
        $pricingPayload = [
            'subdistrict_origin'        => (int) $settingRepo->value,
            'subdistrict_destination'   => $this->destination_area_id,
            'weight'                    => $cartAttributes->data['weight'],
            "length"                    => $cartAttributes->data['length'],
            "width"                     => $cartAttributes->data['width'],
            "height"                    => $cartAttributes->data['height'],
            'insurance'                 => 1,
            'item_value'                => $cartAttributes->data['item_value'],
            'courier'                   => null
        ];
        
        (new \Inc\Base\BaseInit())->logThis('$pricingPayload',[$pricingPayload]);
        
        $kjPricing = (new \Inc\Repositories\KiriminajaApiRepository())->getPricing($pricingPayload);
        (new \Inc\Base\BaseInit())->logThis('$kjPricing',[$kjPricing]);
        
        if(!$kjPricing['data']->status){
            return self::error([],@$kjPricing['data'] ?? 'Terjadi Kesalahan!');
        }
        
        return self::success([
            'options' => self::filterOptions($kjPricing['data'])
        ]);
    }
    
    private function filterOptions($pricingData){
        $options = @$pricingData->results ?? [];
        $filteredOptions = [];
        foreach ($options as $option){
            if (!$this->is_cod || $this->is_cod && $option->cod){
                $filteredOptions[] = [
                    'key'=>$option->service.'_'.$option->service_type,
                    'value'=>$option->service_name.' (Rp'.(localMoneyFormat($option->cost-$option->discount_amount)).')'
                ];                
            }
        }
        return $filteredOptions;
    }
    
}