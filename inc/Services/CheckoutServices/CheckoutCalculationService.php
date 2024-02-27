<?php

namespace Inc\Services\CheckoutServices;

use Inc\Base\BaseService;

class CheckoutCalculationService extends BaseService{
    
    public $payload;
    private $pricingData;
    private $carts;
    
    public function __construct($payload){
        $this->payload = $payload;
        return $this;
    }

    public function call(){
//        (new \Inc\Base\BaseInit())->logThis('payload',[$this->payload]);
        $this->carts = $this->payload['wc_cart_contents'];
//        (new \Inc\Base\BaseInit())->logThis('$cart',[$this->carts]);
        
        $courier = explode('_',$this->payload['expedition'])[0];
        $kjPricing = (new \Inc\Repositories\KiriminajaApiRepository())->getPricing([
            'subdistrict_origin'     => 31552,
            'subdistrict_destination'     => 31552,
            'weight'     => 1100,
            "length"        => 20,
            "width"     => 180,
            "height"    => 20,
            'insurance'     => 0,
            'item_value'     => 100000,
            'courier'     => [$courier]
        ]);

        if(!$kjPricing['data']->status){
            return self::error([],@$kjPricing['data'] ?? 'Terjadi Kesalahan!');
        }
        $this->pricingData = @$kjPricing['data'];
        if (count(@$this->pricingData->results ?? [])<1){
            return self::error([],'Expedition Not Found');
        }

        return self::success([
            'cart' => $this->carts,
            'pricing' => $this->pricingData,
            'payload' => $this->payload,
            'calculation_result' => self::checkoutCalculation(),
        ]);
    }
    
    private function isInsurance(){
        return $this->payload['insurance'];
    }
    private function isCOD(){
        return $this->payload['payment_method'] === 'cod';
    }
    
    private function checkoutCalculation(){
        $cartTotal = self::getCartTotal();
        $is_cod = self::isCOD();
        $is_insurance = self::isInsurance();
        $selected_expedition = self::getSelectedExpedition();
        $insurance_amt = self::getCalculateInsuranceFee();
        $cod_amt = self::getCalculateCODFee();
        $ongkirFee = intval(@$selected_expedition->cost ?? 0);
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
        $service = explode('_',$this->payload['expedition'])[0];
        $service_type = explode('_',$this->payload['expedition'])[1];
        $selected_expedition = array_filter(@$this->pricingData->results ?? [],function ($obj) use ($service,$service_type){
            if ($obj->service === $service && $obj->service_type === $service_type){
                return true;
            }
        });
        return @$selected_expedition[0];
    }
    
    private function getCalculateInsuranceFee(){
        if (!self::isInsurance()){ return 0 ;}
        $cartTotal = self::getCartTotal();
        $selected_expedition = self::getSelectedExpedition();
        $insuranceRate = floatval(@$selected_expedition->setting->insurance_fee ?? 0.0);
        $insuranceAddCost = intval(@$selected_expedition->setting->insurance_add_cost ?? 0);
        $insuranceMinCost = intval(@$selected_expedition->setting->insurance_minimum_cost ?? 0);
        $ongkirFee = intval(@$selected_expedition->cost ?? 0);
        
        $insuranceFee = (($cartTotal+$ongkirFee)*$insuranceRate)+$insuranceAddCost;
        $insuranceFee = $insuranceFee < $insuranceMinCost ? $insuranceMinCost : $insuranceFee;

//        (new \Inc\Base\BaseInit())->logThis('getCalculateInsuranceFee',[
//            '$cartTotal'=>$cartTotal,
//            '$insuranceRate'=>$insuranceRate,
//            '$insuranceAddCost'=>$insuranceAddCost,
//            '$insuranceMinCost'=>$insuranceMinCost,
//            '$ongkirFee'=>$ongkirFee,
//            '$insuranceFee'=>$insuranceFee,
//        ]);
        
        return ceil($insuranceFee);
    }
    
    private function getCalculateCODFee(){
        if (!self::isCOD()){ return 0 ;}
        $selected_expedition = self::getSelectedExpedition();
        $cartTotal = self::getCartTotal();
        $ongkirFee = intval(@$selected_expedition->cost ?? 0);
        $insuranceFee = self::getCalculateInsuranceFee();
        $codRate = floatval(@$selected_expedition->setting->cod_fee ?? 0.0);
        $CODMinCost = intval(@$selected_expedition->setting->minimum_cod_fee ?? 0);
        
        $codFee=($cartTotal+$ongkirFee+$insuranceFee)*$codRate;
        $codFee = $codFee < $CODMinCost ? $CODMinCost : $codFee;

//        (new \Inc\Base\BaseInit())->logThis('getCalculateInsuranceFee',[
//            '$cartTotal'=>$cartTotal,
//            '$ongkirFee'=>$ongkirFee,
//            '$insuranceFee'=>$insuranceFee,
//            '$codRate'=>$codRate,
//            '$codFee'=>$codFee,
//        ]);
        
        return ceil($codFee);
    }
    
}