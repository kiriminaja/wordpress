<?php
namespace Inc\Services\TransactionProcessServices;
use Inc\Base\BaseService;

class SendRequestPickupTransactionService extends BaseService{
    public array $orderIds = [];
    public string $schedule = '';

    public function orderIds($orderIds){
        $this->orderIds = $orderIds;
        return $this;
    }

    public function schedule($schedule){
        $this->schedule = $schedule;
        return $this;
    }

    public function call(){
        if (count($this->orderIds)===0){
            return self::error([],'There is no id');
        }
        if (!$this->schedule || $this->schedule === ''){
            return self::error([],'Schedule is required');
        }
        $getOriginData = self::getOriginData();
        $getPackageData = self::getPackagesData();
        $payload = [
            "address"       => @$getOriginData['origin_address'],
            "phone"         => @$getOriginData['origin_phone'],
            "kelurahan_id"  => @$getOriginData['origin_sub_district_id'],
            "packages"      => $getPackageData,
            "name"          => @$getOriginData['origin_name'],
            "zipcode"       => @$getOriginData['origin_zip_code'],
            "schedule"      => $this->schedule
        ];

        /** 
         * Lion dan Pos Indonesia 
         * Set Lat dan Long
         * 
         **/
        if( in_array($getPackageData[0]['service'],['lion','posindonesia']) ){
            $payload['latitude'] = $getOriginData['origin_latitude'];
            $payload['longitude'] = $getOriginData['origin_longitude'];
        }
        
        $pickupRequest = (new \Inc\Repositories\KiriminajaApiRepository())->sendPickupRequest($payload);
        (new \Inc\Base\BaseInit())->logThis('$pickupRequest',[$pickupRequest]);
        if (!@$pickupRequest['status'] || !@$pickupRequest['data']->status){
            return self::error([],@$pickupRequest['data']->text ?? @$pickupRequest['data'] ?? 'Something is wrong');
        }
        
        /** Update Package Status to Request Pickup*/
        foreach ($this->orderIds as $orderId){
            $payload = [];
            $payload['changes']=[
                'status' => 'request_pickup',
                'pickup_number' => @$pickupRequest['data']->pickup_number,
                'request_pickup_at' => gmdate('Y-m-d H:i:s')
            ];
            $payload['condition']=[
                'order_id' => $orderId
            ];

            (new \Inc\Repositories\TransactionRepository())->updateTransactionByCallback($payload);
        }
        
        /** Create Payment*/
        (new \Inc\Repositories\PaymentRepository())->createPayment([
            'pickup_number'     => @$pickupRequest['data']->pickup_number,
            'status'            => @$pickupRequest['data']->payment_status==='paid' ? 'paid' : 'unpaid',
            'method'            => '',
            'order_amt'         => count($getPackageData),
            'pickup_schedule'   => $this->schedule,
            'created_at'        => gmdate('Y-m-d H:i:s',strtotime("now")),
        ]);
        
        return self::success([
            'pickup_number' => @$pickupRequest['data']->pickup_number,
        ],'success');
    }

    private function getOriginData(){
        $repo = (new \Inc\Repositories\SettingRepository())->getSettingByArray([
            'origin_name',
            'origin_phone',
            'origin_address',
            'origin_sub_district_id',
            'origin_zip_code',
            'origin_latitude',
            'origin_longitude'
        ]);
        
        $array = [];
        foreach ($repo as $setting){
            $array[$setting->key] = $setting->value;
        }
        return $array;
    }
    
    private function getPackagesData(){
        $repo = (new \Inc\Repositories\TransactionRepository())->getTransactionByOrderIds($this->orderIds);
        return array_map(function ($transaction){
            $shipping_info = json_decode($transaction->shipping_info);
            return [
                "order_id"                  => $transaction->order_id,
                "destination_name"          => (@$shipping_info->_shipping_first_name ?? @$shipping_info->_billing_first_name).' '.(@$shipping_info->_shipping_last_name ?? @$shipping_info->_billing_last_name),
                "destination_phone"         => @$shipping_info->_billing_phone,
                "destination_address"       => (@$shipping_info->_shipping_address_1 ?? @$shipping_info->_billing_address_1).' '.(@$shipping_info->_shipping_address_2 ?? @$shipping_info->_billing_address_2).', '.@$transaction->destination_sub_district,
                "destination_kelurahan_id"  => $transaction->destination_sub_district_id,
                "destination_zipcode"       => @$shipping_info->_shipping_postcode,
                "weight"                    => kjHelper()->minAmount($transaction->weight),
                "width"                     => kjHelper()->minAmount($transaction->width),
                "height"                    => kjHelper()->minAmount($transaction->height),
                "length"                    => kjHelper()->minAmount($transaction->length),
                "item_value"                => $transaction->transaction_value,
                "insurance_amount"          => $transaction->insurance_cost,
                "shipping_cost"             => $transaction->shipping_cost,
                "service"                   => $transaction->service,
                "service_type"              => $transaction->service_name,
                "item_name"                 => "Online Shop Goods", // nama barang
                "package_type_id"           => 7, // 7 = Regular
                "cod"=> $transaction->cod_fee > 0 ? 
                    (
                        $transaction->transaction_value +
                        $transaction->shipping_cost +
                        $transaction->insurance_cost +
                        $transaction->cod_fee
                    ) : 0
            ];
        },$repo);
    }
}