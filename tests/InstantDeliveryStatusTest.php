<?php
/** @package KiriminAjaOfficial */

declare(strict_types=1);

use KiriminAjaOfficial\Services\InstantDeliveryStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', PLUGIN_DIR . '/' );
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ): string {
		return $text;
	}
}
require_once PLUGIN_DIR . '/inc/Services/InstantDeliveryStatus.php';

final class InstantDeliveryStatusTest extends TestCase {
	private function remote( $code, $payment, $awb = '' ): array {
		return array(
			'instant_status_code' => $code,
			'instant_payment_status' => $payment,
			'awb' => $awb,
			'destination_latitude' => 0,
			'destination_longitude' => '0',
			'status' => 'finished',
		);
	}

	#[Test]
	public function nested_codes_map_payment_states_without_borrowing_local_status(): void {
		$expected = array(
			100 => array( 'refunded' => 'cancel', 'pending' => 'waiting_for_shipment', 'unpaid' => 'waiting_for_payment', 'paid' => 'ready_delivered' ),
			105 => array( 'paid' => 'ready_delivered' ),
			110 => array( 'paid' => 'ready_delivered', 'unpaid' => 'waiting_for_payment' ),
			106 => array( 'paid' => 'on_delivery' ),
			200 => array( 'paid' => 'finish' ),
		);
		foreach ( $expected as $code => $payments ) {
			foreach ( array( 'pending', 'unpaid', 'paid', 'refunded', '', null, 'invalid', array(), false ) as $payment ) {
				$key = is_string( $payment ) ? ( $payments[ $payment ] ?? 'unknown' ) : 'unknown';
				if ( 100 === $code && 'paid' === $payment ) {
					$key = 'waiting_for_awb_generation';
				}
				foreach ( array( $code, (string) $code ) as $remote_code ) {
					$result = InstantDeliveryStatus::describe( $this->remote( $remote_code, $payment ) );
					$this->assertSame(
					    [
					    '1: result[key]' => $key,
					    '2: result[issue]' => '',
					    ],
					    [
					    '1: result[key]' => $result['key'],
					    '2: result[issue]' => $result['issue'],
					    ]
					);
					if ( 'unknown' === $key ) {
						$this->assertNotSame( 'success', $result['tone'] );
					}
				}
			}
		}
	}

	#[Test]
	public function only_paid_code_100_uses_awb_presence(): void {
		$expectedCases = $actualCases = [];
		foreach ( array( '', 'AWB-123', '0', ' ', null, 0, array(), (object) array() ) as $awb ) {
			$expected = is_string( $awb ) && '' !== trim( $awb ) ? 'ready_delivered' : 'waiting_for_awb_generation';
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: InstantDeliveryStatus::describe( remote( 100, paid, awb ) )[key]' => $expected,
			    '2: InstantDeliveryStatus::describe( remote( 100, pending, awb ) )[key]' => 'waiting_for_shipment',
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: InstantDeliveryStatus::describe( remote( 100, paid, awb ) )[key]' => InstantDeliveryStatus::describe( $this->remote( 100, 'paid', $awb ) )['key'],
			    '2: InstantDeliveryStatus::describe( remote( 100, pending, awb ) )[key]' => InstantDeliveryStatus::describe( $this->remote( 100, 'pending', $awb ) )['key'],
			    ];
		}
		foreach ( array( 105 => 'ready_delivered', 110 => 'ready_delivered', 106 => 'on_delivery', 200 => 'finish' ) as $code => $key ) {
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = $key;
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = InstantDeliveryStatus::describe( $this->remote( $code, 'paid', 'AWB-123' ) )['key'];
		}
		$this->assertSame( $expectedCases, $actualCases );
	}

	#[Test]
	public function all_direct_codes_ignore_payment_for_labels_but_honor_source_tones(): void {
		$expectedCases = $actualCases = [];
		$map = array( 101 => 'find_new_driver', 300 => 'cancel', 302 => 'cancel', 350 => 'cancel_requested', 400 => 'finish_retur', 401 => 'retur', 402 => 'retur', 403 => 'retur', 404 => 'retur', 405 => 'retur', 701 => 'shipment_problem', 702 => 'shipment_problem', 303 => 'shipment_problem', 500 => 'shipment_problem', 555 => 'shipment_problem', 301 => 'shipment_problem', 333 => 'shipment_problem' );
		foreach ( $map as $code => $key ) {
			foreach ( array( 'paid', 'unpaid' ) as $payment ) {
				foreach ( array( $code, (string) $code ) as $value ) {
					$result = InstantDeliveryStatus::describe( (object) $this->remote( $value, $payment ) );
					$contractCase = 'case ' . count( $expectedCases );
					$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
					    '1: result[key]' => $key,
					    '2: result[issue]' => 'shipment_problem' === $key ? 'There is a problem with this shipment.' : ( 405 === $code ? 'The shipment return was rejected.' : '' ),
					    ];
					$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
					    '1: result[key]' => $result['key'],
					    '2: result[issue]' => $result['issue'],
					    ];
					$tone = 'unpaid' === $payment ? 'warning' : ( $code >= 400 && $code <= 405 ? 'warning' : ( in_array( $code, array( 300, 302, 350, 555, 701, 702, 333 ), true ) ? 'critical' : 'info' ) );
					$contractCase = 'case ' . count( $expectedCases );
					$expectedCases[$contractCase . " / " . count( $expectedCases )] = $tone;
					$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $result['tone'];
				}
			}
		}
		foreach ( array( 'pending', 'refunded', '', null ) as $payment ) {
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = 'find_new_driver';
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = InstantDeliveryStatus::describe( $this->remote( 101, $payment ) )['key'];
		}
		$driver = InstantDeliveryStatus::describe( array( 'instant_status_code' => 101 ) );
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: str_contains( driver[tooltip], automatically )' => true,
		    '2: str_contains( driver[tooltip], No manual action )' => true,
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: str_contains( driver[tooltip], automatically )' => str_contains( $driver['tooltip'], 'automatically' ),
		    '2: str_contains( driver[tooltip], No manual action )' => str_contains( $driver['tooltip'], 'No manual action' ),
		    ];
		$this->assertSame( $expectedCases, $actualCases );
	}

	#[Test]
	public function remote_code_and_payment_do_not_borrow_local_enum_or_shopify_aliases(): void {
		$expectedCases = $actualCases = [];
		foreach ( array( 0, '0', -1, 999, '999', true, false, 100.0, '100.0', '1e2', '0100', ' 100 ', '', 'bad', array(), (object) array() ) as $code ) {
			$result = InstantDeliveryStatus::describe( $this->remote( $code, 'paid' ) );
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: result[key]' => 'unknown',
			    '2: (success) !== (result[tone])' => true,
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: result[key]' => $result['key'],
			    '2: (success) !== (result[tone])' => ('success') !== ($result['tone']),
			    ];
		}
		foreach ( array( 100, 105, 106, 110, 200 ) as $code ) {
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = 'unknown';
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = InstantDeliveryStatus::describe( array( 'instant_status_code' => $code, 'status' => 'paid' ) )['key'];
		}
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: InstantDeliveryStatus::describe( array( statusInternalKa => 200, status => paid ) )[key]' => 'unknown',
		    '2: InstantDeliveryStatus::describe( remote( 200, PAID ) )[key]' => 'unknown',
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: InstantDeliveryStatus::describe( array( statusInternalKa => 200, status => paid ) )[key]' => InstantDeliveryStatus::describe( array( 'statusInternalKa' => 200, 'status' => 'paid' ) )['key'],
		    '2: InstantDeliveryStatus::describe( remote( 200, PAID ) )[key]' => InstantDeliveryStatus::describe( $this->remote( 200, 'PAID' ) )['key'],
		    ];
		$this->assertSame( $expectedCases, $actualCases );
	}

	#[Test]
	public function local_fallback_is_shared_for_missing_and_null_remote_status(): void {
		$expectedCases = $actualCases = [];
		$map = array( 'new' => 'waiting_for_shipment', 'request_pickup' => 'waiting_for_awb_generation', 'pending' => 'waiting_for_payment', 'shipped' => 'on_delivery', 'finished' => 'finish', 'return' => 'retur', 'returned' => 'finish_retur', 'rejected' => 'shipment_problem', 'canceled' => 'cancel' );
		foreach ( $map as $status => $key ) {
			foreach ( array( array(), array( 'instant_status_code' => null, 'instant_payment_status' => 'paid' ) ) as $remote ) {
				$row = array_merge( array( 'status' => $status ), $remote );
				$contractCase = 'case ' . count( $expectedCases );
				$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
				    '1: InstantDeliveryStatus::describe( row )[key]' => $key,
				    '2: InstantDeliveryStatus::describe( (object) row )' => InstantDeliveryStatus::describe( $row ),
				    ];
				$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
				    '1: InstantDeliveryStatus::describe( row )[key]' => InstantDeliveryStatus::describe( $row )['key'],
				    '2: InstantDeliveryStatus::describe( (object) row )' => InstantDeliveryStatus::describe( (object) $row ),
				    ];
			}
		}
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: InstantDeliveryStatus::describe( array( status => request_pickup, awb => 0 ) )[key]' => 'ready_delivered',
		    '2: InstantDeliveryStatus::describe( array( status => canceled ) )[issue]' => '',
		    '3: () !== (InstantDeliveryStatus::describe( array( status => rejected ) )[issue])' => true,
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: InstantDeliveryStatus::describe( array( status => request_pickup, awb => 0 ) )[key]' => InstantDeliveryStatus::describe( array( 'status' => 'request_pickup', 'awb' => '0' ) )['key'],
		    '2: InstantDeliveryStatus::describe( array( status => canceled ) )[issue]' => InstantDeliveryStatus::describe( array( 'status' => 'canceled' ) )['issue'],
		    '3: () !== (InstantDeliveryStatus::describe( array( status => rejected ) )[issue])' => ('') !== (InstantDeliveryStatus::describe( array( 'status' => 'rejected' ) )['issue']),
		    ];
		foreach ( array( null, array(), (object) array(), 0, true, 'finished' ) as $row ) {
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = 'unknown';
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = InstantDeliveryStatus::describe( $row )['key'];
		}
		$this->assertSame( $expectedCases, $actualCases );
	}

	#[Test]
	public function ambiguous_pending_booking_uses_only_the_fixed_persisted_issue_without_a_remote_code(): void {
		$expectedCases = $actualCases = [];
		$reason = 'Check remote state before retrying';
		foreach ( array( array(), array( 'instant_status_code' => null ) ) as $remote ) {
			$row = array_merge( array( 'status' => 'pending', 'rejected_reason' => $reason ), $remote );
			foreach ( array( $row, (object) $row ) as $input ) {
				$before = serialize( $input );
				$result = InstantDeliveryStatus::describe( $input );
				$contractCase = 'case ' . count( $expectedCases );
				$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
				    '1: result[key]' => 'waiting_for_shipment',
				    '2: result[label]' => 'Waiting for Shipment',
				    '3: result[tone]' => 'info',
				    '4: result[issue]' => '',
				    '5: serialize( input )' => $before,
				    ];
				$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
				    '1: result[key]' => $result['key'],
				    '2: result[label]' => $result['label'],
				    '3: result[tone]' => $result['tone'],
				    '4: result[issue]' => $result['issue'],
				    '5: serialize( input )' => serialize( $input ),
				    ];
			}
		}
		foreach ( array( '<img src=x onerror=alert(1)>', 'Secret upstream error', $reason . ' ', null, array( $reason ), (object) array( 'reason' => $reason ) ) as $raw_reason ) {
			$result = InstantDeliveryStatus::describe( array( 'status' => 'pending', 'rejected_reason' => $raw_reason ) );
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: result[key]' => 'waiting_for_payment',
			    '2: result[issue]' => '',
			    '3: str_contains( json_encode( result ), <img )' => false,
			    '4: str_contains( json_encode( result ), Secret )' => false,
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: result[key]' => $result['key'],
			    '2: result[issue]' => $result['issue'],
			    '3: str_contains( json_encode( result ), <img )' => str_contains( json_encode( $result ), '<img' ),
			    '4: str_contains( json_encode( result ), Secret )' => str_contains( json_encode( $result ), 'Secret' ),
			    ];
		}
		foreach ( array( 0, '0', 999, 'bad', '' ) as $code ) {
			$result = InstantDeliveryStatus::describe( array( 'status' => 'pending', 'instant_status_code' => $code, 'rejected_reason' => $reason ) );
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: result[key]' => 'unknown',
			    '2: result[issue]' => '',
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: result[key]' => $result['key'],
			    '2: result[issue]' => $result['issue'],
			    ];
		}
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: InstantDeliveryStatus::describe( array( status => new, rejected_reason => reason ) )[key]' => 'waiting_for_shipment',
		    '2: InstantDeliveryStatus::describe( array( status => new, rejected_reason => reason ) )[issue]' => '',
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: InstantDeliveryStatus::describe( array( status => new, rejected_reason => reason ) )[key]' => InstantDeliveryStatus::describe( array( 'status' => 'new', 'rejected_reason' => $reason ) )['key'],
		    '2: InstantDeliveryStatus::describe( array( status => new, rejected_reason => reason ) )[issue]' => InstantDeliveryStatus::describe( array( 'status' => 'new', 'rejected_reason' => $reason ) )['issue'],
		    ];
		$this->assertSame( $expectedCases, $actualCases );
	}

	#[Test]
	public function destination_validation_accepts_zero_boundaries_and_database_strings(): void {
		$expectedCases = $actualCases = [];
		foreach ( array( array( 0, 0 ), array( '0', '0.0' ), array( -90, -180 ), array( '90', '180' ), array( '-6.2', '106.8' ) ) as $coordinates ) {
			$row = $this->remote( 100, 'pending' );
			$row['destination_latitude'] = $coordinates[0];
			$row['destination_longitude'] = $coordinates[1];
			$result = InstantDeliveryStatus::describe( $row );
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: result[key]' => 'waiting_for_shipment',
			    '2: result[issue]' => '',
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: result[key]' => $result['key'],
			    '2: result[issue]' => $result['issue'],
			    ];
		}
		foreach ( array( null, '', ' ', false, true, array(), (object) array(), INF, -INF, NAN, 'NaN', '<script>', '0,0' ) as $invalid ) {
			foreach ( array( 'destination_latitude', 'destination_longitude' ) as $field ) {
				$row = $this->remote( '100', 'pending' );
				$row[ $field ] = $invalid;
				$this->assertConfirmation( $row );
			}
		}
		foreach ( array( array( 'destination_latitude' => 90.1, 'destination_longitude' => 0 ), array( 'destination_latitude' => 0, 'destination_longitude' => -180.1 ), array(), array( 'destination_latitude' => 0 ) ) as $coordinates ) {
			$this->assertConfirmation( array_merge( array( 'instant_status_code' => 100, 'instant_payment_status' => 'pending' ), $coordinates ) );
		}
		foreach ( array( 'paid', 'unpaid', 'refunded' ) as $payment ) {
			$this->assertNotSame( 'need_confirmation', InstantDeliveryStatus::describe( array( 'instant_status_code' => 100, 'instant_payment_status' => $payment ) )['key'] );
		}
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = 'waiting_for_shipment';
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = InstantDeliveryStatus::describe( array( 'status' => 'new' ) )['key'];
		$this->assertSame( $expectedCases, $actualCases );
	}

	private function assertConfirmation( array $row ): void {
		$result = InstantDeliveryStatus::describe( $row );
		$this->assertSame(
		    [
		    '1: result[key]' => 'need_confirmation',
		    '2: result[tone]' => 'critical',
		    '3: () !== (result[issue])' => true,
		    '4: str_contains( result[tooltip], buyer address )' => true,
		    '5: str_contains( result[tooltip], button )' => false,
		    ],
		    [
		    '1: result[key]' => $result['key'],
		    '2: result[tone]' => $result['tone'],
		    '3: () !== (result[issue])' => ('') !== ($result['issue']),
		    '4: str_contains( result[tooltip], buyer address )' => str_contains( $result['tooltip'], 'buyer address' ),
		    '5: str_contains( result[tooltip], button )' => str_contains( $result['tooltip'], 'button' ),
		    ]
		);
    }

	#[Test]
	public function output_is_fixed_string_contract_xss_safe_and_inputs_are_not_mutated(): void {
		$expectedCases = $actualCases = [];
		$bad = '<img src=x onerror=alert(1)>';
		foreach ( array( $bad, array( $bad ), (object) array( 'x' => $bad ) ) as $value ) {
			$row = array( 'status' => $value, 'instant_status_code' => $value, 'instant_payment_status' => $value, 'awb' => $value, 'destination_latitude' => $value, 'destination_longitude' => $value );
			$before = serialize( $row );
			foreach ( array( $row, (object) $row ) as $input ) {
				$serialized = serialize( $input );
				$result = InstantDeliveryStatus::describe( $input );
				$contractCase = 'case ' . count( $expectedCases );
				$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
				    '1: array_keys( result )' => array( 'key', 'label', 'tone', 'tooltip', 'issue' ),
				    '2: result[key]' => 'unknown',
				    ];
				$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
				    '1: array_keys( result )' => array_keys( $result ),
				    '2: result[key]' => $result['key'],
				    ];
				foreach ( $result as $output ) {
					$contractCase = 'case ' . count( $expectedCases );
					$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
					    '1: is_string( output )' => true,
					    '2: str_contains( output, bad )' => false,
					    ];
					$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
					    '1: is_string( output )' => is_string( $output ),
					    '2: str_contains( output, bad )' => str_contains( $output, $bad ),
					    ];
				}
				$contractCase = 'case ' . count( $expectedCases );
				$expectedCases[$contractCase . " / " . count( $expectedCases )] = $serialized;
				$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = serialize( $input );
			}
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = $before;
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = serialize( $row );
		}
		$this->assertSame( $expectedCases, $actualCases );
	}

	#[Test]
	public function labels_and_source_tone_overrides_remain_explicit(): void {
		$this->assertSame(
		    [
		    '1: InstantDeliveryStatus::describe( remote( 100, refunded ) )[tone]' => 'info',
		    '2: InstantDeliveryStatus::describe( remote( 105, paid ) )[tone]' => 'warning',
		    '3: InstantDeliveryStatus::describe( remote( 200, paid ) )[tone]' => 'success',
		    '4: InstantDeliveryStatus::describe( remote( 200, unpaid ) )[tone]' => 'warning',
		    '5: InstantDeliveryStatus::describe( remote( 100, refunded ) )[label]' => 'Cancelled',
		    '6: InstantDeliveryStatus::describe( remote( 301, paid ) )[label]' => 'Shipment Problem',
		    '7: InstantDeliveryStatus::describe( array( status => finished ) )[label]' => 'Delivered',
		    ],
		    [
		    '1: InstantDeliveryStatus::describe( remote( 100, refunded ) )[tone]' => InstantDeliveryStatus::describe( $this->remote( 100, 'refunded' ) )['tone'],
		    '2: InstantDeliveryStatus::describe( remote( 105, paid ) )[tone]' => InstantDeliveryStatus::describe( $this->remote( 105, 'paid' ) )['tone'],
		    '3: InstantDeliveryStatus::describe( remote( 200, paid ) )[tone]' => InstantDeliveryStatus::describe( $this->remote( 200, 'paid' ) )['tone'],
		    '4: InstantDeliveryStatus::describe( remote( 200, unpaid ) )[tone]' => InstantDeliveryStatus::describe( $this->remote( 200, 'unpaid' ) )['tone'],
		    '5: InstantDeliveryStatus::describe( remote( 100, refunded ) )[label]' => InstantDeliveryStatus::describe( $this->remote( 100, 'refunded' ) )['label'],
		    '6: InstantDeliveryStatus::describe( remote( 301, paid ) )[label]' => InstantDeliveryStatus::describe( $this->remote( 301, 'paid' ) )['label'],
		    '7: InstantDeliveryStatus::describe( array( status => finished ) )[label]' => InstantDeliveryStatus::describe( array( 'status' => 'finished' ) )['label'],
		    ]
		);
	}
}
