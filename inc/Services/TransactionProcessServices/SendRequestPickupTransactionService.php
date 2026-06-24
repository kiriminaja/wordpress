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
            $this->helperCache = kiriof_helper();
        }
        return $this->helperCache;
    }

    private function sanitizeApiName($value)
    {
        $decodedValue = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/[^a-zA-Z\d\s]/', '', $decodedValue);
    }

    private function buildDestinationData($shippingInfo, $order, $transaction): array
    {
        $billingFirstName = (string) ($shippingInfo->_billing_first_name ?? '');
        $billingLastName  = (string) ($shippingInfo->_billing_last_name ?? '');
        $billingAddress1  = (string) ($shippingInfo->_billing_address_1 ?? '');
        $billingAddress2  = (string) ($shippingInfo->_billing_address_2 ?? '');
        $billingPostcode  = (string) ($shippingInfo->_billing_postcode ?? '');
        $billingPhone     = (string) ($shippingInfo->_billing_phone ?? '');

        $shippingFirstName = (string) ($shippingInfo->_shipping_first_name ?? $billingFirstName);
        $shippingLastName  = (string) ($shippingInfo->_shipping_last_name ?? $billingLastName);
        $shippingAddress1  = (string) ($shippingInfo->_shipping_address_1 ?? $billingAddress1);
        $shippingAddress2  = (string) ($shippingInfo->_shipping_address_2 ?? $billingAddress2);
        $shippingCity      = (string) ($shippingInfo->_shipping_city ?? '');
        $shippingState     = (string) ($shippingInfo->_shipping_state ?? '');
        $shippingCountry   = (string) ($shippingInfo->_shipping_country ?? '');
        $shippingPostcode  = (string) ($shippingInfo->_shipping_postcode ?? $billingPostcode);
        $shippingPhone     = (string) ($shippingInfo->_shipping_phone ?? $billingPhone);

        if ($order) {
            if ('' === $billingFirstName) {
                $billingFirstName = (string) $order->get_billing_first_name();
            }
            if ('' === $billingLastName) {
                $billingLastName = (string) $order->get_billing_last_name();
            }
            if ('' === $billingAddress1) {
                $billingAddress1 = (string) $order->get_billing_address_1();
            }
            if ('' === $billingAddress2) {
                $billingAddress2 = (string) $order->get_billing_address_2();
            }
            if ('' === $billingPostcode) {
                $billingPostcode = (string) $order->get_billing_postcode();
            }
            if ('' === $billingPhone) {
                $billingPhone = (string) $order->get_billing_phone();
            }
            if ('' === $shippingFirstName) {
                $shippingFirstName = (string) $order->get_shipping_first_name();
            }
            if ('' === $shippingLastName) {
                $shippingLastName = (string) $order->get_shipping_last_name();
            }
            if ('' === $shippingAddress1) {
                $shippingAddress1 = (string) $order->get_shipping_address_1();
            }
            if ('' === $shippingAddress2) {
                $shippingAddress2 = (string) $order->get_shipping_address_2();
            }
            if ('' === $shippingCity) {
                $shippingCity = (string) $order->get_shipping_city();
            }
            if ('' === $shippingState) {
                $shippingState = (string) $order->get_shipping_state();
            }
            if ('' === $shippingCountry) {
                $shippingCountry = (string) $order->get_shipping_country();
            }
            if ('' === $shippingPostcode) {
                $shippingPostcode = (string) $order->get_shipping_postcode();
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
                $payload[$field] = $value;
            }
        }

        if ($discountAmount > 0) {
            if ($discountPercentage !== null && (float) $discountPercentage > 0) {
                $payload['discount_percentage'] = $discountPercentage;
            } elseif ($shippingCost > 0) {
                $payload['discount_percentage'] = round(($discountAmount / $shippingCost) * 100, 2);
            } else {
                $payload['discount_percentage'] = 0;
            }
        } elseif ($discountPercentage !== null && (float) $discountPercentage > 0) {
            $payload['discount_percentage'] = $discountPercentage;
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
            "kelurahan_id"  => $getOriginData['origin_sub_district_id'] ?? '',
            "packages"      => $apiPackages,
            "name"          => $this->sanitizeApiName($getOriginData['origin_name'] ?? ''),
            "zipcode"       => $getOriginData['origin_zip_code'] ?? '',
            "schedule"      => $this->schedule,
            "dropoff"        => false,
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

        (new \KiriminAjaOfficial\Base\BaseInit())->logThis(
            'send_request_pickup_payload',
            [
                'order_ids' => $this->orderIds,
                'schedule' => $this->schedule,
                'package_count' => count($apiPackages),
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

        $pickupRequest = (new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository())->sendPickupRequest($payload);
        (new \KiriminAjaOfficial\Base\BaseInit())->logThis('$pickupRequest', [$pickupRequest]);
        
        if (empty($pickupRequest['status']) || empty($pickupRequest['data']->status)) {
            (new \KiriminAjaOfficial\Base\BaseInit())->logThis(
                'send_request_pickup_failed',
                [
                    'order_ids' => $this->orderIds,
                    'schedule' => $this->schedule,
                    'api_response' => $pickupRequest,
                ]
            );
            return self::error([], $pickupRequest['data']->text ?? $pickupRequest['data'] ?? 'Something is wrong');
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
        (new \KiriminAjaOfficial\Repositories\PaymentRepository())->createPayment([
            'pickup_number'     => $pickupNumber,
            'status'            => ($pickupRequest['data']->payment_status ?? '') === 'paid' ? 'paid' : 'unpaid',
            'method'            => '',
            'order_amt'         => count($getPackageData),
            'pickup_schedule'   => $this->schedule,
            'created_at'        => $currentTime,
        ]);
        return self::success([
            'pickup_number' => $pickupNumber,
            'open_payment' => $hasNonCodPackage,
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
            $destinationData = $this->buildDestinationData($shipping_info, $order, $transaction);
            
            $result = [
                "order_id"                  => $transaction->order_id,
                "destination_name"          => $destinationData['name'],
                "destination_phone"         => $destinationData['phone'],
                "destination_address"       => $destinationData['address'],
                "destination_kelurahan_id"  => $transaction->destination_sub_district_id,
                "destination_zipcode"       => $destinationData['zipcode'],
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
                    $transaction->shipping_cost -
                    $transaction->discount_amount +
                    $transaction->insurance_cost +
                    $transaction->cod_fee) : 0,
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
