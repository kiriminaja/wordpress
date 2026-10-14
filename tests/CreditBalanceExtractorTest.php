<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', PLUGIN_DIR . '/' );
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

require_once PLUGIN_DIR . '/inc/Base/BaseService.php';
require_once PLUGIN_DIR . '/inc/Utils/ServiceResponse.php';
require_once PLUGIN_DIR . '/inc/Utils/CreditBalance.php';
require_once PLUGIN_DIR . '/inc/Services/TransactionProcessServices/GetCreditBalanceService.php';

final class CreditBalanceExtractorTest extends TestCase {
	#[Test]
	public function credit_balance_supports_api_results_wrapper(): void {
		$service = new class() extends \KiriminAjaOfficial\Services\TransactionProcessServices\GetCreditBalanceService {
			public function expose( $data ): ?float {
				$method = new ReflectionMethod( parent::class, 'extract_balance' );
				$method->setAccessible( true );

				return $method->invoke( $this, $data );
			}
		};

		// Shape produced by the SDK after call_sdk + objectify:
		// { status: true, text, results: { balance } } wrapped in ['status','data'].
		$objectified = json_decode(
			wp_json_encode(
				array(
					'status'  => true,
					'text'    => 'Success load credit balance',
					'results' => array( 'balance' => 4000724100 ),
				)
			)
		);

		$this->assertSame( 4000724100.0, $service->expose( $objectified ) );
		$this->assertSame( 4000724100.0, $service->expose( array( 'results' => array( 'balance' => 4000724100 ) ) ) );
		$this->assertSame( 125000.0, $service->expose( array( 'balance' => 125000 ) ) );
		$this->assertNull( $service->expose( array() ) );
	}

	#[Test]
	public function strict_parser_accepts_sdk_arrays_wrappers_zero_and_large_balances(): void {
		foreach ( array( 4000724100, '4000724100', array( 'balance' => 4000724100 ), (object) array( 'balance' => '4000724100' ), array( 'status' => true, 'data' => (object) array( 'results' => array( 'balance' => '4000724100' ) ) ) ) as $payload ) {
			$this->assertSame( 4000724100.0, \KiriminAjaOfficial\Utils\CreditBalance::parse( $payload ) );
		}
		$this->assertSame( 0.0, \KiriminAjaOfficial\Utils\CreditBalance::parse( array( 'balance' => 0 ) ) );
		$this->assertSame( 20001.5, \KiriminAjaOfficial\Utils\CreditBalance::parse( array( 'balance' => '20001.5' ) ) );
	}

	#[Test]
	public function strict_parser_never_coerces_unknown_or_formatted_balances_to_zero(): void {
		foreach ( array( null, false, true, array(), -1, INF, NAN, 'INF', '4.000.724.100', '4,000,724,100', 'Rp 4000724100', ' 100 ', '1e3', '100 rupiah', 9007199254740992, array( 'balance' => array( 100 ) ), array( 'status' => false, 'balance' => 100 ), array( 'status' => 'true', 'balance' => 100 ) ) as $payload ) {
			$this->assertNull( \KiriminAjaOfficial\Utils\CreditBalance::parse( $payload ) );
		}
	}
}
