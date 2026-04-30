<?php
namespace KiriminAjaOfficial\Services\CheckoutServices;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;
class OngkirPricingService extends BaseService{
    
    private bool $is_cod = false;
    private int $destination_area_id = 0;
    private array $wc_cart_contents = [];
    public function __construct($payload)
    {
        $this->is_cod               = $payload['is_cod'] ?? false;
        $this->destination_area_id  = $payload['destination_area_id'] ?? 0;
        $this->wc_cart_contents     = $payload['wc_cart_contents'] ?? [];
        return $this;
    }
    public function call(){
        
        $settingRepo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('origin_sub_district_id');
        if(!$settingRepo||$settingRepo->value === null){
            return self::error([],'Terjadi Kesalahan!');
        }  
        
        $cartAttributes = (new \KiriminAjaOfficial\Services\UtilServices\GetWCCartAttributeService([
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
        
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$pricingPayload',[$pricingPayload]);
        
        $kiriofPricing = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getPricing($pricingPayload);
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$kiriofPricing',[$kiriofPricing]);
        
        if(!$kiriofPricing['data']->status){
            return self::error([], $kiriofPricing['data']->text ?? 'Terjadi Kesalahan!');
        }
        
        return self::success([
            'options' => self::filterOptions($kiriofPricing['data'])
        ]);
    }
    
    private function filterOptions($pricingData){
        $options = $pricingData->results ?? [];
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