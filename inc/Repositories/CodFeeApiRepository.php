<?php
namespace KiriminAjaOfficial\Repositories;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAja\Services\KiriminAja;
use KiriminAjaOfficial\Base\KiriminAjaApi;

class CodFeeApiRepository extends KiriminAjaApi {
    /**
     * Calculate COD fees through the official KiriminAja SDK.
     *
     * @param array $data COD calculation payload.
     * @return array|null
     */
    public function calculateBulkCod( array $data ): ?array {
        $couriers = $data['couriers'] ?? array();
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

        $payload = array(
            'item_price'                    => (int) ( $data['item_price'] ?? 0 ),
            'custom_cod'                    => (int) ( $data['custom_cod'] ?? 1 ),
            'exclude_cod_amount_validation' => (bool) ( $data['exclude_cod_amount_validation'] ?? false ),
            'data'                          => $couriers,
        );
        $response = KiriminAja::calculateCOD( $payload );

        if ( ! $response->status || ! is_array( $response->data ) || empty( $response->data ) ) {
            kiriof_log(
                'warning',
                'COD fee calculation SDK request failed.',
                array(
                    'source'        => 'kiriminaja_payment',
                    'operation'     => 'calculate_bulk_cod',
                    'courier_count' => count( $couriers ),
                    'response'      => $response->message,
                )
            );
            return null;
        }

        return $response->data;
    }
}
