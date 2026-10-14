<?php
namespace KiriminAjaOfficial\Services\TransactionProcessServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use KiriminAjaOfficial\Base\BaseService;
use KiriminAjaOfficial\Utils\CreditBalance;

class GetCreditBalanceService extends BaseService {
	public function call() {
		try {
			$result = ( new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository() )->getCreditBalance();
			$balance = true === ( $result['status'] ?? null ) ? $this->extract_balance( $result['data'] ?? null ) : null;
			if ( null === $balance ) {
				$this->log_unavailable();
				return self::error( array( 'balance' => null ), 'Unable to verify credit balance.' );
			}
			return self::success( array( 'balance' => $balance ), 'success' );
		} catch ( \Throwable $throwable ) {
			$this->log_unavailable();
			return self::error( array( 'balance' => null ), 'Unable to verify credit balance.' );
		}
	}

	private function extract_balance( $data ): ?float {
		return CreditBalance::parse( $data );
	}

	private function log_unavailable(): void {
		if ( function_exists( 'kiriof_log' ) ) {
			kiriof_log( 'warning', 'Unable to verify credit balance.', array( 'source' => 'kiriminaja_api', 'operation' => 'credit_balance', 'reason' => 'balance_unavailable' ) );
		}
	}
}
