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
        $this->is_cod               = @$payload['is_cod'];
        $this->destination_area_id  = @$payload['destination_area_id'];
        $this->wc_cart_contents     = @$payload['wc_cart_contents'];
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
            'item_value'                => (int) $cartAttributes->data['item_value'],
            'courier'                   => null
        ];
        
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$pricingPayload',[$pricingPayload]);
        
        $kiriofPricing = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getPricing($pricingPayload);
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$kiriofPricing',[$kiriofPricing]);
        
        if(!$kiriofPricing['data']->status){
            return self::error([],@$kiriofPricing['data'] ?? 'Terjadi Kesalahan!');
        }
        
        return self::success([
            'options' => $this->filterOptions($kiriofPricing['data'])
        ]);
    }
    
    private function filterOptions($pricingData){
        $options = @$pricingData->results ?? [];
        $filteredOptions = [];
        $allOptions = [];
        foreach ($options as $option){
<<<<<<< HEAD
            $rateOption = [
                'key'=>$option->service.'_'.$option->service_type,
                'value'=>$option->service_name.' (Rp'.(kiriof_money_format($option->cost-$option->discount_amount)).')'
            ];
            $allOptions[] = $rateOption;

            if (!$this->is_cod || $this->isCodCapableOption($option)){
                $filteredOptions[] = $rateOption;
=======
            if (!$this->is_cod || $this->is_cod && $option->cod){
                $filteredOptions[] = [
                    'key'=>$option->service.'_'.$option->service_type,
                    'value'=>kiriof_helper()->formatServiceName($option->service, $option->service_name).' (Rp'.(kiriof_money_format($option->cost-$option->discount_amount)).')'
                ];                
>>>>>>> origin/main
            }
        }
        if ($this->is_cod && empty($filteredOptions) && !empty($allOptions)) {
            return $allOptions;
        }
        return $filteredOptions;
    }

    private function isCodCapableOption($option){
        $codValue = $option->cod ?? null;
        if ($this->isTruthyCodValue($codValue)) {
            return true;
        }

        $setting = is_object($option) && isset($option->setting) && is_object($option->setting)
            ? $option->setting
            : null;
        if (!$setting) {
            return false;
        }

        foreach (array('cod', 'is_cod', 'cod_enabled', 'cod_available') as $key) {
            if (isset($setting->{$key}) && $this->isTruthyCodValue($setting->{$key})) {
                return true;
            }
        }

        foreach (array('cod_fee_amount', 'minimum_cod_fee', 'cod_fee') as $key) {
            if (isset($setting->{$key}) && (float) $setting->{$key} > 0) {
                return true;
            }
        }

        return false;
    }

    private function isTruthyCodValue($value){
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (float) $value > 0;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), array('1', 'true', 'yes', 'y', 'available', 'enabled'), true);
        }

        return false;
    }
}
