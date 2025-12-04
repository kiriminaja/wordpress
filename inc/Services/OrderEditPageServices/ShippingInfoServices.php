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
            'order_id'          =>  @$repo->order_id ? $repo->order_id : '-', 
            'pickup_id'         =>  @$repo->pickup_number ? $repo->pickup_number : '-', 
            'payment_type'      =>  @$repo->cod_fee && $repo->cod_fee > 0 ? 'COD' : 'Non COD', 
            'service'           =>  @$repo->service ? ((strtoupper($repo->service)).' '.(strtoupper($repo->service_name))) : '-', 
            'awb'               =>  @$repo->awb ? $repo->awb : '-' , 
            'status'            =>  kjHelper()->transactionStatusLabel(@$repo->status), 
            'shipping_cost'     =>  @$repo->shipping_cost && $repo->shipping_cost > 0 ? ('Rp.'.localMoneyFormat($repo->shipping_cost - $repo->discount_amount)) : '-', 
            'insurance_fee'     =>  @$repo->insurance_cost&& $repo->insurance_cost > 0 ? ('Rp.'.localMoneyFormat($repo->insurance_cost)) : '-', 
            'cod_fee'           =>  @$repo->cod_fee && $repo->cod_fee > 0 ? ('Rp.'.localMoneyFormat($repo->cod_fee)) : '-', 
            'transaction_value' =>  @$repo->transaction_value && $repo->transaction_value > 0 ? ('Rp.'.localMoneyFormat($repo->transaction_value)) : '-', 
            'total'             =>  'Rp.'.localMoneyFormat(self::calculateTotal($repo)), 
            'discount_amount'  =>  @$repo->discount_amount && $repo->discount_amount > 0 ? ('Rp.'.localMoneyFormat($repo->discount_amount)) : '-',
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
}