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

            $balance = (float) ( $result['data']->results->balance ?? 0 );
            return self::success( [ 'balance' => $balance ], 'success' );
        } catch ( \Throwable $th ) {
            return self::error( [ 'balance' => 0 ], $th->getMessage() );
        }
    }
}
