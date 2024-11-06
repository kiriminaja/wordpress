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
    
    
    private $pricingData;
    private $carts;
    private $selectedExpedition;

    
    public function __construct($payload){
        $this->payload = $payload;
        $this->destination_area_id  = @$payload['destination_area_id'];
        $this->expedition           = @$payload['expedition'];
        $this->wc_cart_contents     = @$payload['wc_cart_contents'];
        $this->is_insurance         = @$payload['is_insurance'];
        $this->is_cod               = @$payload['is_cod'];
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
        
        $courier = explode('_',$this->expedition)[0];

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
        $this->selectedExpedition = self::getSelectedExpedition();
        if (!$this->selectedExpedition){
            return self::error([],'Expedition Not Found');
        }

        return self::success([
            'cart'                  => $this->carts,
            'pricing'               => $this->pricingData,
            'payload'               => $this->payload,
            'calculation_result'    => self::checkoutCalculation(),
            'carts_attribute'       => $cartAttributes->data,
        ]);
    }
    
    private function isInsurance(){
        return $this->is_insurance;
    }
    private function isCOD(){
        return $this->is_cod;
    }
    
    private function checkoutCalculation(){
        $cartTotal = self::getCartTotal();
        $is_cod = self::isCOD();
        $is_insurance = self::isInsurance();
        $selected_expedition = $this->selectedExpedition;
        $insurance_amt = self::getCalculateInsuranceFee();
        $cod_amt = self::getCalculateCODFee();
        $ongkirFee = intval(intval(@$selected_expedition->cost ?? 0) - intval(@$selected_expedition->discount_amount ?? 0));
        $total_amt = $ongkirFee+$cod_amt+$insurance_amt+$cartTotal;
        return [
            'cart_total_amt' => $cartTotal,
            'cod_amt' => $cod_amt,
            'insurance_amt' => $insurance_amt,
            'ongkir_fee_amt' => $ongkirFee,
            'calc_total_amt' => $total_amt,
            'selected_expedition' => $selected_expedition,
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
        $service = explode('_',$this->expedition)[0];
        $service_type = explode('_',$this->expedition)[1];
        $selected_expedition = array_filter(@$this->pricingData->results ?? [],function ($obj) use ($service,$service_type){
            return strtolower($obj->service) == strtolower($service) && strtolower($obj->service_type) == strtolower($service_type);
        });
        return @$selected_expedition[array_key_first($selected_expedition)];
    }
    
    private function getCalculateInsuranceFee(){
        if (!self::isInsurance()){ return 0 ;}
        $cartTotal = self::getCartTotal();
        $selected_expedition = $this->selectedExpedition;
        $insuranceRate = floatval(@$selected_expedition->setting->insurance_fee ?? 0.0);
        $insuranceAddCost = intval(@$selected_expedition->setting->insurance_add_cost ?? 0);
        $insuranceMinCost = intval(@$selected_expedition->setting->insurance_minimum_cost ?? 0);
        $ongkirFee = intval(@$selected_expedition->cost ?? 0);
        
        $insuranceFee = (($cartTotal+$ongkirFee)*$insuranceRate)+$insuranceAddCost;
        $insuranceFee = $insuranceFee < $insuranceMinCost ? $insuranceMinCost : $insuranceFee;

        return ceil($insuranceFee);
    }
    
    private function getCalculateCODFee(){
        if (!self::isCOD()){ return 0 ;}
        $selected_expedition = $this->selectedExpedition;
        $cartTotal = self::getCartTotal();
        $ongkirFee = intval(@$selected_expedition->cost ?? 0);
        $insuranceFee = self::getCalculateInsuranceFee();
        $codRate = floatval(@$selected_expedition->setting->cod_fee ?? 0.0);
        $CODMinCost = intval(@$selected_expedition->setting->minimum_cod_fee ?? 0);
        
        $codFee=($cartTotal+$ongkirFee+$insuranceFee)*$codRate;
        $codFee = $codFee < $CODMinCost ? $CODMinCost : $codFee;

        return ceil($codFee);
    }
    
}