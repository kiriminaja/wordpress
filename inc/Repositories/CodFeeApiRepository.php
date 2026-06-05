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
            error_log( '[KiriminAja] CodFeeApiRepository::calculateBulkCod — no couriers provided, skipping API call.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return null;
        }

        $body = [
            'item_price'                    => (int) ( $data['item_price'] ?? 0 ),
            'custom_cod'                    => (int) ( $data['custom_cod'] ?? 1 ),
            'exclude_cod_amount_validation' => (bool) ( $data['exclude_cod_amount_validation'] ?? false ),
            'data'                          => $couriers,
        ];

        $response = $this->post( '/api/mitra/calculations/cod', $body );

        if ( empty( $response['status'] ) ) {
            error_log( '[KiriminAja] CodFeeApiRepository::calculateBulkCod — API error: ' . ( is_string( $response['data'] ) ? $response['data'] : wp_json_encode( $response['data'] ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return null;
        }

        $results = $response['data']->results ?? null;
        if ( ! is_array( $results ) || empty( $results ) ) {
            error_log( '[KiriminAja] CodFeeApiRepository::calculateBulkCod — empty results in response.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return null;
        }

        return $results;
    }
}
