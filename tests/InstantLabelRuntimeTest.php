<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantLabelRuntimeTest extends TestCase {
	private function run_label( array $input = array() ): array {
		$output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/instant-label-runtime.php' ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) );
		$result = json_decode( (string) $output, true, 512, JSON_THROW_ON_ERROR );
		$this->assertSame( 0, $result['writes'] );
		$this->assertSame( 0, $result['calls'] );
		return $result;
	}

	#[Test]
	public function booked_labels_are_local_read_only_and_use_saved_addresses(): void {
		foreach ( array( 'gosend', 'grab_express' ) as $courier ) {
			foreach ( array( 'request_pickup', 'shipped', 'finished' ) as $status ) {
				$result = $this->run_label( array( 'rows' => array( array( 'service' => $courier, 'status' => $status ) ) ) );
				$this->assertSame( '', $result['error'] );
				$this->assertSame( array( true ), $result['can_print'] );
				$this->assertStringContainsString( 'Booked physical items', $result['html'] );
				$this->assertSame( 'Booked recipient', $result['labels'][0]['recipient']['name'] );
				$this->assertSame( 'Booked sender', $result['labels'][0]['sender']['name'] );
				$this->assertSame( array( array( 'name' => 'Physical item', 'quantity' => 2 ) ), $result['labels'][0]['items'] );
				$this->assertStringContainsString( 'Local shipment label', $result['html'] );
				$this->assertStringContainsString( 'Not a courier-issued shipping label', $result['html'] );
				$this->assertStringContainsString( 'window.print()', $result['html'] );
				$this->assertStringContainsString( 'size: A6', $result['html'] );
				$this->assertStringNotContainsString( 'latitude', $result['html'] );
				$this->assertStringNotContainsString( '<svg', $result['html'] );
			}
		}
	}

	#[Test]
	public function malformed_ids_and_incomplete_or_mixed_batches_fail_closed(): void {
		foreach ( array( array(), array( '' ), array( array( 'KA-1' ) ), array( true ), array( 1.2 ), array( '<b>KA-1</b>' ), array( 'KA-1,KA-2' ), array_fill( 0, 51, 'KA-1' ) ) as $ids ) {
			$result = $this->run_label( array( 'ids' => $ids ) );
			$this->assertNotSame( '', $result['error'] );
			$this->assertSame( array(), $result['ids'] );
			$this->assertSame( '', $result['html'] );
		}
		foreach ( array( array(), array( array( 'order_id' => 'KA-2' ) ), array( array(), array() ), array( array(), array( 'order_id' => 'KA-2', 'service' => 'jne' ) ) ) as $rows ) {
			$ids = count( $rows ) > 1 ? array( 'KA-1', 'KA-2' ) : array( 'KA-1' );
			$result = $this->run_label( array( 'ids' => $ids, 'rows' => $rows ) );
			$this->assertNotSame( '', $result['error'] );
			$this->assertSame( '', $result['html'] );
		}
		$result = $this->run_label( array( 'ids' => array( 'KA-1', 'KA-2' ), 'rows' => array( array(), array( 'order_id' => 'KA-2', 'shipping_info' => '{"instant_items":[]}' ) ) ) );
		$this->assertNotSame( '', $result['error'] );
		$this->assertSame( array( true, false ), $result['can_print'] );
		$this->assertSame( array(), $result['labels'] );
		$this->assertSame( '', $result['html'] );
	}

	#[Test]
	public function duplicates_are_removed_and_request_order_is_preserved(): void {
		$result = $this->run_label( array( 'ids' => array( ' KA-2 ', 'KA-1', 'KA-2' ), 'rows' => array( array(), array( 'order_id' => 'KA-2' ) ) ) );
		$this->assertSame( array( 'KA-2', 'KA-1' ), $result['ids'] );
		$this->assertSame( array( 'KA-2', 'KA-1' ), array_column( $result['labels'], 'order_id' ) );
	}

	#[Test]
	public function unsupported_unbooked_cancelled_and_ineligible_orders_are_rejected(): void {
		foreach ( array( array( 'service' => 'borzo' ), array( 'service' => 'jne' ), array( 'status' => 'new' ), array( 'status' => 'pending' ), array( 'status' => 'canceled' ), array( 'awb' => '' ), array( 'awb' => ' - ' ), array( 'instant_status_code' => 300 ), array( 'instant_status_code' => '302' ), array( 'instant_status_code' => 350 ), array( 'shipping_info' => '{}' ), array( 'shipment_location_snapshot' => '' ) ) as $row ) {
			$result = $this->run_label( array( 'rows' => array( $row ) ) );
			$this->assertNotSame( '', $result['error'] );
			$this->assertSame( array( false ), $result['can_print'] );
			$this->assertSame( '', $result['html'] );
		}
		foreach ( array( array( 'missing_order' => true ), array( 'wc_status' => 'cancelled' ), array( 'wc_status' => 'trash' ), array( 'wc_status' => 'on-hold' ) ) as $input ) {
			$this->assertNotSame( '', $this->run_label( $input )['error'] );
		}
	}

	#[Test]
	public function booked_items_survive_current_item_edits_and_product_removal(): void {
		foreach ( array( array( 'item_name' => 'Edited name', 'item_qty' => 99 ), array( 'product_removed' => true ), array( 'no_physical' => true ), array( 'forbid_item_reads' => true ), array( 'wc_status' => 'completed' ) ) as $input ) {
			$result = $this->run_label( $input );
			$this->assertSame( '', $result['error'] );
			$this->assertSame( array( true ), $result['can_print'] );
			$this->assertSame( array( array( 'name' => 'Physical item', 'quantity' => 2 ) ), $result['labels'][0]['items'] );
			$this->assertStringNotContainsString( 'Edited name', $result['html'] );
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
			$this->assertNotSame( '', $result['error'] );
			$this->assertSame( array( false ), $result['can_print'] );
			$this->assertSame( array(), $result['labels'] );
			$this->assertSame( '', $result['html'] );
		}
	}

	#[Test]
	public function malformed_and_duplicate_awb_identities_fail_closed(): void {
		foreach ( array( null, array( 'AWB-1' ), 123, '<b>AWB-1</b>', ' AWB-1 ', "AWB-1\n", 'AWB/1', str_repeat( 'A', 101 ) ) as $awb ) {
			$result = $this->run_label( array( 'rows' => array( array( 'awb' => $awb ) ) ) );
			$this->assertNotSame( '', $result['error'] );
			$this->assertSame( array( false ), $result['can_print'] );
			$this->assertSame( '', $result['html'] );
		}
		$result = $this->run_label( array( 'ids' => array( 'KA-1', 'KA-2' ), 'rows' => array( array( 'awb' => 'AWB-SAME' ), array( 'order_id' => 'KA-2', 'awb' => 'AWB-SAME' ) ) ) );
		$this->assertNotSame( '', $result['error'] );
		$this->assertSame( array(), $result['labels'] );
		$this->assertSame( '', $result['html'] );
	}

	#[Test]
	public function maximum_batch_of_fifty_is_supported(): void {
		$ids = array_map( static fn( $id ) => 'KA-' . $id, range( 1, 50 ) );
		$rows = array_map( static fn( $id ) => array( 'order_id' => $id ), $ids );
		$result = $this->run_label( array( 'ids' => $ids, 'rows' => $rows ) );
		$this->assertSame( '', $result['error'] );
		$this->assertCount( 50, $result['labels'] );
	}

	#[Test]
	public function every_text_field_is_escaped_and_no_remote_assets_or_fake_barcode_exist(): void {
		$result = $this->run_label( array( 'poison' => true ) );
		$this->assertStringNotContainsString( '<img', $result['html'] );
		$this->assertGreaterThanOrEqual( 17, substr_count( $result['html'], '&lt;img src=x onerror=alert(1)&gt;' ) );
		$this->assertStringNotContainsString( 'https://', $result['html'] );
		$this->assertStringNotContainsString( 'application/pdf', $result['html'] );
	}
}
