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

    private function readShippingInfoValue($shippingInfo, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($shippingInfo->$key)) {
                $value = trim((string) $shippingInfo->$key);
                if ('' !== $value) {
                    return $value;
                }
            }
        }

        return '';
    }

    private function buildDestinationData($shippingInfo, $order, $transaction): array
    {
        $billingFirstName = $this->readShippingInfoValue($shippingInfo, ['_billing_first_name', 'billing_first_name', 'first_name']);
        $billingLastName  = $this->readShippingInfoValue($shippingInfo, ['_billing_last_name', 'billing_last_name', 'last_name']);
        $billingAddress1  = $this->readShippingInfoValue($shippingInfo, ['_billing_address_1', 'billing_address_1', 'address_1']);
        $billingAddress2  = $this->readShippingInfoValue($shippingInfo, ['_billing_address_2', 'billing_address_2', 'address_2']);
        $billingPostcode  = $this->readShippingInfoValue($shippingInfo, ['_billing_postcode', 'billing_postcode', 'postcode']);
        $billingPhone     = $this->readShippingInfoValue($shippingInfo, ['_billing_phone', 'billing_phone', 'phone']);

        $shippingFirstName = $this->readShippingInfoValue($shippingInfo, ['_shipping_first_name', 'shipping_first_name']);
        $shippingLastName  = $this->readShippingInfoValue($shippingInfo, ['_shipping_last_name', 'shipping_last_name']);
        $shippingAddress1  = $this->readShippingInfoValue($shippingInfo, ['_shipping_address_1', 'shipping_address_1', '_billing_address_1', 'billing_address_1', 'address_1']);
        $shippingAddress2  = $this->readShippingInfoValue($shippingInfo, ['_shipping_address_2', 'shipping_address_2', '_billing_address_2', 'billing_address_2', 'address_2']);
        $shippingCity      = $this->readShippingInfoValue($shippingInfo, ['_shipping_city', 'shipping_city', '_billing_city', 'billing_city', 'city']);
        $shippingState     = $this->readShippingInfoValue($shippingInfo, ['_shipping_state', 'shipping_state', '_billing_state', 'billing_state', 'state']);
        $shippingCountry   = $this->readShippingInfoValue($shippingInfo, ['_shipping_country', 'shipping_country', '_billing_country', 'billing_country', 'country']);
        $shippingPostcode  = $this->readShippingInfoValue($shippingInfo, ['_shipping_postcode', 'shipping_postcode', '_billing_postcode', 'billing_postcode', 'postcode']);
        $shippingPhone     = $this->readShippingInfoValue($shippingInfo, ['_shipping_phone', 'shipping_phone', '_billing_phone', 'billing_phone', 'phone']);
        $billingAddressData = $order && method_exists($order, 'get_address') ? (array) $order->get_address('billing') : [];
        $shippingAddressData = $order && method_exists($order, 'get_address') ? (array) $order->get_address('shipping') : [];

        if ($order) {
            if ('' === $billingFirstName) {
                $billingFirstName = trim((string) ($billingAddressData['first_name'] ?? ''));
            }
            if ('' === $billingFirstName) {
                $billingFirstName = (string) $order->get_billing_first_name();
            }
            if ('' === $billingLastName) {
                $billingLastName = trim((string) ($billingAddressData['last_name'] ?? ''));
            }
            if ('' === $billingLastName) {
                $billingLastName = (string) $order->get_billing_last_name();
            }
            if ('' === $billingAddress1) {
                $billingAddress1 = trim((string) ($billingAddressData['address_1'] ?? ''));
            }
            if ('' === $billingAddress1) {
                $billingAddress1 = (string) $order->get_billing_address_1();
            }
            if ('' === $billingAddress2) {
                $billingAddress2 = trim((string) ($billingAddressData['address_2'] ?? ''));
            }
            if ('' === $billingAddress2) {
                $billingAddress2 = (string) $order->get_billing_address_2();
            }
            if ('' === $billingPostcode) {
                $billingPostcode = trim((string) ($billingAddressData['postcode'] ?? ''));
            }
            if ('' === $billingPostcode) {
                $billingPostcode = (string) $order->get_billing_postcode();
            }
            if ('' === $billingPhone) {
                $billingPhone = trim((string) ($billingAddressData['phone'] ?? ''));
            }
            if ('' === $billingPhone) {
                $billingPhone = (string) $order->get_billing_phone();
            }
            if ('' === $shippingFirstName) {
                $shippingFirstName = trim((string) ($shippingAddressData['first_name'] ?? ''));
            }
            if ('' === $shippingFirstName) {
                $shippingFirstName = (string) $order->get_shipping_first_name();
            }
            if ('' === $shippingLastName) {
                $shippingLastName = trim((string) ($shippingAddressData['last_name'] ?? ''));
            }
            if ('' === $shippingLastName) {
                $shippingLastName = (string) $order->get_shipping_last_name();
            }
            if ('' === $shippingAddress1) {
                $shippingAddress1 = trim((string) ($shippingAddressData['address_1'] ?? ''));
            }
            if ('' === $shippingAddress1) {
                $shippingAddress1 = (string) $order->get_shipping_address_1();
            }
            if ('' === $shippingAddress2) {
                $shippingAddress2 = trim((string) ($shippingAddressData['address_2'] ?? ''));
            }
            if ('' === $shippingAddress2) {
                $shippingAddress2 = (string) $order->get_shipping_address_2();
            }
            if ('' === $shippingCity) {
                $shippingCity = trim((string) ($shippingAddressData['city'] ?? ''));
            }
            if ('' === $shippingCity) {
                $shippingCity = (string) $order->get_shipping_city();
            }
            if ('' === $shippingState) {
                $shippingState = trim((string) ($shippingAddressData['state'] ?? ''));
            }
            if ('' === $shippingState) {
                $shippingState = (string) $order->get_shipping_state();
            }
            if ('' === $shippingCountry) {
                $shippingCountry = trim((string) ($shippingAddressData['country'] ?? ''));
            }
            if ('' === $shippingCountry) {
                $shippingCountry = (string) $order->get_shipping_country();
            }
            if ('' === $shippingPostcode) {
                $shippingPostcode = trim((string) ($shippingAddressData['postcode'] ?? ''));
            }
            if ('' === $shippingPostcode) {
                $shippingPostcode = (string) $order->get_shipping_postcode();
            }
            if ('' === $shippingPhone) {
                $shippingPhone = trim((string) ($shippingAddressData['phone'] ?? ''));
            }
            if ('' === $shippingPhone && method_exists($order, 'get_shipping_phone')) {
                $shippingPhone = (string) $order->get_shipping_phone();
            }
        }

        if ('' === $shippingFirstName) {
            $shippingFirstName = $billingFirstName;
        }
        if ('' === $shippingLastName) {
            $shippingLastName = $billingLastName;
        }
        if ('' === $shippingAddress1) {
            $shippingAddress1 = $billingAddress1;
        }
        if ('' === $shippingAddress2) {
            $shippingAddress2 = $billingAddress2;
        }
        if ('' === $shippingPostcode) {
            $shippingPostcode = $billingPostcode;
        }
        if ('' === $shippingPhone) {
            $shippingPhone = $billingPhone;
        }

        $destinationName = trim($shippingFirstName . ' ' . $shippingLastName);
        $destinationAddressParts = array_filter([
            trim($shippingAddress1 . ' ' . $shippingAddress2),
            $transaction->destination_sub_district ?? '',
            $shippingCity,
            $shippingState,
            $shippingCountry,
        ]);

        return [
            'name' => $this->sanitizeApiName($destinationName),
            'phone' => $shippingPhone,
            'address' => implode(', ', $destinationAddressParts),
            'zipcode' => $shippingPostcode,
            'summary' => [
                'name_present' => '' !== $destinationName,
                'phone_present' => '' !== $shippingPhone,
                'address_present' => !empty($destinationAddressParts),
                'zipcode_present' => '' !== $shippingPostcode,
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
            if ((float) ($package['cod'] ?? 0) <= 0) {
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
        /** Create Payment*/
        $paymentMethod = $isTopPaymentMethod ? 'TOP' : $this->paymentMethod;
        if (empty($paymentMethod)) {
            $paymentMethod = 'qris';
        }
        $normalizedPaymentMethod = strtolower((string) $paymentMethod);
        $localPaymentStatus = ($pickupRequest['data']->payment_status ?? '') === 'paid' ? 'paid' : 'unpaid';
        if ($normalizedPaymentMethod === 'top') {
            $localPaymentStatus = 'paid';
        }
        if ($normalizedPaymentMethod === 'qris') {
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
            'api_payment_status' => $pickupRequest['data']->payment_status ?? '',
            'has_non_cod_package' => $hasNonCodPackage,
            'open_payment' => $hasNonCodPackage && $normalizedPaymentMethod === 'qris',
        ], 'kiriminaja_request_pickup');

        return self::success([
            'pickup_number'  => $pickupNumber,
            'open_payment'   => $hasNonCodPackage && $normalizedPaymentMethod === 'qris',
            'payment_method' => $paymentMethod,
            'payment_status' => $pickupRequest['data']->payment_status ?? '',
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
                "cod" => (int) round($transaction->cod_fee > 0 ?
                    ($transaction->transaction_value +
                    $transaction->shipping_cost -
                    $transaction->discount_amount +
                    $transaction->insurance_cost +
                    $transaction->cod_fee) : 0),
                "drop" => false,
                "is_with_insurance" => ( (float) ( $transaction->insurance_cost ?? 0 ) ) > 0,
                "destination_summary" => $destinationData['summary'],
            ];

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
