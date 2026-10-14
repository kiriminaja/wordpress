<?php

use PHPUnit\Framework\TestCase;

final class CouponCourierRestrictionsRuntimeTest extends TestCase {
	private function run_fixture( string $mode ): array {
		$output = array();
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/coupon-courier-restrictions-runtime.php' ) . ' ' . escapeshellarg( $mode ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_actual_admin_render_preserves_exact_instant_choices_and_scope(): void {
		$result = $this->run_fixture( 'render' );
		foreach ( array( 'all', 'selected' ) as $scope ) {
			$document = new DOMDocument();
			$document->loadHTML( $result[ $scope ] );
			$xpath = new DOMXPath( $document );
			$boxes = $xpath->query( '//input[@type="checkbox"]' );
			$codes = array();
			$selected = array();
			foreach ( $boxes as $box ) {
				$code = $box->getAttribute( 'value' );
				$codes[] = $code;
				$this->assertSame( '_kiriof_coupon_couriers[]', $box->getAttribute( 'name' ) );
				if ( $box->hasAttribute( 'checked' ) ) {
					$selected[] = $code;
				}
			}
			$this->assertSame( $codes, array_values( array_unique( $codes ) ), 'No duplicate cached/known courier checkboxes.' );
			foreach ( array( 'gosend' => 'GoSend (instant)', 'grab_express' => 'GrabExpress (instant)' ) as $code => $label ) {
				$matches = $xpath->query( '//input[@type="checkbox" and @value="' . $code . '"]' );
				$this->assertCount( 1, $matches );
				$this->assertSame( $label, trim( $matches->item( 0 )->parentNode->textContent ) );
			}
			$this->assertNotContains( 'unknown', $codes );
			$this->assertNotContains( 'borzo', $codes );
			$this->assertNotContains( 'ninja_inter', $codes );
			sort( $selected );
			$this->assertSame( 'selected' === $scope ? array( 'gosend', 'grab_express', 'ninja' ) : array(), $selected );
			$this->assertSame( $scope, $xpath->query( '//input[@name="_kiriof_coupon_couriers_scope"]' )->item( 0 )->getAttribute( 'value' ) );
			$this->assertCount( 1, $xpath->query( '//input[@type="radio" and @checked and @value="' . $scope . '"]' ) );
			$list = $xpath->query( '//div[@class="kiriof-courier-list"]' )->item( 0 );
			$this->assertSame( 'all' === $scope ? 'display:none' : 'margin-top:16px', $list->getAttribute( 'style' ) );
		}
	}

	public function test_actual_save_hook_round_trips_instant_codes_and_respects_native_save_boundary(): void {
		$result = $this->run_fixture( 'save' );
		$this->assertSame( array( 'grab_express', 'gosend', 'ninja' ), $result['selected'] );
		$this->assertSame( array( true, true, true, false ), $result['allowed'] );
		$this->assertSame( array(), $result['all'] );
		$this->assertSame( array( 'ninja' ), $result['denied_capability'] );
		$this->assertSame( array( 'ninja' ), $result['invalid_nonce'] );
	}
}
