<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// This file doubles as the isolated SQLite fixture; no WordPress/PHPUnit stubs
// participate in executing the production query in the child process.
if (isset($argv[1]) && '--instant-database-fixture' === $argv[1]) {
    $instant_payload = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
    $argv[1] = json_encode(['requests' => []], JSON_THROW_ON_ERROR);
    ob_start();
    require __DIR__ . '/fixtures/transaction-multi-filter-database.php';
    ob_end_clean();
    $wpdb = new TransactionMultiFilterDatabaseWpdb();
    $property = new ReflectionProperty($wpdb, 'db');
    $db = $property->getValue($wpdb);
    foreach (['wp_posts', 'wp_wc_orders', 'wp_kiriminaja_transactions', 'wp_kiriminaja_payments', 'wp_woocommerce_order_items', 'wp_woocommerce_order_itemmeta', 'wp_postmeta'] as $table) {
        $db->exec('DELETE FROM ' . $table);
    }
    $db->exec('ALTER TABLE wp_kiriminaja_transactions ADD instant_payment_id TEXT');
    $db->exec('ALTER TABLE wp_kiriminaja_transactions ADD instant_status_code INTEGER');
    $rows = [
        1 => ['request_pickup', 'booking-1', 0], // Accepted booking, not a fabricated remote lifecycle.
        2 => ['shipped', 'booking-2', 100],
        3 => ['finished', 'booking-3', 200],
        4 => ['return', 'booking-4', 300],
        5 => ['returned', 'booking-5', 400],
        6 => ['rejected', 'booking-6', 500],
        7 => ['request_pickup', '', 0],
        8 => ['request_pickup', null, 0],
        9 => ['request_pickup', 'booking-9', null],
        10 => ['canceled', 'booking-10', 0],
        11 => ['new', 'booking-11', 0], // Unknown remote zero cannot promote a new local row.
        12 => ['shipped', null, null], // Legacy AWB and Express payment are insufficient.
        13 => ['new', null, null],
        14 => ['shipped', 'booking-14', 100, 'express'],
        15 => ['shipped', 'booking-15', 100, 'instant', 'trash'],
        16 => ['shipped', 'booking-16', 100, 'instant', 'wc-processing', 'yes'],
        17 => ['shipped', 'booking-17', 100, 'instant', 'wc-processing', 'no', true],
        18 => ['canceled', 'booking-18', 0, 'instant', 'wc-cancelled'],
    ];
    foreach ($rows as $id => $row) {
        $type = $row[3] ?? 'instant';
        $wc = $row[4] ?? 'wc-processing';
        $date = '2025-02-' . sprintf('%02d', $id) . ' 12:00:00';
        $db->prepare('INSERT INTO wp_posts VALUES (?, ?, ?, ?)')->execute([$id, $date, $wc, 'shop_order']);
        $db->prepare('INSERT INTO wp_wc_orders VALUES (?, ?, ?, ?)')->execute([$id, $date, $wc, 'shop_order']);
        $db->prepare('INSERT INTO wp_kiriminaja_transactions VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $id, $id, $row[0], 'PICKUP-' . $id, 0, 100, 'express' === $type ? 'jne' : 'gosend', $type, 0, 'AWB-' . $id, 'ORDER-' . $id, $date, $row[1], $row[2],
        ]);
        // Deliberately give invalid Instant rows Express payment evidence too.
        if (in_array($id, [7, 8, 9, 10, 11, 12, 14], true)) {
            $db->prepare('INSERT INTO wp_kiriminaja_payments VALUES (?, ?)')->execute([$id, 'PICKUP-' . $id]);
        }
        if (!empty($row[6])) { continue; }
        $db->prepare('INSERT INTO wp_woocommerce_order_items VALUES (?, ?, ?)')->execute([$id, $id, 'line_item']);
        $db->prepare('INSERT INTO wp_woocommerce_order_itemmeta VALUES (?, ?, ?)')->execute([$id, '_product_id', 1000 + $id]);
        $db->prepare('INSERT INTO wp_postmeta VALUES (?, ?, ?)')->execute([1000 + $id, '_virtual', $row[5] ?? 'no']);
    }
    \Automattic\WooCommerce\Utilities\OrderUtil::$enabled = $instant_payload['hpos'];
    $query = new \KiriminAjaOfficial\Queries\WordPressTransactionListQuery($wpdb);
    $responses = [];
    foreach ($instant_payload['requests'] as $request) {
        $wpdb->queries = [];
        $page = $query->getPage(array_replace($defaults, ['delivery_type' => 'instant'], $request['filters'] ?? []), $request['page'] ?? 1, $request['per_page'] ?? 100);
        $counts = $query->getStatusCounts();
        $responses[] = ['page' => $page, 'counts' => $counts, 'queries' => $wpdb->queries, 'last_error' => $wpdb->last_error];
    }
    echo json_encode($responses, JSON_THROW_ON_ERROR);
    return;
}

final class InstantProcessedDatabaseRuntimeTest extends TestCase {
    private function execute(bool $hpos, array $requests): array {
        if (!extension_loaded('pdo_sqlite')) { $this->markTestSkipped('Requires pdo_sqlite.'); }
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --instant-database-fixture ' . escapeshellarg(json_encode(['hpos' => $hpos, 'requests' => $requests], JSON_THROW_ON_ERROR)));
        $responses = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(count($requests), $responses);
        foreach ($responses as $response) { $this->assertSame('', $response['last_error']); }
        return $responses;
    }

    #[Test]
    public function booking_evidence_controls_processed_badges_and_mixed_filters_in_both_storages(): void {
        $cases = [
            [['status' => 'processed'], [1, 2, 3, 4, 5, 6]],
            [['status' => 'wc-processing'], [11, 13]],
            [['status' => 'processed,wc-processing'], [1, 2, 3, 4, 5, 6, 11, 13]],
            [['status' => 'processed,wc-cancelled'], [1, 2, 3, 4, 5, 6, 18]],
            [['status' => 'processed', 'delivery_type' => 'express'], [14]],
            [['status' => 'processed,wc-processing', 'delivery_type' => 'express'], [14]],
            [['status' => 'processed', 'key' => 'pid:PICKUP-1'], [1]],
            [['status' => 'processed,wc-processing', 'courier' => 'jne'], []],
            [['status' => 'processed', 'month' => '2025-03'], []],
            [['status' => 'processed', 'print_status' => '1'], []],
            [['status' => 'processed', 'cod' => '0'], []],
        ];
        foreach ([false, true] as $hpos) {
            $responses = $this->execute($hpos, array_map(static fn ($case) => ['filters' => $case[0]], $cases));
            foreach ($cases as $i => [$filters, $expected]) {
                $response = $responses[$i];
                $actual = array_map('intval', array_column($response['page']['results'], 'wc_order_id'));
                sort($actual);
                $this->assertSame($expected, $actual, json_encode($filters));
                $this->assertSame(count($expected), $response['page']['total']);
                $express = 'express' === ($filters['delivery_type'] ?? 'instant');
                $this->assertSame($express ? 1 : 6, $response['counts']['processed']);
                $sql = implode("\n", $response['queries']);
                $this->assertStringContainsString($hpos ? 'wp_wc_orders' : 'wp_posts', $sql);
                if (!$express) { $this->assertStringNotContainsString('kiriminaja_payments', $sql); }
            }
            $this->assertSame('request_pickup', $responses[6]['page']['results'][0]['status']);
            $this->assertSame(0, $responses[6]['page']['results'][0]['instant_status_code']);
        }
    }

    #[Test]
    public function instant_processed_pagination_and_clamping_preserve_total(): void {
        foreach ([false, true] as $hpos) {
            $requests = array_map(static fn ($page) => ['filters' => ['status' => 'processed'], 'page' => $page, 'per_page' => 2], [1, 2, 3, 99]);
            $responses = $this->execute($hpos, $requests);
            foreach ([[6, 5], [4, 3], [2, 1], [2, 1]] as $i => $expected) {
                $this->assertSame($expected, array_map('intval', array_column($responses[$i]['page']['results'], 'wc_order_id')));
                $this->assertSame(6, $responses[$i]['page']['total']);
                $this->assertSame(3, $responses[$i]['page']['total_pages']);
                $this->assertSame(min($i + 1, 3), $responses[$i]['page']['page']);
            }
        }
    }
}
