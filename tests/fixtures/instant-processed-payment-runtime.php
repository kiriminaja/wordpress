<?php
/** Isolated WordPress boundary; production services run against Mockery repositories. */
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

function esc_html__( $text, $domain ) {
	return $text;
}
function wc_get_order( $id ) {
	return $GLOBALS['processed_payment_order'];
}
function kiriof_log( $level, $message, $context ) {
}
function wp_json_encode( $value ) {
	return json_encode( $value );
}
function __( $text, $domain ) {
	return $text;
}
function get_transient( $key ) {
	return false;
}
function set_transient( $key, $value, $ttl ) {
	return true;
}
function add_option( $key, $value, $deprecated, $autoload ) {
	$GLOBALS['processed_payment_options'][$key] = $value;
	return true;
}
function get_option( $key ) {
	return $GLOBALS['processed_payment_options'][$key] ?? false;
}
function wp_generate_uuid4() {
	return 'fixture-owner';
}
function wp_parse_url( $url ) {
	return parse_url( $url );
}
function esc_url_raw( $url, $protocols ) {
	return $url;
}

$input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
$row = (object) array_replace(
	array(
		'order_id' => 'KA-1',
		'delivery_type' => 'instant',
		'service' => 'gosend',
		'service_name' => 'instant',
		'status' => 'request_pickup',
		'instant_status_code' => 110,
		'instant_payment_id' => 'PAY-1',
		'instant_payment_method' => 'qris',
		'instant_payment_status' => 'unpaid',
		'awb' => null,
		'wp_wc_order_stat_order_id' => 1,
	),
	$input['row'] ?? array()
);
$package = array_replace( array( 'order_id' => 'KA-1', 'status' => 105, 'awb' => 'AWB-1' ), $input['package'] ?? array() );
$payment = $input['payment'] ?? array();
$writes = array();
$race = $input['race'] ?? null;
$repository = Mockery::mock( \KiriminAjaOfficial\Repositories\TransactionRepository::class );
$repository->shouldReceive( 'getTransactionByOrderId' )->with( 'KA-1' )->andReturnUsing( static function () use ( $row ) {
	return clone $row;
} );
$repository->shouldReceive( 'getTransactionByOrderIds' )->with( array( 'KA-1' ) )->andReturnUsing( static function () use ( $row ) {
	return array( clone $row );
} );
$repository->shouldNotReceive( 'updateTransactionByCallbackVerified' );
$repository->shouldReceive( 'compareAndSwapInstant' )->with( 'KA-1', Mockery::type( 'array' ), Mockery::type( 'array' ) )->andReturnUsing(
	static function ( $id, $expected, $changes ) use ( $row, &$writes, &$race, $input ) {
		$writes[] = array( 'condition' => $expected, 'changes' => $changes );
		if ( ! empty( $input['write_failure'] ) ) {
			return false;
		}
		if ( null !== $race ) {
			foreach ( $race as $key => $value ) {
				$row->{$key} = $value;
			}
			$race = null;
			return false;
		}
		foreach ( $expected as $key => $value ) {
			if ( ( $row->{$key} ?? null ) !== $value ) {
				return false;
			}
		}
		foreach ( $changes as $key => $value ) {
			$row->{$key} = $value;
		}
		return true;
	}
);
$order = Mockery::mock();
$order->shouldReceive( 'get_status' )->andReturn( 'processing' );
$order->shouldReceive( 'get_meta' )->with( '_kiriof_instant_lifecycle_status', true )->andReturn( 'canceled' );
$order->shouldNotReceive( 'payment_complete' );
$order->shouldNotReceive( 'update_status' );
$GLOBALS['processed_payment_order'] = $order;
$payments = Mockery::mock( \KiriminAjaOfficial\Repositories\PaymentRepository::class );
$payments->shouldNotReceive( 'getPaymentByPaymentId' );
$payments->shouldNotReceive( 'updatePaymentByCallbackVerified' );
$state = new \KiriminAjaOfficial\Services\InstantShipmentState( $repository );
$results = array();
try {
	for ( $i = 0; $i < ( $input['repeat'] ?? 1 ); ++$i ) {
		$mode = $input['mode'] ?? 'webhook';
		if ( 'webhook' === $mode ) {
			$body = (object) array(
				'method' => 'processed_packages',
				'data' => array( (object) array_merge( array( 'order_id' => 'KA-1' ), $input['event'] ?? array() ) ),
				'packages' => array( (object) $package ),
			);
			if ( array_key_exists( 'payment', $input ) ) {
				$body->payment = null === $input['payment'] ? null : (object) $payment;
			}
			$handler = new \KiriminAjaOfficial\Services\CallbackHandlerService( $repository, $payments, 'fixture-token', new \KiriminAjaOfficial\Services\InstantWebhookService( $state ) );
			$response = $handler->header( array( 'Authorization' => 'Bearer ' . ( $input['token'] ?? 'fixture-token' ) ) )->body( $body )->call();
			$results[] = $response->status();
		} elseif ( 'booking' === $mode ) {
			$state->confirmBooking( 'KA-1', $package, $payment, array() );
		} elseif ( 'metadata' === $mode ) {
			$state->mergeTrackingMetadata( 'KA-1', $package );
		} elseif ( 'tracking' === $mode ) {
			$api = Mockery::mock( \KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository::class );
			$api->shouldReceive( 'tracking' )->once()->with( 'KA-1' )->andReturn( array(
				'status' => true,
				'data' => (object) array( 'status' => true, 'code' => 0, 'result' => (object) array_merge(
					array( 'service' => 'gosend', 'service_type' => 'Instant' ), $package, array( 'payment' => (object) $payment )
				) ),
			) );
			$operations = new \KiriminAjaOfficial\Services\InstantOperationsService( $repository, $api, $state );
			$results[] = $operations->reconcile( array( 'KA-1' ) )['rows'][0]['status'];
		} else {
			$state->apply( 'KA-1', $package, $payment );
		}
	}
	if ( ! empty( $input['poll_unpaid'] ) ) {
		$state->mergePayment( 'KA-1', array( 'id' => 'PAY-1', 'status_code' => 9 ) );
	}
} catch ( InvalidArgumentException $error ) {
	$results[] = 400;
} catch ( RuntimeException $error ) {
	$results[] = 503;
}
Mockery::close();
echo json_encode( array( 'row' => $row, 'writes' => $writes, 'responses' => $results ), JSON_THROW_ON_ERROR );
