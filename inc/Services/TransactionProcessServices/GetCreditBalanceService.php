<?php
namespace KiriminAjaOfficial\Services\TransactionProcessServices;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;

class GetCreditBalanceService extends BaseService {
    public function call() {
        try {
            $result = ( new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository() )->getCreditBalance();

            if ( empty( $result['status'] ) || empty( $result['data'] ) ) {
                return self::error( [ 'balance' => 0 ], $result['data'] ?? 'Failed to get credit balance' );
            }

            $balance = $this->extract_balance( $result['data'] );
            return self::success( [ 'balance' => $balance ], 'success' );
        } catch ( \Throwable $th ) {
            return self::error( [ 'balance' => 0 ], $th->getMessage() );
        }
    }

    /**
     * The credit/balance API returns { status, text, results: { balance } }.
     * Accept object/array payloads plus legacy shapes so the UI never shows Rp0
     * when the API actually returned a balance.
     *
     * @param mixed $data SDK response payload.
     */
    private function extract_balance( $data ): float {
        if ( is_numeric( $data ) ) {
            return (float) $data;
        }

        if ( is_object( $data ) ) {
            $data = json_decode( wp_json_encode( $data ), true );
        }

        if ( ! is_array( $data ) ) {
            return 0.0;
        }

        foreach ( array( 'results', 'data', 'payload', 'result' ) as $wrapper ) {
            if ( isset( $data[ $wrapper ] ) && is_array( $data[ $wrapper ] ) && isset( $data[ $wrapper ]['balance'] ) ) {
                return (float) $data[ $wrapper ]['balance'];
            }
        }

        return (float) ( $data['balance'] ?? 0 );
    }
}
