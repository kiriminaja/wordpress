<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\KiriminAjaApi;

class CodFeeApiRepository extends KiriminAjaApi {

    /**
     * Call the COD fee calculation endpoint.
     *
     * Endpoint: POST /api/mitra/calculations/cod
     * Auth: Bearer token (handled by KiriminAjaApi base class).
     *
     * @param array $data {
     *   @type int    $item_price                     Item subtotal after discounts.
     *   @type int    $custom_cod                     COD amount to calculate fee for.
     *   @type bool   $exclude_cod_amount_validation  Skip min/max validation when true.
     *   @type array  $couriers                       Array of courier detail objects, each with:
     *                                                courier_code, courier_service_code,
     *                                                shipping_cost, discount_amount, insurance_amount.
     * }
     * @return array|null The `results` array from the API response, or null on failure.
     */
    public function calculateBulkCod( array $data ): ?array {
        $couriers = $data['couriers'] ?? [];
        if ( empty( $couriers ) ) {
            kiriof_log(
                'warning',
                'COD fee calculation skipped because no couriers were provided.',
                array(
                    'source'    => 'kiriminaja_payment',
                    'operation' => 'calculate_bulk_cod',
                )
            );
            return null;
        }

        $body = [
            'item_price'                    => (int) ( $data['item_price'] ?? 0 ),
            'custom_cod'                    => (int) ( $data['custom_cod'] ?? 1 ),
            'exclude_cod_amount_validation' => (bool) ( $data['exclude_cod_amount_validation'] ?? false ),
            'data'                          => $couriers,
        ];

        $response = $this->post( '/api/mitra/calculations/cod', $body, array(
            'source'        => 'kiriminaja_payment',
            'operation'     => 'calculate_bulk_cod',
            'courier_count' => count( $couriers ),
        ) );

        if ( empty( $response['status'] ) ) {
            kiriof_log(
                'warning',
                'COD fee calculation API request failed.',
                array(
                    'source'        => 'kiriminaja_payment',
                    'operation'     => 'calculate_bulk_cod',
                    'courier_count' => count( $couriers ),
                    'response'      => is_string( $response['data'] ) ? $response['data'] : $response['data'],
                )
            );
            return null;
        }

        $results = $response['data']->results ?? null;
        if ( ! is_array( $results ) || empty( $results ) ) {
            kiriof_log(
                'warning',
                'COD fee calculation API request returned no result rows.',
                array(
                    'source'        => 'kiriminaja_payment',
                    'operation'     => 'calculate_bulk_cod',
                    'courier_count' => count( $couriers ),
                )
            );
            return null;
        }

        return $results;
    }
}
