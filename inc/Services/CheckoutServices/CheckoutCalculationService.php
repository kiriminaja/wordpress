<?php

namespace Inc\Services\CheckoutServices;

use Inc\Base\BaseService;

class CheckoutCalculationService extends BaseService{
    
    /*
        destination_area_id
        expedition
        is_insurance
        is_cod
        wc_cart_contents
    
     * */
    
    public $payload;
    public int $destination_area_id = 0;
    public string $expedition = '';
    public array $wc_cart_contents = [];
    public bool $is_insurance = false;
    public bool $is_cod = false;
    
    private $carts;
    private $selectedExpedition;
    private $pricingData;
    private $expeditionParts;
    
    public function __construct($payload){
        $this->payload = $payload;
        $this->destination_area_id  = $payload['destination_area_id'] ?? 0;
        $this->expedition           = $payload['expedition'] ?? '';
        $this->wc_cart_contents     = $payload['wc_cart_contents'] ?? [];
        $this->is_insurance         = $payload['is_insurance'] ?? false;
        $this->is_cod               = $payload['is_cod'] ?? false;
        
        // Cache expedition parts to avoid multiple explode calls
        $this->expeditionParts = $this->expedition ? explode('_', $this->expedition, 2) : ['', ''];
        
        return $this;
    }

    public function call(){
        $this->carts = $this->wc_cart_contents;
        
        /** Origin Data*/
        $settingRepo = (new \Inc\Repositories\SettingRepository())->getSettingByKey('origin_sub_district_id');
        if(!$settingRepo||$settingRepo->value === null){
            return self::error([],'Terjadi Kesalahan!');
        }

        /** Cart Attribute Data*/
        $cartAttributes = (new \Inc\Services\UtilServices\GetWCCartAttributeService([
            'wc_cart_contents' => $this->wc_cart_contents
        ]))->call();

        if ($cartAttributes->status !== 200){
            return self::error([],'Terjadi Kesalahan!');
        }
        
        $courier = $this->expeditionParts[0];

        $pricingPayload = [
            'subdistrict_origin'        => (int) $settingRepo->value,
            'subdistrict_destination'   => $this->destination_area_id,
            'weight'                    => $cartAttributes->data['weight'],
            "length"                    => $cartAttributes->data['length'],
            "width"                     => $cartAttributes->data['width'],
            "height"                    => $cartAttributes->data['height'],
            'insurance'                 => empty( $this->is_insurance ) ? 0 : 1,
            'item_value'                => $cartAttributes->data['item_value'],
            'courier'                   => [$courier]
        ];

        
        (new \Inc\Base\BaseInit())->logThis('ck $pricingPayload',[$pricingPayload]);
        
        $kjPricing = (new \Inc\Repositories\KiriminajaApiRepository())->getPricing($pricingPayload);
        
        (new \Inc\Base\BaseInit())->logThis('ck $kjPricing',[$kjPricing]);
        
        if($kjPricing['status'] != 200){
            return self::error([],@$kjPricing['message'] ?? 'Terjadi Kesalahan!');
        }
        
        /** Jika gagal dapat data expedisi*/
        if(!$kjPricing['data']->status){
            return self::error([],@$kjPricing['data'] ?? 'Terjadi Kesalahan!');
        }
        
        /** jika opsi expedisi tidak ada*/
        $this->pricingData = @$kjPricing['data'];

        if (count(@$this->pricingData->results ?? [])<1){
            return self::error([],'Expedition Not Found');
        }

        /** jika expedisi terpilih  tidak ada*/
        $this->selectedExpedition = $this->getSelectedExpedition();
        if (!$this->selectedExpedition){
            return self::error([],'Expedition Not Found');
        }
        
        $checkoutCalculation = $this->checkoutCalculation();
        
        return self::success([
            'cart'                  => $this->carts,
            'pricing'               => $this->pricingData,
            'payload'               => $this->payload,
            'calculation_result'    => $checkoutCalculation,
            'carts_attribute'       => $cartAttributes->data,
            'pricing_payload'       => $pricingPayload,
        ]);
    }
    
    private function isInsurance(): bool{
        return (bool) $this->is_insurance;
    }
    
    private function isCOD(): bool{
        return (bool) $this->is_cod;
    }
    
    private function checkoutCalculation(){
        $cartTotal = $this->getCartTotal();
        $selected_expedition = $this->selectedExpedition;
        $insurance_amt = $this->getCalculateInsuranceFee();
        $cod_amt = $this->getCalculateCODFee();
        $ongkirFee = (int) ($selected_expedition->cost ?? 0);
        $total_amt = $ongkirFee + $cod_amt + $insurance_amt + $cartTotal;
        
        return [
            'cart_total_amt' => $cartTotal,
            'cod_amt' => $cod_amt,
            'insurance_amt' => $insurance_amt,
            'ongkir_fee_amt' => $ongkirFee,
            'calc_total_amt' => $total_amt,
            'selected_expedition' => $selected_expedition,
            'discount_amt' => (int) ($selected_expedition->discount_amount ?? 0),
            'discount_percentage' => (float) ($selected_expedition->discount_percentage ?? 0.0),
        ];
    }
    
    private function getCartTotal(){
        $cartTotal = 0;
        foreach ($this->carts as $cart){
            $cartTotal += @$cart['line_total'] ?? 0;
        }
        return $cartTotal;
    }
    
    private function getSelectedExpedition(){
        $service = $this->expeditionParts[0];
        $service_type = $this->expeditionParts[1] ?? '';
        
        if (empty($service) || empty($service_type)) {
            return null;
        }
        
        $results = $this->pricingData->results ?? [];
        $serviceLower = strtolower($service);
        $serviceTypeLower = strtolower($service_type);
        
        foreach ($results as $result) {
            if (strtolower($result->service) === $serviceLower && 
                strtolower($result->service_type) === $serviceTypeLower) {
                return $result;
            }
        }
        
        return null;
    }
    
    private function getCalculateInsuranceFee(){
        if ($this->isInsurance() || ($this->selectedExpedition->force_insurance ?? false)) { 
            return (float) ($this->selectedExpedition->insurance ?? 0);
        }
        
        return 0;
    }
    
    private function getCalculateCODFee(){
        if (!$this->isCOD()) { 
            return 0;
        }
        
        $codFeeAmount = (float) ($this->selectedExpedition->setting->cod_fee_amount ?? 0);
        $codMinCost = (int) ($this->selectedExpedition->setting->minimum_cod_fee ?? 0);
        
        $codFee = max($codFeeAmount, $codMinCost);

        return (int) ceil($codFee);
    }
    
}