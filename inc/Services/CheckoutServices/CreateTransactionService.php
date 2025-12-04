<?php
namespace KiriminAjaOfficial\Services\CheckoutServices;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;
use WC_Order_Item_Fee;
class CreateTransactionService extends BaseService{
    
    private $payload;
    private $checkoutCalcCache;
    
    
    /**
    $payload array
    keys :
    order_id
    checkout_post_data
    kj_destination_area
    kj_destination_area_name
    kj_expedition
    insurance
    payment_method
    wc_cart_contents
     */
    public function __construct($payload){
        $this->payload = $payload;
        return $this;
    }
    
    
    public function call(){
        try {
            
            $checkoutCalc = $this->getCheckoutCalculation();
            if (!$checkoutCalc['status']){ 
                return self::error([],$checkoutCalc['message']);
            }
            
            $requiredPostMeta = $this->getRequiredPostMeta();
            if (!$requiredPostMeta['status']){ 
                return self::error([],$requiredPostMeta['message']);
            }
            /** Generating Payload*/
            $calcResult = $checkoutCalc['data']['calculation_result'];
            $cartsAttr = $checkoutCalc['data']['carts_attribute'];
            $forceInsurance = @$calcResult['selected_expedition']->force_insurance;
            
            $insurance_cost = ($this->payload['checkout_post_data']['kj_insurance'] || $forceInsurance) 
                ? $calcResult['insurance_amt'] 
                : 0;
            // Cache expedition split to avoid duplicate explode
            $expeditionParts = $this->payload['kj_expedition'] ? explode('_', $this->payload['kj_expedition'], 2) : ['', ''];
            $payload = [
                'order_id'                      => (new \KiriminAjaOfficial\Services\KiriminAja\GenerateOrderId())->call(),
                'shipping_info'                 => wp_json_encode($requiredPostMeta['data']),
                'destination_sub_district_id'   => $this->payload['kj_destination_area'],
                'destination_sub_district'      => $this->payload['kj_destination_area_name'],
                'status'                        => 'new',
                'service'                       => $expeditionParts[0],
                'service_name'                  => $expeditionParts[1] ?? '',
                'weight'                        => $cartsAttr['weight'],
                "length"                        => $cartsAttr['length'],
                "width"                         => $cartsAttr['width'],
                "height"                        => $cartsAttr['height'],
                'shipping_cost'                 => $calcResult['ongkir_fee_raw'],
                'insurance_cost'                => $insurance_cost,
                'cod_fee'                       => $calcResult['cod_amt'],
                'transaction_value'             => $calcResult['cart_total_amt'],
                'created_at'                    => gmdate('Y-m-d H:i:s'),
                'wp_wc_order_stat_order_id'     => $this->payload['order_id'],
                'discount_amount'               => $calcResult['discount_amt'] ?? null,
                'discount_percentage'           => $calcResult['discount_percentage'] ?? null,
            ];
            
            /** Update WC Total Order */
            $this->updateWcTotalOrder($checkoutCalc);
            
            $createTransactionRepo = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->createTransaction($payload);
            
            /** Save in Log Transaction*/
            update_post_meta( $this->payload['order_id'], 'log_after_checkout_order', compact('payload','createTransactionRepo') );
            
            if (!$createTransactionRepo){
                return self::error([],'fail creating transaction');
            }
            return self::success([],'success');
        }catch (\Throwable $th){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('err',[$th->getMessage()]);
            return self::error([],'fail creating transaction');
        }
    }
    private function updateWcTotalOrder($checkoutCalc){
        $order = wc_get_order($this->payload['order_id']);
        if (!$order) {
            return;
        }
        $calcResult = $checkoutCalc['data']['calculation_result'];
        $forceInsurance = @$calcResult['selected_expedition']->force_insurance ?? 0;
        $is_insurance = $this->payload['checkout_post_data']['kj_insurance'] || $forceInsurance;
        $is_cod = $this->payload['is_cod'] ?? 0;
        
        if ($is_cod) {
            $cod_amt = $calcResult['cod_amt'];
            $cod_fee = new WC_Order_Item_Fee();
            $cod_fee->set_name('COD Fee');
            $cod_fee->set_amount($cod_amt);
            $cod_fee->set_total($cod_amt);
            $order->add_item($cod_fee); 
        }
        if ($is_insurance) {
            $insurance_amt = $calcResult['insurance_amt'];
            $insurance_fee = new WC_Order_Item_Fee();
            $insurance_fee->set_name('Insurance');
            $insurance_fee->set_amount($insurance_amt);
            $insurance_fee->set_total($insurance_amt);
            $order->add_item($insurance_fee);
        }
        
        $order->calculate_totals();
        $order->save();
    }
    
    private function getRequiredPostMeta(){
        try {
            $postMetaRepo = (new \KiriminAjaOfficial\Repositories\WpPostMetaRepository())->getRequiredRowsByPostId($this->payload['order_id']);
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$postMetaRepo',[$postMetaRepo]);
            
            // Use array_column for more efficient mapping
            $returnArr = [];
            if (is_array($postMetaRepo) && !empty($postMetaRepo)) {
                foreach ($postMetaRepo as $postMeta) {
                    $returnArr[$postMeta->meta_key] = $postMeta->meta_value;
                }
            }
            
            return [
                'status'    => true,
                'msg'       => 'success',
                'data'      => $returnArr
            ];            
        }catch (\Throwable $th){
            return [
                'status'    => false,
                'msg'       => $th->getMessage(),
                'data'      => []
            ];
        }
    }
    
    private function getCheckoutCalculation(){
        // Return cached result if already calculated
        if ($this->checkoutCalcCache !== null) {
            return $this->checkoutCalcCache;
        }
        
        $this->payload['is_insurance'] = !empty($this->payload['checkout_post_data']['kj_insurance']) ? 1 : 0;
        
        $service = (new \KiriminAjaOfficial\Services\CheckoutServices\CheckoutCalculationService([
            'destination_area_id'   => $this->payload['kj_destination_area'],
            'expedition'            => $this->payload['kj_expedition'],
            'is_insurance'          => $this->payload['is_insurance'],
            'is_cod'                => $this->payload['is_cod'] ?? 0,
            'wc_cart_contents'      => $this->payload['wc_cart_contents'],
        ]))->call();
        
        if ($service->status !== 200){
            $result = [
                'status'    => false,
                'msg'       => $service->message ?? 'Something is wrong',
                'data'      => []
            ];
        } else {
            $result = [
                'status'    => true,
                'msg'       => 'success',
                'data'      => $service->data
            ];
        }
        
        // Cache the result
        $this->checkoutCalcCache = $result;
        return $result;
    }
}