<?php
namespace KiriminAjaOfficial\Services\OrderEditPageServices;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;
class ShippingInfoServices extends BaseService{
    
    public int $wcOrderId = 0;
    
    public function wcOrderId($wcOrderId){
        $this->wcOrderId = $wcOrderId;
        return $this;
    }
    
    public function call(){
        $repo = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransactionByWCOrderId($this->wcOrderId);
        if (!$repo) { return self::error([],'Not Found');}
        
        return self::success([
            'awb'               =>  @$repo->awb ? $repo->awb : '-' , 
            'status'            =>  kiriof_helper()->transactionStatusLabel(@$repo->status), 
            'status_classes'    =>  kiriof_helper()->transactionStatusClass(@$repo->status),
            'service'           =>  @$repo->service ? ((strtoupper($repo->service)).' '.(strtoupper($repo->service_name))) : '-', 
            'order_id'          =>  @$repo->order_id ? $repo->order_id : '-', 
            'pickup_id'         =>  @$repo->pickup_number ? $repo->pickup_number : '-', 
            'payment_type'      =>  @$repo->cod_fee && $repo->cod_fee > 0 ? 'COD' : 'Non COD', 
            'shipping_cost'     =>  @$repo->shipping_cost && $repo->shipping_cost > 0 ? ('Rp.'.kiriof_money_format($repo->shipping_cost - $repo->discount_amount)) : '-', 
            'discount_amount'   =>  @$repo->discount_amount && $repo->discount_amount > 0 ? ('Rp.'.kiriof_money_format($repo->discount_amount)) : '-',
            'insurance_fee'     =>  @$repo->insurance_cost&& $repo->insurance_cost > 0 ? ('Rp.'.kiriof_money_format($repo->insurance_cost)) : '-', 
            'cod_fee'           =>  @$repo->cod_fee && $repo->cod_fee > 0 ? ('Rp.'.kiriof_money_format($repo->cod_fee)) : '-', 
            'transaction_value' =>  @$repo->transaction_value && $repo->transaction_value > 0 ? ('Rp.'.kiriof_money_format($repo->transaction_value)) : '-', 
            'total'             =>  'Rp.'.kiriof_money_format(self::calculateTotal($repo)), 
            'destination_phone'  => $this->getDestinationPhone($repo),
            'destination_address' => $repo->destination_sub_district ?? '',
            'weight_grams'       => @$repo->weight ? number_format_i18n((float) $repo->weight, 0) . ' g' : '-',
            // Deficit-related fields for metabox.
            'is_deficit'         => (int) ( $repo->is_deficit ?? 0 ),
            'cod_minimum'        => (float) ( $repo->cod_minimum ?? 0 ),
            'shipping_cost_raw'  => (float) ( $repo->shipping_cost ?? 0 ),
            'insurance_cost_raw' => (float) ( $repo->insurance_cost ?? 0 ),
            'cod_fee_raw'        => (float) ( $repo->cod_fee ?? 0 ),
            'transaction_value_raw' => (float) ( $repo->transaction_value ?? 0 ),
            'discount_amount_raw'   => (float) ( $repo->discount_amount ?? 0 ),
            'ka_order_id'        => $repo->order_id ?? '',
            'wc_order_id'        => (int) ( $repo->wp_wc_order_stat_order_id ?? 0 ),
        ],'success');
    }
    
    private function calculateTotal($repo){
        return 
            (@$repo->shipping_cost ?? 0) +
            (@$repo->insurance_cost ?? 0) +
            (@$repo->cod_fee ?? 0) +
            (@$repo->transaction_value ?? 0)-
            (@$repo->discount_amount ?? 0);
    }

    private function getDestinationPhone($repo){
        $shipping_info = json_decode($repo->shipping_info ?? '{}');
        $phone = $shipping_info->_shipping_phone ?? $shipping_info->_billing_phone ?? '';
        return $phone;
    }
}