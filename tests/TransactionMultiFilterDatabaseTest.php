<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Behavioral regressions: every count and result comes from executed production SQL. */
final class TransactionMultiFilterDatabaseTest extends TestCase
{
    private const STATUSES = ['wc-processing', 'wc-on-hold', 'wc-pending', 'processed', 'wc-cancelled'];

    private function execute(bool $hpos, array $requests): array
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Database regression tests require pdo_sqlite.');
        }
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PLUGIN_DIR . '/tests/fixtures/transaction-multi-filter-database.php') . ' ' . escapeshellarg(json_encode(['hpos' => $hpos, 'requests' => $requests], JSON_THROW_ON_ERROR));
        $output = shell_exec($command);
        $responses = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        $execution = [];
        foreach ($responses as $index => $response) {
            $execution[$index] = ['error' => $response['last_error'], 'executed' => [] !== $response['queries']];
        }
        $this->assertSame(array_fill(0, count($requests), ['error' => '', 'executed' => true]), $execution);
        return $responses;
    }

    private function ids(array $response): array
    {
        return array_map('intval', array_column($response['page']['results'], 'wc_order_id'));
    }

    private function assertSet(array $expected, array $response, string $message = ''): void
    {
        $actual = $this->ids($response);
        sort($expected);
        sort($actual);
        // Keeping duplicates in actual IDs also proves uniqueness.
        $this->assertSame(
            ['ids' => $expected, 'total' => count($expected)],
            ['ids' => $actual, 'total' => $response['page']['total']],
            $message . "\n" . implode("\n", $response['queries'])
        );
    }

    #[Test]
    public function every_regular_status_pair_returns_the_union_of_single_status_results_in_both_storages(): void
    {
        // Run unconstrained and progressively/fully constrained queries. Every
        // filter is outside the OR, so adding a status cannot remove old matches.
        $profiles = [
            [],
            ['courier' => 'jne,pos'],
            ['month' => '2025-02'],
            ['print_status' => '0'],
            ['cod' => '1'],
            ['key' => 'KA-10'],
            ['courier' => 'jne,pos', 'month' => '2025-02', 'print_status' => '0', 'cod' => '1', 'key' => 'KA-10'],
            ['courier' => 'pos', 'month' => '2025-02', 'print_status' => '0', 'cod' => '1', 'key' => 'pid:PICKUP-6'],
        ];
        foreach ([false, true] as $hpos) {
            foreach ($profiles as $profile) {
                $requests = [];
                foreach (self::STATUSES as $status) { $requests[] = ['filters' => $profile + ['status' => $status]]; }
                $pairs = [];
                foreach (self::STATUSES as $i => $first) {
                    foreach (array_slice(self::STATUSES, $i + 1) as $second) {
                        $pairs[] = [$first, $second];
                        $requests[] = ['filters' => $profile + ['status' => $first . ',' . $second]];
                    }
                }
                $responses = $this->execute($hpos, $requests);
                $single = [];
                foreach (self::STATUSES as $i => $status) {
                    $single[$status] = $this->ids($responses[$i]);
                    $this->assertSame(count($single[$status]), $responses[$i]['page']['total']);
                }
                foreach ($pairs as $i => [$first, $second]) {
                    $expected = array_values(array_unique(array_merge($single[$first], $single[$second])));
                    $this->assertSet($expected, $responses[count(self::STATUSES) + $i], ($hpos ? 'HPOS' : 'legacy') . ' ' . $first . '+' . $second . ' ' . json_encode($profile));
                }
            }
        }
    }

    #[Test]
    public function real_rows_pin_processed_cancelled_new_and_issue_scope_semantics(): void
    {
        $expectations = [
            'wc-processing' => [1],
            'wc-on-hold' => [2],
            'wc-pending' => [3],
            'processed' => [4, 6, 14, 16, 17, 18, 19, 20, 21],
            'wc-cancelled' => [5, 6],
            'processed,wc-cancelled' => [4, 5, 6, 14, 16, 17, 18, 19, 20, 21],
            'wc-processing,processed,wc-cancelled' => [1, 4, 5, 6, 14, 16, 17, 18, 19, 20, 21],
            'order-issue' => [8],
            'order-issue,processed,wc-cancelled' => [4, 5, 6, 14, 16, 17, 18, 19, 20, 21],
            'all' => [1, 2, 3, 4, 5, 6, 7, 12, 14, 16, 17, 18, 19, 20, 21],
        ];
        foreach ([false, true] as $hpos) {
            $requests = array_map(static fn ($status) => ['filters' => ['status' => $status]], array_keys($expectations));
            $responses = $this->execute($hpos, $requests);
            foreach (array_values($expectations) as $i => $expected) {
                $this->assertSet($expected, $responses[$i], array_keys($expectations)[$i]);
            }
        }
        // Fixtures distinguish canceled shipment (7), unpaid shipped (12),
        // virtual product (9), virtual variation (13), physical variation of a
        // virtual parent (14), deficit (8), no line items (15), and trash (10/11).
    }

    #[Test]
    public function combined_filters_constrain_both_or_branches_and_search_values_are_escaped(): void
    {
        $combined = ['status' => 'processed,wc-cancelled', 'courier' => 'jne,pos', 'month' => '2025-02', 'print_status' => '0', 'cod' => '1', 'key' => 'KA-10'];
        $cases = [
            [$combined, [4, 5, 6, 14]],
            [array_replace($combined, ['courier' => 'jne']), [5]],
            [array_replace($combined, ['courier' => 'pos']), [4, 6, 14]],
            [array_replace($combined, ['month' => '2025-03']), [17]],
            [array_replace($combined, ['print_status' => '1']), [18]],
            [array_replace($combined, ['cod' => '0']), [19]],
            [array_replace($combined, ['key' => 'pid:PICKUP-6']), [6]],
            [array_replace($combined, ['key' => '14']), [14]],
            [array_replace($combined, ['key' => 'OTHER']), [20, 21]],
            [array_replace($combined, ['key' => "QUOTE'100%_literal"]), [21]],
            [array_replace($combined, ['key' => "KA-10' OR 1=1 --"]), []],
            [array_replace($combined, ['month' => '2025-02%']), []],
            [array_replace($combined, ['key' => 'KA-10_']), []],
        ];
        foreach ([false, true] as $hpos) {
            $responses = $this->execute($hpos, array_map(static fn ($case) => ['filters' => $case[0]], $cases));
            foreach ($cases as $i => [$filters, $expected]) {
                $this->assertSet($expected, $responses[$i], json_encode($filters));
            }
        }
    }

    #[Test]
    public function duplicate_payment_rows_do_not_inflate_counts_or_paginated_results(): void
    {
        foreach ([false, true] as $hpos) {
            foreach (['processed' => [14, 6, 4], 'processed,wc-cancelled' => [14, 6, 5, 4]] as $status => $orderedIds) {
                $filters = ['status' => $status, 'courier' => 'jne,pos', 'month' => '2025-02', 'print_status' => '0', 'cod' => '1', 'key' => 'KA-10'];
                $pages = (int) ceil(count($orderedIds) / 2);
                $requests = [];
                for ($page = 1; $page <= $pages; ++$page) { $requests[] = ['filters' => $filters, 'page' => $page, 'per_page' => 2]; }
                $requests[] = ['filters' => $filters, 'page' => 99, 'per_page' => 2];
                $responses = $this->execute($hpos, $requests);
                $seen = [];
                foreach ($responses as $i => $response) {
                    $expectedPage = min($i + 1, $pages);
                    $this->assertSame(
                        ['total' => count($orderedIds), 'total_pages' => $pages, 'page' => $expectedPage, 'items_per_page' => 2, 'ids' => array_slice($orderedIds, ($expectedPage - 1) * 2, 2)],
                        ['total' => $response['page']['total'], 'total_pages' => $response['page']['total_pages'], 'page' => $response['page']['page'], 'items_per_page' => $response['page']['items_per_page'], 'ids' => $this->ids($response)]
                    );
                    if ($i < $pages) { $seen = array_merge($seen, $this->ids($response)); }
                }
                $this->assertSame($orderedIds, $seen);
                $this->assertCount(4, $responses[$pages]['queries'], 'Clamped page executes count/rows again.');
            }
        }
    }
}
