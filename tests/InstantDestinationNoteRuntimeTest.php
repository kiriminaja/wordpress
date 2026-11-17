<?php

use PHPUnit\Framework\TestCase;

/** Assert the actual SDK JSON boundary, never source text or live HTTP. */
final class InstantDestinationNoteRuntimeTest extends TestCase {
	private function payload(): array {
		$location = array( 'name' => 'Private recipient', 'phone' => '081234567890', 'address' => str_repeat( 'Full address 🏠 é, ', 12 ), 'latitude' => -7.8, 'longitude' => 110.3, 'zipcode' => '12345', 'extra' => 'untouched' );
		return $location + array( 'address_note' => 'Pickup entrance', 'payment_method' => 'credit', 'pin' => '123456', 'extra_top' => 'retained', 'packages' => array(
			array( 'service' => 'gosend', 'order_id' => 'one', 'shipping_cost' => 12000, 'items' => array( array( 'name' => 'Item', 'qty' => 1 ) ), 'destination' => $location ),
			array( 'service' => 'grab_express', 'order_id' => 'two', 'shipping_cost' => 15000, 'items' => array( array( 'name' => 'Other', 'qty' => 2 ) ), 'destination' => $location ),
		) );
	}

	private function runFixture( array $payload ): array {
		// Exercise the no-mbstring-function path even when the extension is installed.
		$command = escapeshellarg( PHP_BINARY ) . ' -d disable_functions=mb_substr,mb_strlen ' . escapeshellarg( __DIR__ . '/fixtures/instant-destination-note-runtime.php' ) . ' ' . escapeshellarg( base64_encode( serialize( $payload ) ) );
		exec( $command . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
		$this->assertTrue( $result['input_unchanged'] );
		$this->assertSame( array(), $result['logs'] );
		$this->assertSame( '', $result['output'] );
		return $result;
	}

	private function assertSent( array $payload, array $expectedNotes, string $expectedOriginNote = 'Pickup entrance' ): void {
		$result = $this->runFixture( $payload );
		$this->assertTrue( $result['response']['status'] );
		$this->assertCount( 1, $result['requests'] );
		$this->assertSame( 1, $result['clients'] );
		$request = $result['requests'][0];
		$this->assertSame( 'POST', $request['method'] );
		$this->assertSame( 'https://example.invalid/api/mitra/v6.2/instant/request_pickup', $request['url'] );
		$this->assertSame( 'wordpress', $request['agent'] );
		$expected = $payload;
		$expected['address_note'] = $expectedOriginNote;
		foreach ( $expectedNotes as $index => $note ) { $expected['packages'][ $index ]['destination']['address_note'] = $note; }
		// Whole-payload equality protects full addresses and all non-note keys.
		$this->assertSame( $expected, json_decode( $request['body'], true, 512, JSON_THROW_ON_ERROR ) );
	}

	public function test_explicit_notes_at_63_64_and_65_codepoints_preserve_whitespace(): void {
		foreach ( array( 63, 64, 65 ) as $length ) {
			$payload = $this->payload();
			$note = ' ' . str_repeat( 'a', $length - 2 ) . ' ';
			$payload['address_note'] = $note;
			$payload['packages'][0]['destination']['address_note'] = $note;
			$payload['packages'][1]['destination']['address_note'] = "Other note\n ";
			$this->assertSent( $payload, array( substr( $note, 0, 64 ), "Other note\n " ), substr( $note, 0, 64 ) );
		}
	}

	public function test_unicode_is_counted_as_codepoints_not_bytes_or_graphemes(): void {
		foreach ( array(
			array( str_repeat( '🏠', 65 ), str_repeat( '🏠', 64 ) ),
			array( str_repeat( '漢', 63 ) . '🚚tail', str_repeat( '漢', 63 ) . '🚚' ),
			array( str_repeat( "e\u{0301}", 33 ), str_repeat( "e\u{0301}", 32 ) ),
			array( str_repeat( 'a', 63 ) . "e\u{0301}", str_repeat( 'a', 63 ) . 'e' ),
			array( "Keep 🏠 e\u{0301}\n ", "Keep 🏠 e\u{0301}\n " ),
		) as [ $note, $expected ] ) {
			$payload = $this->payload();
			$payload['address_note'] = $note;
			foreach ( $payload['packages'] as &$package ) { $package['destination']['address_note'] = $note; }
			unset( $package );
			$this->assertSent( $payload, array( $expected, $expected ), $expected );
		}
	}

	public function test_missing_empty_and_whitespace_notes_use_bounded_full_address_fallback(): void {
		foreach ( array( null, '', " \t\n " ) as $note ) {
			$payload = $this->payload();
			$address = str_repeat( '🏠', 63 ) . 'e' . "\u{0301}" . ' Full private address';
			$payload['address'] = $address;
			unset( $payload['address_note'] );
			if ( null !== $note ) { $payload['address_note'] = $note; }
			foreach ( $payload['packages'] as &$package ) {
				$package['destination']['address'] = $address;
				if ( null !== $note ) { $package['destination']['address_note'] = $note; }
			}
			unset( $package );
			$expected = str_repeat( '🏠', 63 ) . 'e';
			$this->assertSent( $payload, array( $expected, $expected ), $expected );
		}
	}

	public function test_invalid_nonstring_notes_remain_local_failures(): void {
		foreach ( array( 123, false, array( 'Private recipient' ) ) as $note ) {
			$payload = $this->payload();
			$payload['address_note'] = $note;
			$this->assertNotSubmitted( $payload, 'Instant address notes must be text.' );
			$payload = $this->payload();
			$payload['packages'][1]['destination']['address_note'] = $note;
			$this->assertNotSubmitted( $payload, 'Instant address notes must be text.' );
		}
	}

	public function test_invalid_utf8_including_after_the_limit_is_rejected_without_http_or_pii(): void {
		foreach ( array( "\xc3\x28", str_repeat( 'a', 64 ) . "\xff" ) as $note ) {
			foreach ( array( 'explicit', 'fallback' ) as $mode ) {
				$payload = $this->payload();
				if ( 'fallback' === $mode ) { unset( $payload['address_note'] ); }
				$payload[ 'explicit' === $mode ? 'address_note' : 'address' ] = 'Private recipient ' . $note;
				$this->assertNotSubmitted( $payload, 'Instant origin address notes must be valid UTF-8 text.' );
				$payload = $this->payload();
				$payload['packages'][0]['destination']['address_note'] = 'Valid first package';
				$payload['packages'][1]['destination'][ 'explicit' === $mode ? 'address_note' : 'address' ] = 'Private recipient ' . $note;
				$this->assertNotSubmitted( $payload, 'Instant destination address notes must be valid UTF-8 text.' );
			}
		}
	}

	private function assertNotSubmitted( array $payload, string $message ): void {
		$result = $this->runFixture( $payload );
		$this->assertSame( array( 'status' => false, 'data' => $message, 'operation_not_submitted' => true ), $result['response'] );
		$this->assertSame( array(), $result['requests'] );
		$this->assertSame( 0, $result['clients'] );
		foreach ( array( 'Private recipient', '123456', 'private-token' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, json_encode( $result ) );
		}
	}
}
