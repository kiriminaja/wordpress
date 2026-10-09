<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantLabelRuntimeTest extends TestCase {
	private function run_label( array $input = array() ): array {
		$output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/instant-label-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) );
		$result = json_decode( (string) $output, true, 512, JSON_THROW_ON_ERROR );
		$this->assertSame(
		    [
		    '1: result[writes]' => 0,
		    '2: result[calls]' => ! empty( $input['preview'] ) && '' === $result['error'] ? 1 : 0,
		    ],
		    [
		    '1: result[writes]' => $result['writes'],
		    '2: result[calls]' => $result['calls'],
		    ]
		);
		return $result;
	}

	#[Test]
	public function carrier_preview_accepts_existing_response_aliases_and_keeps_signed_url(): void {
		$expectedCases = $actualCases = [];
		$url = 'https://storage.googleapis.com/kiriminaja/label.pdf?signature=abc%2B123&expires=99';
		foreach ( array( $url, array( 'url' => $url ), array( 'link' => $url ), array( 'data' => array( 'url' => $url ) ), array( 'data' => array( 'link' => $url ) ) ) as $data ) {
			foreach ( array( false, true ) as $object_data ) {
				$result = $this->run_label( array( 'preview' => true, 'print_response' => array( 'status' => true, 'data' => $data ), 'object_data' => $object_data ) );
				$contractCase = 'case ' . count( $expectedCases );
				$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
				    '1: result[preview]' => array( 'url' => $url, 'provider' => 'carrier', 'type' => 'pdf', 'carrier_available' => true ),
				    '2: result[print_awbs]' => array( 'AWB-KA-1' ),
				    '3: ! empty( result[html] )' => true,
				    ];
				$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
				    '1: result[preview]' => $result['preview'],
				    '2: result[print_awbs]' => $result['print_awbs'],
				    '3: ! empty( result[html] )' => ! empty( $result['html'] ),
				    ];
			}
		}
		$result = $this->run_label( array( 'preview' => true, 'print_response' => array( 'status' => true, 'url' => $url ) ) );
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = $url;
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = $result['preview']['url'];
		$this->assertSame( $expectedCases, $actualCases );
	}

	#[Test]
	public function unavailable_malformed_or_unsafe_carrier_responses_fall_back_without_remote_errors(): void {
		$urls = array( 'http://client.kiriminaja.com/label.pdf', 'javascript:alert(1)', '//client.kiriminaja.com/label', 'https://user:pass@client.kiriminaja.com/label', 'https://127.0.0.1/label', 'https://[::1]/label', 'https://localhost/label', 'https://printer.local/label', 'https://client.kiriminaja.com:8443/label', "https://client.kiriminaja.com/label\n", 'https://client.kiriminaja.com/label%0afoo', 'https://client.kiriminaja.com\\@evil.com/label', '' );
		$inputs = array( array( 'print_exception' => true ), array( 'print_response' => array( 'status' => false, 'data' => 'secret remote token' ) ), array( 'print_response' => array( 'status' => false, 'url' => 'https://client.kiriminaja.com/label' ) ), array( 'print_response' => array( 'status' => true, 'data' => array( 'url' => array( 'wrong' ) ) ) ) );
		foreach ( $urls as $url ) { $inputs[] = array( 'print_response' => array( 'status' => true, 'data' => $url ) ); }
		foreach ( $inputs as $input ) {
			$result = $this->run_label( array_merge( $input, array( 'preview' => true ) ) );
			$this->assertSame(
			    [
			    '1: result[preview][provider]' => 'local',
			    '2: result[preview][type]' => 'html',
			    '3: result[preview][carrier_available]' => false,
			    '4: array_key_exists( url, result[preview] )' => false,
			    '5: str_contains( result[preview][fallback_reason], secret )' => false,
			    '6: ! empty( result[html] )' => true,
			    ],
			    [
			    '1: result[preview][provider]' => $result['preview']['provider'],
			    '2: result[preview][type]' => $result['preview']['type'],
			    '3: result[preview][carrier_available]' => $result['preview']['carrier_available'],
			    '4: array_key_exists( url, result[preview] )' => array_key_exists( 'url', $result['preview'] ),
			    '5: str_contains( result[preview][fallback_reason], secret )' => str_contains( $result['preview']['fallback_reason'], 'secret' ),
			    '6: ! empty( result[html] )' => ! empty( $result['html'] ),
			    ]
			);
		}
	}

	#[Test]
	public function invalid_or_mixed_preview_batches_never_call_carrier(): void {
		foreach ( array(
			array( 'rows' => array() ),
			array( 'rows' => array( array( 'awb' => '' ) ) ),
			array( 'ids' => array( 'KA-1', 'KA-2' ), 'rows' => array( array(), array( 'order_id' => 'KA-2', 'service' => 'jne' ) ) ),
			array( 'ids' => array( 'KA-1', 'KA-2' ), 'rows' => array( array( 'awb' => 'SAME' ), array( 'order_id' => 'KA-2', 'awb' => 'SAME' ) ) ),
			array( 'missing_order' => true ),
		) as $input ) {
			$result = $this->run_label( array_merge( $input, array( 'preview' => true ) ) );
			$this->assertSame(
			    [
			    '1: () !== (result[error])' => true,
			    '2: array_key_exists( preview, result )' => false,
			    '3: result[print_awbs]' => array(),
			    ],
			    [
			    '1: () !== (result[error])' => ('') !== ($result['error']),
			    '2: array_key_exists( preview, result )' => array_key_exists( 'preview', $result ),
			    '3: result[print_awbs]' => $result['print_awbs'],
			    ]
			);
		}
	}

	#[Test]
	public function booked_labels_are_local_read_only_and_use_saved_addresses(): void {
		foreach ( array( 'gosend', 'grab_express' ) as $courier ) {
			foreach ( array( 'request_pickup', 'shipped', 'finished' ) as $status ) {
				$result = $this->run_label( array( 'rows' => array( array( 'service' => $courier, 'status' => $status ) ) ) );
				$this->assertSame(
				    [
				    '1: result[error]' => '',
				    '2: result[can_print]' => array( true ),
				    '3: str_contains( result[html], Booked physical items )' => true,
				    '4: result[labels][0][recipient][name]' => 'Booked recipient',
				    '5: result[labels][0][sender][name]' => 'Booked sender',
				    '6: result[labels][0][items]' => array( array( 'name' => 'Physical item', 'quantity' => 2 ) ),
				    '7: str_contains( result[html], Local shipment label )' => true,
				    '8: str_contains( result[html], Not a courier-issued shipping label )' => true,
				    '9: str_contains( result[html], window.print() )' => true,
				    '10: str_contains( result[html], size: A6 )' => true,
				    '11: str_contains( result[html], latitude )' => false,
				    '12: str_contains( result[html], <svg )' => false,
				    ],
				    [
				    '1: result[error]' => $result['error'],
				    '2: result[can_print]' => $result['can_print'],
				    '3: str_contains( result[html], Booked physical items )' => str_contains( $result['html'], 'Booked physical items' ),
				    '4: result[labels][0][recipient][name]' => $result['labels'][0]['recipient']['name'],
				    '5: result[labels][0][sender][name]' => $result['labels'][0]['sender']['name'],
				    '6: result[labels][0][items]' => $result['labels'][0]['items'],
				    '7: str_contains( result[html], Local shipment label )' => str_contains( $result['html'], 'Local shipment label' ),
				    '8: str_contains( result[html], Not a courier-issued shipping label )' => str_contains( $result['html'], 'Not a courier-issued shipping label' ),
				    '9: str_contains( result[html], window.print() )' => str_contains( $result['html'], 'window.print()' ),
				    '10: str_contains( result[html], size: A6 )' => str_contains( $result['html'], 'size: A6' ),
				    '11: str_contains( result[html], latitude )' => str_contains( $result['html'], 'latitude' ),
				    '12: str_contains( result[html], <svg )' => str_contains( $result['html'], '<svg' ),
				    ]
				);
			}
		}
	}

	#[Test]
	public function malformed_ids_and_incomplete_or_mixed_batches_fail_closed(): void {
		$expectedCases = $actualCases = [];
		foreach ( array( array(), array( '' ), array( array( 'KA-1' ) ), array( true ), array( 1.2 ), array( '<b>KA-1</b>' ), array( 'KA-1,KA-2' ), array_fill( 0, 51, 'KA-1' ) ) as $ids ) {
			$result = $this->run_label( array( 'ids' => $ids ) );
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: () !== (result[error])' => true,
			    '2: result[ids]' => array(),
			    '3: result[html]' => '',
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: () !== (result[error])' => ('') !== ($result['error']),
			    '2: result[ids]' => $result['ids'],
			    '3: result[html]' => $result['html'],
			    ];
		}
		foreach ( array( array(), array( array( 'order_id' => 'KA-2' ) ), array( array(), array() ), array( array(), array( 'order_id' => 'KA-2', 'service' => 'jne' ) ) ) as $rows ) {
			$ids = count( $rows ) > 1 ? array( 'KA-1', 'KA-2' ) : array( 'KA-1' );
			$result = $this->run_label( array( 'ids' => $ids, 'rows' => $rows ) );
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: () !== (result[error])' => true,
			    '2: result[html]' => '',
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: () !== (result[error])' => ('') !== ($result['error']),
			    '2: result[html]' => $result['html'],
			    ];
		}
		$result = $this->run_label( array( 'ids' => array( 'KA-1', 'KA-2' ), 'rows' => array( array(), array( 'order_id' => 'KA-2', 'shipping_info' => '{"instant_items":[]}' ) ) ) );
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: () !== (result[error])' => true,
		    '2: result[can_print]' => array( true, false ),
		    '3: result[labels]' => array(),
		    '4: result[html]' => '',
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: () !== (result[error])' => ('') !== ($result['error']),
		    '2: result[can_print]' => $result['can_print'],
		    '3: result[labels]' => $result['labels'],
		    '4: result[html]' => $result['html'],
		    ];
		$this->assertSame( $expectedCases, $actualCases );
	}

	#[Test]
	public function duplicates_are_removed_and_request_order_is_preserved(): void {
		$result = $this->run_label( array( 'ids' => array( ' KA-2 ', 'KA-1', 'KA-2' ), 'rows' => array( array(), array( 'order_id' => 'KA-2' ) ) ) );
		$this->assertSame(
		    [
		    '1: result[ids]' => array( 'KA-2', 'KA-1' ),
		    '2: array_column( result[labels], order_id )' => array( 'KA-2', 'KA-1' ),
		    ],
		    [
		    '1: result[ids]' => $result['ids'],
		    '2: array_column( result[labels], order_id )' => array_column( $result['labels'], 'order_id' ),
		    ]
		);
	}

	#[Test]
	public function unsupported_unbooked_cancelled_and_ineligible_orders_are_rejected(): void {
		foreach ( array( array( 'service' => 'borzo' ), array( 'service' => 'jne' ), array( 'status' => 'new' ), array( 'status' => 'pending' ), array( 'status' => 'canceled' ), array( 'awb' => '' ), array( 'awb' => ' - ' ), array( 'instant_status_code' => 300 ), array( 'instant_status_code' => '302' ), array( 'instant_status_code' => 350 ), array( 'shipping_info' => '{}' ), array( 'shipment_location_snapshot' => '' ) ) as $row ) {
			$result = $this->run_label( array( 'rows' => array( $row ) ) );
			$this->assertSame(
			    [
			    '1: () !== (result[error])' => true,
			    '2: result[can_print]' => array( false ),
			    '3: result[html]' => '',
			    ],
			    [
			    '1: () !== (result[error])' => ('') !== ($result['error']),
			    '2: result[can_print]' => $result['can_print'],
			    '3: result[html]' => $result['html'],
			    ]
			);
		}
		foreach ( array( array( 'missing_order' => true ), array( 'wc_status' => 'cancelled' ), array( 'wc_status' => 'trash' ), array( 'wc_status' => 'on-hold' ) ) as $input ) {
			$this->assertNotSame( '', $this->run_label( $input )['error'] );
		}
	}

	#[Test]
	public function booked_items_survive_current_item_edits_and_product_removal(): void {
		foreach ( array( array( 'item_name' => 'Edited name', 'item_qty' => 99 ), array( 'product_removed' => true ), array( 'no_physical' => true ), array( 'forbid_item_reads' => true ), array( 'wc_status' => 'completed' ) ) as $input ) {
			$result = $this->run_label( $input );
			$this->assertSame(
			    [
			    '1: result[error]' => '',
			    '2: result[can_print]' => array( true ),
			    '3: result[labels][0][items]' => array( array( 'name' => 'Physical item', 'quantity' => 2 ) ),
			    '4: str_contains( result[html], Edited name )' => false,
			    ],
			    [
			    '1: result[error]' => $result['error'],
			    '2: result[can_print]' => $result['can_print'],
			    '3: result[labels][0][items]' => $result['labels'][0]['items'],
			    '4: str_contains( result[html], Edited name )' => str_contains( $result['html'], 'Edited name' ),
			    ]
			);
		}
	}

	#[Test]
	public function missing_and_malformed_booked_items_fail_closed_without_writes(): void {
		$bad_items = array( null, array(), 'items', (object) array( 'item' => (object) array( 'name' => 'Item', 'qty' => 1 ) ), array( null ), array( 'Item' ), array( array( 'name' => '', 'qty' => 1 ) ), array( array( 'name' => '  ', 'qty' => 1 ) ), array( array( 'name' => 123, 'qty' => 1 ) ), array( array( 'name' => array(), 'qty' => 1 ) ) );
		foreach ( array( null, 0, -1, true, '2', 1.5, 40001, PHP_INT_MAX ) as $qty ) {
			$bad_items[] = array( array( 'name' => 'Item', 'qty' => $qty ) );
		}
		$bad_items[] = array( array( 'name' => 'Valid', 'qty' => 1 ), array( 'name' => 'Invalid' ) );
		$inputs = array_map( static fn( $items ) => array( 'snapshot_items' => $items ), $bad_items );
		$inputs[] = array( 'missing_snapshot_items' => true );
		foreach ( $inputs as $input ) {
			$result = $this->run_label( $input );
			$this->assertSame(
			    [
			    '1: () !== (result[error])' => true,
			    '2: result[can_print]' => array( false ),
			    '3: result[labels]' => array(),
			    '4: result[html]' => '',
			    ],
			    [
			    '1: () !== (result[error])' => ('') !== ($result['error']),
			    '2: result[can_print]' => $result['can_print'],
			    '3: result[labels]' => $result['labels'],
			    '4: result[html]' => $result['html'],
			    ]
			);
		}
	}

	#[Test]
	public function malformed_and_duplicate_awb_identities_fail_closed(): void {
		$expectedCases = $actualCases = [];
		foreach ( array( null, array( 'AWB-1' ), 123, '<b>AWB-1</b>', ' AWB-1 ', "AWB-1\n", 'AWB/1', str_repeat( 'A', 101 ) ) as $awb ) {
			$result = $this->run_label( array( 'rows' => array( array( 'awb' => $awb ) ) ) );
			$contractCase = 'case ' . count( $expectedCases );
			$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
			    '1: () !== (result[error])' => true,
			    '2: result[can_print]' => array( false ),
			    '3: result[html]' => '',
			    ];
			$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
			    '1: () !== (result[error])' => ('') !== ($result['error']),
			    '2: result[can_print]' => $result['can_print'],
			    '3: result[html]' => $result['html'],
			    ];
		}
		$result = $this->run_label( array( 'ids' => array( 'KA-1', 'KA-2' ), 'rows' => array( array( 'awb' => 'AWB-SAME' ), array( 'order_id' => 'KA-2', 'awb' => 'AWB-SAME' ) ) ) );
		$contractCase = 'case ' . count( $expectedCases );
		$expectedCases[$contractCase . " / " . count( $expectedCases )] = [
		    '1: () !== (result[error])' => true,
		    '2: result[labels]' => array(),
		    '3: result[html]' => '',
		    ];
		$actualCases[$contractCase . " / " . (count( $expectedCases ) - 1)] = [
		    '1: () !== (result[error])' => ('') !== ($result['error']),
		    '2: result[labels]' => $result['labels'],
		    '3: result[html]' => $result['html'],
		    ];
		$this->assertSame( $expectedCases, $actualCases );
	}

	#[Test]
	public function maximum_batch_of_fifty_is_supported(): void {
		$ids = array_map( static fn( $id ) => 'KA-' . $id, range( 1, 50 ) );
		$rows = array_map( static fn( $id ) => array( 'order_id' => $id ), $ids );
		$result = $this->run_label( array( 'ids' => $ids, 'rows' => $rows ) );
		$this->assertSame(
		    [
		    '1: result[error]' => '',
		    '2: count( result[labels] )' => 50,
		    ],
		    [
		    '1: result[error]' => $result['error'],
		    '2: count( result[labels] )' => count( $result['labels'] ),
		    ]
		);
	}

	#[Test]
	public function every_text_field_is_escaped_and_no_remote_assets_or_fake_barcode_exist(): void {
		$result = $this->run_label( array( 'poison' => true ) );
		$this->assertSame(
		    [
		    '1: str_contains( result[html], <img )' => false,
		    '2: (substr_count( result[html], &lt;img src=x onerror=alert(1)&gt; )) >= (17)' => true,
		    '3: str_contains( result[html], https:// )' => false,
		    '4: str_contains( result[html], application/pdf )' => false,
		    ],
		    [
		    '1: str_contains( result[html], <img )' => str_contains( $result['html'], '<img' ),
		    '2: (substr_count( result[html], &lt;img src=x onerror=alert(1)&gt; )) >= (17)' => (substr_count( $result['html'], '&lt;img src=x onerror=alert(1)&gt;' )) >= (17),
		    '3: str_contains( result[html], https:// )' => str_contains( $result['html'], 'https://' ),
		    '4: str_contains( result[html], application/pdf )' => str_contains( $result['html'], 'application/pdf' ),
		    ]
		);
	}
}
