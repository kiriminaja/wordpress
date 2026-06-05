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
    kiriof_destination_area
    kiriof_destination_area_name
    kiriof_expedition
    insurance
    payment_method
    wc_cart_contents
     */
    public function __construct($payload){
        $this->payload = $payload;
        $this->payload['wc_cart_contents'] = $this->normalizeCartContents( $payload['wc_cart_contents'] ?? array() );
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
            
            $is_insurance = $this->isInsuranceRequested( $forceInsurance );

            $insurance_cost = $is_insurance
                ? $calcResult['insurance_amt'] 
                : 0;
            $transactionValue = (float) ($calcResult['cart_total_after_discount'] ?? $calcResult['cart_total_amt'] ?? 0);
            $shippingCostRaw = (float) ($calcResult['ongkir_fee_raw'] ?? 0);
            $codFee = (float) ($calcResult['cod_amt'] ?? 0);
            $isCod = !empty($this->payload['is_cod']);

            // Determine deficit status via CodDeficitService.
            $expeditionParts = $this->payload['kiriof_expedition'] ? explode('_', $this->payload['kiriof_expedition'], 2) : ['', ''];
            $deficitResult = (new \KiriminAjaOfficial\Services\CheckoutServices\CodDeficitService())->detect([
                'is_cod'               => $isCod,
                'total_cod'            => $transactionValue,
                'shipping_cost'        => $shippingCostRaw,
                'insurance_fee'        => (float) $insurance_cost,
                'cod_fee'              => $codFee,
                'admin_fee'            => 0,
                'item_price'           => $transactionValue,
                'courier_code'         => $expeditionParts[0],
                'courier_service_code' => $expeditionParts[1] ?? '',
                'discount_amount'      => (float) ($calcResult['discount_amt'] ?? 0),
            ]);
            $isDeficit  = $deficitResult['isDeficit'] ? 1 : 0;
            $codMinimum = $deficitResult['codMinimum'];
            $wooDiscountAmount = (float) ($calcResult['woo_discount_amount'] ?? 0);
            if ($wooDiscountAmount <= 0 && !empty($this->payload['woo_discount_amount'])) {
                $wooDiscountAmount = (float) $this->payload['woo_discount_amount'];
            }
            $wooDiscountDescription = (string) ($calcResult['woo_discount_description'] ?? '');
            if ($wooDiscountDescription === '' && !empty($this->payload['woo_discount_description'])) {
                $wooDiscountDescription = (string) $this->payload['woo_discount_description'];
            }
            // $expeditionParts already computed above for deficit detection.
            $payload = [
                'order_id'                      => (new \KiriminAjaOfficial\Services\KiriminAja\GenerateOrderId())->call(),
                'shipping_info'                 => wp_json_encode($requiredPostMeta['data']),
                'destination_sub_district_id'   => $this->payload['kiriof_destination_area'],
                'destination_sub_district'      => $this->payload['kiriof_destination_area_name'],
                'status'                        => 'new',
                'service'                       => $expeditionParts[0],
                'service_name'                  => $expeditionParts[1] ?? '',
                'weight'                        => $cartsAttr['weight'],
                "length"                        => $cartsAttr['length'],
                "width"                         => $cartsAttr['width'],
                "height"                        => $cartsAttr['height'],
                'shipping_cost'                 => $shippingCostRaw,
                'insurance_cost'                => $insurance_cost,
                'cod_fee'                       => $codFee,
                'transaction_value'             => $transactionValue,
                'created_at'                    => gmdate('Y-m-d H:i:s'),
                'wp_wc_order_stat_order_id'     => $this->payload['order_id'],
                'discount_amount'               => $calcResult['discount_amt'] ?? null,
                'discount_percentage'           => $calcResult['discount_percentage'] ?? null,
                'woocommerce_discount_amount'   => $wooDiscountAmount,
                'woocommerce_discount_description' => $wooDiscountDescription,
                'is_deficit'                    => $isDeficit,
                'cod_minimum'                   => $isCod ? $codMinimum : null,
            ];
            
            /** Update WC Total Order */
            $this->updateWcTotalOrder($checkoutCalc);
            
            $createTransactionRepo = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->createTransaction($payload);
            
            /** Save in Log Transaction*/
            update_post_meta( $this->payload['order_id'], 'log_after_checkout_order', compact('payload','createTransactionRepo') );
            
            if (!$createTransactionRepo){
                return self::error([],'fail creating transaction');
            }

            // Add WC order note and meta when deficit is detected.
            if ( $isDeficit ) {
                $wcOrder = wc_get_order( $this->payload['order_id'] );
                if ( $wcOrder ) {
                    $wcOrder->add_order_note(
                        __( 'COD order flagged as deficit — total COD below minimum threshold.', 'kiriminaja-official' )
                    );
                    $wcOrder->update_meta_data( 'cod-deficit', '1' );
                    $wcOrder->save();
                }
            }

            return self::success([],'success');
        }catch (\Throwable $th){
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis('err',[$th->getMessage()]);
            return self::error([],'fail creating transaction');
        }
    }
    private function normalizeCartContents( $cart_contents ){
        if ( is_array( $cart_contents ) && ! empty( $cart_contents ) ) {
            return $cart_contents;
        }

        return $this->getOrderCartContentsFallback();
    }

    private function getOrderCartContentsFallback(){
        if ( empty( $this->payload['order_id'] ) || ! function_exists( 'wc_get_order' ) ) {
            return array();
        }

        $order = wc_get_order($this->payload['order_id']);
        if ( ! $order ) {
            return array();
        }

        $cart_contents = array();
        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            $product_id = $item->get_variation_id() ?: $item->get_product_id();
            if ( empty( $product_id ) ) {
                continue;
            }

            $cart_contents[ $item_id ] = array(
                'product_id' => $product_id,
                'quantity'   => $item->get_quantity(),
                'line_total' => $item->get_total(),
            );
        }

        return $cart_contents;
    }

    private function updateWcTotalOrder($checkoutCalc){
        $order = wc_get_order($this->payload['order_id']);
        if (!$order) {
            return;
        }
        $calcResult = $checkoutCalc['data']['calculation_result'];
        $forceInsurance = @$calcResult['selected_expedition']->force_insurance ?? 0;
        $is_insurance = $this->isInsuranceRequested( $forceInsurance );
        $is_cod = $this->payload['is_cod'] ?? 0;
        
        if ($is_cod && ! $this->orderHasFeeItem($order, 'COD Fee')) {
            $cod_amt = $calcResult['cod_amt'];
            $cod_fee = new WC_Order_Item_Fee();
            $cod_fee->set_name('COD Fee');
            $cod_fee->set_amount($cod_amt);
            $cod_fee->set_total($cod_amt);
            $order->add_item($cod_fee); 
        }
        if ($is_insurance && ! $this->orderHasFeeItem($order, 'Insurance')) {
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

    private function orderHasFeeItem($order, $feeName){
        foreach ($order->get_items('fee') as $feeItem) {
            if ($feeItem->get_name() === $feeName) {
                return true;
            }
        }
        return false;
    }

    private function isInsuranceRequested($forceInsurance = 0){
        $insurance_setting = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByKey('enable_insurance');
        $global_insurance  = ( $insurance_setting && 'yes' === $insurance_setting->value );

        return ! empty( $this->payload['checkout_post_data']['kiriof_insurance'] )
            || ! empty( $this->payload['is_insurance'] )
            || ! empty( $forceInsurance )
            || $global_insurance;
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
        
        $this->payload['is_insurance'] = $this->isInsuranceRequested() ? 1 : 0;
        
        $service = (new \KiriminAjaOfficial\Services\CheckoutServices\CheckoutCalculationService([
            'destination_area_id'   => $this->payload['kiriof_destination_area'],
            'expedition'            => $this->payload['kiriof_expedition'],
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