<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Badge parity is checked against executed production SQL, not canned wpdb counts. */
final class TransactionBadgeCountsRuntimeTest extends TestCase
{
    private function execute(bool $hpos, array $requests = [[]], ?array $onlyIds = null): array
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Database regression tests require pdo_sqlite.');
        }
        $payload = ['hpos' => $hpos, 'requests' => $requests];
        if (null !== $onlyIds) { $payload['only_ids'] = $onlyIds; }
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/transaction-badge-counts-runtime.php') . ' ' . escapeshellarg(json_encode($payload, JSON_THROW_ON_ERROR));
        $output = shell_exec($command);
        $this->assertNotNull($output, 'Database fixture must execute successfully.');
        $responses = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(count($requests), $responses);
        foreach ($responses as $response) {
            $this->assertSame('', $response['last_error'], implode("\n", $response['queries']));
            $this->assertNotEmpty($response['queries']);
            $this->assertSame($response['counts']['total'], array_sum($response['tabs']));
            $this->assertSame($response['counts']['total'], $response['sidebar'], 'Actual repository sidebar getter must share the pending scope.');
            $this->assertSame(array_diff_key($response['counts'], ['total' => true]), $response['tabs']);
            $badgeSql = array_values(array_filter($response['queries'], static fn ($sql) => str_contains($sql, 'AS has_issue')));
            $this->assertCount(3, $badgeSql, 'Shared query, delivery tabs and actual sidebar each execute the badge SQL.');
            foreach ($badgeSql as $sql) {
                $this->assertStringContainsString($hpos ? 'FROM wp_wc_orders p' : 'FROM wp_posts p', $sql);
            }
        }
        return $responses;
    }

    #[Test]
    public function pending_badges_partition_real_orders_and_match_the_sidebar_in_both_storages(): void
    {
        foreach ([false, true] as $hpos) {
            $response = $this->execute($hpos)[0];
            $this->assertSame(['regular' => 7, 'instant' => 4, 'issue' => 2, 'total' => 13], $response['counts']);
        }
    }

    #[Test]
    public function empty_database_returns_integer_zero_counts_without_external_services(): void
    {
        foreach ([false, true] as $hpos) {
            $response = $this->execute($hpos, [[]], [])[0];
            $this->assertSame(['regular' => 0, 'instant' => 0, 'issue' => 0, 'total' => 0], $response['counts']);
            $this->assertSame(0, $response['status_counts']['all']);
        }
    }

    #[Test]
    public function physical_scope_legacy_types_and_duplicate_priority_are_pinned_individually(): void
    {
        // Restrict the seeded database itself, never the production query.
        $groups = [
            'regular' => [1, 4, 5, 8, 9, 23, 28],
            'instant' => [2, 21, 24, 27],
            'issue' => [3, 22],
            'excluded' => [6, 7, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 25, 26],
        ];
        foreach ([false, true] as $hpos) {
            foreach ($groups as $bucket => $ids) {
                foreach ($ids as $id) {
                    $expected = ['regular' => 0, 'instant' => 0, 'issue' => 0, 'total' => 0];
                    if ('excluded' !== $bucket) { $expected[$bucket] = 1; $expected['total'] = 1; }
                    $this->assertSame($expected, $this->execute($hpos, [[]], [$id])[0]['counts'], "Order {$id}, " . ($hpos ? 'HPOS' : 'legacy'));
                }
            }
        }
    }

    #[Test]
    public function historical_all_counts_remain_broader_while_filters_and_active_tabs_do_not_change_badges(): void
    {
        $requests = [
            ['filters' => ['delivery_type' => 'express']],
            ['filters' => ['delivery_type' => 'instant']],
            ['filters' => ['status' => 'order-issue', 'delivery_type' => 'instant']],
            ['filters' => ['delivery_type' => 'express', 'status' => 'wc-processing', 'courier' => 'missing', 'key' => 'no-match', 'month' => '2024-01', 'cod' => '0', 'print_status' => '1']],
            ['filters' => ['delivery_type' => 'instant', 'status' => 'processed', 'date_from' => '2026-01-01', 'date_to' => '2026-01-31']],
        ];
        foreach ([false, true] as $hpos) {
            $responses = $this->execute($hpos, $requests);
            foreach ($responses as $response) {
                $this->assertSame($responses[0]['counts'], $response['counts']);
            }
            // Historical status All includes shipped/finished/rejected/canceled,
            // on-hold/pending/completed/cancelled, not merely pending work.
            $this->assertSame(14, $responses[0]['status_counts']['all']);
            $this->assertSame(5, $responses[1]['status_counts']['all']);
            $this->assertSame(14, $responses[2]['status_counts']['all']);
            $this->assertSame($responses[0]['status_counts'], $responses[3]['status_counts']);
            $this->assertSame($responses[1]['status_counts'], $responses[4]['status_counts']);
            $this->assertSame(0, $responses[3]['page']['total']);
            $this->assertSame(0, $responses[4]['page']['total']);
            $this->assertSame(4, $responses[2]['page']['total'], 'Issue list retains historical deficits, including shipped orders.');
        }
    }
}
