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
require_once PLUGIN_DIR . '/inc/Services/TransactionProcessServices/GetCreditBalanceService.php';

final class CreditBalanceExtractorTest extends TestCase {
	#[Test]
	public function credit_balance_supports_api_results_wrapper(): void {
		$service = new class() extends \KiriminAjaOfficial\Services\TransactionProcessServices\GetCreditBalanceService {
			public function expose( $data ): float {
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
		$this->assertSame( 0.0, $service->expose( array() ) );
	}
}
