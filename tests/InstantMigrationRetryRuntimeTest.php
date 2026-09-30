<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantMigrationRetryRuntimeTest extends TestCase {
    private function runFixture(string $failure): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/instant-migration-retry-runtime.php') . ' ' . escapeshellarg(json_encode(['failure' => $failure], JSON_THROW_ON_ERROR)));
        return json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function an_earlier_failed_alter_is_not_masked_by_later_success_and_retries(): void {
        $result = $this->runFixture('snapshot');
        $firstSql = implode("\n", $result['first_queries']);
        $allSql = implode("\n", $result['all_queries']);

        $this->assertNull($result['first_option']);
        $this->assertSame('', $result['first_error']);
        $this->assertStringContainsString('ADD shipment_location_snapshot', $firstSql);
        $this->assertSame(2, substr_count($allSql, 'ADD shipment_location_snapshot'));
        $this->assertStringContainsString('MODIFY COLUMN status', $firstSql);
        $this->assertSame('1', $result['option']);
        $this->assertContains('shipment_location_snapshot', $result['columns']);
        $this->assertSame(count($result['all_queries']), $result['third_query_count']);
    }

    #[Test]
    public function status_enum_alter_failure_is_retried_even_when_all_columns_exist(): void {
        $result = $this->runFixture('status');
        $this->assertNull($result['first_option']);
        $this->assertSame('', $result['first_error']);
        $this->assertSame(2, substr_count(implode("\n", $result['all_queries']), 'MODIFY COLUMN status'));
        $this->assertSame('1', $result['option']);
        $this->assertSame(count($result['all_queries']), $result['third_query_count']);
    }
}
