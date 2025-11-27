<?php

namespace Inc\Services\TransactionProcessServices;

use Inc\Base\BaseService;

class SendRequestPickupTransactionService extends BaseService
{
    public array $orderIds = [];
    public string $schedule = '';
    private $originDataCache = null;
    private $helperCache = null;

    public function orderIds($orderIds)
    {
        $this->orderIds = $orderIds;
        return $this;
    }

    public function schedule($schedule)
    {
        $this->schedule = $schedule;
        return $this;
    }
    
    private function helper()
    {
        if ($this->helperCache === null) {
            $this->helperCache = kjHelper();
        }
        return $this->helperCache;
    }

    public function call()
    {
        if (empty($this->orderIds)) {
            return self::error([], 'There is no id');
        }
        if (empty($this->schedule)) {
            return self::error([], 'Schedule is required');
        }
        
        $getOriginData = $this->getOriginData();
        $getPackageData = $this->getPackagesData();
        
        if (empty($getPackageData)) {
            return self::error([], 'No valid packages found');
        }
        
        $payload = [
            "address"       => $getOriginData['origin_address'] ?? '',
            "phone"         => $getOriginData['origin_phone'] ?? '',
            "kelurahan_id"  => $getOriginData['origin_sub_district_id'] ?? '',
            "packages"      => $getPackageData,
            "name"          => $getOriginData['origin_name'] ?? '',
            "zipcode"       => $getOriginData['origin_zip_code'] ?? '',
            "schedule"      => $this->schedule,
        ];

        /** 
         * Lion dan Pos Indonesia 
         * Set Lat dan Long
         **/
        $firstService = $getPackageData[0]['service'] ?? '';
        if (in_array($firstService, ['lion', 'posindonesia'], true)) {
            $payload['latitude'] = $getOriginData['origin_latitude'] ?? '';
            $payload['longitude'] = $getOriginData['origin_longitude'] ?? '';
        }

        $pickupRequest = (new \Inc\Repositories\KiriminajaApiRepository())->sendPickupRequest($payload);
        (new \Inc\Base\BaseInit())->logThis('$pickupRequest', [$pickupRequest]);
        
        if (empty($pickupRequest['status']) || empty($pickupRequest['data']->status)) {
            return self::error([], $pickupRequest['data']->text ?? $pickupRequest['data'] ?? 'Something is wrong');
        }

        $pickupNumber = $pickupRequest['data']->pickup_number ?? '';
        $currentTime = gmdate('Y-m-d H:i:s');
        
        /** Update Package Status to Request Pickup*/
        $transactionRepo = new \Inc\Repositories\TransactionRepository();
        foreach ($this->orderIds as $orderId) {
            $payload = [
                'changes' => [
                    'status' => 'request_pickup',
                    'pickup_number' => $pickupNumber,
                    'request_pickup_at' => $currentTime
                ],
                'condition' => [
                    'order_id' => $orderId
                ]
            ];
            $transactionRepo->updateTransactionByCallback($payload);
        }

        /** Create Payment*/
        (new \Inc\Repositories\PaymentRepository())->createPayment([
            'pickup_number'     => $pickupNumber,
            'status'            => ($pickupRequest['data']->payment_status ?? '') === 'paid' ? 'paid' : 'unpaid',
            'method'            => '',
            'order_amt'         => count($getPackageData),
            'pickup_schedule'   => $this->schedule,
            'created_at'        => $currentTime,
        ]);

        return self::success([
            'pickup_number' => $pickupNumber,
        ], 'success');
    }

    private function getOriginData()
    {
        if ($this->originDataCache !== null) {
            return $this->originDataCache;
        }
        
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
        foreach ($repo as $setting) {
            $array[$setting->key] = $setting->value;
        }
        
        $this->originDataCache = $array;
        return $array;
    }

    private function getPackagesData(){
        $repo = (new \Inc\Repositories\TransactionRepository())->getTransactionByOrderIds($this->orderIds);
        
        if (empty($repo)) {
            return [];
        }
        
        $helper = $this->helper();
        $weightConverter = new \Inc\Utils\WeightConverter();
        $homeUrl = get_home_url();
        
        return array_map(function ($transaction) use ($helper, $weightConverter, $homeUrl) {
            $shipping_info = json_decode($transaction->shipping_info);
            $order = wc_get_order($transaction->wp_wc_order_stat_order_id);
            
            if (!$order) {
                return null;
            }
            
            $itemNames = [];
            $itemsPayload = [];
            
            foreach ($order->get_items() as $item) {
                $itemName = $item->get_name();
                $itemNames[] = $itemName;
                
                $product = $item->get_product();
                if ($product) {
                    $weight = $weightConverter->toGram($product->get_weight());
                    $itemsPayload[] = [
                        "qty" => $item->get_quantity(),
                        "weight" => $weight,
                        "length" => $product->get_length() ?: 0,
                        "width" => $product->get_width() ?: 0,
                        "height" => $product->get_height() ?: 0,
                        "name" => $itemName,
                        "price" => $product->get_price() ?: 0,
                    ];
                }
            }

            // Optimize item name generation
            $combinedItemNames = implode(", ", $itemNames);
            if (strlen($combinedItemNames) > 255) {
                $countItemNames = count($itemNames);
                if ($countItemNames > 1 && isset($itemNames[0]) && strlen($itemNames[0]) <= 200) {
                    $combinedItemNames = $itemNames[0] . " dan " . ($countItemNames - 1) . " produk lainnya";
                } else {
                    $combinedItemNames = $countItemNames . " Bundle";
                }
            }
            
            $note = "Order No : " . $transaction->wp_wc_order_stat_order_id . " | " . $homeUrl;
            $note = preg_replace('/[^a-zA-Z\d.\/:,\+\-()\'\"_&;?\s]/', '', $note);
            
            // Get shipping/billing info with fallbacks
            $firstName = $shipping_info->_shipping_first_name ?? $shipping_info->_billing_first_name ?? '';
            $lastName = $shipping_info->_shipping_last_name ?? $shipping_info->_billing_last_name ?? '';
            $address1 = $shipping_info->_shipping_address_1 ?? $shipping_info->_billing_address_1 ?? '';
            $address2 = $shipping_info->_shipping_address_2 ?? $shipping_info->_billing_address_2 ?? '';
            
            $result = [
                "order_id"                  => $transaction->order_id,
                "destination_name"          => trim($firstName . ' ' . $lastName),
                "destination_phone"         => $shipping_info->_billing_phone ?? '',
                "destination_address"       => trim($address1 . ' ' . $address2 . ', ' . ($transaction->destination_sub_district ?? '')),
                "destination_kelurahan_id"  => $transaction->destination_sub_district_id,
                "destination_zipcode"       => $shipping_info->_shipping_postcode ?? '',
                "weight"                    => $helper->minAmount($transaction->weight),
                "width"                     => $helper->minAmount($transaction->width),
                "height"                    => $helper->minAmount($transaction->height),
                "length"                    => $helper->minAmount($transaction->length),
                "item_value"                => $transaction->transaction_value,
                "insurance_amount"          => $transaction->insurance_cost,
                "shipping_cost"             => $transaction->shipping_cost,
                "service"                   => $transaction->service,
                "service_type"              => $transaction->service_name,
                "item_name"                 => $combinedItemNames,
                "note"                      => $note,
                "package_type_id"           => 7,
                "cod" => $transaction->cod_fee > 0 ? 
                    ($transaction->transaction_value +
                    $transaction->shipping_cost +
                    $transaction->insurance_cost +
                    $transaction->cod_fee) : 0,
                "discount_amount" => $transaction->discount_amount ?? 0,
                "discount_percentage" => $transaction->discount_percentage ?? 0,
            ];
            
            if (!empty($itemsPayload)) {
                $result['items'] = $itemsPayload;
            }
            
            return $result;
        }, $repo);
    }
}
