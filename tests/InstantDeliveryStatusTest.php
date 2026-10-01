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
	public function complete_nested_payment_and_awb_permutations(): void {
		$expected = array(
			100 => array( 'refunded' => 'cancel', 'pending' => 'waiting_for_shipment', 'unpaid' => 'waiting_for_payment', 'paid' => 'ready_delivered' ),
			105 => array( 'paid' => 'ready_delivered' ),
			110 => array( 'paid' => 'ready_delivered', 'unpaid' => 'waiting_for_payment' ),
			106 => array( 'paid' => 'on_delivery' ),
			200 => array( 'paid' => 'finish' ),
		);
		foreach ( $expected as $code => $payments ) {
			foreach ( array( 'pending', 'unpaid', 'paid', 'refunded', '', null, 'invalid', array(), false ) as $payment ) {
				foreach ( array( '', 'AWB-123', '0', ' ', null, 0, array(), (object) array() ) as $awb ) {
					$key = is_string( $payment ) ? ( $payments[ $payment ] ?? 'unknown' ) : 'unknown';
					if ( 100 === $code && 'paid' === $payment && ! ( is_string( $awb ) && '' !== trim( $awb ) ) ) {
						$key = 'waiting_for_awb_generation';
					}
					foreach ( array( $code, (string) $code ) as $remote_code ) {
						$result = InstantDeliveryStatus::describe( $this->remote( $remote_code, $payment, $awb ) );
						$this->assertSame( $key, $result['key'] );
						$this->assertSame( '', $result['issue'] );
						if ( 'unknown' === $key ) {
							$this->assertNotSame( 'success', $result['tone'] );
						}
					}
				}
			}
		}
	}

	#[Test]
	public function all_direct_codes_ignore_payment_for_labels_but_honor_source_tones(): void {
		$map = array( 101 => 'find_new_driver', 300 => 'cancel', 302 => 'cancel', 350 => 'cancel_requested', 400 => 'finish_retur', 401 => 'retur', 402 => 'retur', 403 => 'retur', 404 => 'retur', 405 => 'retur', 701 => 'shipment_problem', 702 => 'shipment_problem', 303 => 'shipment_problem', 500 => 'shipment_problem', 555 => 'shipment_problem', 301 => 'shipment_problem', 333 => 'shipment_problem' );
		foreach ( $map as $code => $key ) {
			foreach ( array( 'paid', 'unpaid', 'pending', 'refunded', '', null ) as $payment ) {
				foreach ( array( $code, (string) $code ) as $value ) {
					$result = InstantDeliveryStatus::describe( (object) $this->remote( $value, $payment ) );
					$this->assertSame( $key, $result['key'] );
					$this->assertSame( 'shipment_problem' === $key ? 'There is a problem with this shipment.' : ( 405 === $code ? 'The shipment return was rejected.' : '' ), $result['issue'] );
					$tone = 'unpaid' === $payment ? 'warning' : ( $code >= 400 && $code <= 405 ? 'warning' : ( in_array( $code, array( 300, 302, 350, 555, 701, 702, 333 ), true ) ? 'critical' : 'info' ) );
					$this->assertSame( $tone, $result['tone'] );
				}
			}
		}
		$driver = InstantDeliveryStatus::describe( array( 'instant_status_code' => 101 ) );
		$this->assertStringContainsString( 'automatically', $driver['tooltip'] );
		$this->assertStringContainsString( 'No manual action', $driver['tooltip'] );
	}

	#[Test]
	public function remote_code_and_payment_do_not_borrow_local_enum_or_shopify_aliases(): void {
		foreach ( array( 0, '0', -1, 999, '999', true, false, 100.0, '100.0', '1e2', '0100', ' 100 ', '', 'bad', array(), (object) array() ) as $code ) {
			$result = InstantDeliveryStatus::describe( $this->remote( $code, 'paid' ) );
			$this->assertSame( 'unknown', $result['key'] );
			$this->assertNotSame( 'success', $result['tone'] );
		}
		foreach ( array( 100, 105, 106, 110, 200 ) as $code ) {
			$this->assertSame( 'unknown', InstantDeliveryStatus::describe( array( 'instant_status_code' => $code, 'status' => 'paid' ) )['key'] );
		}
		$this->assertSame( 'unknown', InstantDeliveryStatus::describe( array( 'statusInternalKa' => 200, 'status' => 'paid' ) )['key'] );
		$this->assertSame( 'unknown', InstantDeliveryStatus::describe( $this->remote( 200, 'PAID' ) )['key'] );
	}

	#[Test]
	public function local_fallback_is_shared_for_missing_and_null_remote_status(): void {
		$map = array( 'new' => 'waiting_for_shipment', 'request_pickup' => 'waiting_for_awb_generation', 'pending' => 'waiting_for_payment', 'shipped' => 'on_delivery', 'finished' => 'finish', 'return' => 'retur', 'returned' => 'finish_retur', 'rejected' => 'shipment_problem', 'canceled' => 'cancel' );
		foreach ( $map as $status => $key ) {
			foreach ( array( array(), array( 'instant_status_code' => null, 'instant_payment_status' => 'paid' ) ) as $remote ) {
				$row = array_merge( array( 'status' => $status ), $remote );
				$this->assertSame( $key, InstantDeliveryStatus::describe( $row )['key'] );
				$this->assertSame( InstantDeliveryStatus::describe( $row ), InstantDeliveryStatus::describe( (object) $row ) );
			}
		}
		$this->assertSame( 'ready_delivered', InstantDeliveryStatus::describe( array( 'status' => 'request_pickup', 'awb' => '0' ) )['key'] );
		$this->assertSame( '', InstantDeliveryStatus::describe( array( 'status' => 'canceled' ) )['issue'] );
		$this->assertNotSame( '', InstantDeliveryStatus::describe( array( 'status' => 'rejected' ) )['issue'] );
		foreach ( array( null, array(), (object) array(), 0, true, 'finished' ) as $row ) {
			$this->assertSame( 'unknown', InstantDeliveryStatus::describe( $row )['key'] );
		}
	}

	#[Test]
	public function ambiguous_pending_booking_uses_only_the_fixed_persisted_issue_without_a_remote_code(): void {
		$reason = 'Check remote state before retrying';
		foreach ( array( array(), array( 'instant_status_code' => null ) ) as $remote ) {
			$row = array_merge( array( 'status' => 'pending', 'rejected_reason' => $reason ), $remote );
			foreach ( array( $row, (object) $row ) as $input ) {
				$before = serialize( $input );
				$result = InstantDeliveryStatus::describe( $input );
				$this->assertSame( 'unknown', $result['key'] );
				$this->assertSame( 'Unknown', $result['label'] );
				$this->assertSame( 'info', $result['tone'] );
				$this->assertSame( $reason, $result['issue'] );
				$this->assertSame( $before, serialize( $input ) );
			}
		}
		foreach ( array( '<img src=x onerror=alert(1)>', 'Secret upstream error', $reason . ' ', null, array( $reason ), (object) array( 'reason' => $reason ) ) as $raw_reason ) {
			$result = InstantDeliveryStatus::describe( array( 'status' => 'pending', 'rejected_reason' => $raw_reason ) );
			$this->assertSame( 'waiting_for_payment', $result['key'] );
			$this->assertSame( '', $result['issue'] );
			$this->assertStringNotContainsString( '<img', json_encode( $result ) );
			$this->assertStringNotContainsString( 'Secret', json_encode( $result ) );
		}
		foreach ( array( 0, '0', 999, 'bad', '' ) as $code ) {
			$result = InstantDeliveryStatus::describe( array( 'status' => 'pending', 'instant_status_code' => $code, 'rejected_reason' => $reason ) );
			$this->assertSame( 'unknown', $result['key'] );
			$this->assertSame( '', $result['issue'] );
		}
		$this->assertSame( 'waiting_for_shipment', InstantDeliveryStatus::describe( array( 'status' => 'new', 'rejected_reason' => $reason ) )['key'] );
		$this->assertSame( '', InstantDeliveryStatus::describe( array( 'status' => 'new', 'rejected_reason' => $reason ) )['issue'] );
	}

	#[Test]
	public function destination_validation_accepts_zero_boundaries_and_database_strings(): void {
		foreach ( array( array( 0, 0 ), array( '0', '0.0' ), array( -90, -180 ), array( '90', '180' ), array( '-6.2', '106.8' ) ) as $coordinates ) {
			$row = $this->remote( 100, 'pending' );
			$row['destination_latitude'] = $coordinates[0];
			$row['destination_longitude'] = $coordinates[1];
			$result = InstantDeliveryStatus::describe( $row );
			$this->assertSame( 'waiting_for_shipment', $result['key'] );
			$this->assertSame( '', $result['issue'] );
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
		$this->assertSame( 'waiting_for_shipment', InstantDeliveryStatus::describe( array( 'status' => 'new' ) )['key'] );
	}

	private function assertConfirmation( array $row ): void {
		$result = InstantDeliveryStatus::describe( $row );
		$this->assertSame( 'need_confirmation', $result['key'] );
		$this->assertSame( 'critical', $result['tone'] );
		$this->assertNotSame( '', $result['issue'] );
		$this->assertStringContainsString( 'buyer address', $result['tooltip'] );
		$this->assertStringNotContainsString( 'button', $result['tooltip'] );
	}

	#[Test]
	public function output_is_fixed_string_contract_xss_safe_and_inputs_are_not_mutated(): void {
		$bad = '<img src=x onerror=alert(1)>';
		foreach ( array( $bad, array( $bad ), (object) array( 'x' => $bad ) ) as $value ) {
			$row = array( 'status' => $value, 'instant_status_code' => $value, 'instant_payment_status' => $value, 'awb' => $value, 'destination_latitude' => $value, 'destination_longitude' => $value );
			$before = serialize( $row );
			foreach ( array( $row, (object) $row ) as $input ) {
				$serialized = serialize( $input );
				$result = InstantDeliveryStatus::describe( $input );
				$this->assertSame( array( 'key', 'label', 'tone', 'tooltip', 'issue' ), array_keys( $result ) );
				$this->assertSame( 'unknown', $result['key'] );
				foreach ( $result as $output ) {
					$this->assertIsString( $output );
					$this->assertStringNotContainsString( $bad, $output );
				}
				$this->assertSame( $serialized, serialize( $input ) );
			}
			$this->assertSame( $before, serialize( $row ) );
		}
	}

	#[Test]
	public function labels_and_source_tone_overrides_remain_explicit(): void {
		$this->assertSame( 'info', InstantDeliveryStatus::describe( $this->remote( 100, 'refunded' ) )['tone'] );
		$this->assertSame( 'warning', InstantDeliveryStatus::describe( $this->remote( 105, 'paid' ) )['tone'] );
		$this->assertSame( 'success', InstantDeliveryStatus::describe( $this->remote( 200, 'paid' ) )['tone'] );
		$this->assertSame( 'warning', InstantDeliveryStatus::describe( $this->remote( 200, 'unpaid' ) )['tone'] );
		$this->assertSame( 'Cancelled', InstantDeliveryStatus::describe( $this->remote( 100, 'refunded' ) )['label'] );
		$this->assertSame( 'Shipment Problem', InstantDeliveryStatus::describe( $this->remote( 301, 'paid' ) )['label'] );
		$this->assertSame( 'Delivered', InstantDeliveryStatus::describe( array( 'status' => 'finished' ) )['label'] );
	}
}
