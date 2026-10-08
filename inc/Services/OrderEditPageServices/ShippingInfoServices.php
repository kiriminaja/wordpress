<?php
namespace KiriminAjaOfficial\Services\OrderEditPageServices;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;
use KiriminAjaOfficial\Services\ShipmentDetailAmounts;
use KiriminAjaOfficial\Services\ShipmentDetailPayment;
use KiriminAjaOfficial\Services\TransactionDeliveryType;
class ShippingInfoServices extends BaseService{
    
    public int $wcOrderId = 0;
    
    public function wcOrderId($wcOrderId){
        $this->wcOrderId = $wcOrderId;
        return $this;
    }

    public function call(){
        $repo = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransactionByWCOrderId($this->wcOrderId);
        if (!$repo) { return self::error([],'Not Found');}

        $wc_order = wc_get_order( $this->wcOrderId );
        $delivery_type = TransactionDeliveryType::resolve( $repo );
        $payment = ShipmentDetailPayment::forTransaction( $repo );
        
        return self::success([
            'awb'               =>  @$repo->awb ? $repo->awb : '-' , 
            'status'            =>  kiriof_helper()->transactionStatusLabel(@$repo->status), 
            'status_classes'    =>  kiriof_helper()->transactionStatusClass(@$repo->status),
            'service'           =>  @$repo->service ? kiriof_helper()->formatServiceName($repo->service, $repo->service_name) : '-', 
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
            'transaction_id'     => (int) ( $repo->id ?? 0 ),
            'wc_order_id'        => (int) ( $repo->wp_wc_order_stat_order_id ?? 0 ),
            'admin_fee_raw'      => ShipmentDetailAmounts::adminFee( $wc_order, $repo ),
            'buyer_shipping_raw' => $wc_order ? (float) $wc_order->get_shipping_total() : max( 0.0, (float) ( $repo->shipping_cost ?? 0 ) - (float) ( $repo->discount_amount ?? 0 ) ),
            'delivery_type'      => $delivery_type,
            'vehicle'            => TransactionDeliveryType::normalizeVehicle( $repo->vehicle ?? null ),
            'carrier_payment_id' => $payment['id'],
            'carrier_payment_status' => $payment['status'],
            'carrier_payment_method' => $payment['method'],
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