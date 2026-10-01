<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantMetadataPersistenceRuntimeTest extends TestCase {
    private const FIELDS = ['instant_status_code', 'instant_payment_status', 'instant_payment_method', 'instant_payment_id', 'destination_latitude', 'destination_longitude', 'live_tracking_url'];

    private function runFixture(array $input): array {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/instant-metadata-persistence-runtime.php') . ' ' . escapeshellarg(json_encode($input, JSON_THROW_ON_ERROR)));
        return json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function fresh_schema_and_upgrade_use_an_independent_suffix_scoped_marker(): void {
        foreach ([[], ['fresh' => true], ['suffix' => '_staging!']] as $input) {
            $result = $this->runFixture(array_replace(['mode' => 'migration'], $input));
            $suffix = isset($input['suffix']) ? '_staging' : '';
            $this->assertSame('1', $result['options']['kiriof_instant_metadata_v1' . $suffix]);
            $this->assertSame('1', $result['options']['kiriof_transaction_partition_v1' . $suffix]);
            $this->assertSame('v3', $result['options']['kiriof_sync_version']);
            $this->assertSame(count($result['first_queries']), count($result['queries']));
            $this->assertSame([], array_diff(self::FIELDS, $result['columns']));
            $sql = implode("\n", $result['queries']);
            foreach (['instant_status_code' => 'int', 'instant_payment_status' => 'varchar(20)', 'instant_payment_method' => 'varchar(20)', 'instant_payment_id' => 'varchar(100)', 'destination_latitude' => 'double', 'destination_longitude' => 'double', 'live_tracking_url' => 'text'] as $field => $type) {
                $this->assertStringContainsString("`$field` $type DEFAULT NULL", $sql);
            }
            $this->assertStringNotContainsString('UPDATE ', $sql);
            $this->assertSame(101, $result['row']['instant_status_code']);
            $this->assertSame('shipped', $result['row']['status']);
        }
        $source = file_get_contents(PLUGIN_DIR . '/inc/Migration/SetupMigration.php');
        $this->assertMatchesRegularExpression('/transactionsTable\(\);\s*self::instantMetadataTable\(\);/', $source);
        $done = $this->runFixture(['mode' => 'migration', 'complete' => true]);
        $this->assertSame([], $done['queries']);
    }

    #[Test]
    public function partial_ddl_failure_retries_only_missing_fields_and_requires_final_verification(): void {
        $result = $this->runFixture(['mode' => 'migration', 'columns' => ['instant_status_code'], 'fail_field' => 'instant_payment_status']);
        $this->assertArrayNotHasKey('kiriof_instant_metadata_v1', $result['first_options']);
        $this->assertSame('1', $result['options']['kiriof_instant_metadata_v1']);
        $this->assertSame($result['second_count'], count($result['queries']));
        $retry = implode("\n", array_slice($result['queries'], count($result['first_queries'])));
        $this->assertStringContainsString('ADD `instant_payment_status`', $retry);
        $this->assertStringNotContainsString('ADD `instant_status_code`', $retry);
        $this->assertStringNotContainsString('ADD `destination_latitude`', $retry);
        foreach ([['exists' => false], ['fail_describe' => true], ['ghost_field' => 'instant_payment_id']] as $input) {
            $failed = $this->runFixture(array_replace(['mode' => 'migration'], $input));
            $this->assertArrayNotHasKey('kiriof_instant_metadata_v1', $failed['options']);
        }
    }

    #[Test]
    public function insert_has_matching_placeholders_nullable_values_and_nonnull_zero_coordinates(): void {
        $result = $this->runFixture(['mode' => 'insert', 'changes' => ['service' => 'gosend', 'instant_status_code' => '000100', 'instant_payment_status' => 'PAID', 'instant_payment_method' => 'QRIS', 'instant_payment_id' => '<b>pay-123</b>', 'destination_latitude' => 0, 'destination_longitude' => '0', 'live_tracking_url' => 'https://example.test/track']]);
        $this->assertTrue($result['ok']);
        [$sql, $args] = $result['prepared'][0];
        $this->assertCount(34, $args);
        $this->assertSame(count($args), preg_match_all('/%[sdf]/', $sql));
        $this->assertSame(8, substr_count($sql, "NULLIF(%s, '')"));
        $this->assertSame('100', $result['row']['instant_status_code']);
        $this->assertSame('paid', $result['row']['instant_payment_status']);
        $this->assertSame('qris', $result['row']['instant_payment_method']);
        $this->assertSame('pay-123', $result['row']['instant_payment_id']);
        $this->assertSame('0', $result['row']['destination_latitude']);
        $this->assertSame('0', $result['row']['destination_longitude']);
        $express = $this->runFixture(['mode' => 'insert']);
        $this->assertTrue($express['ok']);
        $this->assertSame('express', $express['row']['delivery_type']);
        foreach (self::FIELDS as $field) { $this->assertNull($express['row'][$field]); }
    }

    #[Test]
    public function incoming_only_updates_preserve_partition_and_omitted_metadata_without_lookup_or_cache_reset(): void {
        foreach (['callback', 'verified'] as $writer) {
            $result = $this->runFixture(['writer' => $writer, 'changes' => ['instant_status_code' => '102', 'destination_latitude' => 0, 'instant_payment_method' => ' TOP ']]);
            $this->assertTrue($result['ok']);
            $this->assertSame([], $result['queries']);
            $this->assertSame([], $result['deleted']);
            $this->assertSame(['instant_status_code' => 102, 'destination_latitude' => 0, 'instant_payment_method' => 'top'], $result['updates'][0]);
            $this->assertSame('old-payment', $result['row']['instant_payment_id']);
            $this->assertSame('paid', $result['row']['instant_payment_status']);
            $this->assertSame('instant', $result['row']['delivery_type']);
            $this->assertSame('motor', $result['row']['vehicle']);
            $this->assertSame('shipped', $result['row']['status']);
            $clear = $this->runFixture(['writer' => $writer, 'changes' => array_fill_keys(self::FIELDS, null)]);
            $this->assertTrue($clear['ok']);
            foreach (self::FIELDS as $field) { $this->assertNull($clear['row'][$field]); }
        }
        $full = $this->runFixture(['writer' => 'full', 'changes' => ['instant_status_code' => '100', 'instant_payment_method' => 'CREDIT']]);
        $this->assertTrue($full['ok']);
        $this->assertSame(100, $full['row']['instant_status_code']);
        $this->assertSame('credit', $full['row']['instant_payment_method']);
    }

    #[Test]
    public function invalid_metadata_is_rejected_before_any_sql_or_partial_write(): void {
        $invalid = [
            'instant_status_code' => [-1, '-1', '1.0', 1.5, '1e2', ' 100 ', true, [], '2147483648', '999999999999999999999999'],
            'instant_payment_status' => ['unknown', '', [], true],
            'instant_payment_method' => ['cash', 'credit_card', 'qris<script>', [], false],
            'instant_payment_id' => [str_repeat('x', 101), [], 123, true],
            'destination_latitude' => [-90.01, 90.01, 'NaN', 'INF', '', [], false],
            'destination_longitude' => [-180.01, 180.01, '1e999', [], true],
            'live_tracking_url' => ['javascript:alert(1)', 'https://', [], true],
        ];
        foreach ($invalid as $field => $values) {
            foreach ($values as $value) {
                foreach (['insert', 'callback', 'verified', 'full'] as $writer) {
                    $result = $this->runFixture(['mode' => 'insert' === $writer ? 'insert' : 'update', 'writer' => $writer, 'changes' => [$field => $value]]);
                    $this->assertFalse($result['ok'], "$writer $field " . json_encode($value));
                    $this->assertSame([], $result['prepared']);
                    $this->assertSame([], $result['queries']);
                    $this->assertSame([], $result['updates']);
                    $this->assertSame([], $result['deleted']);
                }
            }
        }
    }

    #[Test]
    public function boundary_values_enums_and_explicit_null_are_accepted(): void {
        foreach ([0, '0', 2147483647, '2147483647', null] as $code) {
            $result = $this->runFixture(['changes' => ['instant_status_code' => $code, 'destination_latitude' => -90, 'destination_longitude' => 180, 'instant_payment_id' => str_repeat('x', 100)]]);
            $this->assertTrue($result['ok']);
            $this->assertSame(null === $code ? null : (int) $code, $result['row']['instant_status_code']);
        }
        foreach (['paid', 'unpaid', 'pending', 'refunded'] as $status) {
            $this->assertTrue($this->runFixture(['changes' => ['instant_payment_status' => $status]])['ok']);
        }
    }

    #[Test]
    public function database_write_failure_returns_false_even_without_last_error(): void {
        foreach (['insert', 'callback', 'verified', 'full'] as $writer) {
            $result = $this->runFixture(['mode' => 'insert' === $writer ? 'insert' : 'update', 'writer' => $writer, 'fail_write' => true, 'changes' => ['instant_status_code' => 102]]);
            $this->assertFalse($result['ok']);
            $this->assertSame(101, $result['row']['instant_status_code']);
        }
    }

    #[Test]
    public function metadata_validation_uses_wordpress_helpers_and_removes_script_contents(): void {
        $result = $this->runFixture(['changes' => ['instant_payment_id' => '<script>discard</script><b>PAY-1</b>', 'live_tracking_url' => 'https://example.test/track']]);
        $this->assertTrue($result['ok']);
        $this->assertSame('PAY-1', $result['row']['instant_payment_id']);
        $this->assertSame('https://example.test/track', $result['row']['live_tracking_url']);
        $this->assertSame(['sanitize_text_field', 'wp_parse_url', 'esc_url_raw'], $result['wordpress_helpers']);
    }
}
