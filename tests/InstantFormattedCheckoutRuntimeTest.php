<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** The API boundary is doubled; quotes, fees, validation and receipts are production code. */
final class InstantFormattedCheckoutRuntimeTest extends TestCase {
    private function checkout(array $input, string $channel): array {
        $lines = []; $status = 0;
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/instant-formatted-checkout-runtime.php') . ' ' . escapeshellarg(json_encode($input)) . ' ' . escapeshellarg($channel) . ' 2>&1', $lines, $status);
        $this->assertSame(0, $status, implode("\n", $lines));
        return json_decode(implode("\n", $lines), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_equivalent_phone_formats_survive_quote_fees_checkout_and_durable_replay(): void {
        foreach (['classic', 'blocks'] as $channel) {
            foreach ([
                ['+62 (812) 0000-2222', '6281200002222', '62 812-0000-2222', '6281200002222'],
                ['6281200002222', '+62 (812) 0000-2222', '+62 (812) 0000-2222', '6281200002222'],
                ['(0812) 0000-2222', '081200002222', '0812-0000-2222', '081200002222'],
            ] as [$quoted, $ordered, $processed, $canonical]) {
                $r = $this->checkout(['quote_phone' => $quoted, 'order_phone' => $ordered, 'processed_phone' => $processed], $channel);
                $this->assertSame('', $r['error'], $channel);
                $this->assertSame('', $r['processed_error'], $channel);
                $this->assertSame(1, $r['calls']);
                $this->assertSame(1, $r['invoice_calls']);
                $this->assertCount(1, $r['rows']);
                $this->assertCount(1, $r['cart_fees']);
                $this->assertCount(1, $r['fee_lines']);
                $this->assertSame(1000, $r['fee_lines'][0]['total']);
                $this->assertTrue($r['session_empty']);
                $this->assertSame([], $r['locks']);
                $this->assertSame($quoted, $r['package_phone']);
                $this->assertSame($processed, $r['order_address']['phone']);
                $this->assertSame($canonical, $r['quote']['context']['recipient']['phone']);
                $snapshot = $r['meta']['_kiriof_instant_checkout_snapshot'];
                $this->assertSame($r['validated_snapshot'], $snapshot);
                $this->assertSame($r['quote']['context'], $snapshot['context']);
                $this->assertSame($r['quote']['rates'][0], $snapshot['rate']);
                $this->assertSame(hash('sha256', json_encode($snapshot)), $r['meta']['_kiriof_instant_checkout_selection']);
                $shipping = json_decode($r['rows'][0]['shipping_info'], true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame($processed, $shipping['_shipping_phone']);
                $this->assertSame(18000, $r['rows'][0]['shipping_cost']);
                $this->assertSame(19000, $r['meta']['_kiriof_instant_customer_shipping_total']);
            }
        }
    }

    public function test_changed_numbers_invalid_syntax_and_names_fail_before_transaction(): void {
        foreach (['classic', 'blocks'] as $channel) {
            foreach ([
                ['order_phone' => '+62 (812) 0000-2223'],
                ['package_phone' => '+62 (812) 0000-2223', 'order_phone' => '+62 (812) 0000-2223'],
                ['order_phone' => '6281200002222 ext 2'],
                ['order_name' => 'Different Buyer'],
            ] as $change) {
                $r = $this->checkout($change + ['quote_phone' => '+62 (812) 0000-2222'], $channel);
                $this->assertNotSame('', $r['error'], json_encode($change));
                $this->assertSame($channel === 'blocks' ? 400 : null, $r['error_status']);
                $this->assertSame([], $r['rows']);
                $this->assertSame(0, $r['invoice_calls']);
            }
        }
    }

    public function test_recipient_address_diagnostic_survives_both_fixed_allowlists(): void {
        $r = $this->checkout(['quote_phone' => '+62 (812) 0000-2222'], 'blocks');
        $this->assertSame('recipient_address_invalid', $r['address_reason']);
        $this->assertSame('recipient_address_invalid', $r['address_status']['code']);
        $this->assertStringContainsString('20 and 250', $r['address_status']['message']);
        $this->assertFalse($r['address_status']['eligible']);
    }

    public function test_durable_processing_rechecks_phone_and_strict_names_without_session(): void {
        foreach (['classic', 'blocks'] as $channel) {
            foreach ([['processed_phone' => '6281200002223'], ['processed_phone' => '6281200002222x2'], ['processed_name' => 'Different Buyer']] as $change) {
                $r = $this->checkout($change + ['quote_phone' => '+62 (812) 0000-2222'], $channel);
                $this->assertSame('', $r['error']);
                $this->assertNotSame('', $r['processed_error']);
                $this->assertSame([], $r['rows']);
                $this->assertSame(0, $r['invoice_calls']);
                $this->assertTrue($r['session_empty']);
                $this->assertSame($r['validated_snapshot'], $r['meta']['_kiriof_instant_checkout_snapshot']);
                $this->assertSame('recipient_changed', $r['logs'][0]['context']['code']);
            }
        }
    }
}
