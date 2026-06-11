<?php
namespace KiriminAjaOfficial\Services\CheckoutServices;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;

/**
 * Detects whether a COD order is in deficit.
 *
 * A COD order is deficit when the buyer's collected amount (totalCod) is
 * insufficient to cover all seller-side costs, resulting in a negative payout.
 *
 * Detection flow:
 *  1. Non-COD orders → never deficit.
 *  2. Call COD fee API (2 calls) if member_id is available.
 *  3. Fall back to local calculation when API is unavailable.
 */
class CodDeficitService extends BaseService {

    /**
     * Detect whether a COD order is in deficit.
     *
     * @param array $params {
     *   @type bool   $is_cod               Whether the order uses COD.
     *   @type float  $total_cod            COD amount collected from buyer (item total after discount).
     *   @type float  $shipping_cost        Raw shipping cost (before discount).
     *   @type float  $insurance_fee        Insurance fee amount.
     *   @type float  $cod_fee              COD fee from checkout calculation.
     *   @type float  $admin_fee            Admin fee (default 0).
     *   @type float  $item_price           Item price (for API payload).
     *   @type string $courier_code         Courier code (e.g. "sicepat").
     *   @type string $courier_service_code Courier service code (e.g. "REG").
     *   @type float  $discount_amount      Shipping discount amount.
     * }
     * @return array {
     *   @type bool  $isDeficit  Whether the order is deficit.
     *   @type float $codMinimum Minimum COD amount required.
     *   @type float $totalCod   The total COD amount.
     *   @type float $codFee     The effective COD fee used.
     * }
     */
    public function detect( array $params ): array {
        $isCod         = ! empty( $params['is_cod'] );
        $totalCod      = (float) ( $params['total_cod'] ?? 0 );
        $shippingCost  = (float) ( $params['shipping_cost'] ?? 0 );
        $insuranceFee  = (float) ( $params['insurance_fee'] ?? 0 );
        $codFee        = (float) ( $params['cod_fee'] ?? 0 );
        $adminFee      = (float) ( $params['admin_fee'] ?? 0 );
        $itemPrice     = (float) ( $params['item_price'] ?? 0 );
        $courierCode   = (string) ( $params['courier_code'] ?? '' );
        $serviceCode   = (string) ( $params['courier_service_code'] ?? '' );
        $discountAmt   = (float) ( $params['discount_amount'] ?? 0 );

        if ( ! $isCod ) {
            return [
                'isDeficit'  => false,
                'codMinimum' => 0.0,
                'totalCod'   => $totalCod,
                'codFee'     => $codFee,
            ];
        }

        $maxCodAmount = defined( 'KIRIOF_MAX_COD_AMOUNT' ) ? (float) KIRIOF_MAX_COD_AMOUNT : 3000000.0;

        // Attempt API-based detection when courier info is available.
        if ( ! empty( $courierCode ) && ! empty( $serviceCode ) ) {
            $apiResult = $this->detectViaApi(
                $itemPrice,
                $totalCod,
                $shippingCost,
                $discountAmt,
                $insuranceFee,
                $courierCode,
                $serviceCode
            );

            if ( null !== $apiResult ) {
                $apiCodFee        = (float) ( $apiResult['codFee'] ?? $codFee );
                $isSupportCod     = (bool) ( $apiResult['isSupportCod'] ?? true );
                $minimumCustomCod = (float) ( $apiResult['minimumCustomCod'] ?? 0 );

                $localMinimum  = $shippingCost + $insuranceFee + $apiCodFee + $adminFee;
                $codMinimum    = max( $localMinimum, $minimumCustomCod );

                $estimatedPayout = $totalCod - $shippingCost - $insuranceFee - $apiCodFee - $adminFee;

                $isDeficit = ! $isSupportCod
                    || $estimatedPayout < 0
                    || $totalCod < $codMinimum
                    || $totalCod > $maxCodAmount;

                return [
                    'isDeficit'  => $isDeficit,
                    'codMinimum' => $codMinimum,
                    'totalCod'   => $totalCod,
                    'codFee'     => $apiCodFee,
                ];
            }
        } else {
            kiriof_log(
                'warning',
                'COD deficit detection used the local fallback because courier information was missing.',
                array(
                    'source'  => 'kiriminaja_payment',
                    'service' => $serviceCode,
                )
            );
        }

        // Fallback: local calculation only.
        return $this->detectFallback( $totalCod, $shippingCost, $insuranceFee, $codFee, $adminFee, $maxCodAmount );
    }

    /**
     * Perform two-call API-based deficit detection.
     *
     * @return array|null Array with codFee, isSupportCod, minimumCustomCod keys; null on any API failure.
     */
    private function detectViaApi(
        float $itemPrice,
        float $totalCod,
        float $shippingCost,
        float $discountAmt,
        float $insuranceFee,
        string $courierCode,
        string $serviceCode
    ): ?array {
        $repo = new \KiriminAjaOfficial\Repositories\CodFeeApiRepository();

        $courierData = [
            [
                'courier_code'         => $courierCode,
                'courier_service_code' => $serviceCode,
                'shipping_cost'        => (int) $shippingCost,
                'discount_amount'      => (int) $discountAmt,
                'insurance_amount'     => (int) $insuranceFee,
            ],
        ];

        // Call 1: get minimum_custom_cod threshold (custom_cod = 1, skip validation).
        $call1 = $repo->calculateBulkCod( [
            'item_price'                    => (int) $itemPrice,
            'custom_cod'                    => 1,
            'exclude_cod_amount_validation' => true,
            'couriers'                      => $courierData,
        ] );

        if ( null === $call1 ) {
            kiriof_log(
                'warning',
                'COD deficit detection fell back to the local calculation after the minimum threshold API call failed.',
                array(
                    'source'               => 'kiriminaja_payment',
                    'courier_code'         => $courierCode,
                    'courier_service_code' => $serviceCode,
                )
            );
            return null;
        }

        $minimumCustomCod = (float) ( $call1[0]->minimum_custom_cod ?? 0 );

        // Call 2: get actual fee and support check for the real COD amount.
        // Floor custom_cod to max(totalCod, itemPrice, 100000) so very low amounts
        // don't cause API errors — mirrors the Shopify TS Math.max(totalCod, itemValue, 100000) guard.
        $customCodForFee = max( (int) $totalCod, (int) $itemPrice, 100000 );

        $call2 = $repo->calculateBulkCod( [
            'item_price'                    => (int) $itemPrice,
            'custom_cod'                    => $customCodForFee,
            'exclude_cod_amount_validation' => false,
            'couriers'                      => $courierData,
        ] );

        if ( null === $call2 ) {
            kiriof_log(
                'warning',
                'COD deficit detection fell back to the local calculation after the COD fee API call failed.',
                array(
                    'source'               => 'kiriminaja_payment',
                    'courier_code'         => $courierCode,
                    'courier_service_code' => $serviceCode,
                )
            );
            return null;
        }

        return [
            'codFee'           => (float) ( $call2[0]->total_fee ?? 0 ),
            'isSupportCod'     => (bool) ( $call2[0]->is_support_cod ?? true ),
            'minimumCustomCod' => $minimumCustomCod,
        ];
    }

    /**
     * Local fallback deficit detection when API is unavailable.
     */
    private function detectFallback(
        float $totalCod,
        float $shippingCost,
        float $insuranceFee,
        float $codFee,
        float $adminFee,
        float $maxCodAmount
    ): array {
        $minThreshold  = (float) ( ( new \KiriminAjaOfficial\Repositories\SettingRepository() )->getSettingByKey( 'min_cod_threshold' )->value ?? 0 );
        $localMinimum  = $shippingCost + $insuranceFee + $codFee + $adminFee;
        $codMinimum    = max( $localMinimum, $minThreshold );

        $estimatedPayout = $totalCod - $shippingCost - $insuranceFee - $codFee - $adminFee;

        $isDeficit = $estimatedPayout < 0
            || $totalCod < $codMinimum
            || $totalCod > $maxCodAmount;

        return [
            'isDeficit'  => $isDeficit,
            'codMinimum' => $codMinimum,
            'totalCod'   => $totalCod,
            'codFee'     => $codFee,
        ];
    }
}
