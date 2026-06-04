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
     * @param array $data {
     *   @type int    $member_id                      Merchant member ID.
     *   @type int    $item_price                     Item subtotal after discounts.
     *   @type int    $custom_cod                     COD amount to calculate fee for.
     *   @type bool   $exclude_cod_amount_validation  Skip min/max validation when true.
     *   @type int    $add_cost_percentage            Additional cost percentage (default 0).
     *   @type array  $couriers                       Array of courier detail objects.
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
            'member_id'                     => (int) ( $data['member_id'] ?? 0 ),
            'package_id'                    => 0,
            'item_price'                    => (int) ( $data['item_price'] ?? 0 ),
            'custom_cod'                    => (int) ( $data['custom_cod'] ?? 1 ),
            'exclude_cod_amount_validation' => (bool) ( $data['exclude_cod_amount_validation'] ?? false ),
            'add_cost_percentage'           => (int) ( $data['add_cost_percentage'] ?? 0 ),
            'data'                          => $couriers,
        ];

        $response = $this->post( '/api/mitra/v6.2/cod-fee/calculate', $body );

        if ( empty( $response['status'] ) ) {
            error_log( '[KiriminAja] CodFeeApiRepository::calculateBulkCod — API error: ' . ( is_string( $response['data'] ) ? $response['data'] : wp_json_encode( $response['data'] ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return null;
        }

        $results = $response['data']->data->results ?? null;
        if ( ! is_array( $results ) || empty( $results ) ) {
            error_log( '[KiriminAja] CodFeeApiRepository::calculateBulkCod — empty results in response.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return null;
        }

        return $results;
    }
}
