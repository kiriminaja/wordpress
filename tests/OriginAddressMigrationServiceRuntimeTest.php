<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OriginAddressMigrationServiceRuntimeTest extends TestCase {
	public static function cases(): array {
		return array_combine(
			array( 'valid', 'register', 'credentials', 'locked', 'superseded', 'crashed', 'resume', 'legacy-label', 'foreign', 'postal-conflict', 'ambiguous', 'empty', 'snapshot-conflict', 'legacy-postal-conflict', 'lookup-fail', 'exception', 'write-fail', 'read-fail', 'delay' ),
			array_map( static fn( $case ) => array( $case ), array( 'valid', 'register', 'credentials', 'locked', 'superseded', 'crashed', 'resume', 'legacy-label', 'foreign', 'postal-conflict', 'ambiguous', 'empty', 'snapshot-conflict', 'legacy-postal-conflict', 'lookup-fail', 'exception', 'write-fail', 'read-fail', 'delay' ) )
		);
	}

	#[DataProvider( 'cases' )]
	public function test_migration_is_bounded_resumable_and_private( string $case ): void {
		$output = array();
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/origin-address-migration-service-runtime.php' ) . ' ' . escapeshellarg( $case ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$this->assertSame( array( 'ok' ), $output );
	}

	public function test_upgrade_registers_worker_and_activation_schedules_without_lookup(): void {
		$this->assertContains( \KiriminAjaOfficial\Services\OriginAddressMigrationService::class, \KiriminAjaOfficial\Init::get_services() );
		$output = array();
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/origin-address-migration-service-runtime.php' ) . ' lifecycle 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$this->assertSame( array( 'ok' ), $output );
	}
}
