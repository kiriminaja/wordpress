<?php
namespace KiriminAjaOfficial\Services\CheckoutServices;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;
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
        $settingRepo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('origin_sub_district_id');
        if(!$settingRepo||$settingRepo->value === null){
            return self::error([],'Terjadi Kesalahan!');
        }
        /** Cart Attribute Data*/
        $cartAttributes = (new \KiriminAjaOfficial\Services\UtilServices\GetWCCartAttributeService([
            'wc_cart_contents' => $this->wc_cart_contents
        ]))->call();
        if ($cartAttributes->status !== 200){
            return self::error([],'Terjadi Kesalahan!');
        }

        if ($this->hasActiveFreeShippingCoupon()) {
            $this->selectedExpedition = (object) [
                'cost' => 0,
                'discount_amount' => 0,
                'discount_percentage' => 0,
                'service' => $this->expeditionParts[0] ?? '',
                'service_type' => $this->expeditionParts[1] ?? '',
                'setting' => (object) [
                    'cod_fee_amount' => 0,
                    'minimum_cod_fee' => 0,
                ],
            ];

            $checkoutCalculation = $this->checkoutCalculation();

            return self::success([
                'cart'                  => $this->carts,
                'pricing'               => null,
                'payload'               => $this->payload,
                'calculation_result'    => $checkoutCalculation,
                'carts_attribute'       => $cartAttributes->data,
                'pricing_payload'       => [],
            ]);
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
            'item_value'                => (int) $cartAttributes->data['item_value'],
            'courier'                   => [$courier]
        ];
        
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('ck $pricingPayload',[$pricingPayload]);
        
        $kiriofPricing = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->getPricing($pricingPayload);
        
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('ck $kiriofPricing',[$kiriofPricing]);
        
        if($kiriofPricing['status'] != 200){
            return self::error([],@$kiriofPricing['message'] ?? 'Terjadi Kesalahan!');
        }
        
        /** Jika gagal dapat data expedisi*/
        if(!$kiriofPricing['data']->status){
            return self::error([],@$kiriofPricing['data'] ?? 'Terjadi Kesalahan!');
        }
        
        /** jika opsi expedisi tidak ada*/
        $this->pricingData = @$kiriofPricing['data'];
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
        $cartTotals = $this->getCartTotal();
        $cartTotalAfterDiscount = (float) ($cartTotals['after_discount'] ?? 0);
        $cartTotalBeforeDiscount = (float) ($cartTotals['before_discount'] ?? 0);
        $wooDiscountAmount = (float) ($cartTotals['discount_amount'] ?? 0);
        $wooDiscountDescription = (string) ($cartTotals['discount_description'] ?? '');
        $selected_expedition = $this->selectedExpedition;
        $insurance_amt = $this->getCalculateInsuranceFee();
        $cod_amt = $this->getCalculateCODFee();
        $ongkirFee = (int) ($selected_expedition->cost ?? 0) - (int) ($selected_expedition->discount_amount ?? 0);
        $total_amt = $ongkirFee + $cod_amt + $insurance_amt + $cartTotalAfterDiscount;
        
        return [
            'cart_total_amt' => $cartTotalAfterDiscount,
            'cart_total_before_discount' => $cartTotalBeforeDiscount,
            'cart_total_after_discount' => $cartTotalAfterDiscount,
            'woo_discount_amount' => $wooDiscountAmount,
            'woo_discount_description' => $wooDiscountDescription,
            'cod_amt' => $cod_amt,
            'insurance_amt' => $insurance_amt,
            'ongkir_fee_amt' => $ongkirFee,
            'ongkir_fee_raw' => (int) ($selected_expedition->cost ?? 0),
            'calc_total_amt' => $total_amt,
            'selected_expedition' => $selected_expedition,
            'discount_amt' => (int) ($selected_expedition->discount_amount ?? 0),
            'discount_percentage' => (float) ($selected_expedition->discount_percentage ?? 0.0),
        ];
    }
    
    private function getCartTotal(){
        $cartTotalBeforeDiscount = 0;
        foreach ($this->carts as $cart){
            $cartTotalBeforeDiscount += (float) (@$cart['line_subtotal'] ?? @$cart['line_total'] ?? 0);
        }

        $cartTotalAfterDiscount = 0;
        if (function_exists('WC') && WC() && isset(WC()->cart) && WC()->cart) {
            $cartTotalAfterDiscount = (float) WC()->cart->get_total('edit');
        }
        if ($cartTotalAfterDiscount <= 0 && $cartTotalBeforeDiscount > 0) {
            $cartTotalAfterDiscount = 0;
            foreach ($this->carts as $cart) {
                $cartTotalAfterDiscount += (float) (@$cart['line_total'] ?? 0);
            }
        }

        $discountAmount = max(0, $cartTotalBeforeDiscount - $cartTotalAfterDiscount);
        $discountDescription = '';
        if (function_exists('WC') && WC() && isset(WC()->cart) && WC()->cart) {
            $couponCodes = array_keys((array) WC()->cart->get_coupons());
            if (is_array($couponCodes) && !empty($couponCodes)) {
                $discountDescription = implode(', ', array_filter(array_map('sanitize_text_field', $couponCodes)));
            }
        }

        return [
            'before_discount' => (float) $cartTotalBeforeDiscount,
            'after_discount' => (float) $cartTotalAfterDiscount,
            'discount_amount' => (float) $discountAmount,
            'discount_description' => $discountDescription,
        ];
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
        $global_enabled = ((new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('enable_insurance'))->value ?? 'yes';
        if ($this->isInsurance() || ($this->selectedExpedition->force_insurance ?? false) || 'yes' === $global_enabled) { 
            return (float) ($this->selectedExpedition->insurance ?? 0);
        }
        
        return 0;
    }
    
    private function getCalculateCODFee(){
        if (!$this->isCOD()) { 
            return 0;
        }

        // Match main branch behavior: API pricing already returns the COD fee amount
        // for the selected service in cod_fee_amount. Recalculating it locally from
        // cod_fee as a rate can drift from production/main and makes checkout totals
        // miss compared to the current stable branch.
        $codFeeAmount = (float) ($this->selectedExpedition->setting->cod_fee_amount ?? 0);
        $codMinCost = (int) ($this->selectedExpedition->setting->minimum_cod_fee ?? 0);
        
        $codFee = max($codFeeAmount, $codMinCost);
        return (int) ceil($codFee);
    }

    private function hasActiveFreeShippingCoupon(){
        if (!function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart) {
            return false;
        }

        if ((float) WC()->cart->get_shipping_total() > 0) {
            return false;
        }

        foreach (WC()->cart->get_coupons() as $coupon) {
            if ($coupon && method_exists($coupon, 'get_free_shipping') && $coupon->get_free_shipping()) {
                return true;
            }
        }

        return false;
    }
}