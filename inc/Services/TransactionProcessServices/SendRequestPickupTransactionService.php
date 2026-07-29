<?php
namespace KiriminAjaOfficial\Services\TransactionProcessServices;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;
class SendRequestPickupTransactionService extends BaseService
{
    public array $orderIds = [];
    public string $schedule = '';
    public string $paymentMethod = '';
    public string $pin = '';
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
    public function paymentMethod($paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }
    public function pin($pin)
    {
        $this->pin = $pin;
        return $this;
    }
    
    private function helper()
    {
        if ($this->helperCache === null) {
            $this->helperCache = kiriof_helper();
        }
        return $this->helperCache;
    }

    private function isTopPaymentMethod(): bool
    {
        $settingService = new \KiriminAjaOfficial\Services\SettingService();
        $isTop = $settingService->isTopPaymentMethod();

        try {
            $profile = (new \KiriminAjaOfficial\Services\KiriminajaApiService())->getProfile();
            $profilePaymentMethod = strtoupper((string) ($profile->data->metadata->payment_method ?? ''));
            if ($profilePaymentMethod !== '') {
                $isTop = $profilePaymentMethod === 'TOP';
            }
        } catch (\Throwable $th) {
            kiriof_log('warning', 'Unable to refresh merchant payment method before request pickup.', [
                'message' => $th->getMessage(),
            ], 'kiriminaja_request_pickup');
        }

        return $isTop;
    }

    private function sanitizeApiName($value)
    {
        $decodedValue = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/[^a-zA-Z\d\s]/', '', $decodedValue);
    }

    private function buildDestinationData($shippingInfo, $order, $transaction): array
    {
        $recipient = (new RecipientDataResolver())->resolve($order, $shippingInfo, $transaction);
        $destinationName = trim($recipient['first_name'] . ' ' . $recipient['last_name']);
        $destinationAddressParts = array_filter([
            trim($recipient['address_1'] . ' ' . $recipient['address_2']),
            $transaction->destination_sub_district ?? '',
            $recipient['city'],
            $recipient['state'],
            $recipient['country'],
        ]);

        return [
            'name' => $this->sanitizeApiName($destinationName),
            'phone' => $recipient['phone'],
            'address' => implode(', ', $destinationAddressParts),
            'zipcode' => $recipient['postcode'],
            'summary' => [
                'name_present' => '' !== $destinationName,
                'phone_present' => '' !== $recipient['phone'],
                'address_present' => !empty($destinationAddressParts),
                'zipcode_present' => '' !== $recipient['postcode'],
            ],
        ];
    }

    private function appendPickupDiscountFields(array $payload, $transaction)
    {
        $discountAmount = (float) ($transaction->discount_amount ?? 0);
        $shippingCost = (float) ($transaction->shipping_cost ?? 0);
        $discountPercentage = $transaction->discount_percentage ?? null;

        $discountFields = [
            'discount_amount',
            'shipping_discount_amount',
            'woocommerce_discount_amount',
        ];

        foreach ($discountFields as $field) {
            $value = $transaction->$field ?? null;

            if ($value !== null && (float) $value > 0) {
                $payload[$field] = (int) round((float) $value);
            }
        }

        if ($discountAmount > 0) {
            if ($discountPercentage !== null && (float) $discountPercentage > 0) {
                $payload['discount_percentage'] = (float) $discountPercentage;
            } elseif ($shippingCost > 0) {
                $payload['discount_percentage'] = round(($discountAmount / $shippingCost) * 100, 2);
            } else {
                $payload['discount_percentage'] = 0.0;
            }
        } elseif ($discountPercentage !== null && (float) $discountPercentage > 0) {
            $payload['discount_percentage'] = (float) $discountPercentage;
        }

        $description = trim((string) ($transaction->woocommerce_discount_description ?? ''));
        if ($description !== '') {
            $payload['woocommerce_discount_description'] = $description;
        }

        return $payload;
    }

    private function hasNonCodPackage(array $packages): bool
    {
        foreach ($packages as $package) {
            if (!$this->isCodPackage($package)) {
                return true;
            }
        }

        return false;
    }

    private function isCodPackage(array $package): bool
    {
        return !empty($package['is_cod']) || (float) ($package['cod'] ?? 0) > 0;
    }

    private function hasAwbInTransactions($transactions): bool
    {
        foreach ((array) $transactions as $transaction) {
            if (trim((string) ($transaction->awb ?? '')) !== '') {
                return true;
            }
        }

        return false;
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
        $hasNonCodPackage = $this->hasNonCodPackage($getPackageData);
        $isTopPaymentMethod = $this->isTopPaymentMethod();
        $apiPackages = array_map(
            static function ($package) {
                if (isset($package['destination_summary'])) {
                    unset($package['destination_summary']);
                }
                if (isset($package['is_cod'])) {
                    unset($package['is_cod']);
                }

                return $package;
            },
            $getPackageData
        );
        
        $payload = [
            "address"       => $getOriginData['origin_address'] ?? '',
            "phone"         => $getOriginData['origin_phone'] ?? '',
            "kelurahan_id"  => (int) ($getOriginData['origin_sub_district_id'] ?? 0),
            "packages"      => $apiPackages,
            "name"          => $this->sanitizeApiName($getOriginData['origin_name'] ?? ''),
            "zipcode"       => $getOriginData['origin_zip_code'] ?? '',
            "schedule"      => $this->schedule,
            "platform_name" => 'wordpress',
            "dropoff"        => false,
        ];
        /** 
         * Lion dan Pos Indonesia 
         * Set Lat dan Long
         **/
        $firstService = $getPackageData[0]['service'] ?? '';
        if (in_array($firstService, ['lion', 'posindonesia'], true)) {
            $payload['latitude'] = (float) ($getOriginData['origin_latitude'] ?? 0);
            $payload['longitude'] = (float) ($getOriginData['origin_longitude'] ?? 0);
        }

        if (!$isTopPaymentMethod && !empty($this->paymentMethod)) {
            $payload['payment_method'] = $this->paymentMethod;
        }
        if ($this->paymentMethod === 'credit' && !empty($this->pin)) {
            $payload['pin'] = $this->pin;
        }

        (new \KiriminAjaOfficial\Base\BaseInit())->logThis(
            'send_request_pickup_payload',
            [
                'order_ids' => $this->orderIds,
                'schedule' => $this->schedule,
                'package_count' => count($apiPackages),
                'is_top_payment_method' => $isTopPaymentMethod,
                'packages' => array_map(
                    static function ($package) {
                        return [
                            'order_id' => $package['order_id'] ?? '',
                            'service' => $package['service'] ?? '',
                            'service_type' => $package['service_type'] ?? '',
                            'destination_summary' => $package['destination_summary'] ?? [],
                            'cod' => $package['cod'] ?? 0,
                            'is_cod' => $package['is_cod'] ?? false,
                        ];
                    },
                    $getPackageData
                ),
            ]
        );

        $pickupRequest = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->sendPickupRequestV2($payload);
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$pickupRequest', [$pickupRequest]);
        kiriof_log('info', 'Request pickup API response received.', [
            'order_ids' => $this->orderIds,
            'payment_method' => $this->paymentMethod,
            'is_top_payment_method' => $isTopPaymentMethod,
            'api_success' => !empty($pickupRequest['status']),
            'api_data_status' => !empty($pickupRequest['data']->status),
            'pickup_number' => $pickupRequest['data']->pickup_number ?? '',
            'api_payment_status' => $pickupRequest['data']->payment_status ?? '',
        ], 'kiriminaja_request_pickup');
        
        if (empty($pickupRequest['status']) || empty($pickupRequest['data']->status)) {
            $apiData = $pickupRequest['data'] ?? null;
            $errorResult = $apiData->results ?? null;
            $errorCode = $errorResult->error ?? '';

            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(
                'send_request_pickup_failed',
                [
                    'order_ids' => $this->orderIds,
                    'schedule' => $this->schedule,
                    'payment_method' => $this->paymentMethod,
                    'error_code' => $errorCode,
                    'api_response' => $pickupRequest,
                ]
            );

            if (in_array($errorCode, ['PIN_INVALID', 'PIN_MAX_ATTEMPT_REACHED', 'BALANCE_NOT_ENOUGH'], true)) {
                return self::error(
                    [
                        'error_code'     => $errorCode,
                        'error_metafield' => $errorResult->error_metafield ?? null,
                    ],
                    $apiData->text ?? $errorCode
                );
            }

            return self::error([], $apiData->text ?? $apiData ?? 'Something is wrong');
        }
        $pickupNumber = $pickupRequest['data']->pickup_number ?? '';
        $currentTime = gmdate('Y-m-d H:i:s');
        
        /** Update Package Status to Request Pickup*/
        $transactionRepo = new \KiriminAjaOfficial\Repositories\TransactionRepository();
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
        $pickupTransactions = $transactionRepo->getTransactionByPickupNumber($pickupNumber);
        $hasAwbAfterPickup = $this->hasAwbInTransactions($pickupTransactions);
        /** Create Payment*/
        $paymentMethod = $isTopPaymentMethod ? 'TOP' : $this->paymentMethod;
        if (empty($paymentMethod)) {
            $paymentMethod = $hasNonCodPackage ? 'qris' : 'cod';
        }
        $normalizedPaymentMethod = strtolower((string) $paymentMethod);
        $apiPaymentStatus = strtolower((string) ($pickupRequest['data']->payment_status ?? ''));
        $localPaymentStatus = 'unpaid';
        if ($normalizedPaymentMethod !== 'qris' && $apiPaymentStatus === 'paid') {
            $localPaymentStatus = 'paid';
        }
        if ($normalizedPaymentMethod === 'top') {
            $localPaymentStatus = 'paid';
        }
        if ($normalizedPaymentMethod === 'cod') {
            $localPaymentStatus = 'paid';
        }
        if ($normalizedPaymentMethod === 'qris' && ! $hasAwbAfterPickup && $apiPaymentStatus !== 'paid') {
            $localPaymentStatus = 'unpaid';
        }

        (new \KiriminAjaOfficial\Repositories\PaymentRepository())->createPayment([
            'pickup_number'     => $pickupNumber,
            'status'            => $localPaymentStatus,
            'method'            => $paymentMethod,
            'order_amt'         => count($getPackageData),
            'pickup_schedule'   => $this->schedule,
            'created_at'        => $currentTime,
        ]);
        kiriof_log('info', 'Request pickup local payment created.', [
            'pickup_number' => $pickupNumber,
            'payment_method' => $paymentMethod,
            'normalized_payment_method' => $normalizedPaymentMethod,
            'is_top_payment_method' => $isTopPaymentMethod,
            'local_payment_status' => $localPaymentStatus,
            'api_payment_status' => $apiPaymentStatus,
            'has_awb_after_pickup' => $hasAwbAfterPickup,
            'has_non_cod_package' => $hasNonCodPackage,
            'open_payment' => $localPaymentStatus !== 'paid' && $hasNonCodPackage && $normalizedPaymentMethod === 'qris',
        ], 'kiriminaja_request_pickup');

        return self::success([
            'pickup_number'  => $pickupNumber,
            'open_payment'   => $localPaymentStatus !== 'paid' && $hasNonCodPackage && $normalizedPaymentMethod === 'qris',
            'payment_method' => $paymentMethod,
            'payment_status' => $localPaymentStatus,
        ], 'success');
    }
    private function getOriginData()
    {
        if ($this->originDataCache !== null) {
            return $this->originDataCache;
        }
        
        $repo = (new \KiriminAjaOfficial\Repositories\SettingRepository())->getSettingByArray([
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
        $repo = (new \KiriminAjaOfficial\Repositories\TransactionRepository())->getTransactionByOrderIds($this->orderIds);
        
        if (empty($repo)) {
            return [];
        }
        
        $helper = $this->helper();
        $weightConverter = new \KiriminAjaOfficial\Utils\WeightConverter();
        $homeUrl = get_home_url();
        
        $packages = array_map(function ($transaction) use ($helper, $weightConverter, $homeUrl) {
            $shipping_info = json_decode($transaction->shipping_info ?? '{}');
            $order = wc_get_order($transaction->wp_wc_order_stat_order_id);
            
            if (!$order) {
                (new \KiriminAjaOfficial\Base\BaseInit())->logThis(
                    'send_request_pickup_skip_missing_order',
                    [
                        'order_id' => $transaction->order_id ?? '',
                        'wc_order_id' => $transaction->wp_wc_order_stat_order_id ?? 0,
                    ]
                );
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
                        "qty" => (int) $item->get_quantity(),
                        "weight" => (int) $helper->minAmount($weight),
                        "length" => (int) $helper->minAmount($product->get_length() ?: 0),
                        "width" => (int) $helper->minAmount($product->get_width() ?: 0),
                        "height" => (int) $helper->minAmount($product->get_height() ?: 0),
                        "name" => $itemName,
                        "price" => (int) round((float) ($product->get_price() ?: 0)),
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
            $destinationData = $this->buildDestinationData($shipping_info, $order, $transaction);
            
            $result = [
                "order_id"                  => $transaction->order_id,
                "destination_name"          => $destinationData['name'],
                "destination_phone"         => $destinationData['phone'],
                "destination_address"       => $destinationData['address'],
                "destination_kelurahan_id"  => (int) ($transaction->destination_sub_district_id ?? 0),
                "destination_zipcode"       => $destinationData['zipcode'],
                "weight"                    => (int) $helper->minAmount($transaction->weight),
                "width"                     => (int) $helper->minAmount($transaction->width),
                "height"                    => (int) $helper->minAmount($transaction->height),
                "length"                    => (int) $helper->minAmount($transaction->length),
                "item_value"                => (int) round((float) ($transaction->transaction_value ?? 0)),
                "insurance_amount"          => (int) round((float) ($transaction->insurance_cost ?? 0)),
                "shipping_cost"             => (int) round((float) ($transaction->shipping_cost ?? 0)),
                "service"                   => $transaction->service,
                "service_type"              => $transaction->service_name,
                "item_name"                 => $combinedItemNames,
                "note"                      => $note,
                "package_type_id"           => 7,
                "cod" => 0,
                "drop" => false,
                "is_with_insurance" => ( (float) ( $transaction->insurance_cost ?? 0 ) ) > 0,
                "destination_summary" => $destinationData['summary'],
            ];

            $isCodOrder = 'cod' === strtolower((string) $order->get_payment_method());
            if ($isCodOrder) {
                $result['cod'] = (int) round(
                    (float) ($transaction->transaction_value ?? 0) +
                    (float) ($transaction->shipping_cost ?? 0) -
                    (float) ($transaction->discount_amount ?? 0) +
                    (float) ($transaction->insurance_cost ?? 0) +
                    (float) ($transaction->cod_fee ?? 0)
                );
                $result['is_cod'] = true;
            }

            $result = $this->appendPickupDiscountFields($result, $transaction);
            
            if (!empty($itemsPayload)) {
                $result['items'] = $itemsPayload;
            }
            
            return $result;
        }, $repo);

        return array_values(
            array_filter(
                $packages,
                static function ($package) {
                    return is_array($package) && !empty($package);
                }
            )
        );
    }
}
