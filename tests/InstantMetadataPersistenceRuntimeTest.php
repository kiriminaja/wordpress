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
            $this->assertSame(
                [
                    'options.kiriof_instant_metadata_v1\' . suffix' => '1',
                    'options.kiriof_transaction_partition_v1\' . suffix' => '1',
                    'options.kiriof_sync_version' => 'v3',
                    'count(result.queries)' => count($result['first_queries']),
                    'array_diff(self::FIELDS, result.columns)' => [],
                ],
                [
                    'options.kiriof_instant_metadata_v1\' . suffix' => $result['options']['kiriof_instant_metadata_v1' . $suffix],
                    'options.kiriof_transaction_partition_v1\' . suffix' => $result['options']['kiriof_transaction_partition_v1' . $suffix],
                    'options.kiriof_sync_version' => $result['options']['kiriof_sync_version'],
                    'count(result.queries)' => count($result['queries']),
                    'array_diff(self::FIELDS, result.columns)' => array_diff(self::FIELDS, $result['columns']),
                ],
                __FUNCTION__
            );
            $sql = implode("\n", $result['queries']);
            foreach (['instant_status_code' => 'int', 'instant_payment_status' => 'varchar(20)', 'instant_payment_method' => 'varchar(20)', 'instant_payment_id' => 'varchar(100)', 'destination_latitude' => 'double', 'destination_longitude' => 'double', 'live_tracking_url' => 'text'] as $field => $type) {
                $this->assertStringContainsString("`$field` $type DEFAULT NULL", $sql);
            }
            $this->assertSame(
                [
                    'redaction: sql, \'UPDATE \'' => 0,
                    'row.instant_status_code' => 101,
                    'row.status' => 'shipped',
                ],
                [
                    'redaction: sql, \'UPDATE \'' => substr_count($sql, 'UPDATE '),
                    'row.instant_status_code' => $result['row']['instant_status_code'],
                    'row.status' => $result['row']['status'],
                ],
                __FUNCTION__
            );
        }
        $done = $this->runFixture(['mode' => 'migration', 'complete' => true]);
        $this->assertSame([], $done['queries']);
    }

    #[Test]
    public function partial_ddl_failure_retries_only_missing_fields_and_requires_final_verification(): void {
        $result = $this->runFixture(['mode' => 'migration', 'columns' => ['instant_status_code'], 'fail_field' => 'instant_payment_status']);
        $this->assertSame(
            [
                'absent fields: result.first_options, .kiriof_instant_metadata_v1\' => true' => [],
                'options.kiriof_instant_metadata_v1' => '1',
                'count(result.queries)' => $result['second_count'],
            ],
            [
                'absent fields: result.first_options, .kiriof_instant_metadata_v1\' => true' => array_intersect_key($result['first_options'], ['kiriof_instant_metadata_v1' => true]),
                'options.kiriof_instant_metadata_v1' => $result['options']['kiriof_instant_metadata_v1'],
                'count(result.queries)' => count($result['queries']),
            ],
            __FUNCTION__
        );
        $retry = implode("\n", array_slice($result['queries'], count($result['first_queries'])));
        $this->assertSame(
            [
                'message fragment: retry' => 'ADD `instant_payment_status`',
                'redaction: retry, \'ADD `instant_status_code`\'' => 0,
                'redaction: retry, \'ADD `destination_latitude`\'' => 0,
            ],
            [
                'message fragment: retry' => substr($retry, strpos($retry, 'ADD `instant_payment_status`') === false ? strlen($retry) : strpos($retry, 'ADD `instant_payment_status`'), strlen('ADD `instant_payment_status`')),
                'redaction: retry, \'ADD `instant_status_code`\'' => substr_count($retry, 'ADD `instant_status_code`'),
                'redaction: retry, \'ADD `destination_latitude`\'' => substr_count($retry, 'ADD `destination_latitude`'),
            ],
            __FUNCTION__
        );
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
        $this->assertSame(
            [
                'count(args)' => 34,
                'preg_match_all(\'/%.sdf/\', sql)' => count($args),
                'redaction: sql, "NULLIF(%s, \'\')"' => 8,
                'row.instant_status_code' => '100',
                'row.instant_payment_status' => 'paid',
                'row.instant_payment_method' => 'qris',
                'row.instant_payment_id' => 'pay-123',
                'row.destination_latitude' => '0',
                'row.destination_longitude' => '0',
            ],
            [
                'count(args)' => count($args),
                'preg_match_all(\'/%.sdf/\', sql)' => preg_match_all('/%[sdf]/', $sql),
                'redaction: sql, "NULLIF(%s, \'\')"' => substr_count($sql, "NULLIF(%s, '')"),
                'row.instant_status_code' => $result['row']['instant_status_code'],
                'row.instant_payment_status' => $result['row']['instant_payment_status'],
                'row.instant_payment_method' => $result['row']['instant_payment_method'],
                'row.instant_payment_id' => $result['row']['instant_payment_id'],
                'row.destination_latitude' => $result['row']['destination_latitude'],
                'row.destination_longitude' => $result['row']['destination_longitude'],
            ],
            __FUNCTION__
        );
        $express = $this->runFixture(['mode' => 'insert']);
        $this->assertSame(
            [
                'ok' => true,
                'row.delivery_type' => 'express',
            ],
            [
                'ok' => $express['ok'],
                'row.delivery_type' => $express['row']['delivery_type'],
            ],
            __FUNCTION__
        );
        foreach (self::FIELDS as $field) { $this->assertNull($express['row'][$field]); }
    }

    #[Test]
    public function incoming_only_updates_preserve_partition_and_omitted_metadata_without_lookup_or_cache_reset(): void {
        foreach (['callback', 'verified'] as $writer) {
            $result = $this->runFixture(['writer' => $writer, 'changes' => ['instant_status_code' => '102', 'destination_latitude' => 0, 'instant_payment_method' => ' TOP ']]);
            $this->assertSame(
                [
                    'ok' => true,
                    'queries' => [],
                    'deleted' => [],
                    'updates.0' => ['instant_status_code' => 102, 'destination_latitude' => 0, 'instant_payment_method' => 'top'],
                    'row.instant_payment_id' => 'old-payment',
                    'row.instant_payment_status' => 'paid',
                    'row.delivery_type' => 'instant',
                    'row.vehicle' => 'motor',
                    'row.status' => 'shipped',
                ],
                [
                    'ok' => $result['ok'],
                    'queries' => $result['queries'],
                    'deleted' => $result['deleted'],
                    'updates.0' => $result['updates'][0],
                    'row.instant_payment_id' => $result['row']['instant_payment_id'],
                    'row.instant_payment_status' => $result['row']['instant_payment_status'],
                    'row.delivery_type' => $result['row']['delivery_type'],
                    'row.vehicle' => $result['row']['vehicle'],
                    'row.status' => $result['row']['status'],
                ],
                __FUNCTION__
            );
            $clear = $this->runFixture(['writer' => $writer, 'changes' => array_fill_keys(self::FIELDS, null)]);
            $this->assertTrue($clear['ok']);
            foreach (self::FIELDS as $field) { $this->assertNull($clear['row'][$field]); }
        }
        $full = $this->runFixture(['writer' => 'full', 'changes' => ['instant_status_code' => '100', 'instant_payment_method' => 'CREDIT']]);
        $this->assertSame(
            [
                'full.ok' => true,
                'full.row.instant_status_code' => 100,
                'full.row.instant_payment_method' => 'credit',
            ],
            [
                'full.ok' => $full['ok'],
                'full.row.instant_status_code' => $full['row']['instant_status_code'],
                'full.row.instant_payment_method' => $full['row']['instant_payment_method'],
            ],
            __FUNCTION__
        );
    }

    #[Test]
    public function invalid_metadata_is_rejected_before_any_sql_or_partial_write(): void {
        $expectedContracts = [];
        $actualContracts = [];

        $invalid = [
            'instant_status_code' => [-1, '-1', '1.0', 1.5, '1e2', ' 100 ', true, [], '2147483648', '999999999999999999999999'],
            'instant_payment_status' => ['unknown', '', [], true],
            'instant_payment_method' => ['cash', 'credit_card', 'qris<script>', [], false],
            'instant_payment_id' => [str_repeat('x', 101), [], 123, true],
            'destination_latitude' => [-90.01, 90.01, 'NaN', 'INF', '', [], false],
            'destination_longitude' => [-180.01, 180.01, '1e999', [], true],
            'live_tracking_url' => ['javascript:alert(1)', 'https://', [], true],
        ];
        // All four writers call normalizeInstantMetadata before preparing SQL or
        // normalizing the delivery partition. Exercise each semantic rejection
        // once through callback, rather than multiplying the shared validator's
        // cases by four. Keep a rejection smoke for every field on every other
        // writer, including each column's distinct enum/range/length/URL rule.
        // The full invalid corpus remains here: integer syntax and INT overflow,
        // string-only inputs, enums, both coordinate limits, non-finite numeric
        // strings, payment ID length, and URL scheme/structure validation.
        $prior = [
            'service' => 'gosend', 'delivery_type' => 'instant', 'vehicle' => 'motor',
            'status' => 'shipped', 'awb' => 'existing-awb', 'instant_status_code' => 101,
            'instant_payment_status' => 'paid', 'instant_payment_id' => 'old-payment',
            'instant_payment_method' => 'qris', 'destination_latitude' => -6,
            'destination_longitude' => 106, 'live_tracking_url' => 'https://example.test/old',
        ];
        // Valid sibling metadata and a courier change must not be partially
        // persisted or cause a partition lookup/cache reset when one field fails.
        $validChanges = [
            'service' => 'jne', 'status' => 'finished', 'instant_status_code' => 102,
            'instant_payment_status' => 'refunded', 'instant_payment_method' => 'top',
            'instant_payment_id' => 'new-payment', 'destination_latitude' => 0,
            'destination_longitude' => 0, 'live_tracking_url' => 'https://example.test/new',
        ];
        foreach ($invalid as $field => $values) {
            foreach (['callback', 'insert', 'verified', 'full'] as $writer) {
                $writerValues = 'callback' === $writer ? $values : [$values[0]];
                foreach ($writerValues as $value) {
                    $result = $this->runFixture([
                        'mode' => 'insert' === $writer ? 'insert' : 'update',
                        'writer' => $writer,
                        'prior' => $prior,
                        'changes' => array_replace($validChanges, [$field => $value]),
                    ]);
                    $case = ("$writer $field " . json_encode($value)) . ' #' . count($expectedContracts);
                    $expectedContracts[$case] = [
                            'ok' => false,
                            'prepared' => [],
                            'queries' => [],
                            'updates' => [],
                            'deleted' => [],
                            'row' => $prior,
                        ];
                    $actualContracts[$case] = [
                            'ok' => $result['ok'],
                            'prepared' => $result['prepared'],
                            'queries' => $result['queries'],
                            'updates' => $result['updates'],
                            'deleted' => $result['deleted'],
                            'row' => $result['row'],
                        ];
                }
            }
        }
    
        $this->assertSame($expectedContracts, $actualContracts, __FUNCTION__ . ' behavior matrix');
}

    #[Test]
    public function boundary_values_enums_and_explicit_null_are_accepted(): void {
        foreach ([0, '0', 2147483647, '2147483647', null] as $code) {
            $result = $this->runFixture(['changes' => ['instant_status_code' => $code, 'destination_latitude' => -90, 'destination_longitude' => 180, 'instant_payment_id' => str_repeat('x', 100)]]);
            $this->assertSame(
                [
                    'ok' => true,
                    'row.instant_status_code' => null === $code ? null : (int) $code,
                ],
                [
                    'ok' => $result['ok'],
                    'row.instant_status_code' => $result['row']['instant_status_code'],
                ],
                __FUNCTION__
            );
        }
        foreach (['paid', 'unpaid', 'pending', 'refunded'] as $status) {
            $this->assertTrue($this->runFixture(['changes' => ['instant_payment_status' => $status]])['ok']);
        }
    }

    #[Test]
    public function database_write_failure_returns_false_even_without_last_error(): void {
        foreach (['insert', 'callback', 'verified', 'full'] as $writer) {
            $result = $this->runFixture(['mode' => 'insert' === $writer ? 'insert' : 'update', 'writer' => $writer, 'fail_write' => true, 'changes' => ['instant_status_code' => 102]]);
            $this->assertSame(
                [
                    'ok' => false,
                    'row.instant_status_code' => 101,
                ],
                [
                    'ok' => $result['ok'],
                    'row.instant_status_code' => $result['row']['instant_status_code'],
                ],
                __FUNCTION__
            );
        }
    }

    #[Test]
    public function metadata_validation_uses_wordpress_helpers_and_removes_script_contents(): void {
        $result = $this->runFixture(['changes' => ['instant_payment_id' => '<script>discard</script><b>PAY-1</b>', 'live_tracking_url' => 'https://example.test/track']]);
        $this->assertSame(
            [
                'ok' => true,
                'row.instant_payment_id' => 'PAY-1',
                'row.live_tracking_url' => 'https://example.test/track',
                'wordpress_helpers' => ['sanitize_text_field', 'wp_parse_url', 'esc_url_raw'],
            ],
            [
                'ok' => $result['ok'],
                'row.instant_payment_id' => $result['row']['instant_payment_id'],
                'row.live_tracking_url' => $result['row']['live_tracking_url'],
                'wordpress_helpers' => $result['wordpress_helpers'],
            ],
            __FUNCTION__
        );
    }
}
