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
    private int $origin_sub_district_id = 0;
    private array $package_overrides = [];
    public function __construct($payload)
    {
        $this->is_cod               = @$payload['is_cod'];
        $this->destination_area_id  = @$payload['destination_area_id'];
        $this->wc_cart_contents     = ! empty( $payload['wc_cart_contents'] ) && is_array( $payload['wc_cart_contents'] )
            ? $payload['wc_cart_contents']
            : [];
        $this->origin_sub_district_id = ! empty( $payload['origin_sub_district_id'] ) ? (int) $payload['origin_sub_district_id'] : 0;
        $this->package_overrides    = ! empty( $payload['package_overrides'] ) && is_array( $payload['package_overrides'] ) ? $payload['package_overrides'] : [];
        return $this;
    }
    public function call(){
        
        $settingRepository = new \KiriminAjaOfficial\Repositories\SettingRepository();
        $settingRepo = $settingRepository->getSettingByKey('origin_sub_district_id');
        if ( 0 === $this->origin_sub_district_id && ( ! $settingRepo || $settingRepo->value === null ) ) {
            return self::error([],'Terjadi Kesalahan!');
        }  
        $courier_filter = $settingRepository->getWhitelistExpeditionIds();
        
        if ( ! empty( $this->package_overrides ) ) {
            $weight     = (float) ( $this->package_overrides['weight'] ?? 0 );
            $length     = (float) ( $this->package_overrides['length'] ?? 0 );
            $width      = (float) ( $this->package_overrides['width'] ?? 0 );
            $height     = (float) ( $this->package_overrides['height'] ?? 0 );
            $item_value = (int) ( $this->package_overrides['item_value'] ?? 0 );
        } else {
            $cartAttributes = (new \KiriminAjaOfficial\Services\UtilServices\GetWCCartAttributeService([
                'wc_cart_contents' => $this->wc_cart_contents
            ]))->call();
            if ($cartAttributes->status !== 200){
                return self::error([],'Terjadi Kesalahan!');
            }
            $weight     = (float) $cartAttributes->data['weight'];
            $length     = (float) $cartAttributes->data['length'];
            $width      = (float) $cartAttributes->data['width'];
            $height     = (float) $cartAttributes->data['height'];
            $item_value = (int) $cartAttributes->data['item_value'];
        }
        
        $pricingPayload = [
            'subdistrict_origin'        => $this->origin_sub_district_id > 0 ? $this->origin_sub_district_id : (int) $settingRepo->value,
            'subdistrict_destination'   => $this->destination_area_id,
            'weight'                    => $weight,
            "length"                    => $length,
            "width"                     => $width,
            "height"                    => $height,
            'insurance'                 => 1,
            'item_value'                => $item_value,
            'courier'                   => ! empty( $courier_filter ) ? $courier_filter : null
        ];
        
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$pricingPayload',[$pricingPayload]);
        
        $cachedPricingData = PricingCacheService::get( $pricingPayload );
        if ( $cachedPricingData ) {
            $kiriofPricing = array(
                'status' => true,
                'data'   => $cachedPricingData,
            );
        } else {
            $kiriofPricing = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getPricing($pricingPayload);
            if ( ! empty( $kiriofPricing['status'] ) && ! empty( $kiriofPricing['data'] ) ) {
                PricingCacheService::put( $pricingPayload, $kiriofPricing['data'] );
            }
        }
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
            $kiriof_price = max( 0, (float) $option->cost - (float) $option->discount_amount );
            $rateOption = [
                'key'=>$option->service.'_'.$option->service_type,
                'value'=>kiriof_helper()->formatServiceName($option->service, $option->service_name).' (Rp'.(kiriof_money_format($kiriof_price)).')',
                'courier' => kiriof_helper()->formatServiceName($option->service, $option->service_name),
                'service' => (string) $option->service_type,
                'service_code' => (string) $option->service,
                'service_type' => (string) $option->service_type,
                'service_name' => (string) ( $option->service_name ?? '' ),
                'price' => $kiriof_price,
                'etd' => (string) ( $option->etd ?? '' ),
            ];
            $allOptions[] = $rateOption;

            if (!$this->is_cod || $this->isCodCapableOption($option)){
                $filteredOptions[] = $rateOption;
            }
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
